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
 * Volta do aluno depois do Checkout Pro.
 *
 * Esta pagina NAO confirma pagamento. Ela so orienta o aluno: quem confirma e
 * o webhook, porque a volta do navegador nao e confiavel - o aluno pode fechar
 * a aba, e com Pix a aprovacao costuma chegar depois do redirecionamento.
 *
 * Tratar esta volta como confirmacao seria pior do que inutil: bastaria abrir
 * a URL a mao para liberar acesso sem pagar.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use core_payment\helper;

$reference = required_param('ref', PARAM_ALPHANUMEXT);

require_login();

$record = $DB->get_record('paygw_mercadopago', ['externalreference' => $reference]);

// A referencia so vale para quem a criou. Sem esta checagem, um aluno poderia
// ver o estado da compra de outro apenas trocando o parametro.
if (!$record || (int) $record->userid !== (int) $USER->id) {
    throw new moodle_exception('invalidaccess', 'error');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/payment/gateway/mercadopago/return.php', ['ref' => $reference]);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'paygw_mercadopago'));

// Se o webhook ja chegou, manda direto para o que foi comprado.
if ($record->status === 'approved') {
    redirect(
        helper::get_success_url($record->component, $record->paymentarea, (int) $record->itemid),
        get_string('paymentapproved', 'paygw_mercadopago'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

if ($record->status === 'rejected' || $record->status === 'cancelled') {
    echo $OUTPUT->notification(get_string('paymentrejected', 'paygw_mercadopago'), 'error');
} else {
    // A fatura por Pix ou boleto NAO foi paga ainda - e diferente do Checkout
    // Pro, onde o aluno ja viu o QR code la no Mercado Pago antes de voltar.
    // Aqui a pagina e nossa, entao o QR/boleto so existe se mostrarmos.
    $invoice = \paygw_mercadopago\payment_processor::invoice_details($record);

    if ($invoice) {
        echo $OUTPUT->render_from_template('paygw_mercadopago/pay_invoice', $invoice + [
            'waitingmessage' => get_string('subscribeinvoicewaiting', 'paygw_mercadopago'),
        ]);
    } else {
        // Caso comum no cartao aprovado por analise, ou num Checkout Pro
        // ainda nao confirmado: o aluno volta antes de o Mercado Pago avisar.
        echo $OUTPUT->notification(get_string('paymentpending', 'paygw_mercadopago'), 'info');
    }
}

echo $OUTPUT->continue_button(new moodle_url('/my/courses.php'));
echo $OUTPUT->footer();
