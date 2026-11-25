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
 * Estorno de uma venda.
 *
 * Devolve o dinheiro no gateway, revoga o acesso e, quando a venda e a primeira
 * de uma assinatura, cancela a assinatura junto - o gateway estorna a cobranca e
 * mantem as futuras pendentes, entao sem o cancelamento o aluno seria cobrado
 * depois de reembolsado.
 *
 * Nao ha estorno parcial: medido no sandbox, pedir parte do valor deixa o split
 * vivo com a comissao cheia de uma venda parcialmente devolvida.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core_payment\helper;
use local_marketplace\api;
use local_marketplace\company;
use local_marketplace\offer;

$paymentid = required_param('payment', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$sale = $DB->get_record('local_marketplace_sale', ['paymentid' => $paymentid], '*', MUST_EXIST);
$payment = $DB->get_record('payments', ['id' => $paymentid], '*', MUST_EXIST);
$company = company::get_record(['id' => (int) $sale->companyid]);
$offer = offer::get_record(['id' => (int) $sale->offerid]);
if (!$company || !$offer) {
    throw new moodle_exception('invalidrecord', 'error');
}

// No contexto da CATEGORIA da empresa, e nao no sistema: quem estorna venda de
// uma empresa nao estorna a de outra. E a capability nao vai para papel nenhum
// por padrao - nem o gerente tem, a menos que o administrador conceda.
$context = $company->get_context();
require_capability('local/marketplace:refundsale', $context);

$back = new moodle_url('/local/marketplace/report.php', [
    'company' => $company->get('shortname'),
    'view' => 'transactions',
]);
$url = new moodle_url('/local/marketplace/refund.php', ['payment' => $paymentid]);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('refundsale', 'local_marketplace'));
$PAGE->set_heading(format_string($company->get('name')));

// O impedimento e perguntado ao gateway ANTES de qualquer coisa. A tela pode
// ter sido aberta por link antigo, ou por alguem que digitou o endereco.
$blocker = api::refund_blocker($paymentid);
if ($blocker !== '') {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string($blocker, 'paygw_' . $payment->gateway), 'error');
    echo $OUTPUT->continue_button($back);
    echo $OUTPUT->footer();
    exit;
}

$student = \core_user::get_user((int) $payment->userid, '*', IGNORE_MISSING);

if (!$confirm) {
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('refundconfirm', 'local_marketplace', (object) [
            'amount' => helper::get_cost_as_string((float) $payment->amount, $payment->currency),
            'offer' => format_string($offer->get('name')),
            'user' => $student ? fullname($student) : '#' . (int) $payment->userid,
        ]),
        new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]),
        $back
    );
    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

// Gateway primeiro, direito depois: revogar antes deixaria o aluno sem curso e
// sem reembolso se a chamada externa falhasse.
$refunded = api::refund_sale($paymentid);

redirect(
    $back,
    get_string($refunded ? 'refunddone' : 'refundfailed', 'local_marketplace'),
    null,
    $refunded ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
);
