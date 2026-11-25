<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Ajusta preco e situacao de uma oferta.
 *
 * Existe porque ainda nao ha tela de edicao de oferta: os precos nascem do
 * seed_demo e so mudariam por SQL na mao. A alternativa era o operador escrever
 * UPDATE direto na tabela, sem validacao de moeda nem de empresa.
 *
 * Uso:
 *   php local/marketplace/cli/set_offer.php --list
 *   php local/marketplace/cli/set_offer.php --id=3 --price=1.00
 *   php local/marketplace/cli/set_offer.php --id=3 --status=published
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_marketplace\company;
use local_marketplace\offer;

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'list' => false,
        'id' => 0,
        'company' => '',
        'price' => null,
        'status' => '',
    ],
    ['h' => 'help', 'l' => 'list', 'i' => 'id', 'c' => 'company', 'p' => 'price', 's' => 'status']
);

if ($options['help'] || (!$options['list'] && !$options['id'] && $options['company'] === '')) {
    cli_writeln("Ajusta preco e situacao de ofertas.

Todos os tipos - avulso, combo, assinatura e catalogo - vivem na mesma tabela,
entao --company alcanca todos de uma vez.

Opcoes:
  -h, --help            Esta ajuda
  -l, --list            Lista as ofertas com id, empresa, tipo, preco e situacao
  -i, --id=N            Alterar uma oferta
  -c, --company=NOME    Alterar TODAS as ofertas pagas da empresa
  -p, --price=VALOR     Novo preco. 0 torna a oferta gratuita
  -s, --status=ESTADO   draft | published | archived

Com --company, ofertas gratuitas sao PULADAS: coloca-las a 1,00 tiraria do ar o
caminho de acesso gratuito sem que ninguem pedisse. Para mudar uma delas, use
--id.

Exemplos:
  php local/marketplace/cli/set_offer.php --list
  php local/marketplace/cli/set_offer.php --id=3 --price=1.00
  php local/marketplace/cli/set_offer.php --company=demo --price=1.00
");
    exit(0);
}

// Listar.
if ($options['list']) {
    $offers = offer::get_records([], 'companyid, sortorder, name');
    if (!$offers) {
        cli_writeln('Nenhuma oferta cadastrada.');
        exit(0);
    }

    cli_writeln(sprintf(
        '%-5s %-14s %-8s %-26s %10s %-4s %-10s',
        'ID',
        'EMPRESA',
        'TIPO',
        'OFERTA',
        'PRECO',
        'MOE',
        'SITUACAO'
    ));
    foreach ($offers as $o) {
        $c = company::get_record(['id' => $o->get('companyid')]);
        cli_writeln(sprintf(
            '%-5d %-14s %-8s %-26s %10.2f %-4s %-10s',
            $o->get('id'),
            $c ? shorten_text($c->get('shortname'), 14, true) : '?',
            $o->get('offertype'),
            shorten_text($o->get('name'), 26, true),
            $o->get('price'),
            $o->get('currency'),
            $o->get('status')
        ));
    }
    exit(0);
}

// Validado antes de qualquer caminho: em lote, um valor invalido so seria
// pego pelo persistent na primeira oferta, ja depois de o comando ter comecado
// a parecer que ia funcionar.
if ($options['status'] !== '') {
    $valid = [offer::STATUS_DRAFT, offer::STATUS_PUBLISHED, offer::STATUS_ARCHIVED];
    if (!in_array($options['status'], $valid, true)) {
        cli_error('Situacao invalida. Use: ' . implode(', ', $valid));
    }
}

if ($options['price'] !== null && (float) $options['price'] < 0) {
    cli_error('Preco nao pode ser negativo.');
}

// Alterar em lote.
if ($options['company'] !== '') {
    $c = company::get_record(['shortname' => $options['company']]);
    if (!$c) {
        cli_error("Empresa '{$options['company']}' nao existe.");
    }
    if ($options['price'] === null && $options['status'] === '') {
        cli_error('Nada a alterar. Informe --price ou --status.');
    }

    $offers = offer::get_records(['companyid' => $c->get('id')], 'sortorder, name');
    $done = 0;
    foreach ($offers as $o) {
        // Gratuita fica de fora. Um --price em lote que cobrasse pela oferta
        // gratuita derrubaria o acesso livre sem ninguem ter pedido isso.
        if ($o->is_free()) {
            cli_writeln(sprintf('  pulada (gratuita): %d %s', $o->get('id'), $o->get('name')));
            continue;
        }
        if ($options['price'] !== null) {
            $o->set('price', (float) $options['price']);
        }
        if ($options['status'] !== '') {
            $o->set('status', $options['status']);
        }
        $o->update();
        cli_writeln(sprintf(
            '  %-8s %-26s %10.2f %s / %s',
            $o->get('offertype'),
            shorten_text($o->get('name'), 26, true),
            $o->get('price'),
            $o->get('currency'),
            $o->get('status')
        ));
        $done++;
    }
    cli_writeln("$done oferta(s) alterada(s).");
    exit(0);
}

// Alterar.
$offer = offer::get_record(['id' => (int) $options['id']]);
if (!$offer) {
    cli_error("Oferta {$options['id']} nao existe. Use --list para ver as disponiveis.");
}

$before = sprintf('%.2f %s / %s', $offer->get('price'), $offer->get('currency'), $offer->get('status'));
$changed = false;

if ($options['price'] !== null) {
    $offer->set('price', (float) $options['price']);
    $changed = true;
}

if ($options['status'] !== '') {
    $offer->set('status', $options['status']);
    $changed = true;
}

if (!$changed) {
    cli_error('Nada a alterar. Informe --price ou --status.');
}

// Update() dispara a validacao do persistent, inclusive a checagem de que a
// moeda da oferta e aquela em que a empresa realmente recebe. Um preco salvo
// numa moeda que a conta nao aceita so quebraria no checkout, com o aluno na
// tela de pagamento.
$offer->update();

cli_writeln(sprintf('Oferta %d (%s)', $offer->get('id'), $offer->get('name')));
cli_writeln('  antes:  ' . $before);
cli_writeln(sprintf(
    '  agora:  %.2f %s / %s',
    $offer->get('price'),
    $offer->get('currency'),
    $offer->get('status')
));

exit(0);
