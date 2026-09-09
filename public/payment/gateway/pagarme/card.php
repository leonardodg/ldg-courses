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
 * Formulario de cartao, com tokenizacao no navegador.
 *
 * Os campos NAO sao enviados a este servidor. O AMD manda o cartao direto ao
 * Pagar.me com a chave publica, recebe um token, e so o token volta para o
 * Moodle. Sem isso a instalacao entraria no escopo pesado de PCI.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use paygw_pagarme\payment_processor;

$reference = required_param('ref', PARAM_ALPHANUMEXT);

require_login();

$record = $DB->get_record(payment_processor::TABLE, ['externalreference' => $reference]);
if (!$record || (int) $record->userid !== (int) $USER->id) {
    throw new moodle_exception('invalidaccess', 'error');
}

$publickey = (string) get_config('paygw_pagarme', 'publickey');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/pagarme/card.php', ['ref' => $reference]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('cardheading', 'paygw_pagarme'));
$PAGE->set_heading(get_string('cardheading', 'paygw_pagarme'));

if (payment_processor::is_paid((string) $record->status) && !empty($record->paymentid)) {
    redirect(\core_payment\helper::get_success_url(
        $record->component,
        $record->paymentarea,
        (int) $record->itemid
    ));
}

$PAGE->requires->js_call_amd('paygw_pagarme/cardform', 'init', [$reference, $publickey]);

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('cardintro', 'paygw_pagarme'));

echo html_writer::start_div('', ['id' => 'paygw-pagarme-cardform', 'style' => 'max-width: 420px;']);

$fields = [
    'number' => 'cardnumber',
    'holder' => 'cardholder',
    'expiry' => 'cardexpiry',
    'cvv' => 'cardcvv',
];

foreach ($fields as $field => $stringkey) {
    echo html_writer::start_div('mb-2');
    echo html_writer::label(
        get_string($stringkey, 'paygw_pagarme'),
        'paygw-pagarme-' . $field,
        true,
        ['class' => 'form-label']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'id' => 'paygw-pagarme-' . $field,
        'class' => 'form-control',
        'autocomplete' => 'off',
    ]);
    echo html_writer::end_div();
}

echo html_writer::tag('button', get_string('cardsubmit', 'paygw_pagarme'), [
    'type' => 'button',
    'id' => 'paygw-pagarme-submit',
    'class' => 'btn btn-primary',
]);

echo html_writer::div('', 'mt-3', ['id' => 'paygw-pagarme-status']);
echo html_writer::end_div();

echo $OUTPUT->footer();
