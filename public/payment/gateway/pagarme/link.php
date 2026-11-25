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
 * Vincula uma conta Pagar.me a uma conta de pagamento.
 *
 * Cada checagem aqui existe para mover uma falha da tela de compra do aluno
 * para a tela de configuracao do administrador.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use paygw_pagarme\credentials;
use paygw_pagarme\form\link_form;
use paygw_pagarme\gateway;
use paygw_pagarme\pagarme_client;

$accountid = required_param('accountid', PARAM_INT);
$environment = required_param('environment', PARAM_ALPHA);

require_login();

$account = new \core_payment\account($accountid);
$context = $account->get_context();
require_capability('moodle/payment:manageaccounts', $context);

$returnurl = new moodle_url('/payment/manage_gateway.php', [
    'accountid' => $accountid,
    'gateway' => credentials::GATEWAY,
]);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/payment/gateway/pagarme/link.php', [
    'accountid' => $accountid,
    'environment' => $environment,
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('linkheading', 'paygw_pagarme'));
$PAGE->set_heading(get_string('linkheading', 'paygw_pagarme'));

// A chave e cifrada com \core\encryption, e sem a chave de cifragem no
// moodledata nao ha como guardar nada com seguranca.
if (!credentials::encryption_ready()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('errornoencryptionkey', 'paygw_pagarme'), 'error');
    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    exit;
}

$form = new link_form($PAGE->url, null, 'post', '', null, true, [
    'accountid' => $accountid,
    'environment' => $environment,
]);
$form->set_data(['accountid' => $accountid, 'environment' => $environment]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

$error = '';

if ($data = $form->get_data()) {
    $apikey = (string) $data->apikey;
    $platformrecipient = (string) $data->platformrecipient;

    try {
        $client = new pagarme_client($apikey);

        // Confere a chave e o recebedor ANTES de gravar. Uma credencial que
        // so falha no checkout e uma credencial que falha na frente do aluno.
        $recipient = $client->get_recipient($platformrecipient);
        if (empty($recipient['id'])) {
            throw new moodle_exception('errorrecipientrejected', 'paygw_pagarme');
        }

        $default = $client->get_default_recipient();
        $sellerrecipient = (string) ($default['id'] ?? '');

        // Split para o proprio recebedor nao divide nada. Recusar aqui e o
        // que impede a repeticao do erro do Mercado Pago, onde vendedor e
        // marketplace eram a mesma conta e ninguem percebeu.
        //
        // ESTA GUARDA DE ISENCAO EXISTE EM DUAS COPIAS: aqui e em
        // paygw_asaas/link.php (funcao is_platform_account() do
        // local_marketplace, a mesma nos dois). Nao ha um plugin de gateway
        // compartilhado neste projeto onde ela more uma vez so - mudar a
        // regra exige editar as duas.
        //
        // EXCETO quando a conta sendo vinculada e a PROPRIA conta da
        // plataforma (local_marketplace\api::get_or_create_platform_account()
        // - assinatura SaaS, desenhada em 17/09/2026): ai o recebedor da
        // conta TEM que ser o mesmo da plataforma, de proposito - e essa
        // conta que recebe a mensalidade direto, sem vendedor no meio.
        $eplataforma = class_exists('\local_marketplace\api') && \local_marketplace\api::is_platform_account($accountid);
        if (!$eplataforma && $sellerrecipient !== '' && $sellerrecipient === $platformrecipient) {
            throw new moodle_exception('errorsamerecipient', 'paygw_pagarme');
        }

        credentials::store(
            $accountid,
            $environment,
            $apikey,
            $platformrecipient,
            $sellerrecipient,
            (string) ($recipient['name'] ?? '')
        );

        if ($sellerrecipient === '') {
            // O get_default_recipient() engole qualquer falha (docblock dele: a
            // conta pode ainda nao ter recebedor configurado, o que e
            // pendencia do vendedor, nao erro do plugin) e devolve vazio. Sem
            // este aviso, o vinculo parecia "concluido com sucesso" enquanto
            // toda cobranca futura saia sem split nenhum, silenciosamente -
            // so visivel lendo o banco.
            redirect(
                $returnurl,
                get_string('linkdonenosplit', 'paygw_pagarme'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }

        redirect($returnurl, get_string('linkdone', 'paygw_pagarme'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('linkintro', 'paygw_pagarme'));
echo html_writer::tag('p', html_writer::tag('strong', gateway::environment_label($environment)));

if ($error !== '') {
    echo $OUTPUT->notification($error, 'error');
}

$form->display();
echo $OUTPUT->footer();
