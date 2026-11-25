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
 * Para onde o aluno volta depois da fatura do Asaas.
 *
 * Esta pagina NAO confirma nada. Ela so conta o que o webhook ja registrou.
 *
 * A razao e que o aluno controla o proprio navegador: confirmar uma venda
 * porque alguem chegou nesta URL seria entregar curso para quem digitasse o
 * endereco. E o caminho inverso tambem existe - no Pix o aluno costuma voltar
 * antes de a liquidacao chegar, e tratar isso como "nao pagou" seria mentir
 * para quem acabou de pagar.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use core_payment\helper;
use paygw_asaas\payment_processor;

$reference = required_param('ref', PARAM_ALPHANUMEXT);

require_login();

$record = $DB->get_record(payment_processor::TABLE, ['externalreference' => $reference]);

// A referencia identifica a compra de UMA pessoa. Sem esta checagem, quem
// tivesse a referencia de outro aluno veria a situacao da compra dele.
if (!$record || (int) $record->userid !== (int) $USER->id) {
    throw new moodle_exception('invalidaccess', 'error');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/asaas/return.php', ['ref' => $reference]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('returnheading', 'paygw_asaas'));
$PAGE->set_heading(get_string('returnheading', 'paygw_asaas'));

if (payment_processor::is_paid((string) $record->status) && !empty($record->paymentid)) {
    redirect(helper::get_success_url(
        $record->component,
        $record->paymentarea,
        (int) $record->itemid
    ));
}

echo $OUTPUT->header();

if (in_array(strtoupper((string) $record->status), ['REFUNDED', 'REFUND_REQUESTED', 'CHARGEBACK_REQUESTED'], true)) {
    echo $OUTPUT->notification(get_string('returnrefunded', 'paygw_asaas'), 'error');
} else {
    echo $OUTPUT->notification(get_string('returnpending', 'paygw_asaas'), 'info');
}

echo $OUTPUT->continue_button(new moodle_url('/my/courses.php'));
echo $OUTPUT->footer();
