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
 * Provisiona uma empresa.
 *
 * A parceria e fechada fora do sistema; esta tela so executa o que foi
 * combinado. Uma unica chamada a api::create_company() cria a categoria,
 * atribui o papel de vendedor ao dono no contexto dela e provisiona a conta de
 * pagamento - tudo em transacao.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_marketplace\api;
use local_marketplace\form\company_form;

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_marketplace_companies');

$listurl = new moodle_url('/local/marketplace/admin/companies.php');
$existing = $id ? new \local_marketplace\company($id) : null;

$PAGE->set_url(new moodle_url('/local/marketplace/admin/company_edit.php', $id ? ['id' => $id] : []));
$PAGE->navbar->add($existing
    ? format_string($existing->get('name'))
    : get_string('createcompany', 'local_marketplace'));

$customdata = [];
if ($existing) {
    $customdata = [
        'companyid' => (int) $existing->get('id'),
        'shortname' => $existing->get('shortname'),
    ];
}

$form = new company_form(null, $customdata);

// O filemanager precisa de um rascunho preparado ANTES do set_data, senao a
// tela abre vazia mesmo com arquivo salvo.
$draftcss = 0;
$draftlogo = 0;
if ($existing && $existing->get('categoryid')) {
    $filecontext = $existing->get_context();
    foreach ([['pagecss', 'draftcss'], ['pagelogo', 'draftlogo']] as [$area, $var]) {
        $id = 0;
        file_prepare_draft_area($id, $filecontext->id, 'local_marketplace', $area, 0, [
            'subdirs' => 0,
            'maxfiles' => 1,
        ]);
        $$var = $id;
    }
}

if ($existing) {
    $form->set_data([
        'pagecss' => $draftcss,
        'pagelogo' => $draftlogo,
        'pagetitle' => $existing->get('pagetitle'),
        'pageintro' => [
            'text' => $existing->get('pageintro') ?? '',
            'format' => FORMAT_HTML,
        ],
        'pageaccent' => $existing->get('pageaccent'),
        'id' => $existing->get('id'),
        'name' => $existing->get('name'),
        'shortname' => $existing->get('shortname'),
        'cnpj' => $existing->get('cnpj'),
        'planid' => $existing->get('planid'),
        'commissionpct' => $existing->get('commissionpct'),
        // Nulo vira string vazia: e a opcao "herdar a base do site". Sem isto o
        // campo era gravado e nunca lido de volta.
        'commissionbase' => $existing->get('commissionbase') ?? '',
        'themename' => $existing->get('themename'),
        'hostname' => $existing->get('hostname'),
    ]);
}

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($data = $form->get_data()) {
    // O editor devolve array; a api espera string.
    $data->pageintro = $data->pageintro['text'] ?? null;

    if ($existing) {
        api::update_company($existing, $data);

        // Salvo depois do update: o arquivo vive no contexto da categoria, e
        // numa empresa recem-criada a categoria so existe apos o provisionamento.
        if ($existing->get('categoryid')) {
            foreach (['pagecss', 'pagelogo'] as $area) {
                file_save_draft_area_files(
                    (int) ($data->$area ?? 0),
                    $existing->get_context()->id,
                    'local_marketplace',
                    $area,
                    0,
                    ['subdirs' => 0, 'maxfiles' => 1]
                );
            }
        }
        redirect(
            $listurl,
            get_string('companyupdated', 'local_marketplace', format_string($existing->get('name'))),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $company = api::create_company((object) [
        'name' => $data->name,
        'shortname' => $data->shortname,
        'cnpj' => $data->cnpj ?? null,
        'themename' => $data->themename ?? null,
        'hostname' => $data->hostname ?? null,
    ], (int) $data->ownerid);

    // Leva direto para os membros: quase sempre o proximo passo e acrescentar
    // os outros vendedores, e a empresa acabou de nascer com um so.
    redirect(
        new moodle_url('/local/marketplace/admin/members.php', ['id' => $company->get('id')]),
        get_string('companycreated', 'local_marketplace', format_string($company->get('name'))),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($existing
    ? format_string($existing->get('name'))
    : get_string('createcompany', 'local_marketplace'));

if (!$existing) {
    echo $OUTPUT->notification(get_string('createcompanyintro', 'local_marketplace'), 'info');
} else {
    echo $OUTPUT->notification(get_string('editcompanyintro', 'local_marketplace'), 'info');
}

$form->display();
echo $OUTPUT->footer();
