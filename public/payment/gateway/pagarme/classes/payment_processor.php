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

use core_payment\helper;
use moodle_exception;
use moodle_url;

/**
 * Cobranca, assinatura, webhook e estorno.
 *
 * @package    paygw_pagarme
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
     * equivalentes de paygw_mercadopago e paygw_asaas, sem plugin de
     * gateway compartilhado neste projeto onde morar uma vez so. Mudar o
     * padrao de fabrica da plataforma exige editar os tres.
     */
    const DEFAULT_COMMISSION_PERCENT = 25.0;

    /** @var string Tabela do plugin. */
    const TABLE = 'paygw_pagarme';

    /**
     * Situacoes em que o dinheiro entrou.
     *
     * 'overpaid' entra porque o aluno pagou a mais e o acesso nao pode ficar
     * preso a um centavo de diferenca. 'underpaid' NAO entra: liberar curso
     * por pagamento parcial e prejuizo silencioso.
     *
     * @var string[]
     */
    const PAID_STATUSES = ['paid', 'overpaid'];

    /**
     * Meios que aceitam estorno.
     *
     * Boleto fica de fora, e agora por medicao e nao por analogia com o Asaas.
     * Em 11/09/2026 o DELETE de uma cobranca de boleto respondeu:
     *
     *     412 BankAccount information is required to refund boleto payment
     *         method.
     *
     * Ou seja: ele ATE estorna, mas exige os dados bancarios de quem vai
     * receber de volta - e o Moodle nao pede conta bancaria a aluno nenhum,
     * nem deveria. Sem esses dados o botao so produziria um erro cru na cara
     * do gerente, que e exatamente o bug #89 deste projeto.
     *
     * @var string[]
     */
    const REFUNDABLE_METHODS = ['credit_card', 'pix'];

    /** @var array Intervalos aceitos pela API, do menor para o maior. */
    const INTERVALS = [
        'day' => 1,
        'week' => 7,
        'month' => 30,
        'year' => 365,
    ];

    /**
     * Comeca o pagamento e devolve para onde mandar o aluno.
     *
     * A linha nasce ANTES da chamada a API. Uma cobranca criada no Pagar.me
     * que o Moodle nunca registrou nao teria como ser reconciliada depois - e
     * a reconciliacao e o que salva a venda quando o webhook se perde.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return string URL para onde redirecionar.
     */
    public static function start_payment(
        string $component,
        string $paymentarea,
        int $itemid,
        int $userid
    ): string {
        global $DB;

        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $accountid = (int) $payable->get_account_id();
        $amount = (float) $payable->get_amount();
        $currency = $payable->get_currency();

        $environment = credentials::current_environment();
        $apikey = credentials::api_key($accountid, $environment);
        if ($apikey === '') {
            throw new moodle_exception('errornotlinked', 'paygw_pagarme', '', $environment);
        }

        // A recusa da recorrencia vem ANTES de a linha existir.
        //
        // Ela ja morava em create_charge_for(), e nao bastava: com cartao o
        // start_payment volta cedo, mandando o aluno para a pagina de
        // tokenizacao, e create_charge_for so roda depois. A oferta recorrente
        // passava pela porta e deixava uma linha orfa para tras - achado na
        // prova de ponta a ponta em 11/09/2026.
        if (class_exists('\local_marketplace\api') && !self::supports_recurring()) {
            if (\local_marketplace\api::recurrence_for($component, $itemid, $paymentarea)) {
                throw new moodle_exception(self::recurring_blocker(), 'paygw_pagarme');
            }
        }

        // Termos da comissao. Os valores de queda existem para o gateway
        // funcionar com qualquer componente do core_payment, nao so com o
        // marketplace.
        $feepercent = self::DEFAULT_COMMISSION_PERCENT;
        $feebase = 'gross';
        $feesource = 'site';
        if (class_exists('\local_marketplace\api')) {
            $terms = \local_marketplace\api::commission_terms_for($component, $itemid, $paymentarea);
            $feepercent = (float) $terms->percent;
            $feesource = (string) $terms->source;

            // A base gravada e a APLICADA, nao a pedida. O Pagar.me so cobra
            // sobre o bruto, e a venda tem que contar o que aconteceu - e para
            // isso que o feebase existe (ADR-0007).
            $feebase = pagarme_client::applied_base((string) $terms->base);
            if ($feebase !== (string) $terms->base) {
                debugging(
                    'paygw_pagarme: comissao pedida sobre ' . $terms->base
                        . ' mas o Pagar.me so cobra sobre o bruto; a venda registra gross.',
                    DEBUG_DEVELOPER
                );
            }
        }

        $reference = 'mdl-' . $userid . '-' . $itemid . '-' . random_string(12);
        $method = self::payment_method();

        $record = (object) [
            'orderid' => '',
            'chargeid' => '',
            'subscriptionid' => null,
            'customerid' => '',
            'externalreference' => $reference,
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'userid' => $userid,
            'accountid' => $accountid,
            'amount' => $amount,
            'currency' => $currency,
            'feepercent' => $feepercent,
            'feeamount' => 0,
            'feebase' => $feebase,
            'feesource' => $feesource,
            'paymentmethod' => $method,
            'environment' => $environment,
            'status' => 'pending',
            'paymentid' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        // O cartao nao pode nascer aqui: o numero precisa virar token no
        // navegador do aluno, e so depois a order pode ser criada. A pagina
        // propria faz a tokenizacao e chama de volta.
        if ($method === 'credit_card') {
            return (new moodle_url('/payment/gateway/pagarme/card.php', ['ref' => $reference]))->out(false);
        }

        self::create_charge_for($record);

        if ($method === 'pix') {
            return (new moodle_url('/payment/gateway/pagarme/pix.php', ['ref' => $reference]))->out(false);
        }

        // Boleto: a propria pagina do Pagar.me tem o codigo de barras e o PDF.
        $record = $DB->get_record(self::TABLE, ['id' => $record->id]);
        $url = self::boleto_url($record);

        return $url !== ''
            ? $url
            : (new moodle_url('/payment/gateway/pagarme/return.php', ['ref' => $reference]))->out(false);
    }

    /**
     * Cria a cobranca ou a assinatura para uma linha ja gravada.
     *
     * Separado de start_payment porque o cartao chega depois, pela pagina de
     * tokenizacao, e precisa do mesmo caminho.
     *
     * @param \stdClass $record Linha da tabela.
     * @param string $cardtoken Token do cartao, quando houver.
     * @param array $billing Endereco de cobranca, exigido pelo cartao.
     * @return \stdClass Linha atualizada.
     */
    public static function create_charge_for(
        \stdClass $record,
        string $cardtoken = '',
        array $billing = []
    ): \stdClass {
        global $DB;

        $apikey = credentials::api_key((int) $record->accountid, $record->environment);
        if ($apikey === '') {
            throw new moodle_exception('errornotlinked', 'paygw_pagarme', '', $record->environment);
        }

        $client = new pagarme_client($apikey);
        $customer = self::build_customer((int) $record->userid);
        $customerid = $client->find_or_create_customer($customer);

        $split = pagarme_client::build_split(
            credentials::seller_recipient((int) $record->accountid, $record->environment),
            credentials::platform_recipient((int) $record->accountid, $record->environment),
            (float) $record->feepercent,
            (float) $record->amount,
            (string) $record->feebase
        );

        $recurrence = null;
        if (class_exists('\local_marketplace\api')) {
            $recurrence = \local_marketplace\api::recurrence_for(
                $record->component,
                (int) $record->itemid,
                (string) $record->paymentarea
            );
        }

        // Recusa na porta, e nao no extrato. Ver supports_recurring().
        if ($recurrence && !self::supports_recurring()) {
            throw new moodle_exception(self::recurring_blocker(), 'paygw_pagarme');
        }

        if ($recurrence) {
            $response = $client->create_subscription(self::subscription_body(
                $record,
                $customer,
                $split,
                $recurrence,
                $cardtoken
            ));
            $record->subscriptionid = (string) ($response['id'] ?? '');

            // A assinatura nao devolve a cobranca junto. Ela e buscada a
            // parte, e a escolhida e a de vencimento MAIS PROXIMO - pegar a
            // primeira do array mandava o aluno pagar a fatura do mes
            // seguinte, e isso virou bug real no Asaas.
            $charges = $client->subscription_charges((string) $record->subscriptionid);
            $charge = self::earliest_charge($charges);
        } else {
            $response = $client->create_order(
                self::order_body($record, $customer, $split, $cardtoken, $billing)
            );
            $record->orderid = (string) ($response['id'] ?? '');
            $charge = ($response['charges'] ?? [null])[0] ?? [];
        }

        $record->customerid = $customerid;
        $record->timemodified = time();

        if ($charge) {
            $record->chargeid = (string) ($charge['id'] ?? '');
            $record->status = (string) ($charge['status'] ?? 'pending');
            $record->feeamount = pagarme_client::commission_from(
                $charge,
                credentials::platform_recipient((int) $record->accountid, $record->environment)
            );
            $record->qrcode = (string) (($charge['last_transaction'] ?? [])['qr_code'] ?? '');
            $record->checkouturl = self::checkout_url_for((string) $record->paymentmethod, $charge);
        }

        $DB->update_record(self::TABLE, $record);

        // Order/assinatura criada mas SEM cobranca nenhuma na resposta -
        // documentado acima como acontecendo com recipient invalido.
        // charge_verdict([]) devolve status vazio, nunca 'failed', entao sem
        // esta guarda a linha ficava presa para sempre em status='pending',
        // chargeid='' - fora do alcance do webhook (que so chega para um
        // chargeid real) e do sweep do reconcile.php (que exige
        // chargeid <> '').
        if (!$charge) {
            throw new moodle_exception('errorchargemissing', 'paygw_pagarme');
        }

        // O HTTP 200 do POST nao diz nada: uma order com recipient inexistente
        // tambem volta 200, e so a releitura denuncia. Estourar aqui e o que
        // impede o aluno de encarar um QR Code que nunca vai ser pago.
        [$status, $code, $message] = pagarme_client::charge_verdict($charge);
        if ($status === 'failed') {
            throw new moodle_exception('errorchargefailed', 'paygw_pagarme', '', $code . ' ' . $message);
        }

        return $record;
    }

    /**
     * Corpo do POST /orders.
     *
     * @param \stdClass $record
     * @param array $customer
     * @param array $split
     * @param string $cardtoken
     * @param array $billing Endereco de cobranca, exigido pelo cartao.
     * @return array
     */
    public static function order_body(
        \stdClass $record,
        array $customer,
        array $split,
        string $cardtoken = '',
        array $billing = []
    ): array {
        $payment = ['payment_method' => $record->paymentmethod];

        switch ($record->paymentmethod) {
            case 'pix':
                $payment['pix'] = ['expires_in' => self::pix_expires_in()];
                break;
            case 'boleto':
                $payment['boleto'] = [
                    'instructions' => get_string('boletoinstructions', 'paygw_pagarme'),
                    'due_at' => date('Y-m-d\TH:i:s\Z', time() + (self::due_days() * DAYSECS)),
                ];
                break;
            case 'credit_card':
                $payment['credit_card'] = [
                    'installments' => 1,
                    'card_token' => $cardtoken,
                ];

                // O endereco de cobranca NAO vai no token, e a cobranca nao
                // nasce sem ele: medido em 11/09/2026, um token sozinho
                // produz "400 validation_error | billing | value is required".
                // Ele vem da pagina do cartao, onde o aluno digita - o perfil
                // do Moodle nao tem CEP.
                if ($billing) {
                    $payment['credit_card']['card'] = ['billing_address' => $billing];
                }
                break;
        }

        if ($split) {
            $payment['split'] = $split;
        }

        return [
            // O code do item e obrigatorio, e a falta dele nao vira erro de
            // requisicao: volta 200 com a cobranca failed e "The item Code is
            // required" enterrado no gateway_response. Medido em 09/09/2026.
            'items' => [[
                'amount' => pagarme_client::to_cents((float) $record->amount),
                'description' => self::describe_item($record->component, (int) $record->itemid, (string) $record->paymentarea),
                'quantity' => 1,
                'code' => 'mdl-' . $record->itemid,
            ]],
            'customer' => $customer,
            'code' => $record->externalreference,
            'payments' => [$payment],
        ];
    }

    /**
     * Corpo do POST /subscriptions.
     *
     * @param \stdClass $record
     * @param array $customer
     * @param array $split
     * @param \stdClass $recurrence
     * @param string $cardtoken
     * @return array
     */
    public static function subscription_body(
        \stdClass $record,
        array $customer,
        array $split,
        \stdClass $recurrence,
        string $cardtoken = ''
    ): array {
        $interval = self::interval_for((int) $recurrence->days);

        $body = [
            'code' => $record->externalreference,
            'payment_method' => $record->paymentmethod,
            'currency' => $record->currency,
            'interval' => $interval['interval'],
            'interval_count' => $interval['interval_count'],
            // O modo prepaid cobra no comeco do ciclo, que e o que um curso
            // com prazo de acesso exige: primeiro paga, depois assiste.
            'billing_type' => 'prepaid',
            'customer' => $customer,
            'items' => [[
                'description' => self::describe_item($record->component, (int) $record->itemid, (string) $record->paymentarea),
                'quantity' => 1,
                'pricing_scheme' => [
                    'scheme_type' => 'unit',
                    'price' => pagarme_client::to_cents((float) $record->amount),
                ],
            ]],
        ];

        // Zero em maxcycles e assinatura sem fim. Mandar zero seria pedir uma
        // assinatura de zero ciclos, que e o oposto.
        if ((int) $recurrence->maxcycles > 0) {
            $body['cycles'] = (int) $recurrence->maxcycles;
        }

        if ($cardtoken !== '') {
            $body['card_token'] = $cardtoken;
        }

        if ($split) {
            $body['split'] = $split;
        }

        return $body;
    }

    /**
     * Traduz dias de cobranca no par interval/interval_count da API.
     *
     * O empate vai para o intervalo MAIOR: entre errar contra o aluno e errar
     * contra a plataforma, erramos contra nos mesmos.
     *
     * @param int $days
     * @return array
     */
    public static function interval_for(int $days): array {
        if ($days <= 0) {
            return ['interval' => 'month', 'interval_count' => 1];
        }

        foreach (array_reverse(self::INTERVALS, true) as $name => $size) {
            if ($days % $size === 0 && $days >= $size) {
                return ['interval' => $name, 'interval_count' => (int) ($days / $size)];
            }
        }

        return ['interval' => 'day', 'interval_count' => $days];
    }

    /**
     * A cobranca que vence primeiro.
     *
     * @param array $charges
     * @return array
     */
    public static function earliest_charge(array $charges): array {
        $best = [];
        $bestkey = '';

        foreach ($charges as $charge) {
            if (!is_array($charge)) {
                continue;
            }
            $key = (string) ($charge['due_at'] ?? $charge['created_at'] ?? '');
            if ($key === '') {
                continue;
            }
            if ($bestkey === '' || $key < $bestkey) {
                $bestkey = $key;
                $best = $charge;
            }
        }

        // Sem data em nenhuma, devolve a primeira em vez de nada: uma cobranca
        // sem vencimento ainda e pagavel.
        if (!$best && $charges) {
            $first = reset($charges);
            $best = is_array($first) ? $first : [];
        }

        return $best;
    }

    /**
     * Processa a confirmacao de uma cobranca.
     *
     * Idempotente. O status vem SEMPRE da API, nunca do corpo do webhook: o
     * endpoint e publico, e aceitar o status de quem bate na porta seria
     * deixar qualquer um liberar curso com um POST.
     *
     * @param string $chargeid
     * @param string $subscriptionid
     * @return bool Se entregou algo agora.
     */
    public static function process_notification(string $chargeid, string $subscriptionid = ''): bool {
        global $DB;

        // Sem id nao ha o que procurar. O cartao cria a linha ANTES de existir
        // cobranca, entao existem linhas com chargeid vazio - e uma busca por
        // '' acharia uma delas, possivelmente a de outro aluno.
        if ($chargeid === '') {
            return false;
        }

        // Trava a linha (FOR UPDATE) ate o commit logo abaixo: o webhook e a
        // tarefa de reconciliacao horaria batendo no mesmo chargeid quase ao
        // mesmo tempo liam ambos paymentid vazio ANTES de qualquer escrita, e
        // chamavam save_payment()/record_sale() duas vezes para uma unica
        // cobranca real.
        $transaction = $DB->start_delegated_transaction();

        $record = $DB->get_record_sql(
            'SELECT * FROM {' . self::TABLE . '} WHERE chargeid = ? FOR UPDATE',
            [$chargeid]
        );

        // Ciclo 2 em diante: a cobranca nasce no gateway e o Moodle ainda nao
        // a conhece.
        if (!$record && $subscriptionid !== '') {
            // Trava a linha mais recente da ASSINATURA antes de decidir se
            // adota um ciclo novo - fecha a mesma janela de corrida para o
            // caso em que a linha ainda nao existe.
            $DB->get_record_sql(
                'SELECT id FROM {' . self::TABLE . '} WHERE subscriptionid = ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$subscriptionid]
            );
            $record = $DB->get_record(self::TABLE, ['chargeid' => $chargeid])
                ?: self::adopt_subscription_cycle($chargeid, $subscriptionid);
        }
        if (!$record) {
            $transaction->allow_commit();
            return false;
        }

        $apikey = credentials::api_key((int) $record->accountid, $record->environment);
        if ($apikey === '') {
            throw new moodle_exception('errornotlinked', 'paygw_pagarme', '', $record->environment);
        }

        $client = new pagarme_client($apikey);
        $charge = $client->get_charge($chargeid);
        $status = strtolower((string) ($charge['status'] ?? ''));

        $record->status = $status;
        $record->timemodified = time();

        // O que diz se ja entregamos e o paymentid, NAO o status.
        //
        // Havia aqui uma guarda de replay que comparava os dois status e
        // desistia quando os dois estavam pagos. Ela quebrava o cartao: o
        // cartao liquida na CRIACAO, entao create_charge_for() ja grava
        // 'paid', e o webhook seguinte concluia "isto ja estava pago" e ia
        // embora sem nunca ter entregue o curso. O aluno pagava e nao
        // recebia nada.
        //
        // Achado na prova de ponta a ponta em 11/09/2026; nenhum teste com
        // dublê pegaria, porque depende de a cobranca nascer paga.
        if (!self::is_paid($status) || !empty($record->paymentid)) {
            $DB->update_record(self::TABLE, $record);
            $transaction->allow_commit();
            return false;
        }

        $platformrecipient = credentials::platform_recipient((int) $record->accountid, $record->environment);

        // A comissao sai do EXTRATO, nao da cobranca. Medido em 11/09/2026: o
        // split acontece e `charge.splits` volta null do mesmo jeito - ler dali
        // gravaria zero em toda venda.
        $record->feeamount = $client->commission_for_charge($chargeid, $platformrecipient);

        // O payable pode demorar a aparecer. Antes de aceitar zero, tenta o
        // split embutido, que e o caminho que a documentacao descreve.
        if ($record->feeamount <= 0) {
            $record->feeamount = pagarme_client::commission_from($charge, $platformrecipient);
        }

        // Zero aqui quase sempre quer dizer "ainda nao", e nao "nunca": o
        // payable leva cerca de 16 segundos para nascer depois do pagamento
        // (medido em 11/09/2026), e o webhook chega antes disso.
        //
        // Mesmo assim NAO gravamos a comissao esperada no lugar. Gravar o que
        // ainda nao se viu e o erro que este projeto persegue desde o
        // marketplace_fee do Mercado Pago: o numero pareceria certo e ninguem
        // reconferiria. Fica zero, e a reconciliacao corrige quando o extrato
        // existir - ver task\reconcile::fix_missing_commission().
        if ($record->feeamount <= 0 && (float) $record->feepercent > 0) {
            debugging(
                'paygw_pagarme: cobranca ' . $chargeid . ' paga e ainda sem payable; '
                    . 'a comissao fica zero ate a reconciliacao ler o extrato.',
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
            'pagarme'
        );

        $DB->update_record(self::TABLE, $record);

        // Libera a trava aqui: record_sale()/deliver_order() sao mais lentas
        // (chamam o marketplace, matriculam o aluno) e segurar o lock da
        // linha durante isso so aumentaria contencao sem necessidade.
        $transaction->allow_commit();

        if (class_exists('\local_marketplace\api')) {
            $terms = null;
            if (class_exists('\local_marketplace\commission')) {
                $terms = new \local_marketplace\commission(
                    (float) $record->feepercent,
                    (string) $record->feebase,
                    (string) $record->feesource
                );
            }
            \local_marketplace\api::record_sale(
                $record->component,
                (int) $record->paymentid,
                (int) $record->itemid,
                (float) $record->feeamount,
                $chargeid,
                $terms,
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
     * Cria a linha de um ciclo seguinte, copiando o contexto do anterior.
     *
     * Os termos da comissao sao COPIADOS, nunca re-resolvidos: se a comissao
     * da empresa mudar no meio de uma assinatura, os ciclos ja vendidos
     * continuam valendo o que valiam.
     *
     * Devolve null para assinatura desconhecida. O webhook e publico, e adotar
     * uma assinatura que ninguem reconhece seria deixar qualquer POST criar
     * venda.
     *
     * @param string $chargeid
     * @param string $subscriptionid
     * @return \stdClass|null
     */
    public static function adopt_subscription_cycle(string $chargeid, string $subscriptionid): ?\stdClass {
        global $DB;

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

        $record = (object) [
            'orderid' => '',
            'chargeid' => $chargeid,
            'subscriptionid' => $subscriptionid,
            'customerid' => $previous->customerid,
            // A coluna e unica, entao o ciclo novo precisa da propria
            // referencia.
            'externalreference' => $previous->externalreference . '-c' . substr($chargeid, -8),
            'component' => $previous->component,
            'paymentarea' => $previous->paymentarea,
            'itemid' => $previous->itemid,
            'userid' => $previous->userid,
            'accountid' => $previous->accountid,
            'amount' => $previous->amount,
            'currency' => $previous->currency,
            'feepercent' => $previous->feepercent,
            'feeamount' => 0,
            'feebase' => $previous->feebase,
            'feesource' => $previous->feesource,
            'paymentmethod' => $previous->paymentmethod,
            'environment' => $previous->environment,
            'status' => 'pending',
            'paymentid' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        return $record;
    }

    /**
     * Motivo para nao mostrar o botao de estorno.
     *
     * @param \stdClass $record
     * @return string Chave de idioma, ou vazio quando pode estornar.
     */
    public static function refund_blocker(\stdClass $record): string {
        global $DB;

        $status = strtolower((string) $record->status);

        if ($status === 'canceled' || $status === 'partial_canceled') {
            return 'errorrefundalready';
        }
        if (!self::is_paid($status)) {
            return 'errorrefundnotpaid';
        }
        if (!in_array((string) $record->paymentmethod, self::REFUNDABLE_METHODS, true)) {
            return 'errorrefundmethod';
        }
        if (empty($record->subscriptionid)) {
            return '';
        }

        // Ciclo do meio: existe outra cobranca PAGA da mesma assinatura antes
        // desta. Estornar um mes do meio deixaria a assinatura cobrando os
        // seguintes, e o aluno com um mes devolvido sem parar de pagar.
        $earlier = $DB->get_records_select(
            self::TABLE,
            'subscriptionid = :sub AND id < :id AND paymentid IS NOT NULL',
            ['sub' => $record->subscriptionid, 'id' => (int) $record->id],
            '',
            'id',
            0,
            1
        );

        return $earlier ? 'errorrefundnotfirstcycle' : '';
    }

    /**
     * Estorna.
     *
     * Cancela a assinatura ANTES de estornar. Se o estorno falhar sobra uma
     * assinatura cancelada com um mes pago - ruim, mas reversivel. A ordem
     * inversa deixaria dinheiro devolvido e cobranca seguindo.
     *
     * @param \stdClass $record
     * @return bool
     */
    public static function refund(\stdClass $record): bool {
        global $DB;

        $blocker = self::refund_blocker($record);
        if ($blocker !== '') {
            throw new moodle_exception($blocker, 'paygw_pagarme');
        }

        $apikey = credentials::api_key((int) $record->accountid, $record->environment);
        if ($apikey === '') {
            throw new moodle_exception('errornotlinked', 'paygw_pagarme', '', $record->environment);
        }

        $client = new pagarme_client($apikey);

        if (!empty($record->subscriptionid)) {
            $client->cancel_subscription((string) $record->subscriptionid);
        }

        $response = $client->cancel_charge((string) $record->chargeid);

        $record->status = strtolower((string) ($response['status'] ?? 'canceled'));
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        return in_array($record->status, ['canceled', 'partial_canceled'], true);
    }

    /**
     * Fatura em aberto do ciclo corrente.
     *
     * Devolve null em falha de rede em vez de estourar: isto alimenta uma tela
     * e um e-mail, e nenhum dos dois pode quebrar porque o gateway piscou.
     *
     * @param \stdClass $record
     * @return array|null
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
            $client = new pagarme_client($apikey);
            $charges = $client->subscription_charges((string) $record->subscriptionid);
        } catch (\Throwable $e) {
            return null;
        }

        $open = array_filter($charges, static function ($charge): bool {
            $status = strtolower((string) ($charge['status'] ?? ''));

            return !self::is_paid($status) && !in_array($status, ['canceled', 'failed'], true);
        });

        $target = self::earliest_charge($open);
        if (!$target) {
            return null;
        }

        return [
            'url' => self::transaction_url($target),
            'duedate' => (string) ($target['due_at'] ?? ''),
            'value' => pagarme_client::from_cents((int) ($target['amount'] ?? 0)),
            'line' => (string) (($target['last_transaction'] ?? [])['line'] ?? ''),
        ];
    }

    /**
     * Este gateway consegue cobrar assinatura com split?
     *
     * NAO, e a recusa e medida. Em 11/09/2026, com os recebedores ja
     * liberados e o split provado na venda avulsa, o `POST /subscriptions`
     * com split respondeu 400 "The request is invalid" em quatro formatos:
     * v5 padrao, estilo v4 (`percentage`/`liable` soltos), sem `options`, e
     * com `charge_remainder` no singular. Dentro de `items[]` o `200` volta
     * com o split **descartado** - nenhum payable e gerado.
     *
     * O `PATCH /subscriptions/{id}/split` responde 412 "Can't update the
     * split on subscription because doesn't has split", o que indica que a
     * capacidade existe e precisa ser habilitada na conta - como os
     * recebedores precisaram.
     *
     * Ha ainda um segundo impedimento, independente deste: o filtro
     * `?subscription_id=` de `GET /charges` e **ignorado** - um id inventado
     * devolve a conta inteira - e as cobrancas nao carregam vinculo com a
     * assinatura. Nao ha como listar as cobrancas de uma assinatura, o que
     * derruba pending_invoice() e a escolha do vencimento mais proximo.
     *
     * Enquanto isso, cobrar recorrencia aqui renderia comissao ZERO sem
     * acusar erro. Recusar na porta e a saida honesta - e e o mesmo lugar em
     * que o paygw_mercadopago esta, que nao tem recorrencia nenhuma.
     *
     * @return bool
     */
    public static function supports_recurring(): bool {
        return false;
    }

    /**
     * Chave de idioma que explica a recusa da recorrencia.
     *
     * @return string
     */
    public static function recurring_blocker(): string {
        return 'errorrecurringunsupported';
    }

    /**
     * Situacoes que a reconciliacao ainda precisa conferir.
     *
     * 'processing' entra junto com 'pending' por medicao, nao por teoria: em
     * 09/09/2026 uma cobranca de cartao ficou em 'processing' e nunca saiu de
     * la. Varrer so 'pending' deixaria a linha parada para sempre, e o aluno
     * que pagou sem acesso.
     *
     * @param string $status
     * @return bool
     */
    public static function is_sweepable(string $status): bool {
        return in_array(strtolower($status), ['pending', 'processing'], true);
    }

    /**
     * Por quanto tempo vale continuar perguntando por uma cobranca.
     *
     * Existe porque o Pagar.me nunca diz que a cobranca venceu. MEDIDO em
     * 14/09/2026: Pix pendente nao cancela - `412 "This charge cannot be
     * canceled because is pending"` - e o status NAO vira expirado. As
     * cobrancas de teste de 11/09 seguiam 'pending' tres dias depois.
     *
     * Sem isto, um checkout abandonado seria consultado de hora em hora por 30
     * dias: cerca de 720 chamadas a API do VENDEDOR por cobranca que nunca vai
     * ser paga. A conta e dele, nao nossa.
     *
     * A janela sai da propria forma de pagamento, e nao de um numero redondo:
     * o Pix expira no prazo do QR Code, o boleto e pago ate o vencimento e
     * ainda leva dias para compensar, e o cartao liquida na hora.
     *
     * @param string $method pix|boleto|credit_card
     * @return int Segundos desde a criacao.
     */
    public static function sweep_window_for(string $method): int {
        switch ($method) {
            case 'pix':
                // A validade do QR Code mais uma hora: o payable demora, e
                // um Pix pago no ultimo minuto ainda precisa ser visto.
                return self::pix_expires_in() + HOURSECS;

            case 'boleto':
                // O vencimento mais tres dias, que e a compensacao bancaria.
                return (self::due_days() * DAYSECS) + (3 * DAYSECS);

            case 'credit_card':
                // Liquida na hora. O que passa de um dia esta travado, e
                // insistir nao resolve - foi o caso da cobranca que ficou em
                // 'processing' e nunca saiu de la.
                return DAYSECS;
        }

        // Forma desconhecida cai no menor prazo util, e nao no maior: errar
        // para menos custa uma venda reconciliada pelo webhook, que e o
        // caminho normal. Errar para mais custa chamada na conta do vendedor.
        return DAYSECS;
    }

    /**
     * Id da cobranca que um evento de webhook aponta.
     *
     * O formato difere: evento de charge traz o proprio id, evento de order
     * traz a cobranca dentro de charges[]. Um order sem charges devolve vazio
     * de proposito - devolver o id da order faria procurar uma linha por
     * or_..., que no melhor caso nao acha nada.
     *
     * @param string $event
     * @param array $data
     * @return string
     */
    public static function charge_id_from_event(string $event, array $data): string {
        if (str_starts_with(strtolower($event), 'order.')) {
            $charge = ($data['charges'] ?? [null])[0] ?? [];

            return is_array($charge) ? (string) ($charge['id'] ?? '') : '';
        }

        return (string) ($data['id'] ?? '');
    }

    /**
     * Id da assinatura que um evento de webhook carrega, se houver.
     *
     * Serve so para identificar o ciclo. Nunca e fonte da verdade: quem diz se
     * foi pago e a releitura da cobranca na API.
     *
     * @param array $data
     * @return string
     */
    public static function subscription_id_from_event(array $data): string {
        $subscription = $data['subscription'] ?? [];

        return is_array($subscription) ? (string) ($subscription['id'] ?? '') : '';
    }

    /**
     * Para onde mandar o aluno, conforme a forma de pagamento.
     *
     * O Pix quer a IMAGEM do QR Code, porque a pagina que a mostra e nossa. O
     * boleto quer a pagina do Pagar.me, que ja traz codigo de barras e PDF. A
     * ordem generica de transaction_url() serve ao boleto e atrapalha o Pix.
     *
     * @param string $method
     * @param array $charge
     * @return string
     */
    public static function checkout_url_for(string $method, array $charge): string {
        $transaction = $charge['last_transaction'] ?? [];
        if (!is_array($transaction)) {
            return '';
        }

        if ($method === 'pix') {
            return (string) ($transaction['qr_code_url'] ?? '');
        }

        return self::transaction_url($charge);
    }

    /**
     * Onde o aluno paga esta cobranca.
     *
     * @param array $charge
     * @return string
     */
    public static function transaction_url(array $charge): string {
        $transaction = $charge['last_transaction'] ?? [];
        if (!is_array($transaction)) {
            return '';
        }

        foreach (['url', 'pdf', 'qr_code_url'] as $field) {
            if (!empty($transaction[$field])) {
                return (string) $transaction[$field];
            }
        }

        return '';
    }

    /**
     * URL do boleto de uma linha.
     *
     * @param \stdClass $record
     * @return string
     */
    public static function boleto_url(\stdClass $record): string {
        return (string) ($record->checkouturl ?? '');
    }

    /**
     * Dados do comprador para a API.
     *
     * @param int $userid
     * @return array
     */
    public static function build_customer(int $userid): array {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $document = self::buyer_document($user);
        if ($document === '') {
            throw new moodle_exception('errornodocument', 'paygw_pagarme');
        }

        $customer = [
            'name' => fullname($user),
            'email' => $user->email,
            'type' => strlen($document) > 11 ? 'company' : 'individual',
            'document' => $document,
            'document_type' => strlen($document) > 11 ? 'CNPJ' : 'CPF',
        ];

        // O telefone e OBRIGATORIO, e isso foi medido: sem ele a cobranca
        // nasce e o adquirente recusa com "412 At least one customer phone is
        // required". Inventar um numero seria pior - passaria na validacao e
        // viraria dado errado no cadastro do vendedor, onde ninguem procura.
        //
        // Entao recusa aqui, como no CPF: a falha vai para a tela do aluno
        // antes de qualquer cobranca existir, com o que ele precisa fazer.
        $phone = self::buyer_phone($user);
        if (!$phone) {
            throw new moodle_exception('errornophone', 'paygw_pagarme');
        }
        $customer['phones'] = ['mobile_phone' => $phone];

        return $customer;
    }

    /**
     * Telefone do comprador, quebrado no formato da API.
     *
     * @param \stdClass $user
     * @return array Vazio quando o usuario nao tem telefone utilizavel.
     */
    public static function buyer_phone(\stdClass $user): array {
        foreach (['phone2', 'phone1'] as $field) {
            $digits = preg_replace('/\D/', '', (string) ($user->{$field} ?? ''));
            if ($digits === '') {
                continue;
            }

            // Numero brasileiro com codigo do pais colado na frente.
            if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
                $digits = substr($digits, 2);
            }

            // Sem DDD nao da para montar o objeto que a API espera.
            if (strlen($digits) < 10) {
                continue;
            }

            return [
                'country_code' => '55',
                'area_code' => substr($digits, 0, 2),
                'number' => substr($digits, 2),
            ];
        }

        return [];
    }

    /**
     * CPF ou CNPJ do comprador, so os digitos.
     *
     * @param \stdClass $user
     * @return string
     */
    protected static function buyer_document(\stdClass $user): string {
        global $CFG;

        $field = (string) get_config('paygw_pagarme', 'documentfield');
        if ($field === '') {
            return '';
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');
        $profile = profile_user_record($user->id);

        return preg_replace('/\D/', '', (string) ($profile->{$field} ?? ''));
    }

    /**
     * Nome do item para o extrato do aluno.
     *
     * @param string $component
     * @param int $itemid
     * @param string $paymentarea 'offer' (padrao) ou service_provider::PAYMENT_AREA_PLAN
     * @return string
     */
    protected static function describe_item(string $component, int $itemid, string $paymentarea = 'offer'): string {
        if ($component !== 'local_marketplace') {
            return get_string('defaultdescription', 'paygw_pagarme');
        }

        // A paymentarea 'plan' usa companyid como itemid, e o que ha para
        // descrever e o PLANO contratado - nao uma oferta, que nem existe.
        // Na pratica nunca chega aqui: assinatura e sempre recusada na
        // porta neste gateway (ver supports_recurring()), mas a funcao fica
        // correta para o dia em que isso mudar.
        if ($paymentarea === 'plan' && class_exists('\local_marketplace\company')) {
            $company = \local_marketplace\company::get_record(['id' => $itemid]);
            $plan = $company ? $company->get_plan() : null;
            if ($plan) {
                return (string) $plan->get('name');
            }

            return get_string('defaultdescription', 'paygw_pagarme');
        }

        if (class_exists('\local_marketplace\offer')) {
            $offer = \local_marketplace\offer::get_record(['id' => $itemid]);
            if ($offer) {
                return (string) $offer->get('name');
            }
        }

        return get_string('defaultdescription', 'paygw_pagarme');
    }

    /**
     * O dinheiro entrou?
     *
     * @param string $status
     * @return bool
     */
    public static function is_paid(string $status): bool {
        return in_array(strtolower($status), self::PAID_STATUSES, true);
    }

    /**
     * O evento merece consulta a API?
     *
     * Nome de evento nao e valor de status: order.paid e evento, paid e
     * status. Confundir os dois ja custou uma volta neste projeto.
     *
     * @param string $event
     * @return bool
     */
    public static function is_relevant_event(string $event): bool {
        // O evento subscription.canceled fica de FORA de proposito. O 'data'
        // dele e a assinatura, entao o id seria sub_... e seria procurado como
        // se fosse cobranca. E nao ha o que fazer com ele: assinatura
        // cancelada apenas deixa de gerar cobranca, e o acesso ja pago corre
        // ate vencer pelo direito, nao por aviso do gateway.
        return in_array(strtolower($event), [
            'order.paid',
            'charge.paid',
            'charge.refunded',
            'order.payment_failed',
            'charge.payment_failed',
        ], true);
    }

    /**
     * Meio de pagamento configurado.
     *
     * @return string
     */
    public static function payment_method(): string {
        $configured = (string) get_config('paygw_pagarme', 'paymentmethod');

        return in_array($configured, ['pix', 'boleto', 'credit_card'], true) ? $configured : 'pix';
    }

    /**
     * Segundos de validade do QR Code.
     *
     * A API exige entre 15 e 60 minutos, e um valor fora disso e recusado.
     *
     * @return int
     */
    public static function pix_expires_in(): int {
        $minutes = (int) get_config('paygw_pagarme', 'pixexpiresin');
        $minutes = $minutes > 0 ? $minutes : 30;
        $minutes = max(15, min(60, $minutes));

        return $minutes * MINSECS;
    }

    /**
     * Dias ate o boleto vencer.
     *
     * @return int
     */
    public static function due_days(): int {
        $days = (int) get_config('paygw_pagarme', 'duedays');

        return $days > 0 ? $days : 3;
    }

    /**
     * Endereco do webhook, para a tela de configuracao.
     *
     * @return moodle_url
     */
    public static function webhook_url(): moodle_url {
        return new moodle_url('/payment/gateway/pagarme/webhook.php');
    }
}
