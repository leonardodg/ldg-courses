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
 * Recebe a autorizacao do vendedor e grava o token na conta de pagamento.
 *
 * Esta e a URL que precisa estar cadastrada no painel do Mercado Pago, com
 * correspondencia exata.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use paygw_mercadopago\application;
use paygw_mercadopago\mp_client;

$code = optional_param('code', '', PARAM_RAW_TRIMMED);
$state = optional_param('state', '', PARAM_RAW_TRIMMED);
$error = optional_param('error', '', PARAM_RAW_TRIMMED);

require_login();

$pending = $SESSION->paygw_mercadopago_oauth ?? null;
unset($SESSION->paygw_mercadopago_oauth);

// AS TRES FALHAS AQUI SAO DIFERENTES, E JUNTAR AS TRES CUSTOU CARO.
//
// Ate 15/09/2026 uma condicao so cobria "nao ha fluxo", "o state nao bate" e
// "falta o verifier", todas com a mesma mensagem - "nao foi possivel verificar
// a autorizacao". Num fluxo que SAI do site e volta, a causa mais comum e a
// mais inocente de todas, e quem a encontrava nao tinha como saber disso nem
// como sair da tela: a excecao e um beco sem saida.
//
// 1) Nao ha fluxo nenhum na sessao.
//
// Nao e suspeita de ataque. E esta pagina aberta diretamente - o que acontece
// naturalmente quando alguem confere o redirect_uri que acabou de cadastrar no
// painel do Mercado Pago -, ou a sessao morta entre a ida e a volta. O caminho
// certo e dizer isso e devolver a pessoa para onde ela recomeca.
if (empty($pending)) {
    redirect(
        new moodle_url('/payment/accounts.php'),
        get_string('errornopendingflow', 'paygw_mercadopago'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Daqui para baixo EXISTE um fluxo iniciado neste navegador, e a conta e
// conhecida - entao da para voltar para a tela do gateway.
$returnurl = new moodle_url('/payment/manage_gateway.php', [
    'accountid' => (int) $pending->accountid,
    'gateway' => 'mercadopago',
]);

// 2) O state nao confere. Esta e a suspeita de verdade, e continua sendo
// excecao, sem redirecionamento: alguem pode estar tentando concluir, no
// navegador desta pessoa, uma autorizacao iniciada por outro - e o resultado
// seria vincular a conta errada.
//
// A ausencia do code_verifier fica junto por ser da mesma natureza: ou a sessao
// nao veio daqui, ou foi forjada pela metade.
if (empty($state) || !hash_equals($pending->state, $state) || empty($pending->codeverifier)) {
    throw new moodle_exception('errorstatemismatch', 'paygw_mercadopago');
}

// 3) Fluxo iniciado por uma versao do plugin que ainda nao conhecia apptype.
//
// Seguir gravaria o vinculo num campo que ninguem le - perda silenciosa, que e
// o modo de falha caro deste plugin. Mas nao e ataque: e uma aba aberta antes
// da atualizacao, e recomecar resolve.
if (empty($pending->apptype) || !application::is_valid($pending->apptype)) {
    redirect(
        $returnurl,
        get_string('errorstaleflow', 'paygw_mercadopago'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$apptype = (string) $pending->apptype;

$account = new \core_payment\account((int) $pending->accountid);
$context = $account->get_context();
require_capability('moodle/payment:manageaccounts', $context);

// O vendedor pode ter recusado a autorizacao na tela do Mercado Pago. Nao e
// erro de verificacao: e uma escolha dele, e merece o proprio recado.
if ($error !== '' || $code === '') {
    redirect(
        $returnurl,
        get_string('errorauthorisationrefused', 'paygw_mercadopago'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$config = get_config('paygw_mercadopago');
$credentials = application::credentials($apptype);
if (!$credentials) {
    // A aplicacao foi despreenchida entre o inicio e a volta.
    redirect(
        $returnurl,
        get_string(
            'errormissingappconfig',
            'paygw_mercadopago',
            get_string('apptype_' . $apptype, 'paygw_mercadopago')
        ),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$redirecturi = (new moodle_url('/payment/gateway/mercadopago/oauth_callback.php'))->out(false);

// As credenciais sao as DAQUELA aplicacao. Trocar o codigo com o client_secret
// de outra devolve erro generico do Mercado Pago, e a causa - o par errado -
// nao aparece em lugar nenhum da mensagem.
$token = mp_client::exchange_code(
    $credentials->clientid,
    $credentials->clientsecret,
    $code,
    $redirecturi,
    $pending->codeverifier,
    !empty($config->testmode)
);

// Localiza ou cria a linha do gateway nesta conta.
$gateway = \core_payment\account_gateway::get_record([
    'accountid' => $account->get('id'),
    'gateway' => 'mercadopago',
]);
if (!$gateway) {
    $gateway = new \core_payment\account_gateway();
    $gateway->set('accountid', $account->get('id'));
    $gateway->set('gateway', 'mercadopago');
}

// Habilita junto com o vinculo.
//
// Deixar desabilitado criava um segundo passo invisivel: o vendedor concluia o
// OAuth, via a mensagem de sucesso, e a empresa continuava anunciada como "sem
// meio de pagamento" - porque account::is_available() exige o gateway ligado.
// A unica coisa entre o vinculo e a venda era um checkbox sem informacao
// nenhuma, ja que a validacao do formulario recusa habilitar sem token.
//
// Autorizar a plataforma a cobrar em nome dele E a decisao; o checkbox era so
// burocracia. Desvincular desliga de volta, entao a simetria se mantem.
$gateway->set('enabled', 1);

$existing = $gateway->get('id') ? $gateway->get_configuration() : [];

// Em que pais - e portanto em que moeda - este vendedor recebe. Perguntamos ao
// Mercado Pago em vez de deixar o vendedor escolher: a conta e presa a um pais
// e so recebe na moeda dele.
//
// A consulta e obrigatoria, e nao um enfeite. O split so funciona entre contas
// do MESMO pais: a comissao cai na conta da plataforma, e uma conta so guarda a
// moeda do proprio pais - nao ha cambio no caminho. Vincular um vendedor de
// outro pais produziria uma conta que parece pronta e falha na primeira venda,
// com o aluno ja na tela de pagamento.
//
// Falha de rede aqui derruba o vinculo de proposito. Guardar um token cuja
// origem nao conseguimos verificar seria trocar um erro visivel agora, que se
// resolve clicando de novo, por um erro invisivel na conciliacao.
try {
    $me = (new mp_client((string) ($token['access_token'] ?? '')))->get_me();
} catch (moodle_exception $e) {
    redirect(
        $returnurl,
        get_string('errorverifyaccount', 'paygw_mercadopago', $e->getMessage()),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$siteid = (string) ($me['site_id'] ?? '');
$platformsite = strtoupper((string) ($config->platformsite ?? 'MLB'));

if ($siteid === '' || strtoupper($siteid) !== $platformsite) {
    redirect($returnurl, get_string('errorsitemismatch', 'paygw_mercadopago', (object) [
        'platform' => $platformsite,
        'seller' => $siteid !== '' ? $siteid : '?',
    ]), null, \core\output\notification::NOTIFY_ERROR);
}

$currency = mp_client::currency_for_site($siteid);

// Expires_in vem em segundos. Guardar o INSTANTE do vencimento, e nao a
// duracao, evita ter que lembrar quando o token foi emitido.
$expires = time() + (int) ($token['expires_in'] ?? 0);

// Grava SO os campos deste tipo, por cima do que ja existe. Um vendedor pode
// ter autorizado Preferencias antes e Bricks agora, e sobrescrever o conjunto
// inteiro apagaria o vinculo anterior sem nenhum aviso - a empresa pararia de
// vender avulso no instante em que habilitasse a assinatura.
$gateway->set('config', json_encode(array_merge($existing, [
    application::token_field($apptype, 'mpuserid') => (string) ($token['user_id'] ?? ''),
    application::token_field($apptype, 'accesstoken') => (string) ($token['access_token'] ?? ''),
    application::token_field($apptype, 'refreshtoken') => (string) ($token['refresh_token'] ?? ''),
    application::token_field($apptype, 'tokenexpires') => $expires,
    application::token_field($apptype, 'siteid') => $siteid,
    application::token_field($apptype, 'currency') => $currency,
    // Marca sob QUAL modo o access_token foi emitido - o testmode do SITE
    // pode mudar depois deste vinculo (indo ao ar com outra empresa, por
    // exemplo), e sem este marcador o checkout passaria a mostrar a chave
    // publica do modo atual enquanto continua cobrando com um token do modo
    // antigo, recusado pelo Mercado Pago com "Invalid users involved".
    application::token_field($apptype, 'testmode') => !empty($config->testmode) ? 1 : 0,
])));

if ($gateway->get('id')) {
    $gateway->update();
} else {
    $gateway->create();
}

redirect(
    $returnurl,
    get_string('oauthlinked', 'paygw_mercadopago', [
        'mpuserid' => s((string) ($token['user_id'] ?? '?')),
        'expires' => userdate($expires),
    ]),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
