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

namespace paygw_pagarme;

use html_writer;
use moodle_url;

/**
 * O que o nucleo pergunta a este gateway.
 *
 * O core_payment e o local_marketplace nao conhecem o nome de gateway nenhum:
 * perguntam por component_class_callback, e o que nao existir cai no padrao.
 * Por isso nao ha registro em lugar nenhum - basta o metodo existir.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Moedas atendidas.
     *
     * @return string[]
     */
    public static function get_supported_currencies(): array {
        return ['BRL'];
    }

    /**
     * Paises atendidos.
     *
     * @return string[]
     */
    public static function get_supported_countries(): array {
        return ['BR'];
    }

    /**
     * Campos do formulario da conta de pagamento.
     *
     * Os campos ocultos existem por uma razao pratica: o formulario do core
     * serializa em config TUDO que ele devolve, entao um campo que nao esteja
     * aqui e APAGADO no salvamento seguinte. A chave de verdade nunca aparece
     * na tela - ela e cifrada e vive em link.php.
     *
     * @param \core_payment\form\account_gateway $form
     * @return void
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement(
            'static',
            'linkstatus',
            get_string('linkstatus', 'paygw_pagarme'),
            self::describe_status($form)
        );

        foreach ([pagarme_client::ENV_SANDBOX, pagarme_client::ENV_PRODUCTION] as $environment) {
            foreach (credentials::FIELDS as $prefix) {
                $mform->addElement('hidden', $prefix . $environment);
                $mform->setType($prefix . $environment, PARAM_RAW);
            }
        }
    }

    /**
     * Impede habilitar sem vinculo.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors
     * @return void
     */
    public static function validate_gateway_form(
        \core_payment\form\account_gateway $form,
        \stdClass $data,
        array $files,
        array &$errors
    ): void {
        if (!$data->enabled) {
            return;
        }

        $environment = credentials::current_environment();
        if (empty($data->{'apikey_' . $environment})) {
            $errors['enabled'] = get_string(
                'errornotlinked',
                'paygw_pagarme',
                self::environment_label($environment)
            );
        }
    }

    /**
     * Texto de situacao do vinculo, um bloco por ambiente.
     *
     * @param \core_payment\form\account_gateway $form
     * @return string HTML
     */
    protected static function describe_status(\core_payment\form\account_gateway $form): string {
        $accountid = (int) $form->get_gateway_persistent()->get('accountid');
        $out = '';

        foreach ([pagarme_client::ENV_SANDBOX, pagarme_client::ENV_PRODUCTION] as $environment) {
            $config = credentials::get_config($accountid);
            $linked = !empty($config['apikey_' . $environment]);
            $label = self::environment_label($environment);

            if ($linked) {
                $text = get_string('linkedas', 'paygw_pagarme', (object) [
                    'environment' => $label,
                    'account' => s((string) ($config['accountname_' . $environment] ?? '')),
                    'tail' => s((string) ($config['keytail_' . $environment] ?? '')),
                ]);
                $buttons = self::button('link', $accountid, $environment, 'relink', 'btn btn-secondary')
                    . ' ' . self::button('unlink', $accountid, $environment, 'unlink', 'btn btn-link');
            } else {
                $text = get_string('notlinked', 'paygw_pagarme', $label);
                $buttons = self::button('link', $accountid, $environment, 'link', 'btn btn-primary');
            }

            $out .= html_writer::div(
                html_writer::tag('strong', $label) . ' — ' . $text . '<br>' . $buttons,
                'mb-3'
            );
        }

        return $out;
    }

    /**
     * Botao que sai do formulario.
     *
     * E link, e nao single_button: um form dentro do form do gateway quebra o
     * salvamento do formulario de fora.
     *
     * @param string $page
     * @param int $accountid
     * @param string $environment
     * @param string $stringkey
     * @param string $class
     * @return string
     */
    protected static function button(
        string $page,
        int $accountid,
        string $environment,
        string $stringkey,
        string $class
    ): string {
        $url = new moodle_url('/payment/gateway/pagarme/' . $page . '.php', [
            'accountid' => $accountid,
            'environment' => $environment,
        ]);

        return html_writer::link($url, get_string($stringkey, 'paygw_pagarme'), ['class' => $class]);
    }

    /**
     * Nome do ambiente na lingua do usuario.
     *
     * @param string $environment
     * @return string
     */
    public static function environment_label(string $environment): string {
        return get_string('environment' . $environment, 'paygw_pagarme');
    }

    /**
     * Fatura em aberto do ciclo, para a tela de assinaturas do aluno.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return array|null
     */
    public static function pending_invoice(string $component, int $itemid, int $userid): ?array {
        global $DB;

        $rows = $DB->get_records_select(
            payment_processor::TABLE,
            "component = :component AND itemid = :itemid AND userid = :userid
             AND subscriptionid IS NOT NULL AND subscriptionid <> ''",
            ['component' => $component, 'itemid' => $itemid, 'userid' => $userid],
            'id DESC',
            '*',
            0,
            1
        );
        $row = reset($rows);

        return $row ? payment_processor::pending_invoice($row) : null;
    }

    /**
     * Estorna uma venda.
     *
     * @param int $paymentid
     * @return bool
     */
    public static function refund(int $paymentid): bool {
        global $DB;

        $row = $DB->get_record(payment_processor::TABLE, ['paymentid' => $paymentid]);
        if (!$row) {
            return false;
        }

        return payment_processor::refund($row);
    }

    /**
     * Motivo para esconder o botao de estorno.
     *
     * @param int $paymentid
     * @return string
     */
    public static function refund_blocker(int $paymentid): string {
        global $DB;

        $row = $DB->get_record(payment_processor::TABLE, ['paymentid' => $paymentid]);

        return $row ? payment_processor::refund_blocker($row) : 'errorrefundunknown';
    }

    /**
     * Para de cobrar uma assinatura.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return bool
     */
    public static function cancel_recurring(string $component, int $itemid, int $userid): bool {
        global $DB;

        $rows = $DB->get_records_select(
            payment_processor::TABLE,
            "component = :component AND itemid = :itemid AND userid = :userid
             AND subscriptionid IS NOT NULL AND subscriptionid <> ''",
            ['component' => $component, 'itemid' => $itemid, 'userid' => $userid],
            'id DESC'
        );

        $cancelled = false;
        $done = [];

        foreach ($rows as $row) {
            // Uma assinatura tem varias linhas, uma por ciclo. Cancelar a
            // mesma duas vezes so renderia erro da API por nada.
            if (isset($done[$row->subscriptionid])) {
                continue;
            }
            $done[$row->subscriptionid] = true;

            $apikey = credentials::api_key((int) $row->accountid, $row->environment);
            if ($apikey === '') {
                continue;
            }

            (new pagarme_client($apikey))->cancel_subscription((string) $row->subscriptionid);
            $cancelled = true;
        }

        return $cancelled;
    }
}
