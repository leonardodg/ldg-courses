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

namespace paygw_mercadopago;

/**
 * Gateway do Mercado Pago, Checkout Pro.
 *
 * A configuracao e dividida em dois niveis, e a divisao e o que faz o split
 * funcionar:
 *
 *   SITE  - client_id e client_secret da aplicacao. Sao da PLATAFORMA, ficam
 *           em settings.php e valem para todos os vendedores.
 *   CONTA - access_token do vendedor, obtido por OAuth. Fica na config desta
 *           conta de pagamento, que pertence a empresa dele.
 *
 * A preferencia e criada com o token do VENDEDOR, mas pela aplicacao da
 * plataforma - e isso que autoriza o marketplace_fee a voltar para nos. Um
 * token colado a mao pelo vendedor, fora do fluxo OAuth, cria a preferencia
 * mas NAO habilita o split.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Moedas aceitas.
     *
     * Lista restrita aos paises onde o Mercado Pago opera. Fora deles a
     * preferencia e recusada pela API, entao oferecer a moeda so produziria
     * erro no checkout.
     *
     * @return string[]
     */
    public static function get_supported_currencies(): array {
        return ['BRL', 'ARS', 'MXN', 'CLP', 'COP', 'PEN', 'UYU'];
    }

    /**
     * Paises em que este gateway consegue receber, em ISO-3166 alpha-2.
     *
     * Nao faz parte do contrato do core_payment - ele so pergunta por moeda.
     * Existe porque a moeda nao identifica o pais em todos os casos, e porque o
     * marketplace precisa saber a que mercado uma conta pertence antes de
     * qualquer moeda estar definida. O nucleo chama via component_class_callback,
     * entao um gateway que nao declare isto simplesmente nao aparece na lista.
     *
     * @return string[]
     */
    public static function get_supported_countries(): array {
        return ['AR', 'BR', 'CL', 'CO', 'MX', 'PE', 'UY'];
    }

    /**
     * Campos da configuracao por conta de pagamento.
     *
     * Nao ha campo de token editavel: ele e escrito pelo fluxo OAuth. Deixar o
     * vendedor colar um token a mao pareceria mais simples e produziria um
     * split que nao funciona - falha que so apareceria na conciliacao, depois
     * do dinheiro ja ter caido errado.
     *
     * QUEM PREENCHE ESTA TELA E O ADMIN, nunca o vendedor: e a tela de "Site
     * administration > Payments > Payment accounts", atras da mesma capability
     * que gerencia qualquer conta de pagamento do Moodle. O modo de captura de
     * cartao e os meios aceitos por empresa entram aqui exatamente por isso -
     * sao decisao de quem administra a plataforma, empresa a empresa, e nao um
     * ajuste que o vendedor faz sozinho.
     *
     * @param \core_payment\form\account_gateway $form
     * @return void
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement(
            'static',
            'oauthstatus',
            get_string('oauthstatus', 'paygw_mercadopago'),
            self::describe_oauth_status($form)
        );
        // Sem isto o markup sai escapado e o vendedor ve as tags na tela.
        $mform->setType('oauthstatus', PARAM_RAW);

        // Modo de captura do cartao e meios aceitos, POR EMPRESA.
        //
        // O valor vazio ('') significa "usar o padrao DESTE PLUGIN" (nao da
        // plataforma inteira - Asaas e Pagar.me tem os proprios), e e o
        // padrao do proprio campo - contas que existiam antes desta opcao
        // continuam se comportando exatamente como antes, porque cai direto
        // no get_config('paygw_mercadopago', ...) dentro de
        // card_capture/payment_methods.
        $mform->addElement(
            'select',
            'cardcapture',
            get_string('cardcaptureaccount', 'paygw_mercadopago'),
            card_capture::form_options(true)
        );
        $mform->setDefault('cardcapture', '');
        $mform->addHelpButton('cardcapture', 'cardcaptureaccount', 'paygw_mercadopago');

        foreach (payment_methods::METHODS as $metodo) {
            $mform->addElement(
                'select',
                'method' . $metodo,
                get_string('methodaccount' . $metodo, 'paygw_mercadopago'),
                payment_methods::form_options()
            );
            $mform->setDefault('method' . $metodo, '');
        }

        // NAO ha caixa de "modo de teste" aqui.
        //
        // Existia uma, por conta, com o MESMO rotulo da configuracao de site.
        // Duas caixas indistinguiveis governando a mesma coisa: desligar uma e
        // esquecer a outra mandava o aluno para o sandbox com um token de
        // producao, e a tela nao dava nenhuma pista de qual faltava.
        //
        // O ambiente e propriedade do SITE, nao da conta - comprador, vendedor
        // e aplicacao precisam estar todos do mesmo lado. Uma chave por conta
        // permitia justamente a mistura que o Mercado Pago recusa.

        // Guardados pelo callback do OAuth, nunca digitados. Precisam estar no
        // formulario mesmo escondidos: a configuracao salva e a que o
        // formulario devolve, entao um campo ausente aqui seria APAGADO na
        // primeira vez que o vendedor salvasse a tela.
        //
        // Sao os campos de TODOS os tipos, e nao so os do que esta vinculado:
        // esquecer um faria salvar a tela apagar o vinculo daquela aplicacao,
        // em silencio, sem que ninguem tivesse mexido nela.
        foreach (application::TYPES as $type) {
            foreach (application::ACCOUNT_FIELDS as $field) {
                $name = application::token_field($type, $field);
                $mform->addElement('hidden', $name);
                $mform->setType($name, $field === 'tokenexpires' ? PARAM_INT : PARAM_RAW);
            }
        }
    }

    /**
     * Valida a configuracao.
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

        // Habilitar a conta sem token concluido faria o aluno chegar ao
        // checkout e receber erro do Mercado Pago. Melhor recusar aqui.
        //
        // Basta UMA aplicacao vinculada: quem tem so Preferencias vende avulso,
        // e isso e uma conta util. Exigir as tres impediria a empresa que nao
        // vende assinatura de vender coisa nenhuma.
        foreach (application::TYPES as $type) {
            if (!empty($data->{application::token_field($type, 'accesstoken')})) {
                return;
            }
        }

        $errors['enabled'] = get_string('errornotlinked', 'paygw_mercadopago');
    }

    /**
     * A fatura em aberto do proximo ciclo deste aluno neste item.
     *
     * Chamada pelo local_marketplace via component_class_callback, com a mesma
     * assinatura para todo gateway - e por isso o nucleo continua sem saber o
     * nome de nenhum.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return array|null url, duedate, value e line, ou null quando nao ha
     */
    public static function pending_invoice(string $component, int $itemid, int $userid): ?array {
        $linha = self::latest_subscription_row($component, $itemid, $userid);

        return $linha ? payment_processor::pending_invoice($linha) : null;
    }

    /**
     * Por qual meio esta assinatura esta sendo cobrada, ou null quando nao
     * ha assinatura.
     *
     * Mesma chamada generica de pending_invoice() - o nucleo continua sem
     * saber o nome de nenhum gateway.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return string|null
     */
    public static function payment_method(string $component, int $itemid, int $userid): ?string {
        $linha = self::latest_subscription_row($component, $itemid, $userid);

        return $linha ? payment_processor::payment_method_label($linha) : null;
    }

    /**
     * Endereco para trocar Pix/boleto por cartao guardado, ou null quando
     * nao faz sentido oferecer a troca.
     *
     * Mesma chamada generica de pending_invoice() - o nucleo continua sem
     * saber o nome de nenhum gateway.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return string|null
     */
    public static function switch_to_card_url(string $component, int $itemid, int $userid): ?string {
        $linha = self::latest_subscription_row($component, $itemid, $userid);

        if (!$linha || !payment_processor::can_switch_to_card($linha)) {
            return null;
        }

        return (new \moodle_url(
            '/payment/gateway/mercadopago/switch_to_card.php',
            ['ref' => (string) $linha->externalreference]
        ))->out(false);
    }

    /**
     * Estorna a venda correspondente a um pagamento do core.
     *
     * @param int $paymentid Registro em {payments}
     * @return bool Verdadeiro quando o gateway aceitou o estorno
     */
    public static function refund(int $paymentid): bool {
        global $DB;

        $linha = $DB->get_record(payment_processor::TABLE, ['paymentid' => $paymentid]);

        return $linha ? payment_processor::refund($linha) : false;
    }

    /**
     * Motivo pelo qual esta venda nao pode ser estornada.
     *
     * Serve a TELA: e com isto que o botao some, em vez de aparecer e falhar na
     * hora do clique. Devolve vazio quando o estorno e possivel.
     *
     * @param int $paymentid
     * @return string Chave de string do erro, ou vazio
     */
    public static function refund_blocker(int $paymentid): string {
        global $DB;

        $linha = $DB->get_record(payment_processor::TABLE, ['paymentid' => $paymentid]);

        return $linha ? payment_processor::refund_blocker($linha) : 'errorrefundunknown';
    }

    /**
     * Para de cobrar a assinatura deste aluno neste item.
     *
     * Cancelar para de COBRAR e nada mais. O acesso ja pago vale ate o fim do
     * ciclo: quem cancela no dia 3 nao perde os 27 dias que comprou, e revogar
     * direito e decisao de negocio que vive no entitlement::revoke().
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return bool Verdadeiro se havia assinatura ativa e ela foi cancelada
     */
    public static function cancel_recurring(string $component, int $itemid, int $userid): bool {
        $linha = self::latest_subscription_row($component, $itemid, $userid);

        if (!$linha || empty($linha->subscriptionid)) {
            return false;
        }

        return payment_processor::cancel_subscription((string) $linha->subscriptionid);
    }

    /**
     * A configuracao desta CONTA de pagamento (empresa), ou vazia quando a
     * conta ainda nao existe ou nao esta vinculada a este gateway.
     *
     * Compartilhada por card_capture e payment_methods, para as duas nao lerem
     * o account_gateway cada uma do seu jeito - o dia em que a leitura mudasse
     * (por exemplo, cache), mudaria num lugar so.
     *
     * @param int $accountid
     * @return array
     */
    public static function account_configuration(int $accountid): array {
        try {
            $gateway = \core_payment\account_gateway::get_record([
                'accountid' => $accountid,
                'gateway' => 'mercadopago',
            ]);

            return $gateway ? $gateway->get_configuration() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * A linha mais recente da assinatura deste aluno neste item.
     *
     * Cada ciclo e uma linha propria, entao "a assinatura" e sempre a mais
     * nova: e ela que carrega o cartao guardado e o estado atual.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return \stdClass|null
     */
    protected static function latest_subscription_row(string $component, int $itemid, int $userid): ?\stdClass {
        global $DB;

        $linhas = $DB->get_records_select(
            payment_processor::TABLE,
            "component = :component AND itemid = :itemid AND userid = :userid
             AND subscriptionid IS NOT NULL AND subscriptionid <> ''",
            ['component' => $component, 'itemid' => $itemid, 'userid' => $userid],
            'id DESC',
            '*',
            0,
            1
        );

        return reset($linhas) ?: null;
    }

    /**
     * Texto do estado do vinculo, exibido no formulario.
     *
     * @param \core_payment\form\account_gateway $form
     * @return string
     */
    protected static function describe_oauth_status(\core_payment\form\account_gateway $form): string {
        $gateway = $form->get_gateway_persistent();
        $accountid = $gateway ? (int) $gateway->get('accountid') : 0;

        if (!$accountid) {
            // A conta ainda nao existe: o gateway precisa ser salvo antes,
            // senao nao ha accountid para levar ao fluxo OAuth.
            return get_string('savebeforelinking', 'paygw_mercadopago');
        }

        $config = $gateway->get('id') ? $gateway->get_configuration() : [];

        // So as aplicacoes CONFIGURADAS no site aparecem. Oferecer o botao de
        // uma aplicacao sem client_id levaria o vendedor a um erro que ele nao
        // tem como resolver: quem preenche aquilo e o administrador.
        $types = application::configured_types();
        if (!$types) {
            return get_string('errormissingappconfig', 'paygw_mercadopago', '');
        }

        $linhas = [];
        foreach ($types as $type) {
            $linhas[] = \html_writer::tag(
                'div',
                \html_writer::tag('strong', get_string('apptype_' . $type, 'paygw_mercadopago'))
                    . '<br>' . self::describe_one_application($type, $accountid, $config),
                ['class' => 'mb-3']
            );
        }

        return implode('', $linhas);
    }

    /**
     * Estado do vinculo de UMA aplicacao nesta conta.
     *
     * @param string $type Um dos application::TYPES
     * @param int $accountid
     * @param array $config Configuracao da conta
     * @return string
     */
    protected static function describe_one_application(string $type, int $accountid, array $config): string {
        $rotulo = get_string('apptype_' . $type, 'paygw_mercadopago');
        $token = (string) ($config[application::token_field($type, 'accesstoken')] ?? '');

        // Link, NAO single_button. single_button renderiza um <form>, e este
        // texto vai DENTRO do formulario de configuracao do gateway.
        // Formulario aninhado e HTML invalido: o navegador descarta o interno,
        // e o clique submete o externo - que aponta para manage_gateway.php sem
        // accountid nem gateway, produzindo "Gateway not found".
        $vincular = static fn(string $chave, string $classe): string => \html_writer::link(
            new \moodle_url('/payment/gateway/mercadopago/oauth_start.php', [
                'accountid' => $accountid,
                'apptype' => $type,
            ]),
            $chave === 'linkaccounttype'
                ? get_string('linkaccounttype', 'paygw_mercadopago', $rotulo)
                : get_string($chave, 'paygw_mercadopago'),
            ['class' => $classe, 'target' => '_self']
        );

        if ($token === '') {
            return get_string('oauthnotlinked', 'paygw_mercadopago')
                . '<br>' . $vincular('linkaccounttype', 'btn btn-secondary');
        }

        $expires = (int) ($config[application::token_field($type, 'tokenexpires')] ?? 0);

        // Trocar de conta nao exige desvincular - autorizar de novo sobrescreve
        // o token. Os dois botoes existem porque as intencoes sao diferentes:
        // um corrige a conta errada, o outro encerra a operacao.
        $acoes = $vincular('relinkaccount', 'btn btn-secondary') . ' ' . \html_writer::link(
            new \moodle_url('/payment/gateway/mercadopago/oauth_unlink.php', [
                'accountid' => $accountid,
                'apptype' => $type,
            ]),
            get_string('unlinkaccount', 'paygw_mercadopago'),
            ['class' => 'btn btn-outline-danger', 'target' => '_self']
        );

        if ($expires && $expires <= time()) {
            return get_string('oauthexpired', 'paygw_mercadopago') . '<br>' . $acoes;
        }

        $currency = (string) ($config[application::token_field($type, 'currency')] ?? '');

        return get_string('oauthlinked', 'paygw_mercadopago', [
            'mpuserid' => s($config[application::token_field($type, 'mpuserid')] ?? '?'),
            'expires' => $expires ? userdate($expires) : '-',
        ])
            . ($currency !== '' ? ' ' . get_string('oauthcurrency', 'paygw_mercadopago', s($currency)) : '')
            . '<br>' . $acoes;
    }
}
