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
 * Relatorio de estado do marketplace.
 *
 * SO LEITURA: nao cria, nao altera e nao apaga nada. Serve para revisar o que
 * esta configurado e para depurar "por que este aluno nao tem acesso" sem
 * precisar abrir o banco.
 *
 * Uso:
 *   php local/marketplace/cli/status.php
 *   php local/marketplace/cli/status.php --user=alunoteste
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\member;
use local_marketplace\offer;

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'user' => ''],
    ['h' => 'help', 'u' => 'user']
);

if ($options['help']) {
    cli_writeln("Relatorio de estado do marketplace (so leitura).

Opcoes:
  -h, --help            Mostra esta ajuda
  -u, --user=USERNAME   Detalha os direitos de acesso de um usuario
");
    exit(0);
}

$yes = 'sim';
$no = 'nao';

// Plugins.
cli_heading('Plugins');
$enrolok = enrol_is_enabled('marketplace');
cli_writeln(sprintf(
    '  enrol_marketplace habilitado ......... %s',
    $enrolok ? $yes : 'NAO  <-- sem isto a compra nao vira matricula'
));
$availok = array_key_exists('marketplace', core_component::get_plugin_list('availability'));
cli_writeln(sprintf('  availability_marketplace instalado ... %s', $availok ? $yes : 'NAO'));
$roles = [
    'papel de responsavel ................. ' => \local_marketplace\roles::MANAGER,
    'papel de vendedor .................... ' => \local_marketplace\roles::SELLER,
];
foreach ($roles as $label => $shortname) {
    $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
    $capcount = $roleid ? $DB->count_records('role_capabilities', ['roleid' => $roleid]) : 0;
    cli_writeln(sprintf('  %s%s (%d capabilities)', $label, $roleid ? $yes : 'NAO', $capcount));
    if ($roleid && $capcount === 0) {
        cli_writeln('    ATENCAO: papel existe sem capability nenhuma; reinstale o plugin.');
    }
}

// Os repositorios que ninguem proibiu.
//
// Este relatorio NAO conserta nada, e e de proposito. Quem tranca e a constante
// roles::PROHIBIT; codigo que lesse a configuracao e proibisse sozinho seria
// seguranca dependente de a configuracao estar certa. O que este bloco faz e
// avisar que alguem habilitou um caminho novo para o moodledata - e, no plano
// Free, caminho para o moodledata e video servido pela NOSSA banda.
$open = \local_marketplace\roles::open_repositories();
cli_writeln(sprintf(
    '  repositorios sem proibicao ........... %s',
    $open ? implode(', ', $open) . '  <-- caminho aberto para o moodledata' : 'NENHUM'
));
cli_writeln(sprintf(
    '  tema por categoria ligado ............ %s',
    !empty($CFG->allowcategorythemes) ? $yes : 'NAO  <-- tema por empresa nao vai funcionar'
));

// Empresas.
$companies = company::get_records([], 'name');
cli_heading(sprintf('Empresas (%d)', count($companies)));

foreach ($companies as $c) {
    // Uma linha por pais: a empresa tem uma conta em cada mercado onde vende.
    $accounts = $c->get_payment_accounts();
    $accountpairs = [];
    $gateways = [];
    foreach ($accounts as $countrycode => $account) {
        $accountpairs[] = $countrycode . ':' . $account->get('id');
        foreach ($account->get_gateways(false) as $gw) {
            if ($gw->get('id')) {
                $gateways[] = $countrycode . ':' . $gw->get('gateway') . ($gw->get('enabled') ? '' : ' (desligado)');
            }
        }
    }

    cli_writeln(sprintf("\n  %s  [%s]", $c->get('name'), $c->get('shortname')));
    cli_writeln(sprintf('    situacao ........... %s', $c->get('status')));
    cli_writeln(sprintf('    categoria .......... %s', $c->get('categoryid') ?: 'NENHUMA'));
    cli_writeln(sprintf('    dominio ............ %s', $c->get('hostname') ?: '-'));
    cli_writeln(sprintf('    vendedores ......... %d', count(member::get_by_company((int) $c->get('id')))));
    cli_writeln(sprintf('    contas por pais .... %s', $accountpairs ? implode(', ', $accountpairs) : 'NENHUMA'));
    cli_writeln(sprintf('    gateways ........... %s', $gateways ? implode(', ', $gateways) : 'nenhum'));
    cli_writeln(sprintf('    PODE VENDER ........ %s', $c->can_sell() ? $yes : 'nao - so oferta gratuita'));

    $offers = offer::get_records(['companyid' => $c->get('id')], 'sortorder, name');
    cli_writeln(sprintf('    ofertas (%d):', count($offers)));
    foreach ($offers as $o) {
        cli_writeln(sprintf(
            '      %-28s %-8s %-9s %8.2f %s  libera %d curso(s)  [%s]',
            core_text::substr($o->get('name'), 0, 28),
            $o->get('offertype'),
            $o->get('accessmode'),
            $o->get('price'),
            $o->get('currency'),
            count($o->get_course_ids()),
            $o->get('status')
        ));
    }
}

// Direitos.
$total = $DB->count_records('local_marketplace_entitlement');
$active = $DB->count_records_select(
    'local_marketplace_entitlement',
    'status = :s AND (timeend = 0 OR timeend > :now)',
    ['s' => entitlement::STATUS_ACTIVE, 'now' => time()]
);
cli_heading('Direitos de acesso');
cli_writeln(sprintf('  total ............... %d', $total));
cli_writeln(sprintf('  vigentes ............ %d', $active));
cli_writeln(sprintf('  vencidos/cancelados . %d', $total - $active));

// Por usuario.
if ($options['user'] !== '') {
    $user = $DB->get_record('user', ['username' => $options['user']]);
    cli_heading('Usuario: ' . $options['user']);

    if (!$user) {
        cli_writeln('  usuario nao encontrado.');
    } else {
        $ents = entitlement::get_active_for_user((int) $user->id);
        cli_writeln(sprintf('  direitos vigentes: %d', count($ents)));

        $courses = [];
        foreach ($ents as $e) {
            $o = new offer($e->get('offerid'));
            $end = $e->get('timeend') ? userdate($e->get('timeend'), '%d/%m/%Y') : 'vitalicio';
            cli_writeln(sprintf('    %-28s ate %s', core_text::substr($o->get('name'), 0, 28), $end));
            foreach ($o->get_course_ids() as $cid) {
                $courses[$cid] = true;
            }
        }

        cli_writeln(sprintf('  cursos que DEVERIA acessar: %d', count($courses)));
        $enrolled = $DB->get_records_sql(
            "SELECT e.courseid, ue.status
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.enrol = 'marketplace'",
            ['userid' => $user->id]
        );
        $activeenrolments = array_filter($enrolled, fn($m) => (int) $m->status === ENROL_USER_ACTIVE);
        cli_writeln(sprintf('  matriculas ativas .........: %d', count($activeenrolments)));
        cli_writeln(sprintf('  matriculas suspensas ......: %d', count($enrolled) - count($activeenrolments)));

        $missing = array_diff(array_keys($courses), array_keys($activeenrolments));
        if ($missing) {
            cli_writeln('  DIVERGENCIA: tem direito mas nao esta matriculado nos cursos '
                . implode(', ', $missing));
            cli_writeln('  Rode o sync: enrol_get_plugin(\'marketplace\')->sync_user(' . $user->id . ')');
        } else {
            cli_writeln('  direitos e matriculas estao em sincronia.');
        }
    }
}

cli_writeln('');
exit(0);
