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
 * Onde o aluno informa o cartao da assinatura.
 *
 * Esta pagina existe porque a assinatura NAO comeca no Mercado Pago. Um ciclo
 * so pode ser cobrado com cartao guardado, e o cartao so vira token no
 * navegador - medido em 16/09/2026, o /v1/card_tokens devolve 403 para token
 * de acesso, e so a public_key tokeniza.
 *
 * O que chega aqui pelo POST e sempre um card_token, nunca um numero de cartao,
 * EXCETO no modo nativo - que existe por escolha explicita do administrador e
 * coloca o projeto em PCI DSS SAQ D. Ver docs/legal/pci-dss-captura-de-cartao.md.
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

// Tres guardas, e as tres recusam do mesmo jeito de proposito: uma mensagem que
// distinguisse "nao existe" de "nao e sua" diria a um estranho que aquela
// referencia existe.
if (
    !$record
    || (int) $record->userid !== (int) $USER->id
    || empty($record->subscriptionid)
) {
    throw new moodle_exception('errorsubscriptionnotfound', 'paygw_mercadopago');
}

$returnurl = new moodle_url('/payment/gateway/mercadopago/return.php', ['ref' => $reference]);

// Ciclo ja cobrado: nao ha o que fazer aqui, e recarregar a pagina depois de
// pagar nao pode oferecer pagar de novo.
if ($record->status === 'approved' || !empty($record->mppaymentid)) {
    redirect($returnurl);
}

// Cartao, Pix ou boleto - so o cartao usa os tres modos de captura abaixo.
// Pix e boleto nao tem SDK nem token: e so um formulario nosso, sem relacao
// com card_capture (que decide SO como o CARTAO e digitado).
$metodo = optional_param('method', 'card', PARAM_ALPHA);
if (!in_array($metodo, ['card', 'pix', 'boleto'], true)) {
    $metodo = 'card';
}

$url = new moodle_url('/payment/gateway/mercadopago/subscribe.php', ['ref' => $reference, 'method' => $metodo]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('subscribetitle', 'paygw_mercadopago'));
$PAGE->set_heading(get_string('subscribetitle', 'paygw_mercadopago'));

$modo = card_capture::current();
$apptype = (string) $record->apptype;
$publickey = application::public_key($apptype);

if ($metodo === 'card' && $publickey === '') {
    // Sem a chave publica nao ha como montar campo de cartao em modo nenhum.
    // Dizer isso aqui e melhor do que renderizar um formulario que nunca vai
    // tokenizar, e cuja falha aparece como "o botao nao faz nada".
    throw new moodle_exception(
        'errormissingpublickey',
        'paygw_mercadopago',
        '',
        get_string('apptype_' . $apptype, 'paygw_mercadopago')
    );
}

if (data_submitted() && confirm_sesskey()) {
    $metodoenviado = optional_param('selectedmethod', 'card', PARAM_ALPHA);

    if ($metodoenviado === 'pix' || $metodoenviado === 'boleto') {
        $paymentmethod = $metodoenviado === 'pix' ? 'pix' : 'bolbradesco';

        $payerinfo = [
            'cpf' => optional_param('payerdoc', '', PARAM_ALPHANUM),
            'name' => optional_param('payername', '', PARAM_TEXT),
        ];

        if ($paymentmethod === 'bolbradesco') {
            $payerinfo += [
                'zipcode' => optional_param('payerzipcode', '', PARAM_ALPHANUM),
                'street' => optional_param('payerstreet', '', PARAM_TEXT),
                'number' => optional_param('payerstreetnumber', '', PARAM_ALPHANUM),
                'neighborhood' => optional_param('payerneighborhood', '', PARAM_TEXT),
                'city' => optional_param('payercity', '', PARAM_TEXT),
                'state' => optional_param('payerstate', '', PARAM_ALPHA),
            ];
        }

        try {
            payment_processor::charge_first_cycle_invoice($record, $paymentmethod, $payerinfo);
        } catch (moodle_exception $e) {
            redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }

        redirect($returnurl);
    }

    // No modo brick e no direto o navegador ja tokenizou, e chega UM token. O
    // token que cobra nasce depois, do cartao guardado - ver
    // payment_processor::charge_first_cycle().
    $cardtoken = optional_param('cardtoken', '', PARAM_ALPHANUMEXT);
    $paymentmethod = optional_param('paymentmethod', '', PARAM_ALPHANUMEXT);
    $issuerid = optional_param('issuerid', '', PARAM_ALPHANUMEXT);

    if ($modo === card_capture::MODE_NATIVE) {
        // AQUI, e so aqui, o numero do cartao passa pelo nosso servidor.
        //
        // Ele nao e gravado em lugar nenhum: nao vai para o banco, nao vai para
        // a sessao e nao entra em log. As variaveis morrem no fim da
        // requisicao, e o que sobra e o token.
        [$cardtoken, $paymentmethod, $issuerid] = paygw_mercadopago_tokenize_native($publickey);
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
        payment_processor::charge_first_cycle($record, $cardtoken, $paymentmethod, $issuerid);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }

    redirect($returnurl);
}

echo $OUTPUT->header();

// O aluno precisa saber o que esta assinando ANTES de digitar o cartao: de
// quanto em quanto tempo sera cobrado, e quantas vezes. Uma tela que so diz o
// valor esconde justamente o que diferencia assinatura de compra avulsa.
$recorrencia = class_exists('\local_marketplace\api')
    ? \local_marketplace\api::recurrence_for($record->component, (int) $record->itemid)
    : null;

$periodo = '';
$ciclos = '';
if ($recorrencia) {
    $periodo = get_string('subscribeevery', 'paygw_mercadopago', (int) $recorrencia->days);
    $ciclos = (int) $recorrencia->maxcycles > 0
        ? get_string('subscribecycles', 'paygw_mercadopago', (int) $recorrencia->maxcycles)
        : get_string('subscribeuntilcancelled', 'paygw_mercadopago');
}

echo $OUTPUT->render_from_template('paygw_mercadopago/subscribe', [
    'formaction' => $url->out(false),
    'sesskey' => sesskey(),
    'amount' => \core_payment\helper::get_cost_as_string(
        (float) $record->amount,
        (string) $record->currency
    ),
    'periodo' => $periodo,
    'ciclos' => $ciclos,
    'cancelurl' => (new moodle_url('/local/marketplace/mysubscriptions.php'))->out(false),
    'methodcard' => $metodo === 'card',
    'methodpix' => $metodo === 'pix',
    'methodboleto' => $metodo === 'boleto',
    'cardurl' => (new moodle_url($url, ['method' => 'card']))->out(false),
    'pixurl' => (new moodle_url($url, ['method' => 'pix']))->out(false),
    'boletourl' => (new moodle_url($url, ['method' => 'boleto']))->out(false),
    'brick' => $modo === card_capture::MODE_BRICK,
    'direct' => $modo === card_capture::MODE_DIRECT,
    'native' => $modo === card_capture::MODE_NATIVE,
]);

// O modo nativo nao carrega SDK nenhum: o formulario e HTML puro e quem
// tokeniza e o servidor. Os outros dois precisam do SDK do Mercado Pago. Pix
// e boleto nao precisam de SDK nenhum - e so um formulario nosso.
if ($metodo === 'card' && $modo !== card_capture::MODE_NATIVE) {
    $PAGE->requires->js_call_amd('paygw_mercadopago/card_form', 'init', [[
        'publickey' => $publickey,
        'mode' => $modo,
        'amount' => (float) $record->amount,
    ]]);
}

echo $OUTPUT->footer();

/**
 * Tokeniza o cartao a partir dos campos enviados - modo nativo.
 *
 * ESTA FUNCAO E A FRONTEIRA DO ESCOPO PCI, e por isso vive aqui, isolada, em
 * vez de dentro de uma classe onde alguem a chamaria sem perceber o que ela
 * faz. Ela so roda quando o administrador escolheu explicitamente o modo
 * nativo, e o card_capture ja garantiu que o site e HTTPS.
 *
 * O numero do cartao nao e gravado, nao e logado e nao entra na sessao: as
 * variaveis morrem no fim da requisicao, e o que sobra e o token.
 *
 * @param string $publickey Chave publica da aplicacao que vai cobrar
 * @return array [token, bandeira, emissor]
 */
function paygw_mercadopago_tokenize_native(string $publickey): array {
    $cardnumber = optional_param('cardnumber', '', PARAM_ALPHANUM);
    $expirationmonth = optional_param('expirationmonth', 0, PARAM_INT);
    $expirationyear = optional_param('expirationyear', 0, PARAM_INT);
    $securitycode = optional_param('securitycode', '', PARAM_ALPHANUM);
    $holdername = optional_param('holdername', '', PARAM_TEXT);
    $holderdoc = optional_param('holderdoc', '', PARAM_ALPHANUM);

    $corpo = [
        'card_number' => $cardnumber,
        'expiration_month' => $expirationmonth,
        'expiration_year' => $expirationyear,
        'security_code' => $securitycode,
        'cardholder' => [
            'name' => $holdername,
            'identification' => ['type' => 'CPF', 'number' => $holderdoc],
        ],
    ];

    $resposta = \paygw_mercadopago\mp_client::tokenize_card($publickey, $corpo);

    // O /v1/card_tokens NAO devolve bandeira nem emissor - so o token. Quem
    // tem o numero do cartao aqui (SO no modo nativo) pode descobrir os dois
    // pelo BIN, sem depender do navegador.
    $metodo = \paygw_mercadopago\mp_client::guess_payment_method(
        $publickey,
        substr($cardnumber, 0, 8)
    );

    return [
        (string) ($resposta['id'] ?? ''),
        (string) ($metodo['id'] ?? ''),
        (string) ($metodo['issuerid'] ?? ''),
    ];
}
