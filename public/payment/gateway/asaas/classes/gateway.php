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

namespace paygw_asaas;

use html_writer;
use moodle_url;

/**
 * O gateway Asaas para o core_payment.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Moedas aceitas.
     *
     * O Asaas so opera no Brasil. Declarar qualquer outra moeda faria o gateway
     * aparecer no checkout de uma oferta que ele nao tem como cobrar.
     *
     * @return string[]
     */
    public static function get_supported_currencies(): array {
        return ['BRL'];
    }

    /**
     * Paises atendidos, em ISO-3166 alpha-2.
     *
     * Nao faz parte do contrato do core_payment - e o marketplace que pergunta,
     * para montar a lista de meios de pagamento de cada pais sem ter nome de
     * gateway escrito no codigo.
     *
     * @return string[]
     */
    public static function get_supported_countries(): array {
        return ['BR'];
    }

    /**
     * Campos da configuracao por conta de pagamento.
     *
     * Nao ha campo de chave editavel aqui, e a razao e a mesma do Mercado Pago
     * neste projeto: o formulario do core serializa em config TUDO que nao e
     * propriedade do account_gateway. Uma chave de API passando por ele ficaria
     * em claro no JSON e voltaria para a tela a cada edicao. O vinculo mora em
     * link.php, que valida a chave contra a API antes de cifrar e gravar.
     *
     * Os campos abaixo existem como hidden porque o formulario grava o que ele
     * devolve: um campo ausente e APAGADO no salvamento seguinte. Foi assim que
     * o vinculo do Mercado Pago sumiu na primeira versao dele.
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('static', 'linkstatus', get_string('linkstatus', 'paygw_asaas'), self::describe_status($form));

        foreach ([asaas_client::ENV_SANDBOX, asaas_client::ENV_PRODUCTION] as $environment) {
            foreach (['apikey_', 'walletid_', 'accountname_', 'keytail_'] as $prefix) {
                $mform->addElement('hidden', $prefix . $environment);
                $mform->setType($prefix . $environment, PARAM_RAW);
            }
        }
    }

    /**
     * Nao deixa habilitar um gateway que nao tem como cobrar.
     *
     * Habilitado sem credencial faz a empresa anunciar um meio de pagamento que
     * quebra no checkout - com o aluno ja decidido a comprar, que e o pior
     * lugar para descobrir um erro de configuracao.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors
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
            $errors['enabled'] = get_string('errornotlinked', 'paygw_asaas', self::environment_label($environment));
        }
    }

    /**
     * Texto de situacao do vinculo, com um bloco por ambiente.
     *
     * @param \core_payment\form\account_gateway $form
     * @return string
     */
    protected static function describe_status(\core_payment\form\account_gateway $form): string {
        $gateway = $form->get_gateway_persistent();
        $accountid = (int) $gateway->get('accountid');

        if (!$gateway->get('id')) {
            return get_string('savebeforelinking', 'paygw_asaas');
        }

        if (!credentials::encryption_ready()) {
            return html_writer::div(get_string('errornoencryptionkey', 'paygw_asaas'), 'alert alert-danger');
        }

        $config = $gateway->get_configuration();
        $current = credentials::current_environment();
        $blocks = [];

        foreach ([asaas_client::ENV_SANDBOX, asaas_client::ENV_PRODUCTION] as $environment) {
            $label = self::environment_label($environment);
            $isactive = ($environment === $current);
            $linked = !empty($config['apikey_' . $environment]);

            $title = html_writer::tag('strong', $label)
                . ($isactive ? ' ' . html_writer::tag('span', get_string('environmentactive', 'paygw_asaas'), [
                    'class' => 'badge bg-primary',
                ]) : '');

            if ($linked) {
                // Nunca a chave inteira: os ultimos digitos bastam para a pessoa
                // reconhecer qual chave esta ali sem que a tela vire um lugar de
                // onde se copia credencial.
                $detail = get_string('linkedas', 'paygw_asaas', (object) [
                    'name' => s((string) ($config['accountname_' . $environment] ?? '?')),
                    'wallet' => s((string) ($config['walletid_' . $environment] ?? '?')),
                    'tail' => s((string) ($config['keytail_' . $environment] ?? '')),
                ]);
                $buttons = self::button('link', $accountid, $environment, 'relink', 'btn-outline-secondary')
                    . ' ' . self::button('unlink', $accountid, $environment, 'unlink', 'btn-outline-danger');
            } else {
                $detail = get_string('notlinked', 'paygw_asaas');
                $buttons = self::button('link', $accountid, $environment, 'link', 'btn-primary');
            }

            $blocks[] = html_writer::div(
                $title . html_writer::div($detail, 'small text-muted mb-1') . $buttons,
                'mb-3'
            );
        }

        return implode('', $blocks);
    }

    /**
     * Um botao de vinculo.
     *
     * html_writer::link e nao single_button: o single_button renderiza um
     * <form>, e um form dentro do form do gateway quebra o formulario de fora.
     *
     * @param string $page link|unlink
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
        return html_writer::link(
            new moodle_url('/payment/gateway/asaas/' . $page . '.php', [
                'accountid' => $accountid,
                'environment' => $environment,
            ]),
            get_string($stringkey, 'paygw_asaas'),
            ['class' => 'btn btn-sm ' . $class]
        );
    }

    /**
     * Nome legivel do ambiente.
     *
     * @param string $environment
     * @return string
     */
    public static function environment_label(string $environment): string {
        return get_string('environment' . $environment, 'paygw_asaas');
    }

    /**
     * A fatura em aberto do proximo ciclo deste aluno neste item.
     *
     * Chamada pelo local_marketplace via component_class_callback, com a mesma
     * assinatura para todo gateway. Quem nao tem assinatura nao implementa, e o
     * callback devolve o padrao.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return array|null url, duedate, value e line, ou null quando nao ha
     */
    public static function pending_invoice(string $component, int $itemid, int $userid): ?array {
        global $DB;

        $records = $DB->get_records_select(
            payment_processor::TABLE,
            "component = :component AND itemid = :itemid AND userid = :userid
             AND subscriptionid IS NOT NULL AND subscriptionid <> ''",
            ['component' => $component, 'itemid' => $itemid, 'userid' => $userid],
            'id DESC',
            '*',
            0,
            1
        );
        $record = reset($records);

        return $record ? payment_processor::pending_invoice($record) : null;
    }

    /**
     * Estorna a venda correspondente a um pagamento do core.
     *
     * Chamado pelo local_marketplace via component_class_callback, com a mesma
     * assinatura para todo gateway - e por isso o nucleo continua sem saber o
     * nome de nenhum.
     *
     * @param int $paymentid Registro em {payments}
     * @return bool Verdadeiro quando o gateway aceitou o estorno.
     */
    public static function refund(int $paymentid): bool {
        global $DB;

        $record = $DB->get_record(payment_processor::TABLE, ['paymentid' => $paymentid]);
        if (!$record) {
            return false;
        }

        return payment_processor::refund($record);
    }

    /**
     * Motivo pelo qual esta venda nao pode ser estornada.
     *
     * Serve a TELA: e com isto que o botao some, em vez de aparecer e falhar
     * na hora do clique. Devolve vazio quando o estorno e possivel.
     *
     * @param int $paymentid
     * @return string Chave de string do erro, ou vazio
     */
    public static function refund_blocker(int $paymentid): string {
        global $DB;

        $record = $DB->get_record(payment_processor::TABLE, ['paymentid' => $paymentid]);

        return $record ? payment_processor::refund_blocker($record) : 'errorrefundunknown';
    }

    /**
     * Para de cobrar a assinatura deste aluno neste item.
     *
     * Chamado pelo local_marketplace quando o aluno cancela, via
     * component_class_callback - por isso a assinatura e a mesma para todo
     * gateway, e por isso o nucleo continua sem saber o nome de nenhum.
     *
     * Cancelar para de COBRAR e nada mais. O acesso ja pago vale ate o fim do
     * ciclo: quem cancela no dia 3 nao perde os 27 dias que comprou, e revogar
     * direito e decisao de negocio que vive no entitlement::revoke().
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return bool Verdadeiro se havia assinatura ativa e ela foi cancelada.
     */
    public static function cancel_recurring(string $component, int $itemid, int $userid): bool {
        global $DB;

        $records = $DB->get_records_select(
            payment_processor::TABLE,
            "component = :component AND itemid = :itemid AND userid = :userid
             AND subscriptionid IS NOT NULL AND subscriptionid <> ''",
            ['component' => $component, 'itemid' => $itemid, 'userid' => $userid],
            'id DESC'
        );

        $cancelled = false;
        $done = [];

        foreach ($records as $record) {
            // Uma assinatura tem varias linhas, uma por ciclo. Cancelar a mesma
            // duas vezes devolveria erro do Asaas por nada.
            if (isset($done[$record->subscriptionid])) {
                continue;
            }
            $done[$record->subscriptionid] = true;

            $apikey = credentials::api_key((int) $record->accountid, $record->environment);
            if ($apikey === '') {
                continue;
            }

            (new asaas_client($apikey, $record->environment))->cancel_subscription($record->subscriptionid);
            $cancelled = true;
        }

        return $cancelled;
    }
}
