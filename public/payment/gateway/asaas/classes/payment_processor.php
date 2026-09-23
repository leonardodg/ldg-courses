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

use core_payment\helper;
use moodle_exception;
use moodle_url;

/**
 * Cria a cobranca e processa a confirmacao.
 *
 * A direcao do dinheiro e o ponto inteiro desta classe: a cobranca nasce com a
 * chave do VENDEDOR, entao o liquido fica com ele, ele aparece como recebedor
 * no proprio payload do Pix e e ele quem emite a nota. O split leva so a
 * comissao para a carteira da plataforma.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_processor {
    /**
     * Comissao de fabrica quando o local_marketplace nao esta instalado.
     *
     * So entra em jogo sem o marketplace, caso em que
     * local_marketplace\api::default_commission_percent() nem existe para
     * ser chamado - por isso o mesmo valor esta duplicado aqui e nos
     * equivalentes de paygw_mercadopago e paygw_pagarme, sem plugin de
     * gateway compartilhado neste projeto onde morar uma vez so. Mudar o
     * padrao de fabrica da plataforma exige editar os tres.
     */
    const DEFAULT_COMMISSION_PERCENT = 25.0;

    /** @var string Tabela de transacoes do plugin. */
    const TABLE = 'paygw_asaas';

    /**
     * Cria a cobranca e devolve para onde mandar o aluno.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return string URL da fatura hospedada.
     */
    public static function start_payment(string $component, string $paymentarea, int $itemid, int $userid): string {
        global $DB;

        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $accountid = (int) $payable->get_account_id();
        $amount = (float) $payable->get_amount();
        $currency = $payable->get_currency();

        $environment = credentials::current_environment();
        $apikey = credentials::api_key($accountid, $environment);
        if ($apikey === '') {
            throw new moodle_exception('errornotlinked', 'paygw_asaas', '', gateway::environment_label($environment));
        }

        // A comissao e regra do marketplace, nao do gateway. O guarda de
        // componente vive la dentro, para nao existir em copia em cada gateway.
        //
        // Vem taxa E base: o Asaas consegue aplicar as duas bases, entao aqui a
        // base configurada e sempre a aplicada - o que nem sempre vale para
        // outro gateway.
        $feepercent = self::DEFAULT_COMMISSION_PERCENT;
        $feebase = 'gross';
        $feesource = 'site';
        if (class_exists('\local_marketplace\api')) {
            $terms = \local_marketplace\api::commission_terms_for($component, $itemid, $paymentarea);
            $feepercent = $terms->percent;
            $feebase = $terms->base;
            $feesource = $terms->source;
        }

        $reference = 'mdl-' . $userid . '-' . $itemid . '-' . random_string(12);

        // A linha nasce ANTES da chamada. Se a API responder e nos perdermos a
        // resposta, existe rastro para conciliar; o contrario seria uma cobranca
        // criada no Asaas que o Moodle nunca soube que existiu.
        $record = (object) [
            'asaaspaymentid' => '',
            'subscriptionid' => '',
            'externalreference' => $reference,
            'customerid' => '',
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'userid' => $userid,
            'accountid' => $accountid,
            'amount' => $amount,
            'currency' => $currency,
            'feeamount' => 0,
            'feepercent' => $feepercent,
            'feebase' => $feebase,
            'feesource' => $feesource,
            'billingtype' => self::billing_type(),
            'environment' => $environment,
            'status' => 'PENDING',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        $client = new asaas_client($apikey, $environment);

        $buyer = \core_user::get_user($userid, '*', MUST_EXIST);
        $document = self::buyer_document($buyer);

        // O Asaas RECUSA a cobranca de um cliente sem CPF/CNPJ - "Para criar
        // esta cobranca e necessario preencher o CPF ou CNPJ do cliente". Ele
        // aceita criar o cliente sem documento, o que torna o erro tardio e
        // confuso: a compra so quebra na segunda chamada.
        //
        // Falhamos aqui, com mensagem propria, para o administrador saber o que
        // configurar em vez de ler um erro cru da API no meio do checkout.
        if ($document === '') {
            throw new moodle_exception('errornodocument', 'paygw_asaas');
        }

        $customerid = $client->find_or_create_customer(
            fullname($buyer),
            $buyer->email,
            $document
        );

        $common = [
            'customer' => $customerid,
            'billingtype' => self::billing_type(),
            'value' => $amount,
            'description' => self::describe_item($component, $itemid, $paymentarea),
            'externalreference' => $reference,
            'returnurl' => self::use_callback()
                ? (new moodle_url('/payment/gateway/asaas/return.php', ['ref' => $reference]))->out(false)
                : '',
        ];

        // Sem comissao, sem split - AUSENTE, e nao zero. A assinatura SaaS
        // (paymentarea 'plan') sempre cai aqui: a plataforma e a UNICA
        // parte, e mandar splitwalletid pra propria carteira da plataforma
        // seria a mesma coisa que o errorsamewallet existe pra bloquear na
        // venda de curso, so que pelo lado errado. Mesmo padrao ja usado no
        // Mercado Pago (marketplace_fee ausente quando feeamount <= 0).
        if ($feepercent > 0) {
            $common['splitwalletid'] = credentials::platform_wallet($environment);
            $common['splitpercent'] = $feepercent;
            $common['splitbase'] = $feebase;
        }

        // Assinatura ou cobranca avulsa? Quem sabe e o marketplace - o gateway
        // nao tem como saber o que e uma "oferta recorrente". Sem ele
        // instalado, ou para item que nao e assinatura, recurrence_for()
        // devolve null e nada muda em relacao ao que existia.
        $recurrence = class_exists('\local_marketplace\api')
            ? \local_marketplace\api::recurrence_for($component, $itemid, $paymentarea)
            : null;

        if ($recurrence) {
            $response = $client->create_subscription($common + [
                'nextduedate' => date('Y-m-d', time() + (self::due_days() * DAYSECS)),
                'cycle' => self::cycle_for($recurrence->days),
                'maxpayments' => $recurrence->maxcycles,
            ]);

            // A resposta de /subscriptions NAO traz invoiceUrl: ela descreve a
            // assinatura, e nao uma cobranca. A primeira cobranca ja existe, e
            // e para ela que o aluno precisa ir agora.
            $record->subscriptionid = (string) ($response['id'] ?? '');
            $charges = $client->subscription_payments($record->subscriptionid);
            $response = self::earliest_charge($charges);
        } else {
            $response = $client->create_payment($common + [
                'duedate' => date('Y-m-d', time() + (self::due_days() * DAYSECS)),
            ]);
        }

        $invoiceurl = (string) ($response['invoiceUrl'] ?? '');
        if ($invoiceurl === '') {
            throw new moodle_exception('errorinvalidresponse', 'paygw_asaas');
        }

        $record->asaaspaymentid = (string) ($response['id'] ?? '');
        $record->customerid = $customerid;
        $record->status = (string) ($response['status'] ?? 'PENDING');
        // Guardamos o que o gateway devolveu, e nao o que calculamos: na criacao
        // os dois batem, mas estorno parcial e split recusado mudam o numero
        // depois, e o relatorio tem que seguir o extrato.
        $record->feeamount = self::fee_from($response, $amount, $feepercent, '', $feebase);
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        return $invoiceurl;
    }

    /**
     * Processa uma notificacao do Asaas.
     *
     * O payload NUNCA e a fonte da verdade: mesmo com o header validado, o
     * status vem de uma consulta a API com a chave do vendedor. Um webhook e
     * uma dica de que algo mudou, nao a prova do que mudou.
     *
     * @param string $asaaspaymentid
     * @return bool Verdadeiro quando a entrega aconteceu agora.
     */
    public static function process_notification(string $asaaspaymentid, string $subscriptionid = ''): bool {
        global $DB;

        // Trava a linha (FOR UPDATE) ate o commit logo abaixo: um retry do
        // webhook e a tarefa de reconciliacao horaria batendo no mesmo
        // asaaspaymentid quase ao mesmo tempo liam ambos paymentid vazio
        // ANTES de qualquer escrita, e chamavam save_payment() duas vezes.
        $transaction = $DB->start_delegated_transaction();

        $record = $DB->get_record_sql(
            'SELECT * FROM {' . self::TABLE . '} WHERE asaaspaymentid = ? FOR UPDATE',
            [$asaaspaymentid]
        );
        if (!$record && $subscriptionid !== '') {
            // Ainda nao existe linha para este pagamento - do ciclo 2 em
            // diante e o Asaas quem cria a cobranca sozinho. Trava a linha
            // mais recente da ASSINATURA antes de decidir se adota um ciclo
            // novo: sem isto, duas notificacoes concorrentes para o MESMO
            // asaaspaymentid liam ambas "nao existe ainda" e cada uma
            // inserida a sua propria linha via adopt_subscription_cycle() -
            // nao ha indice unico em asaaspaymentid que rejeitasse a segunda
            // (a coluna nasce vazia em toda cobranca pendente, e um indice
            // unico colidiria entre elas).
            $DB->get_record_sql(
                'SELECT id FROM {' . self::TABLE . '} WHERE subscriptionid = ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$subscriptionid]
            );
            // Reconfere DEPOIS de travar: se a outra notificacao ja adotou
            // este pagamento enquanto esta esperava a trava, usa a linha
            // dela em vez de criar uma segunda.
            $record = $DB->get_record(self::TABLE, ['asaaspaymentid' => $asaaspaymentid])
                ?: self::adopt_subscription_cycle($asaaspaymentid, $subscriptionid);
        }
        if (!$record) {
            $transaction->allow_commit();
            return false;
        }

        $apikey = credentials::api_key((int) $record->accountid, $record->environment);
        if ($apikey === '') {
            throw new moodle_exception('errornotlinked', 'paygw_asaas', '', $record->environment);
        }

        $client = new asaas_client($apikey, $record->environment);
        $payment = $client->get_payment($asaaspaymentid);
        $status = (string) ($payment['status'] ?? 'PENDING');

        if (self::is_paid($record->status) && self::is_paid($status)) {
            // Reenvio de algo ja entregue.
            $transaction->allow_commit();
            return false;
        }

        $record->status = $status;
        $record->timemodified = time();

        if (!self::is_paid($status) || !empty($record->paymentid)) {
            $DB->update_record(self::TABLE, $record);
            $transaction->allow_commit();
            return false;
        }

        $platformwallet = credentials::platform_wallet($record->environment);

        if ($platformwallet === '') {
            // Sem wallet da plataforma configurada, asaas_client::build_split()
            // nunca envia split nenhum na cobranca - o vendedor fica com 100%
            // e a plataforma nao recebe nada. Cair no ramo de ESTIMATIVA de
            // fee_from() aqui gravaria uma comissao que jamais foi transferida,
            // e o ledger reportaria receita fantasma indefinidamente.
            $record->feeamount = 0.0;
        } else {
            // Aqui o split ja existe na resposta, entao le-se o valor real em
            // vez de recalcular o percentual - inclusive o zero de um split
            // cancelado. A base e a da LINHA (feebase), nao o padrao 'gross'
            // de fee_from(): sem isto, uma venda com comissao configurada
            // sobre o liquido caia na estimativa de bruto se algum dia
            // chegasse aqui sem split (o que agora nunca acontece, ja que o
            // ramo acima cobre a wallet vazia).
            $record->feeamount = self::fee_from(
                $payment,
                (float) $record->amount,
                (float) $record->feepercent,
                $platformwallet,
                (string) $record->feebase
            );
        }

        // Curso entregue sem comissao nenhuma nao e erro tecnico, e um fato do
        // negocio que alguem precisa ver. Acontece quando o vendedor da baixa
        // manual na cobranca: o aluno pagou por fora, o Asaas cancela o split,
        // e a plataforma nao recebe.
        if ((float) $record->feeamount <= 0 && (float) $record->feepercent > 0) {
            debugging(
                'paygw_asaas: cobranca ' . $asaaspaymentid . ' entregue com comissao zero - '
                    . 'split cancelado ou ausente. Status: ' . $status,
                DEBUG_NORMAL
            );
        }
        $record->paymentid = helper::save_payment(
            (int) $record->accountid,
            $record->component,
            $record->paymentarea,
            (int) $record->itemid,
            (int) $record->userid,
            (float) $record->amount,
            $record->currency,
            'asaas'
        );
        $DB->update_record(self::TABLE, $record);

        // Libera a trava aqui: record_sale()/deliver_order() sao mais lentas
        // (chamam o marketplace, matriculam o aluno) e segurar o lock da
        // linha durante isso so aumentaria contencao sem necessidade.
        $transaction->allow_commit();

        if (class_exists('\local_marketplace\api')) {
            \local_marketplace\api::record_sale(
                $record->component,
                (int) $record->paymentid,
                (int) $record->itemid,
                (float) $record->feeamount,
                $asaaspaymentid,
                // Os termos vem da LINHA, e nao de uma nova resolucao: entre a
                // criacao da cobranca e o webhook a configuracao pode ter
                // mudado, e a venda tem que registrar o que foi cobrado.
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

    /**
     * Cria a linha do ciclo seguinte de uma assinatura.
     *
     * O ciclo 1 nasce no checkout, com o aluno na tela. Do ciclo 2 em diante
     * quem cria a cobranca e o Asaas, sozinho, e o webhook chega falando de
     * algo que o Moodle nunca viu. Sem isto, process_notification() nao
     * encontraria a linha e devolveria false - o aluno pagaria a mensalidade e
     * o acesso nao seria estendido.
     *
     * O contexto e COPIADO da linha anterior da mesma assinatura: componente,
     * item, aluno, conta e os termos da comissao. Os termos vem da linha, e
     * nao de uma nova resolucao, pelo mesmo motivo de sempre (ADR-0007) -
     * mudar a comissao hoje nao pode reescrever o que foi contratado.
     *
     * O subscriptionid vem do payload do webhook, e isso e seguro: ele serve
     * para IDENTIFICAR de quem e a cobranca, e nada mais. Valor e status
     * continuam vindo da API, com a chave do vendedor.
     *
     * @param string $asaaspaymentid Cobranca nova, criada pelo Asaas
     * @param string $subscriptionid Assinatura a que ela pertence
     * @return \stdClass|null Nula quando a assinatura nao e nossa
     */
    public static function adopt_subscription_cycle(string $asaaspaymentid, string $subscriptionid): ?\stdClass {
        global $DB;

        // A mais recente da mesma assinatura e a que tem o contexto mais atual.
        $previous = $DB->get_records(
            self::TABLE,
            ['subscriptionid' => $subscriptionid],
            'id DESC',
            '*',
            0,
            1
        );
        $previous = reset($previous);
        if (!$previous) {
            return null;
        }

        // Referencia propria por ciclo: ela e UNIQUE na tabela, e reaproveitar
        // a do ciclo anterior faria o insert falhar bem no meio da renovacao.
        $new = (object) [
            'asaaspaymentid' => $asaaspaymentid,
            'subscriptionid' => $subscriptionid,
            'externalreference' => 'mdl-' . (int) $previous->userid . '-' . (int) $previous->itemid
                . '-' . random_string(12),
            'customerid' => $previous->customerid,
            'component' => $previous->component,
            'paymentarea' => $previous->paymentarea,
            'itemid' => $previous->itemid,
            'userid' => $previous->userid,
            'accountid' => $previous->accountid,
            'amount' => $previous->amount,
            'currency' => $previous->currency,
            'feeamount' => 0,
            'feepercent' => $previous->feepercent,
            'feebase' => $previous->feebase,
            'feesource' => $previous->feesource,
            'billingtype' => $previous->billingtype,
            'environment' => $previous->environment,
            'status' => 'PENDING',
            'paymentid' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $new->id = $DB->insert_record(self::TABLE, $new);

        return $new;
    }

    /**
     * Formas de pagamento que o Asaas aceita estornar.
     *
     * Boleto fica de fora porque o gateway o recusa, e nao porque escolhemos:
     * "somente e possivel estornar cobrancas cuja a forma de pagamento seja
     * cartao de credito ou Pix". Nem baixa manual muda isso.
     *
     * UNDEFINED entra: quando o aluno escolhe na fatura, a coluna guarda o que
     * foi escolhido, e o que chega aqui ja e o tipo real.
     *
     * @var string[]
     */
    const REFUNDABLE_TYPES = ['CREDIT_CARD', 'PIX'];

    /**
     * Esta venda pode ser estornada, e por que nao.
     *
     * Tres regras, e as tres vieram de medicao no sandbox em 09/09/2026, e nao
     * de preferencia.
     *
     * SO O QUE FOI PAGO. O Asaas responde "e possivel estornar somente
     * cobrancas confirmadas ou recebidas" - pedir estorno de pendente e erro
     * cru no meio da tela de quem esta resolvendo um problema.
     *
     * SO O PRIMEIRO CICLO DE UMA ASSINATURA. Estornar um ciclo do meio devolve
     * o dinheiro daquele mes e NAO PARA a assinatura: as cobrancas futuras
     * seguem pendentes, e o aluno continua sendo cobrado depois de reembolsado.
     * Do segundo ciclo em diante o caminho e cancelar, e nao estornar - o aluno
     * usou os meses anteriores.
     *
     * NUNCA DUAS VEZES. Estorno ja feito nao se repete.
     *
     * @param \stdClass $record Linha da tabela do gateway
     * @return string Vazio quando pode; a chave do erro quando nao pode
     */
    public static function refund_blocker(\stdClass $record): string {
        global $DB;

        if (strtoupper((string) $record->status) === 'REFUNDED') {
            return 'errorrefundalready';
        }

        if (!self::is_paid((string) $record->status)) {
            return 'errorrefundnotpaid';
        }

        // BOLETO NAO TEM ESTORNO, em circunstancia nenhuma. O Asaas recusa pela
        // forma de pagamento: "somente e possivel estornar cobrancas cuja a
        // forma de pagamento seja cartao de credito ou Pix" - e continua
        // recusando mesmo depois de baixa manual. Medido em 09/09/2026.
        //
        // Sem esta checagem o botao aparecia numa venda por boleto e o clique
        // morria com erro cru da API, diante de quem esta resolvendo um
        // problema de dinheiro com um aluno.
        if (!in_array(strtoupper((string) $record->billingtype), self::REFUNDABLE_TYPES, true)) {
            return 'errorrefundbillingtype';
        }

        if (empty($record->subscriptionid)) {
            return '';
        }

        // Ciclo do meio: existe outra cobranca PAGA da mesma assinatura antes
        // desta. Comparar por id basta - as linhas nascem em ordem.
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
     * Estorna uma venda: devolve o dinheiro e para de cobrar.
     *
     * O CANCELAMENTO DA ASSINATURA ANDA JUNTO, e nao e cortesia. O Asaas
     * estorna a cobranca e mantem a assinatura ATIVA, com as cobrancas futuras
     * pendentes - deixar isso a cargo de quem clica seria confiar em memoria
     * humana para nao continuar cobrando alguem que ja foi reembolsado.
     *
     * A ordem importa: cancela primeiro, estorna depois. Se o estorno falhar,
     * sobra uma assinatura cancelada com um mes pago, que e ruim mas e
     * reversivel; a ordem inversa deixaria dinheiro devolvido e a cobranca
     * seguindo.
     *
     * Nao revoga o acesso aqui: quem faz isso e o marketplace, porque o direito
     * e dele. O gateway devolve o dinheiro e avisa.
     *
     * @param \stdClass $record Linha da tabela do gateway
     * @return bool Verdadeiro quando o estorno foi aceito pelo gateway.
     */
    public static function refund(\stdClass $record): bool {
        global $DB;

        $bloqueio = self::refund_blocker($record);
        if ($bloqueio !== '') {
            throw new moodle_exception($bloqueio, 'paygw_asaas');
        }

        $apikey = credentials::api_key((int) $record->accountid, $record->environment);
        if ($apikey === '') {
            throw new moodle_exception('errornotlinked', 'paygw_asaas', '', $record->environment);
        }

        $client = new asaas_client($apikey, $record->environment);

        if (!empty($record->subscriptionid)) {
            $client->cancel_subscription((string) $record->subscriptionid);
        }

        $response = $client->refund_payment((string) $record->asaaspaymentid);

        $record->status = (string) ($response['status'] ?? 'REFUNDED');
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        return strtoupper((string) $record->status) === 'REFUNDED';
    }

    /**
     * A fatura em aberto do proximo ciclo, se houver.
     *
     * Existe porque o aviso de vencimento mandava o aluno para a VITRINE, e o
     * que ele precisa e da FATURA. Com boleto a diferenca e grande: a cobranca
     * do ciclo ja nasce pronta, com linha digitavel, e o aluno estava sendo
     * mandado para uma tela onde teria que comprar de novo.
     *
     * As cobrancas futuras nao tem linha na nossa tabela - so as pagas viram
     * linha, pelo webhook. Entao a lista vem do gateway, pela assinatura.
     *
     * Falha de rede devolve null, e nao excecao: isto alimenta uma tela e um
     * e-mail, e nenhum dos dois pode quebrar porque o gateway piscou.
     *
     * @param \stdClass $record Linha com subscriptionid, accountid e environment
     * @return array|null url, duedate, value e line (linha digitavel), ou null
     */
    public static function pending_invoice(\stdClass $record): ?array {
        if (empty($record->subscriptionid)) {
            return null;
        }

        $apikey = credentials::api_key((int) $record->accountid, $record->environment);
        if ($apikey === '') {
            return null;
        }

        try {
            $client = new asaas_client($apikey, $record->environment);
            $charges = $client->subscription_payments((string) $record->subscriptionid);
        } catch (\Throwable $e) {
            return null;
        }

        // A mais proxima entre as que ainda nao foram pagas. Vencida entra: ela
        // e justamente a que o aluno precisa pagar para o acesso voltar.
        $open = array_filter(
            $charges,
            static fn(array $c): bool => !self::is_paid((string) ($c['status'] ?? ''))
                && strtoupper((string) ($c['status'] ?? '')) !== 'REFUNDED'
        );
        $target = self::earliest_charge($open);
        if (!$target) {
            return null;
        }

        return [
            'url' => (string) ($target['invoiceUrl'] ?? ''),
            'duedate' => (string) ($target['dueDate'] ?? ''),
            'value' => (float) ($target['value'] ?? 0),
            // So o boleto tem; para Pix e cartao volta vazio, e a tela nao
            // mostra o campo em vez de mostrar um espaco vago.
            'line' => empty($target['bankSlipUrl'])
                ? ''
                : $client->identification_field((string) $target['id']),
        ];
    }

    /**
     * A cobranca que o aluno tem que pagar AGORA.
     *
     * O Asaas nao gera uma cobranca por vez: uma assinatura semanal nasce com
     * CINCO cobrancas pendentes de uma vez, e a lista volta da mais distante
     * para a mais proxima. Pegar a primeira do array mandava o aluno para a
     * fatura que vence daqui a um mes - ele pagaria, o webhook entregaria o
     * acesso, e a cobranca de hoje ficaria vencida atras dele.
     *
     * Ordena por vencimento e devolve a mais proxima. Empate no vencimento cai
     * na ordem que veio, e tanto faz: mesmas data e valor.
     *
     * @param array $charges Resposta de subscription_payments()
     * @return array A cobranca mais proxima, ou vazio quando nao ha nenhuma
     */
    public static function earliest_charge(array $charges): array {
        $best = [];
        foreach ($charges as $charge) {
            if (!is_array($charge) || empty($charge['dueDate'])) {
                continue;
            }
            if (!$best || $charge['dueDate'] < $best['dueDate']) {
                $best = $charge;
            }
        }

        return $best;
    }

    /**
     * Ciclos que o Asaas aceita, em dias.
     *
     * @var array<string,int>
     */
    const CYCLES = [
        'WEEKLY' => 7,
        'BIWEEKLY' => 14,
        'MONTHLY' => 30,
        'BIMONTHLY' => 60,
        'QUARTERLY' => 90,
        'SEMIANNUALLY' => 180,
        'YEARLY' => 365,
    ];

    /**
     * Traduz um intervalo em dias para o ciclo nomeado do Asaas.
     *
     * A traducao e LOSSY, e nao ha como nao ser: o marketplace guarda dias
     * porque o acesso e contado em dias, e o Asaas so aceita nomes. Uma
     * assinatura de 45 dias vira BIMONTHLY, e o vendedor precisa saber disso -
     * por isso escolhemos o ciclo mais PROXIMO, e nao o teto ou o piso, que
     * dariam erro sistematico para um dos lados.
     *
     * O acesso continua sendo contado pelo accessdays do direito. Divergencia
     * entre cobrar a cada 60 dias e liberar 45 e problema de configuracao da
     * oferta, e aparece no relatorio - nao e este metodo que a esconde.
     *
     * @param int $days
     * @return string
     */
    public static function cycle_for(int $days): string {
        if ($days <= 0) {
            return 'MONTHLY';
        }

        // EMPATE VAI PARA O CICLO MAIOR, e isso e decisao e nao acaso: 45 dias
        // fica a 15 de MONTHLY e a 15 de BIMONTHLY. Cobrar a cada 30 quando o
        // contrato diz 45 tira do aluno dinheiro que ele nao combinou; cobrar a
        // cada 60 da a ele quinze dias que o vendedor absorve. Entre errar
        // contra o aluno e errar contra a plataforma, erramos contra nos.
        //
        // O <= no lugar do < e o que produz isso, porque CYCLES esta em ordem
        // crescente - trocar por < voltaria a preferir o menor em silencio.
        $best = 'MONTHLY';
        $distance = PHP_INT_MAX;
        foreach (self::CYCLES as $name => $cycledays) {
            $current = abs($cycledays - $days);
            if ($current <= $distance) {
                $distance = $current;
                $best = $name;
            }
        }

        return $best;
    }

    /**
     * Este EVENTO de webhook merece uma consulta a API?
     *
     * Cuidado com a diferenca, que custou uma volta: o nome do EVENTO nao e o
     * mesmo do STATUS da cobranca. Existe o status RECEIVED_IN_CASH, mas nao
     * existe evento PAYMENT_RECEIVED_IN_CASH - o Asaas responde "O evento
     * [PAYMENT_RECEIVED_IN_CASH] e invalido" a quem tentar cadastrar. Receber
     * em dinheiro chega como PAYMENT_RECEIVED, com o status na cobranca.
     *
     * Por isso a lista aqui e curta e a de is_paid() e maior: uma fala de
     * eventos, a outra de status.
     *
     * Estorno e desfazimento (PAYMENT_REFUNDED, PAYMENT_RECEIVED_IN_CASH_UNDONE)
     * ficam de fora de proposito: revogar acesso e decisao de negocio deste
     * projeto, tomada em entitlement::revoke(), e nunca por automacao.
     *
     * @param string $event
     * @return bool
     */
    public static function is_relevant_event(string $event): bool {
        return in_array(strtoupper($event), ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true);
    }

    /**
     * O STATUS do Asaas significa "dinheiro entrou"?
     *
     * RECEIVED e o valor creditado; CONFIRMED e o cartao autorizado, com o
     * credito ainda por cair; RECEIVED_IN_CASH e a baixa manual, que e como o
     * sandbox confirma cobranca. Os tres liberam o acesso: segurar o curso ate
     * a liquidacao puniria o aluno por um prazo bancario.
     *
     * @param string $status
     * @return bool
     */
    public static function is_paid(string $status): bool {
        return in_array(strtoupper($status), ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true);
    }

    /**
     * Comissao que a plataforma vai DE FATO receber.
     *
     * Le o split da propria resposta quando ele existe, e nao um percentual
     * calculado por nos. A diferenca nao e cosmetica: se o vendedor der baixa
     * manual na cobranca (receiveInCash), o Asaas marca o split como CANCELLED -
     * dinheiro que nao passou por ele nao tem como ser dividido - e a plataforma
     * recebe ZERO. Calculando o percentual, o relatorio anunciaria uma comissao
     * que nunca vai chegar, e relatorio financeiro que discorda do extrato e
     * pior que nenhum.
     *
     * Sem split na resposta - na criacao da cobranca, quando ele ainda nao
     * existe - cai no percentual sobre o valor BRUTO, que e a base combinada
     * com o parceiro e a mesma que o build_split usa para montar o fixedValue.
     *
     * @param array $payment Resposta da API.
     * @param float $amount
     * @param float $feepercent
     * @param string $walletid Carteira da plataforma. Vazio ignora o split.
     * @return float
     */
    public static function fee_from(
        array $payment,
        float $amount,
        float $feepercent,
        string $walletid = '',
        string $base = 'gross'
    ): float {
        $splits = $payment['split'] ?? [];

        if ($walletid !== '' && is_array($splits) && $splits) {
            $total = 0.0;
            foreach ($splits as $split) {
                if ((string) ($split['walletId'] ?? '') !== $walletid) {
                    continue;
                }
                // CANCELLED e REFUSED nao transferem nada. Somar o valor deles
                // faria o relatorio prometer dinheiro que nao vem.
                if (in_array(strtoupper((string) ($split['status'] ?? '')), ['CANCELLED', 'REFUSED'], true)) {
                    continue;
                }
                $total += (float) ($split['totalValue'] ?? $split['fixedValue'] ?? 0);
            }

            return round($total, 2);
        }

        // A estimativa tem que usar a MESMA base do split que foi enviado, senao
        // a tela mostra uma comissao e o gateway recebe outra.
        if ($base === 'net') {
            $net = (float) ($payment['netValue'] ?? 0);

            // Sem netValue na resposta - acontece na criacao da cobranca - o
            // valor cheio e a unica base disponivel. Superestima a comissao, e
            // e o erro menos ruim: o numero certo chega pelo webhook, com o
            // split real, e ate la e melhor prometer menos ao vendedor do que
            // mais.
            return round(($net > 0 ? $net : $amount) * ($feepercent / 100), 2);
        }

        return round($amount * ($feepercent / 100), 2);
    }

    /**
     * Forma de cobranca configurada no site.
     *
     * UNDEFINED abre a fatura com todas as formas e deixa o aluno escolher.
     *
     * @return string
     */
    public static function billing_type(): string {
        $configured = strtoupper((string) get_config('paygw_asaas', 'billingtype'));
        $allowed = ['UNDEFINED', 'PIX', 'BOLETO', 'CREDIT_CARD'];

        return in_array($configured, $allowed, true) ? $configured : 'UNDEFINED';
    }

    /**
     * Mandar o aluno de volta ao Moodle depois de pagar?
     *
     * Existe como interruptor porque o Asaas so aceita URL de retorno se a
     * conta do vendedor tiver um SITE cadastrado - sem isso ele recusa a
     * cobranca inteira com "Nao ha nenhum dominio configurado em sua conta".
     * Um vendedor que nao consiga cadastrar o dominio continua vendendo; o
     * aluno e que volta pela fatura em vez de voltar sozinho.
     *
     * O vinculo ja barra esse caso, entao aqui e a segunda linha de defesa.
     *
     * @return bool
     */
    public static function use_callback(): bool {
        $configured = get_config('paygw_asaas', 'usecallback');

        return $configured === false || (bool) $configured;
    }

    /**
     * Dias ate o vencimento da cobranca.
     *
     * @return int
     */
    public static function due_days(): int {
        $configured = (int) get_config('paygw_asaas', 'duedays');

        return $configured > 0 ? $configured : 3;
    }

    /**
     * CPF/CNPJ do comprador.
     *
     * OBRIGATORIO, e nao por escolha nossa: o Asaas emite cliente sem
     * documento, mas recusa a COBRANCA desse cliente. Como o Moodle nao pede
     * documento no cadastro, o site precisa ter um campo de perfil para isso e
     * apontar aqui - e o formulario de inscricao deve exigi-lo, senao a falha
     * aparece so na hora de pagar.
     *
     * @param \stdClass $user
     * @return string Somente digitos, ou vazio quando nao ha.
     */
    protected static function buyer_document(\stdClass $user): string {
        global $CFG;

        $field = trim((string) get_config('paygw_asaas', 'documentfield'));
        if ($field === '') {
            return '';
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');
        $profile = profile_user_record($user->id);
        $value = (string) ($profile->{$field} ?? '');

        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * Descricao que o aluno ve na fatura.
     *
     * @param string $component
     * @param int $itemid
     * @return string
     */
    protected static function describe_item(string $component, int $itemid, string $paymentarea = 'offer'): string {
        if ($component !== 'local_marketplace') {
            return get_string('defaultdescription', 'paygw_asaas');
        }

        // A paymentarea 'plan' usa companyid como itemid, e o que ha para
        // descrever e o PLANO contratado - nao uma oferta, que nem existe.
        if ($paymentarea === 'plan' && class_exists('\local_marketplace\company')) {
            $company = \local_marketplace\company::get_record(['id' => $itemid]);
            $plan = $company ? $company->get_plan() : null;
            if ($plan) {
                return (string) $plan->get('name');
            }

            return get_string('defaultdescription', 'paygw_asaas');
        }

        if (class_exists('\local_marketplace\offer')) {
            $offer = \local_marketplace\offer::get_record(['id' => $itemid]);
            if ($offer) {
                return (string) $offer->get('name');
            }
        }

        return get_string('defaultdescription', 'paygw_asaas');
    }

    /**
     * URL do webhook deste site.
     *
     * @return moodle_url
     */
    public static function webhook_url(): moodle_url {
        return new moodle_url('/payment/gateway/asaas/webhook.php');
    }
}
