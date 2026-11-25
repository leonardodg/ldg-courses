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
 * Endpoint que o Pagar.me chama.
 *
 * Duas camadas, e nenhuma das duas basta sozinha.
 *
 * A primeira e o HTTP Basic. O Pagar.me manda usuario e senha cadastrados no
 * painel - nao ha header proprio como o asaas-access-token. Isso impede que
 * qualquer um que descubra a URL fabrique uma notificacao.
 *
 * A segunda e reconsultar a cobranca na API antes de entregar qualquer coisa.
 * Mesmo com o Basic conferindo, o corpo do POST nunca e a fonte da verdade:
 * segredo vazado deixa de ser suficiente para inventar uma venda.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../config.php');

use paygw_pagarme\credentials;
use paygw_pagarme\payment_processor;

/**
 * Responde e encerra.
 *
 * @param int $status
 * @param string $message
 * @return void
 */
function paygw_pagarme_respond(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$environment = credentials::current_environment();
$expecteduser = credentials::webhook_user($environment);
$expectedpassword = credentials::webhook_password($environment);

// Segredo nao configurado NAO e passe livre. Sem isto, uma instalacao
// esquecida aceitaria qualquer POST como verdade.
if ($expecteduser === '' || $expectedpassword === '') {
    paygw_pagarme_respond(401, 'unauthorized');
}

$sentuser = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
$sentpassword = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');

// Alguns servidores nao populam PHP_AUTH_*, e entregam o cabecalho cru.
if ($sentuser === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
    if (stripos($header, 'Basic ') === 0) {
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            [$sentuser, $sentpassword] = explode(':', $decoded, 2);
        }
    }
}

$okuser = hash_equals($expecteduser, $sentuser);
$okpassword = hash_equals($expectedpassword, $sentpassword);
if (!$okuser || !$okpassword) {
    paygw_pagarme_respond(401, 'unauthorized');
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    paygw_pagarme_respond(400, 'bad request');
}

$event = (string) ($payload['type'] ?? '');
$data = $payload['data'] ?? [];
$data = is_array($data) ? $data : [];

// O formato difere entre evento de order e de charge, e a extracao vive no
// payment_processor para ser testavel sem servidor.
$chargeid = payment_processor::charge_id_from_event($event, $data);
$subscriptionid = payment_processor::subscription_id_from_event($data);

// Evento que nao interessa responde 200: um 4xx aqui faria o Pagar.me
// reenfileirar para sempre algo que nunca vamos processar.
if (!payment_processor::is_relevant_event($event) || $chargeid === '') {
    paygw_pagarme_respond(200, 'ignored');
}

try {
    $delivered = payment_processor::process_notification($chargeid, $subscriptionid);
    paygw_pagarme_respond(200, $delivered ? 'processed' : 'ignored');
} catch (\Throwable $e) {
    debugging('paygw_pagarme webhook: ' . $e->getMessage(), DEBUG_DEVELOPER);
    // 500 para o Pagar.me tentar de novo. Uma falha passageira nossa nao pode
    // custar o acesso de quem pagou.
    paygw_pagarme_respond(500, 'error');
}
