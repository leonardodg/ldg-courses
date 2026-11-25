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
 * Vendedores de uma empresa.
 *
 * Uma empresa tem N vendedores e uma pessoa pode estar em varias empresas. O
 * vinculo daqui faz duas coisas ao mesmo tempo: registra a participacao e
 * atribui o papel no contexto da CATEGORIA da empresa. Sao inseparaveis -
 * registro sem papel produz alguem que consta como vendedor e nao consegue
 * fazer nada.
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

$companyid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

admin_externalpage_setup('local_marketplace_companies');

$company = new company($companyid);
$url = new moodle_url('/local/marketplace/admin/members.php', ['id' => $companyid]);

$PAGE->set_url($url);
$PAGE->navbar->add(format_string($company->get('name')));

// Acoes.
if ($action !== '' && $userid > 0) {
    require_sesskey();

    if ($action === 'remove') {
        // O dono nao sai por aqui. Uma empresa sem dono fica sem responsavel
        // pela conta de pagamento, e a tela nao teria como recuperar isso.
        $membership = member::get_membership($companyid, $userid);
        if ($membership && $membership->is_owner()) {
            redirect(
                $url,
                get_string('errorcannotremoveowner', 'local_marketplace'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        api::remove_member($company, $userid);
        redirect(
            $url,
            get_string('memberremoved', 'local_marketplace'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($action === 'promote' || $action === 'demote') {
        api::set_member_role(
            $company,
            $userid,
            $action === 'promote' ? member::ROLE_OWNER : member::ROLE_SELLER
        );
        redirect(
            $url,
            get_string('memberrolechanged', 'local_marketplace'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Acrescentar um.
// Processado ANTES do header: um redirect depois de imprimir a pagina
// dispararia "Cannot modify header information".
$mform = new \local_marketplace\form\member_form($url, ['companyid' => $companyid]);

if ($data = $mform->get_data()) {
    api::add_member($company, (int) $data->userid);
    redirect(
        $url,
        get_string('memberadded', 'local_marketplace'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('membersof', 'local_marketplace', format_string($company->get('name'))));

$mform->display();

// Lista.
$members = member::get_by_company($companyid);

if (!$members) {
    echo $OUTPUT->notification(get_string('nomembers', 'local_marketplace'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('fullname'),
    get_string('email'),
    get_string('role'),
    '',
];
$table->attributes['class'] = 'generaltable';

foreach ($members as $m) {
    $user = \core_user::get_user((int) $m->get('userid'), '*', IGNORE_MISSING);
    if (!$user) {
        continue;
    }

    $isowner = $m->is_owner();

    $actions = [];
    $actions[] = html_writer::link(
        new moodle_url($url, [
            'action' => $isowner ? 'demote' : 'promote',
            'userid' => $user->id,
            'sesskey' => sesskey(),
        ]),
        get_string($isowner ? 'makeseller' : 'makeowner', 'local_marketplace'),
        ['class' => 'btn btn-sm btn-secondary']
    );

    if (!$isowner) {
        $actions[] = html_writer::link(
            new moodle_url($url, [
                'action' => 'remove',
                'userid' => $user->id,
                'sesskey' => sesskey(),
            ]),
            get_string('remove'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    }

    $table->data[] = [
        fullname($user),
        s($user->email),
        get_string('member' . $m->get('memberrole'), 'local_marketplace'),
        implode(' ', $actions),
    ];
}

echo html_writer::table($table);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/marketplace/admin/companies.php'),
        get_string('back'),
        ['class' => 'btn btn-secondary']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
