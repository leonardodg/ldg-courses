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
 * Planos comerciais, visao do administrador.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_marketplace\company;
use local_marketplace\plan;

admin_externalpage_setup('local_marketplace_plans');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('plans', 'local_marketplace'));

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/marketplace/admin/plan_edit.php'),
        get_string('addplan', 'local_marketplace'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

$plans = plan::get_records([], 'sortorder, name');

if (!$plans) {
    echo $OUTPUT->notification(get_string('noplans', 'local_marketplace'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('planname', 'local_marketplace'),
    get_string('planmonthlyfee', 'local_marketplace'),
    get_string('plancommissionpct', 'local_marketplace'),
    get_string('planhostingmodel', 'local_marketplace'),
    get_string('companies', 'local_marketplace'),
    get_string('planstatus', 'local_marketplace'),
    '',
];
$table->attributes['class'] = 'generaltable';

foreach ($plans as $p) {
    $planid = (int) $p->get('id');

    // Quantas empresas dependem deste plano. E o numero que diz se arquivar
    // uma linha e inofensivo ou se vai mexer na comissao de alguem.
    $companies = count(company::get_records(['planid' => $planid]));

    $hosting = $p->get('hostingmodel') === plan::HOSTING_BYOS
        ? get_string('hostingbyos', 'local_marketplace')
        : get_string('hostingnative', 'local_marketplace');

    $status = $p->get('status') === plan::STATUS_ACTIVE
        ? html_writer::tag('span', get_string('planstatusactive', 'local_marketplace'), ['class' => 'badge bg-success'])
        : html_writer::tag('span', get_string('planstatusarchived', 'local_marketplace'), ['class' => 'badge bg-secondary']);

    if (!$p->get('ispublic')) {
        $status .= ' ' . html_writer::tag('span', get_string('hide'), ['class' => 'badge bg-light text-dark']);
    }

    $table->data[] = [
        html_writer::link(
            new moodle_url('/local/marketplace/admin/plan_edit.php', ['id' => $planid]),
            format_string($p->get('name'))
        ) . html_writer::tag('div', s($p->get('shortname')), ['class' => 'text-muted small']),
        format_float((float) $p->get('monthlyfee'), 2) . ' ' . s($p->get('currency')),
        format_float((float) $p->get('commissionpct'), 2) . '%',
        $hosting,
        $companies,
        $status,
        html_writer::link(
            new moodle_url('/local/marketplace/admin/plan_edit.php', ['id' => $planid]),
            get_string('edit'),
            ['class' => 'btn btn-secondary btn-sm']
        ),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
