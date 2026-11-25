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
 * Onde o aluno confirma um ciclo de cartao com o CVV.
 *
 * ESTA PAGINA EXISTE PORQUE O CARTAO NAO COBRA SOZINHO NESTA CONTA - medido
 * em 16/09/2026, cobrar o cartao guardado sem CVV devolve "400
 * security_code_id can't be null", e o Mercado Pago nao tem ESC habilitado
 * aqui (nem liga sozinho por historico de pagamento). issue_card_cycle()
 * cria a linha do ciclo sem cobrar, e este e o lugar onde o aluno completa.
 *
 * O CVV NUNCA CHEGA A ESTE ARQUIVO: `mp.createCardToken({cardId,
 * securityCode})` tokeniza no navegador, pela public key, e so o TOKEN chega
 * por POST - o mesmo desenho de subscribe.php para o cartao, so que aqui
 * nao ha numero nem validade para digitar, o cartao ja esta guardado.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use paygw_mercadopago\application;
use paygw_mercadopago\payment_processor;

$reference = required_param('ref', PARAM_ALPHANUMEXT);

require_login();

$record = $DB->get_record(payment_processor::TABLE, ['externalreference' => $reference]);

// Mesma logica de tres guardas das outras paginas do gateway: uma mensagem
// que distinguisse "nao existe" de "nao e sua" diria a um estranho que
// aquela referencia existe.
if (
    !$record
    || (int) $record->userid !== (int) $USER->id
    || empty($record->subscriptionid)
) {
    throw new moodle_exception('errorsubscriptionnotfound', 'paygw_mercadopago');
}

$mysubscriptions = new moodle_url('/local/marketplace/mysubscriptions.php');

if (!payment_processor::can_confirm_card_cycle($record)) {
    redirect($mysubscriptions);
}

$url = new moodle_url('/payment/gateway/mercadopago/confirm_cycle.php', ['ref' => $reference]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('confirmcycletitle', 'paygw_mercadopago'));
$PAGE->set_heading(get_string('confirmcycletitle', 'paygw_mercadopago'));

$apptype = (string) $record->apptype;
$publickey = application::public_key($apptype, (int) $record->accountid);

if ($publickey === '') {
    throw new moodle_exception(
        'errormissingpublickey',
        'paygw_mercadopago',
        '',
        get_string('apptype_' . $apptype, 'paygw_mercadopago')
    );
}

if (data_submitted() && confirm_sesskey()) {
    $cardtoken = optional_param('cardtoken', '', PARAM_ALPHANUMEXT);

    if ($cardtoken === '') {
        redirect(
            $url,
            get_string('errorcardtokenmissing', 'paygw_mercadopago'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    try {
        payment_processor::confirm_card_cycle($record, $cardtoken);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }

    redirect(
        new moodle_url('/payment/gateway/mercadopago/return.php', ['ref' => $reference])
    );
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('paygw_mercadopago/confirm_cycle', [
    'formaction' => $url->out(false),
    'sesskey' => sesskey(),
    'amount' => \core_payment\helper::get_cost_as_string(
        (float) $record->amount,
        (string) $record->currency
    ),
    'cancelurl' => $mysubscriptions->out(false),
]);

$PAGE->requires->js_call_amd('paygw_mercadopago/card_form', 'initConfirmCycle', [[
    'publickey' => $publickey,
    'cardid' => (string) $record->mpcardid,
]]);

echo $OUTPUT->footer();
