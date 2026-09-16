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

use core_payment\helper;
use moodle_exception;

/**
 * Cria a cobranca e processa a confirmacao.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_processor {
    /** @var string Tabela do gateway. */
    const TABLE = 'paygw_mercadopago';

    /**
     * Abre a cobranca e devolve para onde mandar o aluno.
     *
     * Sao dois destinos, e eles nao sao variacao do mesmo: dependem de o item
     * ser assinatura ou venda avulsa.
     *
     *   AVULSA     - cria a preferencia e devolve o init_point do Checkout Pro.
     *                O aluno sai do site e paga no Mercado Pago.
     *   ASSINATURA - devolve uma pagina NOSSA. Nao ha o que criar no Mercado
     *                Pago ainda: um ciclo so pode ser cobrado com um cartao
     *                guardado, e o cartao so vira token no navegador do aluno.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return string Endereco para onde redirecionar o aluno
     */
    public static function start_payment(string $component, string $paymentarea, int $itemid, int $userid): string {
        global $DB, $CFG;

        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $accountid = $payable->get_account_id();
        $amount = (float) $payable->get_amount();
        $currency = $payable->get_currency();

        $appconfig = get_config('paygw_mercadopago');

        // Assinatura ou venda avulsa? Quem sabe e o MARKETPLACE - o gateway nao
        // tem como saber o que e uma "oferta recorrente". Sem ele instalado, ou
        // para item que nao e assinatura, recurrence_for() devolve null e nada
        // muda em relacao ao que existia antes.
        //
        // Vem ANTES da configuracao porque decide qual APLICACAO precisa estar
        // vinculada. Conferir a de Preferencias e depois cobrar por Bricks
        // deixaria a venda falhar adiante, com o aluno ja decidido a comprar.
        $recorrencia = self::recurrence_for($component, $itemid);
        $apptype = $recorrencia
            ? application::type_for_recurring()
            : application::TYPE_PREFERENCES;

        $config = self::get_gateway_config($accountid, $apptype);

        $reference = self::build_reference($userid, $itemid, (bool) $recorrencia);

        // A comissao e regra do marketplace, nao do gateway. Perguntamos a ele
        // quando ele esta presente, e caimos no padrao de fabrica quando outro
        // componente usa este gateway - assim o plugin continua servindo a
        // qualquer componente do core_payment, sem depender do marketplace.
        $feepercent = 25.0;
        $feesource = 'site';
        if (class_exists('\local_marketplace\api')) {
            $terms = \local_marketplace\api::commission_terms_for($component, $itemid);
            $feepercent = $terms->percent;
            $feesource = $terms->source;
        }

        // A base APLICADA e sempre o bruto, e nao ha escolha: o marketplace_fee
        // e valor absoluto, e a taxa do Mercado Pago so e conhecida depois que
        // o pagamento acontece. Nao da para cobrar um percentual do liquido de
        // um numero que ainda nao existe.
        //
        // Quando o marketplace pede 'net', esta e a divergencia que o registro
        // existe para expor: gravamos gross, que foi o que aconteceu, em vez de
        // gravar a intencao e deixar o relatorio mentir.
        $feebase = 'gross';
        $fee = self::fee_for($amount, $feepercent);

        $record = (object) [
            'preferenceid' => '',
            'externalreference' => $reference,
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'userid' => $userid,
            'accountid' => $accountid,
            'amount' => $amount,
            'currency' => $currency,
            'feeamount' => $fee,
            'feepercent' => $feepercent,
            'feebase' => $feebase,
            'feesource' => $feesource,
            'status' => 'pending',
            // Qual APLICACAO cria esta cobranca. A avulsa sai por Preferencias,
            // com marketplace_fee na preferencia; o ciclo de assinatura sai por
            // Bricks, com application_fee no pagamento. Sem esta coluna nao se
            // sabe com que token consultar o pagamento de volta.
            'apptype' => $apptype,
            'subscriptionid' => $recorrencia ? $reference : null,
            'cycles' => $recorrencia ? 1 : 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        if ($recorrencia) {
            // A assinatura NAO comeca no Mercado Pago, e essa e a diferenca
            // estrutural para a venda avulsa. Para cobrar um ciclo e preciso um
            // cartao guardado, e o cartao so nasce token no navegador do aluno
            // - o servidor nao pode tokenizar: medido em 16/09/2026, o
            // /v1/card_tokens devolve 403 para token de acesso.
            //
            // Entao o aluno vai para uma pagina NOSSA, que monta os campos do
            // cartao conforme o card_capture, e e de la que sai a primeira
            // cobranca.
            return (new \moodle_url(
                '/payment/gateway/mercadopago/subscribe.php',
                ['ref' => $reference]
            ))->out(false);
        }

        $client = new mp_client($config['accesstoken']);

        $preference = $client->create_preference(self::build_preference_body(
            $amount,
            $currency,
            $reference,
            $fee,
            $CFG->wwwroot,
            !empty($appconfig->testmode)
        ));

        $record->preferenceid = (string) ($preference['id'] ?? '');
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        // O ambiente vem da configuracao do SITE, nao da conta. Havia uma
        // caixa por conta com o mesmo rotulo da de site, e desligar so uma
        // mandava o aluno para o sandbox segurando um token de producao - o
        // checkout abria em sandbox.mercadopago.com.br e o Pix nem aparecia,
        // sem nada na tela indicando o porque.
        $point = !empty($appconfig->testmode) && !empty($preference['sandbox_init_point'])
            ? $preference['sandbox_init_point']
            : ($preference['init_point'] ?? '');

        if (empty($point)) {
            throw new moodle_exception('errorinvalidresponse', 'paygw_mercadopago', '', 'preference');
        }

        return $point;
    }

    /**
     * A referencia externa, que e a chave de ligacao da transacao.
     *
     * O ID do pagamento so existe DEPOIS que o aluno paga, entao precisamos de
     * algo nosso que acompanhe a transacao desde a criacao.
     *
     * O PREFIXO distingue assinatura de venda avulsa. O webhook chega sem
     * contexto nenhum e precisa saber que linha procurar; duas familias de
     * referencia com o mesmo formato obrigariam a consultar o banco so para
     * descobrir de que tipo era - e a consulta erraria justamente no caso em
     * que a linha ainda nao existe.
     *
     * @param int $userid
     * @param int $itemid
     * @param bool $recurring
     * @return string
     */
    public static function build_reference(int $userid, int $itemid, bool $recurring = false): string {
        return ($recurring ? 'mdlsub-' : 'mdl-') . $userid . '-' . $itemid . '-' . random_string(12);
    }

    /**
     * Corpo da cobranca de UM ciclo de assinatura.
     *
     * Separado do resto para ser testavel, pela mesma razao do
     * build_preference_body(): e aqui que mora o application_fee, o numero que
     * move dinheiro na assinatura.
     *
     * O application_fee e HONRADO por este endpoint - medido em 16/09/2026,
     * pagamento 1352076103, aprovado, com a comissao em fee_details ao lado da
     * taxa do Mercado Pago. E o contraste com o preapproval, que aceita cinco
     * formatos do mesmo campo e descarta todos: por isso a assinatura com
     * comissao e uma sequencia de cobrancas, e nao um preapproval.
     *
     * Nao vai currency_id: no /v1/payments a moeda e a da conta que recebe, e
     * mandar outra nao converte nada - so produz recusa.
     *
     * @param float $amount Valor bruto do ciclo
     * @param string $currency Moeda da conta, para o descritivo
     * @param string $reference Referencia externa da linha deste ciclo
     * @param float $fee Comissao absoluta, ja calculada por fee_for()
     * @param array $card token, customerid e paymentmethod
     * @param string $payeremail E-mail do aluno
     * @param string $wwwroot Endereco do site
     * @param string $description O que esta sendo cobrado
     * @return array
     */
    public static function build_cycle_payment_body(
        float $amount,
        string $currency,
        string $reference,
        float $fee,
        array $card,
        string $payeremail,
        string $wwwroot,
        string $description
    ): array {
        $body = [
            'transaction_amount' => $amount,
            // Nunca parcelado, e nao ha configuracao para isso. Parcelar uma
            // cobranca que se repete todo mes empilha parcela sobre parcela, e
            // o aluno passa a dever mais do que assinou.
            'installments' => 1,
            'token' => (string) ($card['token'] ?? ''),
            'payment_method_id' => (string) ($card['paymentmethod'] ?? ''),
            // O pagador e o CLIENTE do vendedor, e e esse vinculo que alcanca o
            // cartao guardado: o cartao pertence a um cliente, e o cliente a
            // conta que recebe. Sem payer.type=customer o Mercado Pago trata
            // como compra avulsa e o cartao guardado nao entra.
            'payer' => [
                'type' => 'customer',
                'id' => (string) ($card['customerid'] ?? ''),
                'email' => $payeremail,
            ],
            'external_reference' => $reference,
            'description' => $description . ' (' . $currency . ')',
            // O webhook e a fonte da verdade, e nao a resposta desta chamada: a
            // cobranca pode ser aprovada depois, por analise de risco.
            'notification_url' => $wwwroot . '/payment/gateway/mercadopago/webhook.php',
        ];

        // Ausente, e nao zero. Mandar zero e pedir ao Mercado Pago que reparta
        // nada: uma empresa isenta viraria uma cobranca com repasse de valor
        // zero no extrato, o que polui a conciliacao sem significar coisa
        // alguma.
        if ($fee > 0) {
            $body['application_fee'] = $fee;
        }

        return $body;
    }

    /**
     * Comissao da plataforma, em moeda.
     *
     * O marketplace_fee e VALOR ABSOLUTO, e nao percentual - por isso o numero
     * sai daqui, calculado sobre o bruto, antes de a preferencia existir. A
     * taxa do proprio Mercado Pago so e conhecida depois do pagamento, entao
     * nao ha como cobrar percentual do liquido.
     *
     * O teto de 100% e a guarda contra configuracao errada: comissao maior que
     * o bruto produz uma preferencia que o Mercado Pago recusa, e a recusa
     * apareceria no checkout, diante do aluno. E a mesma trava do
     * asaas_client::build_split().
     *
     * @param float $amount Valor bruto
     * @param float $percent Percentual da comissao
     * @return float Zero quando nao ha comissao a cobrar
     */
    public static function fee_for(float $amount, float $percent): float {
        if ($amount <= 0 || $percent <= 0) {
            return 0.0;
        }

        return round($amount * (min($percent, 100.0) / 100), 2);
    }

    /**
     * Monta o corpo da preferencia do Checkout Pro.
     *
     * Separado do start_payment() para ser testavel: e aqui que mora o
     * marketplace_fee, o unico numero deste plugin que move dinheiro, e ele
     * ficou sem teste enquanto so existia dentro de um metodo que precisa de
     * banco, sessao e rede para rodar.
     *
     * O wwwroot entra por parametro, e nao por $CFG, para o teste poder afirmar
     * que as quatro URLs saem dele em vez de estarem escritas a mao.
     *
     * @param float $amount Valor bruto
     * @param string $currency Moeda ISO da conta do vendedor
     * @param string $reference Referencia externa, a chave de ligacao
     * @param float $fee Comissao absoluta, ja calculada por fee_for()
     * @param string $wwwroot Endereco do site
     * @param bool $testmode Modo de teste do SITE, nao da conta
     * @return array
     */
    public static function build_preference_body(
        float $amount,
        string $currency,
        string $reference,
        float $fee,
        string $wwwroot,
        bool $testmode
    ): array {
        $returnurl = $wwwroot . '/payment/gateway/mercadopago/return.php?ref=' . $reference;

        $body = [
            'items' => [[
                'title' => helper::get_cost_as_string($amount, $currency),
                'quantity' => 1,
                'unit_price' => $amount,
                'currency_id' => $currency,
            ]],
            'external_reference' => $reference,
            // O marketplace_fee e a comissao da plataforma. So funciona porque
            // o token usado na chamada veio do fluxo OAuth da NOSSA aplicacao.
            'marketplace_fee' => $fee,
            'back_urls' => [
                'success' => $returnurl,
                'pending' => $returnurl,
                'failure' => $returnurl,
            ],
            // O webhook e a fonte da verdade, nao a volta do navegador: o aluno
            // pode fechar a aba antes de voltar, e com Pix a aprovacao chega
            // depois do redirecionamento.
            'notification_url' => $wwwroot . '/payment/gateway/mercadopago/webhook.php',
            'auto_return' => 'approved',
        ];

        // Em teste, exige que o comprador entre na conta dele.
        //
        // Sem isto o Checkout Pro oferece pagar como visitante, e o pagador
        // fica sem identidade - o Mercado Pago recusa a compra com "uma das
        // partes e de teste", porque um visitante nao e usuario de teste. Nao
        // existe forma de o comprador se identificar como conta de teste sem
        // fazer login.
        //
        // Fica preso ao modo de teste de proposito. Em producao, wallet_purchase
        // elimina pagamento sem cadastro, boleto e dinheiro - ou seja, corta
        // conversao real para resolver um problema que so existe no sandbox.
        if ($testmode) {
            $body['purpose'] = 'wallet_purchase';
        }

        return $body;
    }

    /**
     * Processa uma notificacao do Mercado Pago.
     *
     * @param string $mppaymentid
     * @return bool Verdadeiro se a entrega aconteceu agora.
     */
    public static function process_notification(string $mppaymentid): bool {
        global $DB;

        // O token para consultar e o do VENDEDOR, e nao sabemos qual e antes de
        // achar a transacao. Por isso a consulta e feita em duas etapas: acha o
        // registro pela referencia externa que volta no pagamento.
        //
        // Numa primeira notificacao o mppaymentid ainda nao esta gravado, entao
        // e preciso perguntar ao Mercado Pago com QUALQUER token valido de uma
        // conta que tenha gateway configurado. Usamos o da transacao assim que
        // ela e localizada; para localiza-la, consultamos com cada conta ativa
        // ate uma responder - sao poucas, e so na primeira notificacao.
        $existing = $DB->get_record(self::TABLE, ['mppaymentid' => $mppaymentid]);
        if ($existing) {
            // O token e o da aplicacao que CRIOU esta linha. Consultar um
            // pagamento de assinatura com o token de Preferencias devolve 404,
            // e o sintoma seria "o webhook nao encontrou o pagamento".
            $payment = (new mp_client(self::get_gateway_config(
                (int) $existing->accountid,
                (string) ($existing->apptype ?? application::TYPE_PREFERENCES)
            )['accesstoken']))->get_payment($mppaymentid);
            $record = $existing;
        } else {
            [$payment, $record] = self::locate_transaction($mppaymentid);
        }

        if (!$record) {
            // Pagamento que nao e nosso, ou transacao ja removida.
            return false;
        }

        $status = (string) ($payment['status'] ?? 'pending');

        if ($record->status === 'approved' && $status === 'approved') {
            // Reenvio do Mercado Pago de algo ja entregue.
            return false;
        }

        $record->mppaymentid = $mppaymentid;
        $record->status = $status;
        $record->timemodified = time();

        if ($status === 'approved' && empty($record->paymentid)) {
            $record->paymentid = helper::save_payment(
                (int) $record->accountid,
                $record->component,
                $record->paymentarea,
                (int) $record->itemid,
                (int) $record->userid,
                (float) $record->amount,
                $record->currency,
                'mercadopago'
            );
            $DB->update_record(self::TABLE, $record);

            // Registra a venda na tabela neutra do marketplace. O gateway e o
            // unico que sabe quanto de comissao foi DE FATO enviado: recalcular
            // 25% do bruto no relatorio daria outro numero, porque a taxa do
            // gateway sai antes e cada um deduz numa ordem diferente.
            if (class_exists('\local_marketplace\api')) {
                \local_marketplace\api::record_sale(
                    $record->component,
                    (int) $record->paymentid,
                    (int) $record->itemid,
                    (float) $record->feeamount,
                    (string) $record->mppaymentid,
                    // Da LINHA, e nao de nova resolucao: entre a preferencia e
                    // o webhook a configuracao pode ter mudado, e a venda tem
                    // que registrar o que foi cobrado.
                    class_exists('\local_marketplace\commission')
                        ? new \local_marketplace\commission(
                            (float) $record->feepercent,
                            (string) $record->feebase,
                            (string) $record->feesource
                        )
                        : null
                );
            }

            helper::deliver_order(
                $record->component,
                $record->paymentarea,
                (int) $record->itemid,
                (int) $record->paymentid,
                (int) $record->userid
            );

            return true;
        }

        $DB->update_record(self::TABLE, $record);
        return false;
    }

    /**
     * Confere no Mercado Pago se uma transacao pendente ja foi paga.
     *
     * Usada pela tarefa de reconciliacao. A busca e pela external_reference,
     * porque uma linha pendente ainda nao tem mppaymentid: a preferencia
     * existe, o pagamento nao. Ver o docblock de task\reconcile.
     *
     * Nao decide nada sozinha - achando o pagamento, entrega o id para o
     * process_notification(), que e onde vivem a idempotencia e a entrega. Duas
     * portas para o mesmo caminho evitariam-se discordando com o tempo.
     *
     * @param \stdClass $record Linha com id, externalreference e accountid
     * @return bool Verdadeiro se a entrega aconteceu agora.
     */
    public static function reconcile_transaction(\stdClass $record): bool {
        $config = self::get_gateway_config(
            (int) $record->accountid,
            (string) ($record->apptype ?? application::TYPE_PREFERENCES)
        );
        $pagamentos = (new mp_client($config['accesstoken']))
            ->search_by_reference((string) $record->externalreference);

        foreach ($pagamentos as $pagamento) {
            $id = (string) ($pagamento['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (self::process_notification($id)) {
                return true;
            }
        }

        // Ninguem pagou ainda, ou o pagamento existe e nao esta aprovado. Nos
        // dois casos nao ha o que entregar, e a linha continua pendente para a
        // proxima passada.
        return false;
    }

    /**
     * Descobre a qual transacao um pagamento pertence.
     *
     * @param string $mppaymentid
     * @return array [dados do pagamento, registro da transacao|null]
     */
    protected static function locate_transaction(string $mppaymentid): array {
        global $DB;

        $gateways = $DB->get_records('payment_gateways', ['gateway' => 'mercadopago', 'enabled' => 1]);
        foreach ($gateways as $gw) {
            $config = @json_decode($gw->config, true);
            if (empty($config['accesstoken'])) {
                continue;
            }
            try {
                $payment = (new mp_client($config['accesstoken']))->get_payment($mppaymentid);
            } catch (\Throwable $e) {
                // Pagamento de outro vendedor: o token desta conta nao o
                // enxerga. Seguir para a proxima e o comportamento correto.
                continue;
            }
            $reference = (string) ($payment['external_reference'] ?? '');
            if ($reference === '') {
                continue;
            }
            $record = $DB->get_record(self::TABLE, ['externalreference' => $reference]);
            if ($record) {
                return [$payment, $record];
            }
        }

        return [[], null];
    }

    /**
     * O item e uma assinatura?
     *
     * Existe como metodo proprio, e nao como chamada direta, por duas razoes.
     * A primeira e a costura de teste, a mesma do make_curl(): sem ela, o ramo
     * de assinatura so seria exercitavel montando empresa, oferta e conta de
     * pagamento. A segunda e o class_exists(), que mantem o plugin servindo a
     * QUALQUER componente do core_payment - quem nao tem o marketplace
     * instalado simplesmente nunca entra no ramo recorrente.
     *
     * @param string $component
     * @param int $itemid
     * @return \stdClass|null days e maxcycles, ou null para venda avulsa
     */
    protected static function recurrence_for(string $component, int $itemid): ?\stdClass {
        if (!class_exists('\local_marketplace\api')) {
            return null;
        }

        return \local_marketplace\api::recurrence_for($component, $itemid);
    }

    /**
     * Configuracao do gateway numa conta.
     *
     * @param int $accountid
     * @return array
     */
    protected static function get_gateway_config(
        int $accountid,
        string $apptype = application::TYPE_PREFERENCES
    ): array {
        $gateway = \core_payment\account_gateway::get_record([
            'accountid' => $accountid,
            'gateway' => 'mercadopago',
        ]);
        if (!$gateway) {
            throw new moodle_exception('errornotlinked', 'paygw_mercadopago');
        }

        $config = $gateway->get_configuration();

        // O token e o DAQUELA aplicacao. Uma conta pode ter autorizado
        // Preferencias e nao Bricks: nesse caso ela vende avulso e nao vende
        // assinatura, e recusar aqui e melhor do que criar a linha e falhar na
        // pagina do cartao, com o aluno ja decidido a comprar.
        $token = (string) ($config[application::token_field($apptype, 'accesstoken')] ?? '');
        if ($token === '') {
            throw new moodle_exception('errornotlinked', 'paygw_mercadopago');
        }

        // Normaliza para que quem chama nao precise repetir o sufixo. A chave
        // sem sufixo e a de Preferencias, entao sobrescrever aqui e seguro: o
        // valor e o mesmo quando o apptype E preferencias.
        $config['accesstoken'] = $token;

        return $config;
    }
}
