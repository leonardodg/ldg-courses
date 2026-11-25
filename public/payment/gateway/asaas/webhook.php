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
 * Recebe as notificacoes do Asaas.
 *
 * Duas camadas, e as duas sao necessarias:
 *
 * 1. O header asaas-access-token e conferido contra um segredo que nos mesmos
 *    definimos ao cadastrar o webhook. Sem isso, qualquer um que descubra a URL
 *    manda "pagamento recebido" e ganha curso de graca. O gateway do Mercado
 *    Pago neste projeto nao tem essa camada porque o Mercado Pago nao oferece
 *    uma; o Asaas oferece, e nao usa-la seria deixar valor na mesa.
 *
 * 2. Mesmo com o header certo, o status vem de uma consulta a API com a chave
 *    do vendedor. O payload e uma DICA de que algo mudou, nunca a prova do que
 *    mudou - um segredo vazado deixaria de ser suficiente para forjar uma venda.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../config.php');

use paygw_asaas\credentials;
use paygw_asaas\payment_processor;

/**
 * Responde e encerra.
 *
 * @param int $status Codigo HTTP.
 * @param string $message
 * @return void
 */
function paygw_asaas_respond(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$expected = credentials::webhook_token(credentials::current_environment());
$received = (string) ($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '');

// Segredo nao configurado e recusa, e nao passe livre. Um webhook aberto
// enquanto "ninguem configurou ainda" e exatamente a janela que interessa a
// quem esta atacando.
if ($expected === '' || !hash_equals($expected, $received)) {
    paygw_asaas_respond(401, 'unauthorized');
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    paygw_asaas_respond(400, 'invalid payload');
}

$event = (string) ($payload['event'] ?? '');
$asaaspaymentid = (string) ($payload['payment']['id'] ?? '');

// A assinatura vem do payload, e so ela. E identificacao, e nao verdade: serve
// para descobrir de quem e uma cobranca que o Moodle nunca viu - a do ciclo 2
// em diante, criada pelo Asaas sozinho. Valor e status continuam vindo da API,
// consultados com a chave do vendedor.
$subscriptionid = (string) ($payload['payment']['subscription'] ?? '');

// Eventos que nao mudam o direito do aluno saem com 200 para o Asaas parar de
// reenviar. 4xx aqui viraria fila de retentativa por um evento que nunca nos
// interessou - e com envio sequencial, uma fila travada.
if (!payment_processor::is_relevant_event($event) || $asaaspaymentid === '') {
    paygw_asaas_respond(200, 'ignored');
}

try {
    $delivered = payment_processor::process_notification($asaaspaymentid, $subscriptionid);
    paygw_asaas_respond(200, $delivered ? 'processed' : 'ignored');
} catch (\Throwable $e) {
    // 500 de proposito: o Asaas reenvia, e uma falha nossa - rede, banco - nao
    // pode virar acesso perdido de um aluno que pagou.
    debugging('paygw_asaas webhook: ' . $e->getMessage(), DEBUG_DEVELOPER);
    paygw_asaas_respond(500, 'error');
}
