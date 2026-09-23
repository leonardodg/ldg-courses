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
 * Cria um cenario completo de teste: vendedor, empresa, cursos e ofertas.
 *
 * CRIA DADOS. Nao rode num site com conteudo real sem entender o que faz.
 * Foi escrito para exercitar o fluxo de venda de ponta a ponta sem depender
 * de telas que ainda nao existem.
 *
 * Uso:
 *   php local/marketplace/cli/seed_demo.php --company=teste --seller=vendedor1
 *   php local/marketplace/cli/seed_demo.php --company=teste --clean
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

use local_marketplace\api;
use local_marketplace\company;
use local_marketplace\offer;

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'company' => 'teste',
        'seller' => 'vendedor1',
        'password' => '',
        'courses' => 3,
        'clean' => false,
    ],
    ['h' => 'help', 'c' => 'company', 's' => 'seller', 'p' => 'password']
);

if ($options['help']) {
    cli_writeln("Cria um cenario de teste do marketplace.

Opcoes:
  -h, --help              Esta ajuda
  -c, --company=NOME      Nome curto da empresa (padrao: teste)
  -s, --seller=USUARIO    Username do vendedor (padrao: vendedor1)
  -p, --password=SENHA    Senha do vendedor. Sem ela, uma e sorteada e exibida
      --courses=N         Quantos cursos criar (padrao: 3)
      --clean             Remove o cenario em vez de criar
");
    exit(0);
}

$shortname = $options['company'];

// Limpeza.
if ($options['clean']) {
    $existing = company::get_record(['shortname' => $shortname]);
    if (!$existing) {
        cli_writeln("Empresa '$shortname' nao existe.");
        exit(0);
    }

    $companyid = (int) $existing->get('id');
    $categoryid = (int) $existing->get('categoryid');

    // A ordem importa: direitos e ofertas referenciam a empresa.
    $DB->delete_records('local_marketplace_entitlement', ['companyid' => $companyid]);
    foreach (offer::get_records(['companyid' => $companyid]) as $o) {
        $DB->delete_records('local_marketplace_offer_course', ['offerid' => $o->get('id')]);
        $o->delete();
    }
    $DB->delete_records('local_marketplace_member', ['companyid' => $companyid]);

    if ($categoryid) {
        $context = context_coursecat::instance($categoryid, IGNORE_MISSING);
        if ($context) {
            $DB->delete_records('payment_gateways', [
                'accountid' => $DB->get_field('payment_accounts', 'id', ['contextid' => $context->id]),
            ]);
            $DB->delete_records('payment_accounts', ['contextid' => $context->id]);
        }
        $cat = core_course_category::get($categoryid, IGNORE_MISSING, true);
        if ($cat) {
            $cat->delete_full(false);
        }
    }
    $existing->delete();

    cli_writeln("Cenario '$shortname' removido.");
    exit(0);
}

// Criacao.
if (company::get_record(['shortname' => $shortname])) {
    cli_error("Empresa '$shortname' ja existe. Use --clean para remover antes.");
}

// Vendedor.
$seller = $DB->get_record('user', ['username' => $options['seller']]);
if (!$seller) {
    $new = (object) [
        'username' => $options['seller'],
        'firstname' => 'Vendedor',
        'lastname' => ucfirst($options['seller']),
        'email' => $options['seller'] . '@exemplo.local',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ];
    // A senha e obrigatoria aqui. user_create_user() sem senha produz um
    // usuario que ninguem consegue usar - e o vendedor precisa entrar para
    // vincular a conta Mercado Pago dele.
    $new->password = $options['password'] !== ''
        ? $options['password']
        : 'Teste@' . random_int(100000, 999999);
    $plain = $new->password;

    $new->id = user_create_user($new, true, false);
    $seller = $DB->get_record('user', ['id' => $new->id]);
    cli_writeln("Vendedor criado: {$seller->username} (id {$seller->id})");
    cli_writeln("  senha: {$plain}");
} else {
    cli_writeln("Vendedor existente: {$seller->username} (id {$seller->id})");
}

// Empresa. O provisionamento cria categoria, papel e conta de pagamento.
$company = api::create_company((object) [
    'name' => 'Empresa ' . ucfirst($shortname),
    'shortname' => $shortname,
    'cnpj' => null,
    'themename' => null,
    'hostname' => null,
], (int) $seller->id);

$accounts = $company->get_payment_accounts();
$accountpairs = [];
foreach ($accounts as $countrycode => $account) {
    $accountpairs[] = $countrycode . ':' . $account->get('id');
}
cli_writeln(sprintf(
    "Empresa criada: id %d | categoria %d | contas por pais %s",
    $company->get('id'),
    $company->get('categoryid'),
    $accountpairs ? implode(', ', $accountpairs) : 'nenhuma'
));

// Cursos.
$courseids = [];
for ($i = 1; $i <= (int) $options['courses']; $i++) {
    $course = create_course((object) [
        'fullname' => "Curso Demo $i",
        'shortname' => $shortname . '-curso' . $i . '-' . time(),
        'category' => $company->get('categoryid'),
        'visible' => 1,
    ]);
    $courseids[] = (int) $course->id;
    cli_writeln("  curso criado: {$course->fullname} (id {$course->id})");
}

/**
 * Cria uma oferta.
 *
 * @param company $company
 * @param string $name
 * @param string $type
 * @param string $mode
 * @param int $days
 * @param float $price
 * @param int[] $courses
 * @return offer
 */
function seed_offer(
    company $company,
    string $name,
    string $type,
    string $mode,
    int $days,
    float $price,
    array $courses
): offer {
    $o = new offer();
    $o->set('companyid', (int) $company->get('id'));
    $o->set('name', $name);
    $o->set('offertype', $type);
    $o->set('accessmode', $mode);
    $o->set('accessdays', $days);
    $o->set('price', $price);
    $o->set('currency', 'BRL');
    $o->set('status', offer::STATUS_PUBLISHED);
    $o->create();
    foreach ($courses as $cid) {
        $o->add_course($cid);
    }
    return $o;
}

// Uma oferta de cada formato, para exercitar todos os caminhos.
$offers = [
    seed_offer($company, 'Curso 1 - avulso', 'single', 'lifetime', 0, 49.90, [$courseids[0]]),
    seed_offer($company, 'Curso 1 - 30 dias', 'single', 'days', 30, 19.90, [$courseids[0]]),
    seed_offer($company, 'Combo completo', 'bundle', 'lifetime', 0, 99.90, $courseids),
    seed_offer($company, 'Gratuito', 'single', 'lifetime', 0, 0.00, [$courseids[count($courseids) - 1]]),
];
// Assinatura do catalogo: nao lista cursos, segue a categoria.
$offers[] = seed_offer($company, 'Assinatura mensal', 'catalog', 'recurring', 30, 29.90, []);

cli_writeln('');
cli_heading('Ofertas publicadas');
foreach ($offers as $o) {
    cli_writeln(sprintf(
        '  %-24s %-8s %-9s %8.2f  libera %d curso(s)',
        $o->get('name'),
        $o->get('offertype'),
        $o->get('accessmode'),
        $o->get('price'),
        count($o->get_course_ids())
    ));
}

cli_writeln('');
cli_heading('Proximos passos');
cli_writeln("  1. Vincular a conta Mercado Pago do vendedor:");
cli_writeln("     categoria 'Empresa " . ucfirst($shortname) . "' -> Contas de pagamento -> Mercado Pago -> Vincular");
cli_writeln("  2. Abrir a vitrine e comprar:");
cli_writeln("     {$CFG->wwwroot}/local/marketplace/offers.php?company={$shortname}");
cli_writeln('');
cli_writeln("  Estado atual: php local/marketplace/cli/status.php");
cli_writeln('');

exit(0);
