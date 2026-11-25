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
 * Onde o aluno cai depois de pagar.
 *
 * Nao confirma nada. O navegador e do aluno, e com Pix ou boleto ele volta
 * antes de o dinheiro cair - quem confirma e o webhook, ou a reconciliacao.
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
$PAGE->set_url(new moodle_url('/payment/gateway/pagarme/return.php', ['ref' => $reference]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('returnheading', 'paygw_pagarme'));
$PAGE->set_heading(get_string('returnheading', 'paygw_pagarme'));

if (payment_processor::is_paid((string) $record->status) && !empty($record->paymentid)) {
    redirect(\core_payment\helper::get_success_url(
        $record->component,
        $record->paymentarea,
        (int) $record->itemid
    ));
}

echo $OUTPUT->header();

if (in_array(strtolower((string) $record->status), ['canceled', 'partial_canceled'], true)) {
    echo $OUTPUT->notification(get_string('returnrefunded', 'paygw_pagarme'), 'error');
} else {
    echo $OUTPUT->notification(get_string('returnpending', 'paygw_pagarme'), 'info');
}

echo $OUTPUT->continue_button(new moodle_url('/my/courses.php'));
echo $OUTPUT->footer();
