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
 * Inicia o vinculo da conta Mercado Pago do vendedor.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use paygw_mercadopago\application;
use paygw_mercadopago\mp_client;

$accountid = required_param('accountid', PARAM_INT);

// O padrao e Preferencias porque e a aplicacao que ja existia: um link antigo,
// em favorito ou em e-mail, continua vinculando o que vinculava antes.
$apptype = optional_param('apptype', application::TYPE_PREFERENCES, PARAM_ALPHANUMEXT);

if (!application::is_valid($apptype)) {
    throw new moodle_exception('errorunknownapptype', 'paygw_mercadopago');
}

require_login();

$account = new \core_payment\account($accountid);
$context = $account->get_context();

// A capability e verificada no contexto da CONTA, nao no sistema: e isso que
// permite ao vendedor vincular a propria conta sem poder tocar nas outras.
require_capability('moodle/payment:manageaccounts', $context);

$credentials = application::credentials($apptype);
if (!$credentials) {
    throw new moodle_exception(
        'errormissingappconfig',
        'paygw_mercadopago',
        '',
        get_string('apptype_' . $apptype, 'paygw_mercadopago')
    );
}

// O state protege contra CSRF: sem ele, alguem poderia induzir o vendedor a
// concluir um fluxo iniciado por terceiro e vincular a conta errada. Guardamos
// na sessao e conferimos no retorno.
//
// O code_verifier do PKCE anda junto e resolve outro problema: o codigo de
// autorizacao viaja na barra de enderecos e sobra no historico e em log de
// proxy. Sem o verifier - que nunca sai daqui - esse codigo capturado nao troca
// por token nenhum.
$state = random_string(32);
$codeverifier = mp_client::create_code_verifier();

// O apptype viaja na SESSAO, junto do state, e nao no redirect_uri.
//
// O Mercado Pago exige que o redirect_uri case EXATAMENTE com o cadastrado no
// painel. Pondo o tipo como parametro da URL, cada aplicacao precisaria de um
// redirect_uri proprio cadastrado - tres chances de errar uma letra, e o erro
// que volta e "invalid redirect_uri" sem dizer qual aplicacao foi consultada.
// Na sessao, o endereco e UM SO nas tres, e o tipo nao pode ser adulterado no
// caminho de volta.
$SESSION->paygw_mercadopago_oauth = (object) [
    'state' => $state,
    'accountid' => $accountid,
    'apptype' => $apptype,
    'codeverifier' => $codeverifier,
    'timecreated' => time(),
];

$redirecturi = (new moodle_url('/payment/gateway/mercadopago/oauth_callback.php'))->out(false);

redirect(mp_client::build_authorization_url(
    $credentials->clientid,
    $redirecturi,
    $state,
    mp_client::create_code_challenge($codeverifier),
    $credentials->site
));
