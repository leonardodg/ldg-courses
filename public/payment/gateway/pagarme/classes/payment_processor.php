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
     * Boleto fica de fora: no Asaas ele nao estorna em circunstancia nenhuma,
     * e ate medir aqui o botao nao aparece. Errar para o lado de nao oferecer
     * custa um clique; errar para o outro custa um erro cru da API na cara do
     * gerente, que ja aconteceu neste projeto.
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

        // Termos da comissao. Os valores de queda existem para o gateway
        // funcionar com qualquer componente do core_payment, nao so com o
        // marketplace.
        $feepercent = 25.0;
        $feebase = 'gross';
        $feesource = 'site';
        if (class_exists('\local_marketplace\api')) {
            $terms = \local_marketplace\api::commission_terms_for($component, $itemid);
            $feepercent = (float) $terms->percent;
            $feebase = (string) $terms->base;
            $feesource = (string) $terms->source;
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
     * @return \stdClass Linha atualizada.
     */
    public static function create_charge_for(\stdClass $record, string $cardtoken = ''): \stdClass {
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
            $recurrence = \local_marketplace\api::recurrence_for($record->component, (int) $record->itemid);
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
            $response = $client->create_order(self::order_body($record, $customer, $split, $cardtoken));
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
            $record->checkouturl = self::transaction_url($charge);
        }

        $DB->update_record(self::TABLE, $record);

        // O HTTP 200 do POST nao diz nada: uma order com recipient inexistente
        // tambem volta 200, e so a releitura denuncia. Estourar aqui e o que
        // impede o aluno de encarar um QR Code que nunca vai ser pago.
        [$status, $code, $message] = pagarme_client::charge_verdict($charge ?: []);
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
     * @return array
     */
    public static function order_body(
        \stdClass $record,
        array $customer,
        array $split,
        string $cardtoken = ''
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
                'description' => self::describe_item($record->component, (int) $record->itemid),
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
                'description' => self::describe_item($record->component, (int) $record->itemid),
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

        $record = $DB->get_record(self::TABLE, ['chargeid' => $chargeid]);

        // Ciclo 2 em diante: a cobranca nasce no gateway e o Moodle ainda nao
        // a conhece.
        if (!$record && $subscriptionid !== '') {
            $record = self::adopt_subscription_cycle($chargeid, $subscriptionid);
        }
        if (!$record) {
            return false;
        }

        $apikey = credentials::api_key((int) $record->accountid, $record->environment);
        if ($apikey === '') {
            throw new moodle_exception('errornotlinked', 'paygw_pagarme', '', $record->environment);
        }

        $charge = (new pagarme_client($apikey))->get_charge($chargeid);
        $status = strtolower((string) ($charge['status'] ?? ''));

        // Replay do mesmo evento.
        if (self::is_paid((string) $record->status) && self::is_paid($status)) {
            return false;
        }

        $record->status = $status;
        $record->timemodified = time();

        if (!self::is_paid($status) || !empty($record->paymentid)) {
            $DB->update_record(self::TABLE, $record);
            return false;
        }

        $platformrecipient = credentials::platform_recipient((int) $record->accountid, $record->environment);
        $record->feeamount = pagarme_client::commission_from($charge, $platformrecipient);

        // Comissao esperada que voltou zero quer dizer split recusado ou
        // ausente. A venda entra assim mesmo - o aluno pagou - mas o registro
        // precisa gritar, senao vira venda sem comissao que ninguem percebe.
        if ($record->feeamount <= 0 && (float) $record->feepercent > 0) {
            debugging(
                'paygw_pagarme: cobranca ' . $chargeid . ' paga sem split - comissao zero.',
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
                $terms
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

        return [
            'name' => fullname($user),
            'email' => $user->email,
            'type' => strlen($document) > 11 ? 'company' : 'individual',
            'document' => $document,
            'document_type' => strlen($document) > 11 ? 'CNPJ' : 'CPF',
            'phones' => [
                'mobile_phone' => [
                    'country_code' => '55',
                    'area_code' => '11',
                    'number' => '999999999',
                ],
            ],
        ];
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
     * @return string
     */
    protected static function describe_item(string $component, int $itemid): string {
        if ($component === 'local_marketplace' && class_exists('\local_marketplace\offer')) {
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
        return in_array(strtolower($event), [
            'order.paid',
            'charge.paid',
            'charge.refunded',
            'order.payment_failed',
            'charge.payment_failed',
            'subscription.canceled',
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
