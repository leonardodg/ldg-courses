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
     * Meios sem cobranca automatica - Pix e boleto nao deixam instrumento
     * guardado no Mercado Pago, ao contrario do cartao. Cada ciclo e uma
     * fatura NOVA, e o aluno precisa agir para pagar - ver
     * issue_invoice_cycle().
     *
     * @var string[]
     */
    const INVOICE_METHODS = ['pix', 'bolbradesco'];

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
        $recorrencia = self::recurrence_for($component, $itemid, $paymentarea);
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
            $terms = \local_marketplace\api::commission_terms_for($component, $itemid, $paymentarea);
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
     * Cobra o primeiro ciclo e guarda o cartao para os seguintes.
     *
     * E aqui que a assinatura passa a existir de verdade. O que chega do
     * navegador e UM card_token - nunca um numero de cartao -, e ele e de USO
     * UNICO.
     *
     * ESTE CICLO NAO RE-TOKENIZA O CARTAO GUARDADO - cobra com o MESMO token
     * que o navegador criou, o que ainda carrega o codigo de seguranca por
     * dentro. Medido em 16/09/2026, e contradiz o que este metodo fazia ate
     * aqui: POST /v1/card_tokens com so {"card_id": ...} (sem security_code)
     * devolve token com status "active", mas COBRAR com ele devolve "400
     * security_code_id can't be null" (causa 3031) - o "active" do token nao
     * significa "cobravel". O mesmo teste, com security_code incluido na
     * tokenizacao, cobra normalmente.
     *
     * ISSO NAO EXISTE nos ciclos 2 em diante - ver charge_due_cycles() e
     * build_next_cycle(): la nao ha aluno na tela para digitar o CVV de novo,
     * entao a mesma re-tokenizacao sem security_code que falha aqui falha lá
     * TAMBEM. Medido: mesmo depois de uma cobranca aprovada no cartao, uma
     * nova tokenizacao por card_id sem CVV continua "security_code_id can't
     * be null" - o Mercado Pago nao ativa cobranca sem CVV (ESC) sozinho por
     * historico de pagamento. Fica em aberto: pedir ESC ao suporte do
     * Mercado Pago, ou redesenhar o ciclo 2+ para pedir uma acao do aluno
     * (o mesmo modelo já aceito para Pix/boleto). Ver
     * docs/data-validation/mercadopago-assinatura.md.
     *
     * @param \stdClass $record Linha do ciclo 1, ja criada por start_payment()
     * @param string $cardtoken Token vindo do navegador, de uso unico
     * @param string $paymentmethod Bandeira, como o Mercado Pago a nomeia
     * @param string $issuerid Emissor, como o Mercado Pago o nomeia - ver save_card()
     * @return bool Verdadeiro quando a entrega aconteceu agora
     */
    public static function charge_first_cycle(
        \stdClass $record,
        string $cardtoken,
        string $paymentmethod,
        string $issuerid = ''
    ): bool {
        global $DB, $CFG;

        $config = self::get_gateway_config((int) $record->accountid, (string) $record->apptype);
        $client = new mp_client($config['accesstoken']);

        $user = \core_user::get_user((int) $record->userid, 'id, email, firstname, lastname', MUST_EXIST);

        // CADA CHAMADA DIZ O PROPRIO NOME AO FALHAR.
        //
        // Sao quatro no caminho, e o Mercado Pago devolve a mesma mensagem
        // generica em mais de uma. Sem o nome do passo, "400: invalid parameter
        // in payment method" nao diz se o problema foi criar o cliente, guardar
        // o cartao, tokenizar o guardado ou cobrar - e a linha no banco fica
        // igual nos tres primeiros casos, porque so e gravada depois. Custou
        // tres rodadas de prova real em 16/09/2026.
        $customerid = (string) self::step('customer', fn() => self::ensure_customer($client, $user->email));

        $card = (array) self::step(
            'savecard',
            fn() => $client->save_card($customerid, $cardtoken, $paymentmethod, $issuerid)
        );
        $cardid = (string) ($card['id'] ?? '');

        if ($cardid === '') {
            throw new moodle_exception('errorinvalidresponse', 'paygw_mercadopago', '', 'card');
        }

        // O MESMO token que guardou o cartao cobra o ciclo 1 - ver o porque no
        // docblock do metodo. Re-tokenizar por card_id aqui devolveria
        // "security_code_id can't be null", porque esse token novo nao carrega
        // CVV nenhum.
        $chargetoken = $cardtoken;

        $record->mpcustomerid = $customerid;
        // O id do cartao NO MERCADO PAGO. Nao e o cartao: e o endereco dele la.
        $record->mpcardid = $cardid;
        $record->paymentmethod = $paymentmethod;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        $corpo = self::build_cycle_payment_body(
            (float) $record->amount,
            (string) $record->currency,
            (string) $record->externalreference,
            (float) $record->feeamount,
            [
                'token' => $chargetoken,
                // COM o cliente, porque este token nasceu do cartao guardado
                // DELE. E o oposto do que vale para token recem-digitado - ver
                // build_payer(), que tem a medicao das duas formas.
                'customerid' => $customerid,
                'paymentmethod' => $paymentmethod,
            ],
            $user->email,
            $CFG->wwwroot,
            self::describe_subscription($record)
        );

        $payment = (array) self::step('payment', fn() => $client->create_payment($corpo));

        $record->mppaymentid = (string) ($payment['id'] ?? '');
        $record->status = (string) ($payment['status'] ?? 'pending');
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        if ($record->mppaymentid === '') {
            throw new moodle_exception('errorinvalidresponse', 'paygw_mercadopago', '', 'payment');
        }

        // A entrega passa pelo process_notification, e nao acontece aqui.
        // Cartao aprova na hora, mas o webhook chega de qualquer jeito - duas
        // portas para a mesma entrega acabariam discordando com o tempo, e a
        // idempotencia ja mora la.
        return self::process_notification($record->mppaymentid);
    }

    /**
     * Cobra o primeiro ciclo por Pix ou boleto - sem cartao, sem cliente.
     *
     * NAO GUARDA INSTRUMENTO NENHUM, porque nao ha o que guardar: Pix e
     * boleto nao tem token reaproveitavel no Mercado Pago. O que sobrevive
     * para o ciclo seguinte e o `payerinfo` (CPF e, no boleto, endereco),
     * gravado nesta linha e copiado adiante por build_next_cycle() - e um
     * FATO sobre o aluno, nao sobre a cobranca, entao nao muda de ciclo para
     * ciclo.
     *
     * O status volta SEMPRE pendente aqui: nem Pix nem boleto aprovam na
     * hora da criacao, so quando o aluno realmente paga - e e por isso que
     * process_notification() nao entrega nada ainda, so grava o estado. A
     * entrega espera o webhook.
     *
     * @param \stdClass $record Linha do ciclo 1, ja criada por start_payment()
     * @param string $paymentmethod pix|bolbradesco
     * @param array $payerinfo cpf, name e, no boleto, zipcode/street/number/neighborhood/city/state
     * @return bool Verdadeiro quando a entrega aconteceu agora (nunca, na pratica: fica para o webhook)
     */
    public static function charge_first_cycle_invoice(
        \stdClass $record,
        string $paymentmethod,
        array $payerinfo
    ): bool {
        global $DB, $CFG;

        $config = self::get_gateway_config((int) $record->accountid, (string) $record->apptype);
        $client = new mp_client($config['accesstoken']);
        $user = \core_user::get_user((int) $record->userid, 'id, email', MUST_EXIST);

        $corpo = self::build_invoice_payment_body(
            (float) $record->amount,
            (string) $record->currency,
            (string) $record->externalreference,
            (float) $record->feeamount,
            $paymentmethod,
            $payerinfo,
            $user->email,
            $CFG->wwwroot,
            self::describe_subscription($record)
        );

        $payment = (array) self::step('payment', fn() => $client->create_payment($corpo));

        $record->paymentmethod = $paymentmethod;
        $record->payerinfo = json_encode($payerinfo);
        $record->mppaymentid = (string) ($payment['id'] ?? '');
        $record->status = (string) ($payment['status'] ?? 'pending');
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        if ($record->mppaymentid === '') {
            throw new moodle_exception('errorinvalidresponse', 'paygw_mercadopago', '', 'payment');
        }

        return self::process_notification($record->mppaymentid);
    }

    /**
     * Troca Pix/boleto por cartao guardado - so PARA A FRENTE, sem cobrar
     * nada agora.
     *
     * NAO CRIA PAGAMENTO. Guarda o cartao (mesmo caminho de save_card() do
     * ciclo 1) e atualiza a linha mais recente com o instrumento novo -
     * exatamente os campos que build_next_cycle() copia adiante
     * (mpcustomerid, mpcardid, paymentmethod). O ciclo atual, se ja foi
     * pago por Pix ou boleto, continua registrado como foi pago: trocar a
     * forma de pagamento nao reescreve o passado, so decide como o ciclo
     * SEGUINTE vai ser cobrado.
     *
     * `payerinfo` fica limpo: sem instrumento de Pix/boleto para reaproveitar,
     * o campo perde a razao de existir - e mante-lo so ia confundir uma
     * leitura futura que achasse que a assinatura ainda e por fatura.
     *
     * @param \stdClass $record Linha mais recente da assinatura
     * @param string $cardtoken Token vindo do navegador, de uso unico
     * @param string $paymentmethod Bandeira, como o Mercado Pago a nomeia
     * @param string $issuerid Emissor, como o Mercado Pago o nomeia - ver save_card()
     * @return void
     */
    public static function switch_to_card(
        \stdClass $record,
        string $cardtoken,
        string $paymentmethod,
        string $issuerid = ''
    ): void {
        global $DB;

        $config = self::get_gateway_config((int) $record->accountid, (string) $record->apptype);
        $client = new mp_client($config['accesstoken']);
        $user = \core_user::get_user((int) $record->userid, 'id, email', MUST_EXIST);

        $customerid = (string) self::step('customer', fn() => self::ensure_customer($client, $user->email));
        $card = (array) self::step(
            'savecard',
            fn() => $client->save_card($customerid, $cardtoken, $paymentmethod, $issuerid)
        );
        $cardid = (string) ($card['id'] ?? '');

        if ($cardid === '') {
            throw new moodle_exception('errorinvalidresponse', 'paygw_mercadopago', '', 'card');
        }

        $record->mpcustomerid = $customerid;
        $record->mpcardid = $cardid;
        $record->paymentmethod = $paymentmethod;
        $record->payerinfo = null;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Roda um passo do fluxo dizendo o NOME dele quando falha.
     *
     * O Mercado Pago repete a mesma mensagem generica em endpoints diferentes,
     * e as tres primeiras chamadas deste fluxo deixam a linha do banco
     * identica quando falham - ela so e gravada depois. O resultado e um erro
     * que nao localiza nada.
     *
     * O retorno e MIXED, e nao array: os passos devolvem coisas diferentes -
     * o cliente e uma string com o id, os outros sao a resposta da API. Um
     * "array" aqui derrubou a primeira tentativa com TypeError antes mesmo de
     * o Mercado Pago ser chamado.
     *
     * @param string $step Nome curto do passo
     * @param callable $call
     * @return mixed O que o passo devolver
     */
    protected static function step(string $step, callable $call) {
        try {
            return $call();
        } catch (moodle_exception $e) {
            throw new moodle_exception(
                'errorapistep',
                'paygw_mercadopago',
                '',
                (object) ['step' => $step, 'message' => $e->getMessage()]
            );
        }
    }

    /**
     * O cliente do aluno na conta do vendedor, criando se preciso.
     *
     * O Mercado Pago recusa dois clientes com o mesmo e-mail na mesma conta,
     * entao "criar" e "procurar" sao o mesmo passo visto de dois lados. Tratar
     * a recusa como erro faria a segunda assinatura do mesmo aluno falhar.
     *
     * @param mp_client $client
     * @param string $email
     * @return string
     */
    protected static function ensure_customer(mp_client $client, string $email): string {
        try {
            $customer = $client->create_customer($email);
            $id = (string) ($customer['id'] ?? '');
            if ($id !== '') {
                return $id;
            }
        } catch (moodle_exception $e) {
            // Ja existe, ou a criacao falhou por outro motivo. A busca decide.
            $e->getMessage();
        }

        $encontrados = $client->search_customer($email);
        $id = (string) ($encontrados[0]['id'] ?? '');
        if ($id === '') {
            throw new moodle_exception('errorcustomer', 'paygw_mercadopago');
        }

        return $id;
    }

    /**
     * Texto que descreve a assinatura no extrato do aluno.
     *
     * @param \stdClass $record
     * @return string
     */
    protected static function describe_subscription(\stdClass $record): string {
        return get_string('subscriptioncycle', 'paygw_mercadopago', (int) $record->cycles);
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
            'payer' => self::build_payer($card, $payeremail),
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

        // A bandeira segue a MESMA regra, e por uma razao medida: o token de
        // cartao NAO devolve payment_method_id - o campo volta nulo. Mandar o
        // que se tem, que e vazio, devolve 400 Invalid payment_method_id;
        // omitir faz o Mercado Pago inferir do proprio token e aprovar.
        //
        // Medido em 16/09/2026, na primeira compra real desta rodada.
        $paymentmethod = (string) ($card['paymentmethod'] ?? '');
        if ($paymentmethod !== '') {
            $body['payment_method_id'] = $paymentmethod;
        }

        return $body;
    }

    /**
     * Quem paga, e a forma MUDA conforme o cartao ser novo ou ja guardado.
     *
     * Medido em 16/09/2026, mesma conta, mesmo cartao, mesma chamada, so
     * variando o payer:
     *
     *   payer: {email}                      -> approved
     *   payer: {type: customer, id, email}  -> REJECTED cc_rejected_other_reason
     *   payer: {id, email}                  -> REJECTED cc_rejected_other_reason
     *
     * Ou seja: mandar o cliente junto de um token RECEM-CRIADO faz a cobranca
     * ser recusada, e a recusa chega disfarcada de problema com o cartao. O
     * vinculo com o cliente so vale quando o token nasceu de um cartao que ja
     * pertence a ele.
     *
     * Dai as duas formas: o ciclo 1 cobra um cartao novo e manda so o e-mail; o
     * cartao e guardado no cliente em outra chamada. Do ciclo 2 em diante o
     * token vem do card_id, e ai o cliente entra.
     *
     * @param array $card token, customerid e paymentmethod
     * @param string $payeremail
     * @return array
     */
    protected static function build_payer(array $card, string $payeremail): array {
        $customerid = (string) ($card['customerid'] ?? '');

        if ($customerid === '') {
            return ['email' => $payeremail];
        }

        return [
            'type' => 'customer',
            'id' => $customerid,
            'email' => $payeremail,
        ];
    }

    /**
     * Quem paga um Pix ou boleto - sem cliente guardado, porque nao ha cartao
     * para vincular a ele.
     *
     * O CPF e OBRIGATORIO nos dois. O endereco so entra no boleto: medido em
     * 16/09/2026, criar um boleto sem `address` completo (CEP, rua, numero,
     * bairro, cidade, UF) devolve 400 pedindo exatamente esses seis campos -
     * o Pix nunca pediu nenhum deles.
     *
     * @param array $payerinfo cpf e, no boleto, zipcode/street/number/neighborhood/city/state
     * @param string $payeremail
     * @param bool $comendereco O boleto exige; o Pix nao
     * @return array
     */
    protected static function build_invoice_payer(array $payerinfo, string $payeremail, bool $comendereco): array {
        $payer = [
            'email' => $payeremail,
            'identification' => [
                'type' => 'CPF',
                'number' => (string) ($payerinfo['cpf'] ?? ''),
            ],
        ];

        if (!empty($payerinfo['name'])) {
            $partes = explode(' ', trim((string) $payerinfo['name']), 2);
            $payer['first_name'] = $partes[0];
            $payer['last_name'] = $partes[1] ?? $partes[0];
        }

        if ($comendereco) {
            $payer['address'] = [
                'zip_code' => (string) ($payerinfo['zipcode'] ?? ''),
                'street_name' => (string) ($payerinfo['street'] ?? ''),
                'street_number' => (string) ($payerinfo['number'] ?? ''),
                'neighborhood' => (string) ($payerinfo['neighborhood'] ?? ''),
                'city' => (string) ($payerinfo['city'] ?? ''),
                'federal_unit' => (string) ($payerinfo['state'] ?? ''),
            ];
        }

        return $payer;
    }

    /**
     * Corpo de um pagamento por Pix ou boleto - sem token, sem cartao.
     *
     * @param float $amount
     * @param string $currency
     * @param string $reference
     * @param float $fee
     * @param string $paymentmethod pix|bolbradesco
     * @param array $payerinfo Ver build_invoice_payer()
     * @param string $payeremail
     * @param string $wwwroot
     * @param string $description
     * @return array
     */
    public static function build_invoice_payment_body(
        float $amount,
        string $currency,
        string $reference,
        float $fee,
        string $paymentmethod,
        array $payerinfo,
        string $payeremail,
        string $wwwroot,
        string $description
    ): array {
        $body = [
            'transaction_amount' => $amount,
            'payment_method_id' => $paymentmethod,
            'payer' => self::build_invoice_payer(
                $payerinfo,
                $payeremail,
                $paymentmethod === 'bolbradesco'
            ),
            'external_reference' => $reference,
            'description' => $description . ' (' . $currency . ')',
            'notification_url' => $wwwroot . '/payment/gateway/mercadopago/webhook.php',
        ];

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
                        : null,
                    (string) $record->paymentarea
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
     * Este ciclo esta vencido e deve ser cobrado?
     *
     * Pura de proposito, e com o "agora" por parametro: uma decisao que so
     * fosse exercitavel esperando trinta dias nao seria exercitada nunca.
     *
     * Sao cinco condicoes, e cada uma existe por um desfecho ruim concreto:
     *
     *   assinatura cancelada  - cobrar quem pediu para sair e tirar dinheiro
     *                           de quem mandou parar;
     *   ciclo anterior nao pago - cobrar o ciclo 3 com o 2 em aberto empilha
     *                           divida no cartao de quem ja esta com problema;
     *   sem cartao guardado   - nao ha o que cobrar sozinho, e tentar geraria
     *                           uma linha pendente por ciclo, para sempre;
     *   teto de ciclos        - a assinatura de 12 meses cobraria para sempre,
     *                           e o aluno so veria no extrato do decimo terceiro;
     *   intervalo             - o resto.
     *
     * @param \stdClass $record Linha do ultimo ciclo
     * @param int $days Intervalo de cobranca, do marketplace
     * @param int $maxcycles Teto, ou zero para sem teto
     * @param int $now
     * @return bool
     */
    public static function is_due(\stdClass $record, int $days, int $maxcycles, int $now): bool {
        if (!self::due_by_calendar($record, $days, $maxcycles, $now)) {
            return false;
        }

        if (empty($record->mpcardid) || empty($record->mpcustomerid)) {
            return false;
        }

        return true;
    }

    /**
     * A fatura por Pix ou boleto do ciclo seguinte deve ser emitida?
     *
     * Mesmo calendario do cartao - cancelada, ciclo anterior nao pago, teto,
     * intervalo -, mas a guarda de instrumento e OUTRA: aqui nao ha card_id
     * nenhum para exigir, e sim a bandeira em si. Cartao guardado numa linha
     * de Pix seria estado impossivel, mas testar por ele em vez de pela
     * bandeira deixaria a intencao dependendo de um acidente de dados.
     *
     * @param \stdClass $record Linha do ultimo ciclo
     * @param int $days Intervalo de cobranca, do marketplace
     * @param int $maxcycles Teto, ou zero para sem teto
     * @param int $now
     * @return bool
     */
    public static function is_due_for_invoice(\stdClass $record, int $days, int $maxcycles, int $now): bool {
        if (!self::due_by_calendar($record, $days, $maxcycles, $now)) {
            return false;
        }

        return in_array((string) $record->paymentmethod, self::INVOICE_METHODS, true);
    }

    /**
     * O calendario da cobranca - cancelamento, ciclo anterior, teto e
     * intervalo -, comum ao cartao e a fatura por Pix/boleto. O que muda
     * entre os dois e SO a guarda de instrumento, em is_due() e
     * is_due_for_invoice().
     *
     * @param \stdClass $record
     * @param int $days
     * @param int $maxcycles
     * @param int $now
     * @return bool
     */
    protected static function due_by_calendar(\stdClass $record, int $days, int $maxcycles, int $now): bool {
        if ((string) $record->subscriptionstatus === 'cancelled') {
            return false;
        }

        if (strtolower((string) $record->status) !== 'approved') {
            return false;
        }

        if ($maxcycles > 0 && (int) $record->cycles >= $maxcycles) {
            return false;
        }

        if ($days <= 0) {
            return false;
        }

        return $now >= ((int) $record->timecreated + ($days * DAYSECS));
    }

    /**
     * Falta pouco para o proximo ciclo de Pix/boleto vencer - hora de avisar?
     *
     * SO PIX E BOLETO: cartao cobra sozinho, e "sua cobranca automatica esta
     * chegando" nao muda a acao de ninguem, porque nao ha acao nenhuma para o
     * aluno tomar. Aqui, sim: sem o aviso, o aluno so descobre que precisa
     * pagar quando a fatura ja existir - e no boleto isso pode ser tarde
     * demais para compensar o prazo de compensacao bancaria.
     *
     * `reminderat` e o que impede mandar o MESMO aviso a cada execucao diaria
     * da tarefa, enquanto a linha estiver dentro da janela.
     *
     * @param \stdClass $record Linha do ultimo ciclo pago
     * @param int $days Intervalo de cobranca, do marketplace
     * @param int $reminderdays Quantos dias antes avisar
     * @param int $maxcycles Teto, ou zero para sem teto
     * @param int $now
     * @return bool
     */
    public static function needs_reminder(
        \stdClass $record,
        int $days,
        int $reminderdays,
        int $maxcycles,
        int $now
    ): bool {
        if ((string) $record->subscriptionstatus === 'cancelled') {
            return false;
        }

        if (strtolower((string) $record->status) !== 'approved') {
            return false;
        }

        if (!in_array((string) $record->paymentmethod, self::INVOICE_METHODS, true)) {
            return false;
        }

        if (!empty($record->reminderat)) {
            return false;
        }

        if ($maxcycles > 0 && (int) $record->cycles >= $maxcycles) {
            return false;
        }

        if ($days <= 0 || $reminderdays <= 0) {
            return false;
        }

        $vencimento = (int) $record->timecreated + ($days * DAYSECS);
        $iniciojanela = $vencimento - ($reminderdays * DAYSECS);

        return $now >= $iniciojanela && $now < $vencimento;
    }

    /**
     * Manda o lembrete e marca a linha, para nao mandar de novo amanha.
     *
     * @param \stdClass $record
     * @return void
     */
    public static function send_reminder(\stdClass $record): void {
        global $DB;

        $user = \core_user::get_user((int) $record->userid, '*', MUST_EXIST);
        $valor = helper::get_cost_as_string((float) $record->amount, (string) $record->currency);

        $mensagem = new \core\message\message();
        $mensagem->component = 'paygw_mercadopago';
        $mensagem->name = 'reminderupcoming';
        $mensagem->userfrom = \core_user::get_noreply_user();
        $mensagem->userto = $user;
        $mensagem->subject = get_string('reminderupcoming_subject', 'paygw_mercadopago');
        $mensagem->fullmessage = get_string('reminderupcoming_body', 'paygw_mercadopago', $valor);
        $mensagem->fullmessageformat = FORMAT_PLAIN;
        $mensagem->fullmessagehtml = '<p>' . $mensagem->fullmessage . '</p>';
        $mensagem->smallmessage = $mensagem->subject;
        $mensagem->notification = 1;
        $mensagem->contexturl = (new \moodle_url('/local/marketplace/mysubscriptions.php'))->out(false);
        $mensagem->contexturlname = get_string('reminderupcoming_subject', 'paygw_mercadopago');

        message_send($mensagem);

        $DB->set_field(self::TABLE, 'reminderat', time(), ['id' => $record->id]);
    }

    /**
     * Monta a linha do proximo ciclo a partir da anterior.
     *
     * COPIA OS TERMOS, e nunca os resolve de novo. Entre um ciclo e outro a
     * comissao da empresa pode ter mudado, e o ciclo seguinte tem que cobrar o
     * que foi combinado - nao o que passou a valer. E o ADR-0007 aplicado ao
     * tempo: mudar a configuracao nao pode reescrever o passado nem o contrato
     * em curso.
     *
     * O que NAO se copia e o que pertence a cobranca anterior: referencia,
     * id do pagamento no Mercado Pago e o registro em {payments}. Cada ciclo
     * precisa da propria referencia, senao o webhook nao sabe qual linha e.
     *
     * @param \stdClass $previous
     * @return \stdClass Ainda nao gravada
     */
    public static function build_next_cycle(\stdClass $previous): \stdClass {
        $agora = time();

        return (object) [
            'preferenceid' => '',
            'externalreference' => self::build_reference(
                (int) $previous->userid,
                (int) $previous->itemid,
                true
            ),
            'mppaymentid' => null,
            'component' => $previous->component,
            'paymentarea' => $previous->paymentarea,
            'itemid' => $previous->itemid,
            'userid' => $previous->userid,
            'accountid' => $previous->accountid,
            'amount' => $previous->amount,
            'currency' => $previous->currency,
            'feeamount' => $previous->feeamount,
            'feepercent' => $previous->feepercent,
            'feebase' => $previous->feebase,
            'feesource' => $previous->feesource,
            'status' => 'pending',
            'paymentid' => null,
            'apptype' => $previous->apptype,
            'subscriptionid' => $previous->subscriptionid,
            'cycles' => (int) $previous->cycles + 1,
            'mpcustomerid' => $previous->mpcustomerid,
            'mpcardid' => $previous->mpcardid,
            'paymentmethod' => $previous->paymentmethod,
            // Fato sobre o ALUNO, nao sobre o ciclo anterior - so existe
            // porque Pix e boleto nao deixam instrumento guardado, ao
            // contrario do card_id. Ver o comentario do campo no install.xml.
            'payerinfo' => $previous->payerinfo ?? null,
            'subscriptionstatus' => 'active',
            'timecreated' => $agora,
            'timemodified' => $agora,
        ];
    }

    /**
     * Cobra um ciclo no cartao ja guardado.
     *
     * O token nasce do card_id, sem codigo de seguranca - e o mesmo caminho que
     * o ciclo 1 ja exercitou, de proposito: uma falha aqui aparece na primeira
     * compra, e nao um mes depois num cron silencioso.
     *
     * @param \stdClass $previous Linha do ultimo ciclo pago
     * @return bool Verdadeiro quando a entrega aconteceu agora
     */
    public static function charge_cycle(\stdClass $previous): bool {
        global $DB, $CFG;

        $config = self::get_gateway_config((int) $previous->accountid, (string) $previous->apptype);
        $client = new mp_client($config['accesstoken']);
        $user = \core_user::get_user((int) $previous->userid, 'id, email', MUST_EXIST);

        $record = self::build_next_cycle($previous);
        $record->id = $DB->insert_record(self::TABLE, $record);

        $chargetoken = (string) ($client->tokenize_saved_card((string) $record->mpcardid)['id'] ?? '');

        $payment = $client->create_payment(self::build_cycle_payment_body(
            (float) $record->amount,
            (string) $record->currency,
            (string) $record->externalreference,
            (float) $record->feeamount,
            [
                'token' => $chargetoken,
                'customerid' => (string) $record->mpcustomerid,
                'paymentmethod' => (string) $record->paymentmethod,
            ],
            $user->email,
            $CFG->wwwroot,
            self::describe_subscription($record)
        ));

        $record->mppaymentid = (string) ($payment['id'] ?? '');
        $record->status = (string) ($payment['status'] ?? 'pending');
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        if ($record->mppaymentid === '') {
            return false;
        }

        return self::process_notification($record->mppaymentid);
    }

    /**
     * Cria a linha do proximo ciclo no cartao, SEM COBRAR - e AVISA o aluno
     * para confirmar com o CVV.
     *
     * charge_cycle() (acima) continua no codigo, mas nao e mais chamada pela
     * tarefa agendada: medido em 16/09/2026, cobrar com o token do card_id
     * sem CVV devolve "400 security_code_id can't be null", e isso nao muda
     * por historico de pagamento - a conta nao tem ESC habilitado, e ESC nao
     * liga sozinho. Sem o recurso, nao ha cobranca de cartao verdadeiramente
     * automatica nesta conta, e fingir que ha so trocaria uma falha visivel
     * (o aluno confirma) por uma silenciosa (o cron falha e ninguem sabe).
     *
     * O AVISO PEDE SO O CVV, e nao o cartao inteiro de novo - e a diferenca
     * de fundo com Pix/boleto, que nao tem instrumento guardado nenhum. Ver
     * confirm_card_cycle().
     *
     * @param \stdClass $previous Linha do ultimo ciclo pago
     * @return void
     */
    public static function issue_card_cycle(\stdClass $previous): void {
        global $DB;

        $record = self::build_next_cycle($previous);
        $record->id = $DB->insert_record(self::TABLE, $record);

        self::notify_invoice_due(
            $record,
            (new \moodle_url(
                '/payment/gateway/mercadopago/confirm_cycle.php',
                ['ref' => (string) $record->externalreference]
            ))->out(false)
        );
    }

    /**
     * O aluno confirmou o ciclo com o CVV - cobra de verdade.
     *
     * O TOKEN JA CHEGA PRONTO DO NAVEGADOR, e isso nao e um detalhe: medido
     * em 17/09/2026, `mp.createCardToken({cardId, securityCode})` tokeniza
     * pela PUBLIC KEY, no navegador, sem o CVV passar pelo nosso servidor -
     * o mesmo SDK que ja tokeniza o cartao novo em subscribe.php faz isto
     * para um cartao ja guardado, so trocando o corpo por
     * `{card_id, security_code}`. Cobrar o token resultante funciona
     * (`in_process`, testado contra a conta real) exatamente como cobrar o
     * token de um cartao novo.
     *
     * NAO HA CVV NENHUM AQUI: esta funcao so recebe o token ja pronto,
     * porque o CVV nunca devia chegar ao PHP para comecar. E a mesma
     * fronteira PCI de subscribe.php, so que aqui a fronteira e o navegador
     * inteiro, e nao so "nao gravar depois de receber".
     *
     * @param \stdClass $record Linha do ciclo, ja criada por issue_card_cycle()
     * @param string $chargetoken Token do card_id+CVV, ja tokenizado no navegador
     * @return bool Verdadeiro quando a entrega aconteceu agora
     */
    public static function confirm_card_cycle(\stdClass $record, string $chargetoken): bool {
        global $DB, $CFG;

        $config = self::get_gateway_config((int) $record->accountid, (string) $record->apptype);
        $client = new mp_client($config['accesstoken']);
        $user = \core_user::get_user((int) $record->userid, 'id, email', MUST_EXIST);

        $payment = (array) self::step('payment', fn() => $client->create_payment(self::build_cycle_payment_body(
            (float) $record->amount,
            (string) $record->currency,
            (string) $record->externalreference,
            (float) $record->feeamount,
            [
                'token' => $chargetoken,
                'customerid' => (string) $record->mpcustomerid,
                'paymentmethod' => (string) $record->paymentmethod,
            ],
            $user->email,
            $CFG->wwwroot,
            self::describe_subscription($record)
        )));

        $record->mppaymentid = (string) ($payment['id'] ?? '');
        $record->status = (string) ($payment['status'] ?? 'pending');
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        if ($record->mppaymentid === '') {
            throw new moodle_exception('errorinvalidresponse', 'paygw_mercadopago', '', 'payment');
        }

        return self::process_notification($record->mppaymentid);
    }

    /**
     * Faz sentido pedir o CVV para confirmar este ciclo?
     *
     * SO PARA CARTAO, ciclo ja criado (por issue_card_cycle()) e AINDA NAO
     * cobrado - confirmar de novo um ciclo ja pago cobraria duas vezes.
     *
     * @param \stdClass $record
     * @return bool
     */
    public static function can_confirm_card_cycle(\stdClass $record): bool {
        if (empty($record->mpcardid) || empty($record->mpcustomerid)) {
            return false;
        }

        if (in_array((string) $record->paymentmethod, self::INVOICE_METHODS, true)) {
            return false;
        }

        return strtolower((string) $record->status) !== 'approved' && empty($record->mppaymentid);
    }

    /**
     * Emite a fatura do ciclo seguinte por Pix ou boleto, e AVISA o aluno.
     *
     * O aviso e a diferenca de fundo com charge_cycle(): la o cartao cobra
     * sozinho, e o aluno so fica sabendo se recusar. Aqui a fatura existe e
     * NINGUEM sabe, porque nao ha aluno na tela - sem mensagem, ela fica
     * esperando pagamento que nunca vem, e o sintoma e "o aluno perdeu o
     * acesso sem aviso nenhum".
     *
     * O `payerinfo` vem da linha anterior, copiado por build_next_cycle():
     * e o CPF (e, no boleto, o endereco) que o aluno digitou no ciclo 1, e
     * que nao muda de ciclo para ciclo.
     *
     * @param \stdClass $previous Linha do ultimo ciclo pago
     * @return bool Verdadeiro quando a entrega aconteceu agora (nunca, na pratica)
     */
    public static function issue_invoice_cycle(\stdClass $previous): bool {
        global $DB, $CFG;

        $config = self::get_gateway_config((int) $previous->accountid, (string) $previous->apptype);
        $client = new mp_client($config['accesstoken']);
        $user = \core_user::get_user((int) $previous->userid, 'id, email', MUST_EXIST);

        $record = self::build_next_cycle($previous);
        $record->id = $DB->insert_record(self::TABLE, $record);

        $payerinfo = json_decode((string) ($record->payerinfo ?? ''), true) ?: [];

        $payment = $client->create_payment(self::build_invoice_payment_body(
            (float) $record->amount,
            (string) $record->currency,
            (string) $record->externalreference,
            (float) $record->feeamount,
            (string) $record->paymentmethod,
            $payerinfo,
            $user->email,
            $CFG->wwwroot,
            self::describe_subscription($record)
        ));

        $record->mppaymentid = (string) ($payment['id'] ?? '');
        $record->status = (string) ($payment['status'] ?? 'pending');
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        if ($record->mppaymentid === '') {
            return false;
        }

        self::notify_invoice_due(
            $record,
            (new \moodle_url('/payment/gateway/mercadopago/return.php', ['ref' => $record->externalreference]))->out(false)
        );

        return self::process_notification($record->mppaymentid);
    }

    /**
     * Avisa o aluno de que ha uma fatura nova para pagar.
     *
     * So existe porque Pix e boleto nao cobram sozinhos: no cartao, quem
     * precisa saber do resultado e quem esta olhando o extrato, nao a tela
     * do Moodle.
     *
     * @param \stdClass $record Linha da fatura recem-criada
     * @param string $payurl
     * @return void
     */
    protected static function notify_invoice_due(\stdClass $record, string $payurl): void {
        $user = \core_user::get_user((int) $record->userid, '*', MUST_EXIST);
        $valor = helper::get_cost_as_string((float) $record->amount, (string) $record->currency);

        $mensagem = new \core\message\message();
        $mensagem->component = 'paygw_mercadopago';
        $mensagem->name = 'invoicedue';
        $mensagem->userfrom = \core_user::get_noreply_user();
        $mensagem->userto = $user;
        $mensagem->subject = get_string('invoicedue_subject', 'paygw_mercadopago');
        $mensagem->fullmessage = get_string('invoicedue_body', 'paygw_mercadopago', (object) [
            'amount' => $valor,
            'url' => $payurl,
        ]);
        $mensagem->fullmessageformat = FORMAT_PLAIN;
        $mensagem->fullmessagehtml = '<p>' . $mensagem->fullmessage . '</p>';
        $mensagem->smallmessage = $mensagem->subject;
        $mensagem->notification = 1;
        $mensagem->contexturl = $payurl;
        $mensagem->contexturlname = get_string('invoicedue_subject', 'paygw_mercadopago');

        message_send($mensagem);
    }

    /**
     * O QR code do Pix ou a linha digitavel do boleto, para a TELA.
     *
     * NAO fica gravado na linha - so consultado sob demanda, direto no
     * Mercado Pago. Um QR code de Pix vence (`date_of_expiration`, medido em
     * 16/09/2026: proximo dia), e guardar um valor que pode ja ter vencido
     * seria pior do que nao ter nada.
     *
     * @param \stdClass $record
     * @return array|null Vazio quando nao e uma fatura por Pix/boleto, ou ainda nao tem pagamento
     */
    public static function invoice_details(\stdClass $record): ?array {
        if (!in_array((string) $record->paymentmethod, self::INVOICE_METHODS, true)) {
            return null;
        }

        if (empty($record->mppaymentid)) {
            return null;
        }

        $config = self::get_gateway_config((int) $record->accountid, (string) $record->apptype);
        $client = new mp_client($config['accesstoken']);
        $payment = $client->get_payment((string) $record->mppaymentid);

        $dadostransacao = (array) ($payment['point_of_interaction']['transaction_data'] ?? []);

        if ((string) $record->paymentmethod === 'pix') {
            return [
                'pix' => true,
                'qrcode' => (string) ($dadostransacao['qr_code'] ?? ''),
                'qrcodeimage' => !empty($dadostransacao['qr_code_base64'])
                    ? 'data:image/png;base64,' . $dadostransacao['qr_code_base64']
                    : '',
                'ticketurl' => (string) ($dadostransacao['ticket_url'] ?? ''),
            ];
        }

        return [
            'boleto' => true,
            'barcode' => (string) ($payment['barcode']['content'] ?? ''),
            'ticketurl' => (string) ($payment['transaction_details']['external_resource_url'] ?? ''),
        ];
    }

    /**
     * A linha mais recente de cada assinatura, candidata a cobranca.
     *
     * Uma por assinatura: sao varias linhas por aluno, e so a ultima diz o
     * estado atual.
     *
     * @return \stdClass[]
     */
    public static function latest_cycles(): array {
        global $DB;

        $sql = "SELECT p.*
                  FROM {" . self::TABLE . "} p
                  JOIN (SELECT subscriptionid, MAX(id) AS maxid
                          FROM {" . self::TABLE . "}
                         WHERE subscriptionid IS NOT NULL AND subscriptionid <> ''
                      GROUP BY subscriptionid) ultima
                    ON ultima.maxid = p.id
              ORDER BY p.id";

        return $DB->get_records_sql($sql);
    }

    /**
     * Motivo pelo qual esta venda nao pode ser estornada.
     *
     * Serve a TELA: e com isto que o botao some, em vez de aparecer e falhar na
     * hora do clique, diante de quem esta resolvendo um problema de dinheiro
     * com um aluno. Devolve vazio quando o estorno e possivel.
     *
     * @param \stdClass $record
     * @return string Chave de string do erro, ou vazio
     */
    public static function refund_blocker(\stdClass $record): string {
        global $DB;

        $status = strtolower((string) $record->status);

        if ($status === 'refunded') {
            return 'errorrefundalready';
        }

        if ($status !== 'approved') {
            return 'errorrefundnotpaid';
        }

        if (empty($record->subscriptionid)) {
            return '';
        }

        // CICLO DO MEIO NAO SE ESTORNA, e a razao e a mesma dos outros dois
        // gateways: estorno parcial NAO reduz o split. O que ja foi repassado a
        // plataforma continua repassado, entao devolver o bruto ao aluno
        // deixaria o vendedor no prejuizo da comissao - sem que nenhuma tela
        // avisasse.
        //
        // Comparar por id basta: as linhas de uma assinatura nascem em ordem.
        $anteriores = $DB->get_records_select(
            self::TABLE,
            'subscriptionid = :sub AND id < :id AND paymentid IS NOT NULL',
            ['sub' => $record->subscriptionid, 'id' => (int) $record->id],
            '',
            'id',
            0,
            1
        );

        return $anteriores ? 'errorrefundnotfirstcycle' : '';
    }

    /**
     * Estorna uma venda no Mercado Pago.
     *
     * @param \stdClass $record
     * @return bool
     */
    public static function refund(\stdClass $record): bool {
        global $DB;

        if (self::refund_blocker($record) !== '') {
            return false;
        }

        $config = self::get_gateway_config((int) $record->accountid, (string) $record->apptype);
        (new mp_client($config['accesstoken']))->refund_payment((string) $record->mppaymentid);

        $record->status = 'refunded';
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        return true;
    }

    /**
     * Para de cobrar uma assinatura.
     *
     * AQUI NAO HA NADA A CANCELAR NO MERCADO PAGO, e essa e a diferenca de
     * fundo para o Asaas. La existe um objeto de assinatura que cobra sozinho,
     * e cancelar e pedir ao gateway que pare; aqui quem dispara a cobranca de
     * cada ciclo somos nos, entao cancelar e parar de disparar.
     *
     * O cartao guardado NAO e apagado. Ele nao cobra nada sozinho, e apagar
     * obrigaria o aluno a digitar tudo de novo se mudasse de ideia - custo real
     * para resolver um risco que nao existe.
     *
     * @param string $subscriptionid
     * @return bool Verdadeiro quando havia assinatura ativa e ela foi parada
     */
    public static function cancel_subscription(string $subscriptionid): bool {
        global $DB;

        if ($subscriptionid === '') {
            return false;
        }

        $ativas = $DB->count_records_select(
            self::TABLE,
            'subscriptionid = :sub AND subscriptionstatus <> :cancelada',
            ['sub' => $subscriptionid, 'cancelada' => 'cancelled']
        );

        // Devolver verdadeiro sem ter parado nada faria o nucleo relatar um
        // cancelamento que nao houve.
        if (!$ativas) {
            return false;
        }

        $DB->set_field(self::TABLE, 'subscriptionstatus', 'cancelled', ['subscriptionid' => $subscriptionid]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['subscriptionid' => $subscriptionid]);

        return true;
    }

    /**
     * O que o aluno precisa pagar para o acesso voltar.
     *
     * NAO HA FATURA HOSPEDADA NO MERCADO PAGO para cartao, e por isso a URL e
     * uma pagina NOSSA. No Asaas cada ciclo gera uma cobranca com invoiceUrl
     * no gateway; no cartao daqui o ciclo e uma cobranca automatica, e quando
     * ela falha nao sobra documento nenhum - o que resolve e o aluno informar
     * um cartao que funcione, que e exatamente o que o subscribe.php faz.
     *
     * PIX E BOLETO SAO DIFERENTES, e a linha digitavel do boleto vem daqui -
     * o `line` que esta funcao devolve e o mesmo campo que
     * mysubscriptions.php ja exibia, so que sempre vazio antes de Pix/boleto
     * existirem. Consultada na hora, e nao gravada: ver invoice_details().
     *
     * @param \stdClass $record Linha mais recente da assinatura
     * @return array|null url, duedate, value e line
     */
    public static function pending_invoice(\stdClass $record): ?array {
        if (empty($record->subscriptionid) || strtolower((string) $record->status) === 'approved') {
            return null;
        }

        if ((string) $record->subscriptionstatus === 'cancelled') {
            return null;
        }

        $linha = '';
        if (!empty($record->mppaymentid) && in_array((string) $record->paymentmethod, self::INVOICE_METHODS, true)) {
            $fatura = self::invoice_details($record);
            $linha = (string) ($fatura['barcode'] ?? '');
        }

        // Ciclo de cartao recem-emitido por issue_card_cycle() (sem
        // mppaymentid ainda) pede so o CVV - nao o cartao inteiro de novo.
        // Qualquer outro caso (Pix/boleto, ou um ciclo 1 recusado que ja tem
        // mppaymentid) continua indo para subscribe.php.
        $pagina = self::can_confirm_card_cycle($record) ? 'confirm_cycle.php' : 'subscribe.php';

        return [
            'url' => (new \moodle_url(
                '/payment/gateway/mercadopago/' . $pagina,
                ['ref' => (string) $record->externalreference]
            ))->out(false),
            // Sem vencimento: a cobranca precisa da confirmacao do aluno, e
            // nao ha data-limite fixa. Inventar uma data faria a tela prometer
            // um prazo que nao existe.
            'duedate' => '',
            'value' => (float) $record->amount,
            'line' => $linha,
        ];
    }

    /**
     * Por qual meio esta assinatura esta sendo cobrada, para exibir na tela.
     *
     * NAO diz a bandeira do cartao nem os ultimos digitos - so o meio
     * (cartao, Pix ou boleto). A bandeira exigiria consultar a API a cada
     * linha da lista, e nem o admin nem o aluno decidem nada com esse detalhe
     * que "trocar para cartao" ja nao resolva.
     *
     * @param \stdClass $record
     * @return string
     */
    public static function payment_method_label(\stdClass $record): string {
        $metodo = (string) $record->paymentmethod;

        if ($metodo === 'pix') {
            return get_string('subscribepix', 'paygw_mercadopago');
        }

        if ($metodo === 'bolbradesco') {
            return get_string('subscribeboleto', 'paygw_mercadopago');
        }

        return get_string('subscribecard', 'paygw_mercadopago');
    }

    /**
     * Faz sentido oferecer "trocar para cartao" nesta assinatura?
     *
     * SO PARA QUEM JA PAGA POR PIX OU BOLETO, e assinatura ainda ativa - quem
     * ja paga com cartao nao tem para onde trocar (ver switch_to_card.php,
     * que so troca NESSE sentido), e quem cancelou nao tem ciclo futuro para
     * mudar o instrumento de.
     *
     * @param \stdClass $record Linha mais recente da assinatura
     * @return bool
     */
    public static function can_switch_to_card(\stdClass $record): bool {
        if (empty($record->subscriptionid)) {
            return false;
        }

        if ((string) $record->subscriptionstatus === 'cancelled') {
            return false;
        }

        if (!payment_methods::enabled(payment_methods::METHOD_CARD, (int) $record->accountid)) {
            // A empresa desligou cartao nesta conta: nao ha para onde trocar.
            return false;
        }

        return in_array((string) $record->paymentmethod, self::INVOICE_METHODS, true);
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
     * @param string $paymentarea
     * @return \stdClass|null days e maxcycles, ou null para venda avulsa
     */
    protected static function recurrence_for(string $component, int $itemid, string $paymentarea): ?\stdClass {
        if (!class_exists('\local_marketplace\api')) {
            return null;
        }

        return \local_marketplace\api::recurrence_for($component, $itemid, $paymentarea);
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
