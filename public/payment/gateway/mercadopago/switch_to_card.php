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
 * Trocar Pix/boleto por cartao guardado - so PARA A FRENTE.
 *
 * NAO COBRA NADA aqui: guarda o cartao e diz a partir de qual instrumento os
 * PROXIMOS ciclos serao cobrados. O ciclo atual, se ja foi pago por Pix ou
 * boleto, continua registrado como foi pago - ver
 * payment_processor::switch_to_card().
 *
 * Reusa exatamente o mesmo card_form.js e a mesma partial de captura de
 * cartao de subscribe.php: e o mesmo cartao, tokenizado do mesmo jeito, so
 * que sem uma cobranca em seguida.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use paygw_mercadopago\application;
use paygw_mercadopago\card_capture;
use paygw_mercadopago\payment_processor;

$reference = required_param('ref', PARAM_ALPHANUMEXT);

require_login();

$record = $DB->get_record(payment_processor::TABLE, ['externalreference' => $reference]);

// Mesma logica de tres guardas do subscribe.php: uma mensagem que
// distinguisse "nao existe" de "nao e sua" diria a um estranho que aquela
// referencia existe.
if (
    !$record
    || (int) $record->userid !== (int) $USER->id
    || empty($record->subscriptionid)
) {
    throw new moodle_exception('errorsubscriptionnotfound', 'paygw_mercadopago');
}

$mysubscriptions = new moodle_url('/local/marketplace/mysubscriptions.php');

// So faz sentido para quem paga por Pix/boleto, na assinatura ainda ativa -
// quem ja paga com cartao nao tem para onde trocar.
if (!payment_processor::can_switch_to_card($record)) {
    redirect($mysubscriptions);
}

$url = new moodle_url('/payment/gateway/mercadopago/switch_to_card.php', ['ref' => $reference]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('switchtocard', 'local_marketplace'));
$PAGE->set_heading(get_string('switchtocard', 'local_marketplace'));

$mode = card_capture::current((int) $record->accountid);
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
    $paymentmethod = optional_param('paymentmethod', '', PARAM_ALPHANUMEXT);
    $issuerid = optional_param('issuerid', '', PARAM_ALPHANUMEXT);

    if ($mode === card_capture::MODE_NATIVE) {
        [$cardtoken, $paymentmethod, $issuerid] = paygw_mercadopago_switch_tokenize_native($publickey);
    }

    if ($cardtoken === '') {
        redirect(
            $url,
            get_string('errorcardtokenmissing', 'paygw_mercadopago'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    try {
        payment_processor::switch_to_card($record, $cardtoken, $paymentmethod, $issuerid);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }

    redirect(
        $mysubscriptions,
        get_string('switchtocarddone', 'paygw_mercadopago'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('paygw_mercadopago/switch_to_card', [
    'formaction' => $url->out(false),
    'sesskey' => sesskey(),
    'cancelurl' => $mysubscriptions->out(false),
    'brick' => $mode === card_capture::MODE_BRICK,
    'direct' => $mode === card_capture::MODE_DIRECT,
    'native' => $mode === card_capture::MODE_NATIVE,
]);

if ($mode !== card_capture::MODE_NATIVE) {
    $PAGE->requires->js_call_amd('paygw_mercadopago/card_form', 'init', [[
        'publickey' => $publickey,
        'mode' => $mode,
        // So usado pelo Brick para exibir o valor - nao ha cobranca aqui, e
        // 0 nao quebra a montagem do componente.
        'amount' => 0.0,
    ]]);
}

echo $OUTPUT->footer();

/**
 * Tokeniza o cartao a partir dos campos enviados - modo nativo.
 *
 * Espelha paygw_mercadopago_tokenize_native() de subscribe.php. Duplicada, e
 * nao compartilhada, porque as duas paginas tem guardas de acesso
 * diferentes - uma funcao comum precisaria receber os dois contextos e
 * decidir qual regra vale, o que e mais confuso do que duas copias
 * pequenas e obvias.
 *
 * @param string $publickey Chave publica da aplicacao que vai cobrar
 * @return array [token, bandeira, emissor]
 */
function paygw_mercadopago_switch_tokenize_native(string $publickey): array {
    $cardnumber = optional_param('cardnumber', '', PARAM_ALPHANUM);
    $expirationmonth = optional_param('expirationmonth', 0, PARAM_INT);
    $expirationyear = optional_param('expirationyear', 0, PARAM_INT);
    $securitycode = optional_param('securitycode', '', PARAM_ALPHANUM);
    $holdername = optional_param('holdername', '', PARAM_TEXT);
    $holderdoc = optional_param('holderdoc', '', PARAM_ALPHANUM);

    $body = [
        'card_number' => $cardnumber,
        'expiration_month' => $expirationmonth,
        'expiration_year' => $expirationyear,
        'security_code' => $securitycode,
        'cardholder' => [
            'name' => $holdername,
            'identification' => ['type' => 'CPF', 'number' => $holderdoc],
        ],
    ];

    $response = \paygw_mercadopago\mp_client::tokenize_card($publickey, $body);

    $method = \paygw_mercadopago\mp_client::guess_payment_method(
        $publickey,
        substr($cardnumber, 0, 8)
    );

    return [
        (string) ($response['id'] ?? ''),
        (string) ($method['id'] ?? ''),
        (string) ($method['issuerid'] ?? ''),
    ];
}
