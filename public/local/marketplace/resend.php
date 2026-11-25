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
 * Reenvia ao aluno o aviso com a fatura do ciclo.
 *
 * "Nao recebi o boleto" e o motivo mais comum de uma mensalidade nao ser paga, e
 * ate aqui a unica saida do gerente era copiar o link na mao - se soubesse onde
 * achar. A mensagem e a MESMA que o cron manda, com link da fatura e linha
 * digitavel quando o gateway a fornece.
 *
 * Nao marca a preferencia de aviso enviado: se marcasse, reenviar hoje calaria o
 * aviso automatico de amanha.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\task\notify_expiring;

$entid = required_param('id', PARAM_INT);

require_login();
require_sesskey();

$ent = new entitlement($entid);
$company = company::get_record(['id' => (int) $ent->get('companyid')]);
if (!$company) {
    throw new moodle_exception('invalidrecord', 'error');
}

// No contexto da CATEGORIA: quem gere uma empresa nao reenvia cobranca de outra.
require_capability('local/marketplace:managesales', $company->get_context());

$voltar = new moodle_url('/local/marketplace/report.php', [
    'company' => $company->get('shortname'),
    'view' => 'subscriptions',
]);

$enviou = notify_expiring::send_notice($ent->to_record(), true);

redirect(
    $voltar,
    get_string($enviou ? 'resenddone' : 'resendfailed', 'local_marketplace'),
    null,
    $enviou ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
);
