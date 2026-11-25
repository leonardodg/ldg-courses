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
 * Cadastro de oferta, feito pelo vendedor.
 *
 * Fica fora de /admin porque quem publica oferta e o vendedor, no contexto da
 * categoria da empresa dele - nao o administrador do site.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_marketplace\company;
use local_marketplace\form\offer_form;
use local_marketplace\offer;

$shortname = required_param('company', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);

require_login();

$company = company::get_record(['shortname' => $shortname]);
if (!$company) {
    throw new moodle_exception('invalidrecord', 'error');
}

$context = $company->get_context();
require_capability('local/marketplace:managecompany', $context);

$existing = null;
if ($id) {
    $existing = offer::get_record(['id' => $id]);
    // A oferta tem que ser DESTA empresa. Sem esta checagem, trocar o id na URL
    // editaria a oferta de outro vendedor, ja que a capability foi verificada
    // no contexto da empresa que veio no parametro.
    if (!$existing || (int) $existing->get('companyid') !== (int) $company->get('id')) {
        throw new moodle_exception('invalidrecord', 'error');
    }
}

$panelurl = new moodle_url('/local/marketplace/company.php', ['company' => $shortname]);
$url = new moodle_url(
    '/local/marketplace/offer_edit.php',
    array_filter(['company' => $shortname, 'id' => $id])
);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('offeredit', 'local_marketplace'));
$PAGE->set_heading(format_string($company->get('name')));

$form = new offer_form($url, [
    'company' => $company,
    'offerid' => $existing ? (int) $existing->get('id') : 0,
]);

if ($existing) {
    $form->set_data([
        'id' => $existing->get('id'),
        'company' => $shortname,
        'name' => $existing->get('name'),
        'description' => ['text' => $existing->get('description') ?? '', 'format' => FORMAT_HTML],
        'offertype' => $existing->get('offertype'),
        'courses' => $existing->get_course_ids(),
        'price' => $existing->get('price'),
        'currency' => $existing->get('currency'),
        'accessmode' => $existing->get('accessmode'),
        'accessdays' => $existing->get('accessdays'),
        'billingdays' => $existing->get('billingdays'),
        'maxcycles' => $existing->get('maxcycles'),
        'status' => $existing->get('status'),
        'sortorder' => $existing->get('sortorder'),
    ]);
}

if ($form->is_cancelled()) {
    redirect($panelurl);
}

if ($data = $form->get_data()) {
    $o = $existing ?: new offer();
    $o->set('companyid', (int) $company->get('id'));
    $o->set('name', $data->name);
    $o->set('description', $data->description['text'] ?? null);
    $o->set('offertype', $data->offertype);
    $o->set('price', (float) $data->price);
    $o->set('currency', $data->currency);
    $o->set('accessmode', $data->accessmode);
    $o->set('accessdays', (int) $data->accessdays);
    $o->set('billingdays', $data->accessmode === offer::ACCESS_RECURRING ? (int) $data->billingdays : 0);
    $o->set('maxcycles', $data->accessmode === offer::ACCESS_RECURRING ? (int) $data->maxcycles : 0);
    $o->set('status', $data->status);
    $o->set('sortorder', (int) $data->sortorder);

    if ($existing) {
        $o->update();
    } else {
        $o->create();
    }

    // Catalogo nao lista cursos: segue a categoria e cursos novos entram
    // sozinhos. Gravar uma lista ali criaria um vinculo que o sistema ignora.
    if ($data->offertype !== offer::TYPE_CATALOG) {
        $wanted = array_map('intval', $data->courses ?? []);
        $current = $o->get_course_ids();

        foreach (array_diff($wanted, $current) as $courseid) {
            $o->add_course((int) $courseid);
        }
        foreach (array_diff($current, $wanted) as $courseid) {
            $DB->delete_records('local_marketplace_offer_course', [
                'offerid' => $o->get('id'),
                'courseid' => (int) $courseid,
            ]);
        }
    }

    redirect(
        $panelurl,
        get_string('offersaved', 'local_marketplace'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($existing
    ? format_string($existing->get('name'))
    : get_string('offercreate', 'local_marketplace'));
$form->display();
echo $OUTPUT->footer();
