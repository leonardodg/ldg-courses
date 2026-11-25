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
 * Remove o vinculo de UM ambiente.
 *
 * Desvincular a homologacao nao derruba a producao: seria interromper venda de
 * verdade para arrumar um teste.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use paygw_asaas\asaas_client;
use paygw_asaas\credentials;
use paygw_asaas\gateway;

$accountid = required_param('accountid', PARAM_INT);
$environment = required_param('environment', PARAM_ALPHA);

require_login();

if (!isset(asaas_client::BASE_URL[$environment])) {
    throw new moodle_exception('errorunknownenvironment', 'paygw_asaas');
}

$account = new \core_payment\account($accountid);
require_capability('moodle/payment:manageaccounts', $account->get_context());

$returnurl = new moodle_url('/payment/manage_gateway.php', [
    'accountid' => $accountid,
    'gateway' => 'asaas',
]);

$url = new moodle_url('/payment/gateway/asaas/unlink.php', [
    'accountid' => $accountid,
    'environment' => $environment,
]);

$PAGE->set_context($account->get_context());
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('unlink', 'paygw_asaas'));
$PAGE->set_heading(get_string('unlink', 'paygw_asaas'));

if (optional_param('confirm', 0, PARAM_BOOL)) {
    require_sesskey();

    credentials::forget($accountid, $environment);

    redirect(
        $returnurl,
        get_string('unlinkdone', 'paygw_asaas', gateway::environment_label($environment)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('unlinkconfirm', 'paygw_asaas', gateway::environment_label($environment)),
    new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]),
    $returnurl
);

// O vinculo some daqui, mas a chave continua valida no painel do Asaas. Quem
// quiser revoga-la de verdade tem que faze-lo la - dizer isso evita a falsa
// sensacao de que remover o vinculo cancelou o acesso.
echo $OUTPUT->notification(get_string('unlinknotice', 'paygw_asaas'), 'info');
echo $OUTPUT->footer();
