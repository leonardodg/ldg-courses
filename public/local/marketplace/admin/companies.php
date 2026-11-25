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
 * Empresas do marketplace, visao do administrador.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_marketplace\api;
use local_marketplace\company;
use local_marketplace\member;
use local_marketplace\plan;

admin_externalpage_setup('local_marketplace_companies');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('companies', 'local_marketplace'));

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/marketplace/admin/company_edit.php'),
        get_string('createcompany', 'local_marketplace'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

$companies = company::get_records([], 'name');

if (!$companies) {
    echo $OUTPUT->notification(get_string('nocompanies', 'local_marketplace'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('companyname', 'local_marketplace'),
    get_string('companyshortname', 'local_marketplace'),
    get_string('members', 'local_marketplace'),
    get_string('paymentsection', 'local_marketplace'),
    get_string('commissioneffective', 'local_marketplace'),
    get_string('companystatus', 'local_marketplace'),
    '',
];
$table->attributes['class'] = 'generaltable';

foreach ($companies as $c) {
    // O estado do meio de pagamento e a informacao que o administrador mais
    // procura: e o portao que decide se a empresa pode vender.
    // Uma empresa pode vender em varios paises, e cada pais tem a propria
    // moeda. Mostrar so a primeira esconderia metade da operacao dela.
    $currencies = implode(', ', $c->get_currencies());
    if ($c->can_sell()) {
        $payment = html_writer::tag(
            'span',
            get_string('cansell', 'local_marketplace') . ($currencies !== '' ? ' · ' . s($currencies) : ''),
            ['class' => 'badge bg-success']
        );
    } else {
        $payment = html_writer::tag(
            'span',
            get_string('cannotsell', 'local_marketplace'),
            ['class' => 'badge bg-warning text-dark']
        );
    }

    // A comissao efetiva e a ORIGEM dela. Sem dizer de onde o numero veio,
    // por uma empresa no plano Starter e ver 25% na tela parece defeito - e na
    // verdade e a comissao negociada com ela vencendo a do plano, que e o
    // comportamento desejado.
    $negotiated = $c->get_commission_percent();
    $plan = $c->get_plan();

    if ($negotiated !== null) {
        $pct = $negotiated;
        $origem = get_string('commissionfromcompany', 'local_marketplace');
    } else if ($plan && $plan->get('status') === plan::STATUS_ACTIVE) {
        $pct = (float) $plan->get('commissionpct');
        $origem = get_string('commissionfromplan', 'local_marketplace', format_string($plan->get('name')));
    } else {
        $pct = api::default_commission_percent();
        $origem = get_string('commissionfromsite', 'local_marketplace');
    }

    $commission = format_float($pct, 2) . '%'
        . html_writer::tag('div', s($origem), ['class' => 'text-muted small']);

    $actions = html_writer::link(
        new moodle_url('/local/marketplace/admin/company_edit.php', ['id' => $c->get('id')]),
        get_string('edit'),
        ['class' => 'btn btn-sm btn-secondary']
    ) . ' ' . html_writer::link(
        new moodle_url('/local/marketplace/admin/members.php', ['id' => $c->get('id')]),
        get_string('managemembers', 'local_marketplace'),
        ['class' => 'btn btn-sm btn-secondary']
    ) . ' ' . html_writer::link(
        new moodle_url('/local/marketplace/company.php', ['company' => $c->get('shortname')]),
        get_string('companypanel', 'local_marketplace'),
        ['class' => 'btn btn-sm btn-secondary']
    );

    $table->data[] = [
        format_string($c->get('name')),
        s($c->get('shortname')),
        count(member::get_by_company((int) $c->get('id'))),
        $payment,
        $commission,
        get_string('status' . $c->get('status'), 'local_marketplace'),
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
