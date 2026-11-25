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
 * Vincula a conta Asaas do vendedor a uma conta de pagamento, por ambiente.
 *
 * A chave e conferida contra a API ANTES de ser gravada. Guardar primeiro e
 * descobrir depois deixaria a empresa habilitada para vender com uma credencial
 * que nao funciona - e o aluno seria quem descobriria, no checkout.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use paygw_asaas\asaas_client;
use paygw_asaas\credentials;
use paygw_asaas\form\link_form;
use paygw_asaas\gateway;

$accountid = required_param('accountid', PARAM_INT);
$environment = required_param('environment', PARAM_ALPHA);

require_login();

if (!isset(asaas_client::BASE_URL[$environment])) {
    throw new moodle_exception('errorunknownenvironment', 'paygw_asaas');
}

// Falha limpa para id invalido, em vez de deixar o construtor lancar
// dml_missing_record_exception cru antes de qualquer checagem de capability.
if (!$DB->record_exists('payment_accounts', ['id' => $accountid])) {
    throw new moodle_exception('invalidrecord', 'error', '', 'payment_accounts');
}

$account = new \core_payment\account($accountid);

// A capability e avaliada no contexto DA CONTA - a categoria da empresa. E o
// que deixa o vendedor vincular a propria conta sem enxergar as das outras.
require_capability('moodle/payment:manageaccounts', $account->get_context());

$returnurl = new moodle_url('/payment/manage_gateway.php', [
    'accountid' => $accountid,
    'gateway' => 'asaas',
]);

$url = new moodle_url('/payment/gateway/asaas/link.php', [
    'accountid' => $accountid,
    'environment' => $environment,
]);

$PAGE->set_context($account->get_context());
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('linkheading', 'paygw_asaas'));
$PAGE->set_heading(get_string('linkheading', 'paygw_asaas'));

if (!credentials::encryption_ready()) {
    // Sem chave de cifragem nao gravamos credencial nenhuma. Cair para texto
    // puro "so desta vez" e como esse tipo de coisa vira permanente.
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('errornoencryptionkey', 'paygw_asaas'), 'error');
    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    exit;
}

$form = new link_form($url, ['environment' => $environment]);
$form->set_data((object) ['accountid' => $accountid, 'environment' => $environment]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $client = new asaas_client($data->apikey, $environment);

    try {
        $me = $client->get_my_account();
        $walletid = $client->get_wallet_id();
    } catch (\Throwable $e) {
        redirect(
            $url,
            get_string('errorkeyrejected', 'paygw_asaas', $e->getMessage()),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    if ($walletid === '') {
        redirect($url, get_string('errornowallet', 'paygw_asaas'), null, \core\output\notification::NOTIFY_ERROR);
    }

    // O Asaas exige que a URL de retorno use O MESMO dominio cadastrado na
    // conta que emite a cobranca - nao basta ter um site qualquer la. E como a
    // cobranca nasce na conta do VENDEDOR e o retorno aponta para a PLATAFORMA,
    // o vendedor precisa cadastrar o dominio DESTE site na conta Asaas dele.
    //
    // E contraintuitivo: por instinto ele cadastraria o proprio site. Sem esta
    // checagem o erro apareceria como recusa da cobranca inteira, com o aluno
    // ja no checkout, e com uma mensagem que nao diz de qual dominio se trata.
    $needsdomain = \paygw_asaas\payment_processor::use_callback()
        && !asaas_client::same_host((string) ($me['site'] ?? ''), $CFG->wwwroot);

    if ($needsdomain) {
        redirect(
            $url,
            get_string('errornosite', 'paygw_asaas', (object) [
                'expected' => parse_url($CFG->wwwroot, PHP_URL_HOST),
                'found' => !empty($me['site']) ? s((string) $me['site']) : get_string('none'),
            ]),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    // A carteira da plataforma nao pode ser a mesma do vendedor: o Asaas recusa
    // split para a propria carteira, e o erro so apareceria na primeira compra.
    // Barrar aqui e o mesmo principio do resto desta tela.
    //
    // ESTA GUARDA DE ISENCAO EXISTE EM DUAS COPIAS: aqui e em
    // paygw_pagarme/link.php (funcao is_platform_account() do local_marketplace,
    // a mesma nos dois). Nao ha um plugin de gateway compartilhado neste
    // projeto onde ela more uma vez so - mudar a regra exige editar as duas.
    //
    // EXCETO quando a conta sendo vinculada e a PROPRIA conta da plataforma
    // (local_marketplace\api::get_or_create_platform_account() - assinatura
    // SaaS, desenhada em 17/09/2026). Nesse caso a carteira
    // vinculada TEM que ser a da plataforma, de proposito: e essa conta que
    // recebe a mensalidade direto, sem vendedor nenhum no meio. A guarda
    // continua valendo para toda conta de empresa.
    $eplataforma = class_exists('\local_marketplace\api') && \local_marketplace\api::is_platform_account($accountid);
    if (!$eplataforma && $walletid === credentials::platform_wallet($environment)) {
        redirect($url, get_string('errorsamewallet', 'paygw_asaas'), null, \core\output\notification::NOTIFY_ERROR);
    }

    credentials::store(
        $accountid,
        $environment,
        $data->apikey,
        $walletid,
        (string) ($me['name'] ?? $me['email'] ?? '')
    );

    redirect(
        $returnurl,
        get_string('linkdone', 'paygw_asaas', gateway::environment_label($environment)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('linkintro', 'paygw_asaas'), 'info');
$form->display();
echo $OUTPUT->footer();
