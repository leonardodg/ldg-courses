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
 * Detalhe de uma candidatura, com a decisao de aprovar ou recusar.
 *
 * Aprovar cria uma EMPRESA e uma CATEGORIA de curso. A decisao e humana porque
 * categoria e objeto global do site - e a mesma razao pela qual a plataforma
 * nao tem auto-atendimento para criar empresa.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_marketplace\company;
use local_marketplace\plan;
use local_partners\api;
use local_partners\application;
use local_partners\form\approval_form;

$id = required_param('id', PARAM_INT);

admin_externalpage_setup('local_partners_applications');

$listurl = new moodle_url('/local/partners/admin/applications.php');
$PAGE->set_url(new moodle_url('/local/partners/admin/application_view.php', ['id' => $id]));

$app = application::get_record(['id' => $id]);

if (!$app) {
    throw new moodle_exception('errorapplicationnotfound', 'local_partners');
}

$planid = $app->get('planid');
$plan = $planid ? plan::get_record(['id' => (int) $planid]) : null;

// O formulario e montado e PROCESSADO antes de qualquer saida.
//
// Todo redirect daqui - cancelar, aprovar, recusar - precisa acontecer com o
// buffer limpo. Com o header ja impresso, o redirect() do Moodle emite
// "You should really redirect before you start page output" e cai num
// redirecionamento por meta/JS, que e mais lento e some com a mensagem de
// sucesso em navegador que bloqueia script.
// Se ja existe conta com o e-mail do contato, ela vem pre-selecionada: o
// caminho comum e o candidato ja ser aluno da plataforma, e obrigar o
// administrador a procurar de novo o que o sistema ja sabe e atrito a toa.
$matched = $DB->get_record('user', [
    'email' => $app->get('contactemail'),
    'deleted' => 0,
    'mnethostid' => $CFG->mnet_localhost_id,
], 'id', IGNORE_MULTIPLE);

$form = new approval_form($PAGE->url, [
    'id' => (int) $app->get('id'),
    'contactemail' => $app->get('contactemail'),
    'matcheduserid' => $matched ? (int) $matched->id : null,
    'planid' => $app->get('planid'),
    'suggestedshortname' => api::suggest_shortname($app->get('companyname')),
]);

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($decision = $form->get_data()) {
    if ($decision->decision === application::STATUS_APPROVED) {
        $created = api::approve($app, $decision);

        redirect(
            $listurl,
            get_string('approvedmessage', 'local_partners', format_string($created->get('name'))),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    api::reject($app, $decision);

    redirect(
        $listurl,
        get_string('rejectedmessage', 'local_partners', format_string($app->get('companyname'))),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($app->get('companyname')));

$rows = [
    'companyname' => format_string($app->get('companyname')),
    'cnpj' => $app->get('cnpj') ? s($app->get('cnpj')) : '-',
    'contactname' => format_string($app->get('contactname')),
    'contactemail' => html_writer::link('mailto:' . s($app->get('contactemail')), s($app->get('contactemail'))),
    'contactphone' => $app->get('contactphone') ? s($app->get('contactphone')) : '-',
    'website' => $app->get('website')
        ? html_writer::link($app->get('website'), s($app->get('website')), ['target' => '_blank', 'rel' => 'noopener'])
        : '-',
    'planofinterest' => $plan ? format_string($plan->get('name')) : get_string('planundecided', 'local_partners'),
    'country' => $app->get('country')
        ? (get_string_manager()->get_list_of_countries()[$app->get('country')] ?? s($app->get('country')))
        : '-',
    'learnersband' => $app->get('learnersband')
        ? application::band_label($app->get('learnersband'))
        : '-',
    'applicationmessage' => $app->get('message') ? nl2br(s($app->get('message'))) : '-',
    'submittedon' => userdate($app->get('timecreated')),
    // Mostra QUANDO aceitou, e nao "sim": e a informacao que tem valor se
    // alguem perguntar o que foi consentido e em que momento. Candidatura
    // anterior ao campo mostra o traco, que e a verdade sobre ela.
    'termsacceptedon' => $app->get('termsaccepted') ? userdate($app->get('termsaccepted')) : '-',
    'applicationstatus' => get_string('status' . $app->get('status'), 'local_partners'),
];

$table = new html_table();
$table->attributes['class'] = 'generaltable';

foreach ($rows as $key => $value) {
    $table->data[] = [
        html_writer::tag('strong', get_string($key, 'local_partners')),
        $value,
    ];
}

echo html_writer::table($table);

// Candidatura ja decidida nao volta a ser decidivel: o formulario sai da tela e
// no lugar dele fica o que aconteceu. Reenvio depois de recusa e linha nova, e
// nao reabertura desta.
if ($app->get('status') !== application::STATUS_PENDING) {
    if ($app->get('companyid') && ($provisioned = company::get_record(['id' => (int) $app->get('companyid')]))) {
        echo $OUTPUT->notification(
            get_string('alreadyapproved', 'local_partners', format_string($provisioned->get('name'))),
            'success'
        );
        echo html_writer::link(
            new moodle_url('/local/marketplace/admin/company_edit.php', ['id' => $provisioned->get('id')]),
            get_string('opencompany', 'local_partners'),
            ['class' => 'btn btn-primary me-2']
        );
    } else {
        echo $OUTPUT->notification(get_string('alreadydecided', 'local_partners'), 'info');
    }

    echo html_writer::link($listurl, get_string('back'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->heading(get_string('decision', 'local_partners'), 3);
$form->display();

echo html_writer::link($listurl, get_string('back'), ['class' => 'btn btn-secondary']);

echo $OUTPUT->footer();
