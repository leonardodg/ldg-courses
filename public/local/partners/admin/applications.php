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
 * Fila de candidaturas, visao do administrador.
 *
 * Tabela, e nao mustache: e uma lista de decisao, e o html_table do Moodle ja
 * traz acessibilidade e marcacao consistente com as outras telas de admin do
 * projeto. O mustache foi aberto so para a landing, que e pagina de marketing.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_marketplace\plan;
use local_partners\application;

admin_externalpage_setup('local_partners_applications');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('applications', 'local_partners'));

$applications = application::get_records([], 'status, timecreated');

if (!$applications) {
    echo $OUTPUT->notification(get_string('noapplications', 'local_partners'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('companyname', 'local_partners'),
    get_string('contactname', 'local_partners'),
    get_string('planofinterest', 'local_partners'),
    get_string('submittedon', 'local_partners'),
    get_string('applicationstatus', 'local_partners'),
    '',
];
$table->attributes['class'] = 'generaltable';

foreach ($applications as $app) {
    $planid = $app->get('planid');
    $plan = $planid ? plan::get_record(['id' => (int) $planid]) : null;

    $status = $app->get('status');
    $badge = [
        // A nao confirmada aparece na lista, mas apagada: ela e visivel para o
        // administrador enxergar spam e envio que nao completou, e nao para ser
        // decidida - a tela de decisao so aceita candidatura na fila.
        application::STATUS_UNCONFIRMED => 'bg-light text-dark border',
        application::STATUS_PENDING => 'bg-warning text-dark',
        application::STATUS_APPROVED => 'bg-success',
        application::STATUS_REJECTED => 'bg-secondary',
    ][$status] ?? 'bg-secondary';

    $table->data[] = [
        format_string($app->get('companyname'))
            . html_writer::tag('div', s($app->get('contactemail')), ['class' => 'text-muted small']),
        format_string($app->get('contactname')),
        $plan ? format_string($plan->get('name')) : get_string('planundecided', 'local_partners'),
        userdate($app->get('timecreated'), get_string('strftimedatetimeshort', 'langconfig')),
        html_writer::tag('span', get_string('status' . $status, 'local_partners'), ['class' => 'badge ' . $badge]),
        html_writer::link(
            new moodle_url('/local/partners/admin/application_view.php', ['id' => $app->get('id')]),
            get_string('view'),
            ['class' => 'btn btn-sm btn-secondary']
        ),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
