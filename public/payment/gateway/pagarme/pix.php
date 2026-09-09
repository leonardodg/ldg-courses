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
 * Pagina do Pix, com QR Code e copia-e-cola.
 *
 * Existe porque o Link de Pagamento hospedado do Pagar.me nao faz split em
 * Pix - so em cartao. Para o split funcionar, a cobranca tem que nascer no
 * POST /orders transparente, e ai o QR Code passa a ser problema nosso.
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

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/pagarme/pix.php', ['ref' => $reference]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pixheading', 'paygw_pagarme'));
$PAGE->set_heading(get_string('pixheading', 'paygw_pagarme'));

// Ja pago antes de a pagina abrir: nao faz sentido mostrar QR Code.
if (payment_processor::is_paid((string) $record->status) && !empty($record->paymentid)) {
    redirect(\core_payment\helper::get_success_url(
        $record->component,
        $record->paymentarea,
        (int) $record->itemid
    ));
}

$PAGE->requires->js_call_amd('paygw_pagarme/pixpoll', 'init', [$reference]);

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('pixinstructions', 'paygw_pagarme'));

$qrimage = (string) ($record->checkouturl ?? '');
if ($qrimage !== '') {
    echo html_writer::div(
        html_writer::empty_tag('img', [
            'src' => $qrimage,
            'alt' => get_string('pixheading', 'paygw_pagarme'),
            'style' => 'max-width: 260px; width: 100%; height: auto;',
        ]),
        'mb-3'
    );
}

$code = (string) ($record->qrcode ?? '');
if ($code !== '') {
    echo html_writer::tag('textarea', s($code), [
        'id' => 'paygw-pagarme-code',
        'readonly' => 'readonly',
        'rows' => 4,
        'class' => 'form-control mb-2',
        'style' => 'font-family: monospace; word-break: break-all;',
    ]);
    echo html_writer::tag('button', get_string('pixcopy', 'paygw_pagarme'), [
        'type' => 'button',
        'id' => 'paygw-pagarme-copy',
        'class' => 'btn btn-primary',
    ]);
}

echo html_writer::div(
    get_string('pixwaiting', 'paygw_pagarme'),
    'mt-3 text-muted',
    ['id' => 'paygw-pagarme-status']
);

echo $OUTPUT->footer();
