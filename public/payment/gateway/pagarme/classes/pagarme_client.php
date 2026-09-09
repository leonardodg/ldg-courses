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

use curl;
use moodle_exception;

/**
 * Toda a conversa HTTP com o Pagar.me passa por aqui.
 *
 * A costura make_curl() existe desde a primeira linha, e nao por gosto: o
 * mp_client do paygw_mercadopago instancia curl inline, e por isso o
 * payment_processor dele tem zero cobertura de teste ate hoje. Montagem de
 * corpo e mapeamento de erro so se testam sem rede quando o transporte e
 * substituivel.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pagarme_client {
    /** @var string Ambiente de homologacao. */
    const ENV_SANDBOX = 'sandbox';

    /** @var string Ambiente de producao. */
    const ENV_PRODUCTION = 'production';

    /**
     * Endereco unico, para os dois ambientes.
     *
     * Medido em 09/09/2026: https://sdx-api.pagar.me/core/v5 responde
     * 404 "no Route matched with those values" e nao existe. Quem separa
     * homologacao de producao e o PREFIXO DA CHAVE, nao a URL - por isso aqui
     * ha uma constante e nao um mapa por ambiente como no asaas_client.
     *
     * @var string
     */
    const BASE_URL = 'https://api.pagar.me/core/v5';

    /** @var string Prefixo das chaves de homologacao. */
    const TEST_KEY_PREFIX = 'sk_test_';

    /** @var int Segundos ate desistir da resposta. */
    const TIMEOUT = 20;

    /** @var int Segundos ate desistir da conexao. */
    const CONNECT_TIMEOUT = 10;

    /** @var string Chave secreta do vendedor. */
    protected string $apikey;

    /**
     * Guarda a chave que vai assinar todas as chamadas.
     *
     * @param string $apikey Chave secreta do vendedor (sk_...).
     */
    public function __construct(string $apikey) {
        $this->apikey = trim($apikey);
    }

    /**
     * Ambiente a que uma chave pertence.
     *
     * Erra para o lado de producao quando a chave nao e reconhecida: tratar
     * chave desconhecida como homologacao faria uma cobranca real ser lida
     * como teste.
     *
     * @param string $apikey
     * @return string sandbox|production
     */
    public static function environment_of_key(string $apikey): string {
        return str_starts_with(trim($apikey), self::TEST_KEY_PREFIX)
            ? self::ENV_SANDBOX
            : self::ENV_PRODUCTION;
    }

    /**
     * Ambiente da chave em uso.
     *
     * @return string
     */
    public function get_environment(): string {
        return self::environment_of_key($this->apikey);
    }

    /**
     * Converte moeda para centavos, que e a unidade da API.
     *
     * @param float $value
     * @return int
     */
    public static function to_cents(float $value): int {
        return (int) round($value * 100);
    }

    /**
     * Converte centavos de volta para moeda.
     *
     * @param int $cents
     * @return float
     */
    public static function from_cents(int $cents): float {
        return round($cents / 100, 2);
    }

    /**
     * Monta as regras de split de uma cobranca.
     *
     * Sao sempre DUAS regras, e a soma cobre o valor inteiro. A do vendedor
     * carrega liable, charge_processing_fee e charge_remainder_fee: a
     * documentacao exige que ao menos um recebedor seja responsavel pelas
     * tres coisas, e a regra fiscal do projeto ja dizia que quem vende arca
     * com o custo, porque e quem emite a nota (ADR-0003).
     *
     * A base decide o tipo, e por um motivo concreto:
     *
     * - 'gross' vira 'flat' com o valor calculado por nos. Determinista, e
     *   imune a duvida sobre o que a API entende por percentual.
     * - 'net' vira 'percentage' e deixa o Pagar.me dividir.
     *
     * NAO MEDIDO: sobre o que o 'percentage' do Pagar.me incide - bruto ou
     * liquido. A documentacao nao diz, e a conta de homologacao nao processou
     * cobranca nenhuma ate agora. No Asaas o percentual incide sobre o
     * liquido; se aqui for sobre o bruto, a base 'net' entrega comissao maior
     * que a pedida e este metodo precisa mudar. Ver
     * docs/data-validation/pagarme-sandbox.md.
     *
     * @param string $sellerrecipient Recebedor do vendedor (rp_...).
     * @param string $platformrecipient Recebedor da plataforma (rp_...).
     * @param float $percent Comissao da plataforma, 0 a 100.
     * @param float $grossvalue Valor cheio da cobranca, em moeda.
     * @param string $base gross|net
     * @return array Regras prontas para o campo split, ou vazio.
     */
    public static function build_split(
        string $sellerrecipient,
        string $platformrecipient,
        float $percent,
        float $grossvalue,
        string $base = 'gross'
    ): array {
        $sellerrecipient = trim($sellerrecipient);
        $platformrecipient = trim($platformrecipient);

        // Sem os dois recebedores nao ha divisao possivel. Devolver vazio faz
        // a cobranca nascer inteira do vendedor, que e o desfecho seguro:
        // comissao esquecida se corrige, dinheiro mandado para o recebedor
        // errado nao.
        if ($sellerrecipient === '' || $platformrecipient === '') {
            return [];
        }
        if ($sellerrecipient === $platformrecipient) {
            return [];
        }
        if ($percent <= 0 || $grossvalue <= 0) {
            return [];
        }

        $percent = min($percent, 100.0);

        // O vendedor e o responsavel nas tres opcoes, sempre.
        $sellingoptions = [
            'liable' => true,
            'charge_processing_fee' => true,
            'charge_remainder_fee' => true,
        ];
        $platformoptions = [
            'liable' => false,
            'charge_processing_fee' => false,
            'charge_remainder_fee' => false,
        ];

        if ($base === 'net') {
            $platformshare = round($percent, 2);
            $sellershare = round(100 - $platformshare, 2);

            // Comissao de 100% deixaria o vendedor com uma regra de valor
            // zero, e regra zerada e recusada pela API. Mesma guarda que o
            // ramo do bruto ja tinha - a falta dela aqui foi achada por
            // teste, nao por leitura.
            if ($sellershare <= 0) {
                return [];
            }

            return [
                [
                    'amount' => $sellershare,
                    'recipient_id' => $sellerrecipient,
                    'type' => 'percentage',
                    'options' => $sellingoptions,
                ],
                [
                    'amount' => $platformshare,
                    'recipient_id' => $platformrecipient,
                    'type' => 'percentage',
                    'options' => $platformoptions,
                ],
            ];
        }

        $total = self::to_cents($grossvalue);
        $commission = (int) round($total * ($percent / 100));

        // O arredondamento pode zerar a comissao em valores muito pequenos.
        // Split de valor zero e recusado pela API, entao some a regra inteira.
        if ($commission <= 0) {
            return [];
        }
        $commission = min($commission, $total);
        $remainder = $total - $commission;
        if ($remainder <= 0) {
            return [];
        }

        return [
            [
                'amount' => $remainder,
                'recipient_id' => $sellerrecipient,
                'type' => 'flat',
                'options' => $sellingoptions,
            ],
            [
                'amount' => $commission,
                'recipient_id' => $platformrecipient,
                'type' => 'flat',
                'options' => $platformoptions,
            ],
        ];
    }

    /**
     * O que de fato aconteceu com uma cobranca.
     *
     * Existe porque o status HTTP do POST /orders nao vale como sinal.
     * Medido em 09/09/2026: uma order com split apontando para dois
     * recipient_id INVENTADOS foi aceita com HTTP 200 e corpo completo - id,
     * code, cliente criado, item ativo. So a releitura denunciou, com
     * splits: null e "404 Recipient not found" enterrado no gateway_response.
     *
     * @param array $charge Cobranca vinda da API.
     * @return array [status, codigo do adquirente, mensagem de erro]
     */
    public static function charge_verdict(array $charge): array {
        $status = strtolower((string) ($charge['status'] ?? ''));
        $transaction = $charge['last_transaction'] ?? [];
        $response = is_array($transaction) ? ($transaction['gateway_response'] ?? []) : [];

        $code = (string) ($response['code'] ?? '');
        $messages = [];
        foreach (($response['errors'] ?? []) as $error) {
            if (!empty($error['message'])) {
                $messages[] = (string) $error['message'];
            }
        }

        return [$status, $code, implode(' | ', $messages)];
    }

    /**
     * Comissao efetivamente destinada a plataforma numa cobranca.
     *
     * Le o split de volta em vez de recalcular. Split recusado, cancelado ou
     * ausente precisa aparecer como zero, e nao como o valor que se pediu -
     * foi a leitura de volta que separou o Asaas do Mercado Pago.
     *
     * @param array $charge Cobranca vinda da API.
     * @param string $platformrecipient Recebedor da plataforma.
     * @return float Em moeda.
     */
    public static function commission_from(array $charge, string $platformrecipient): float {
        $platformrecipient = trim($platformrecipient);
        if ($platformrecipient === '') {
            return 0.0;
        }

        $splits = $charge['splits'] ?? null;
        if (!is_array($splits) || !$splits) {
            return 0.0;
        }

        $cents = 0;
        foreach ($splits as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if ((string) ($rule['recipient_id'] ?? '') !== $platformrecipient) {
                continue;
            }
            $cents += (int) ($rule['amount'] ?? 0);
        }

        return self::from_cents($cents);
    }

    /**
     * Cria um pedido.
     *
     * @param array $body
     * @return array
     */
    public function create_order(array $body): array {
        return $this->request('POST', '/orders', $body);
    }

    /**
     * Le um pedido.
     *
     * @param string $orderid
     * @return array
     */
    public function get_order(string $orderid): array {
        return $this->request('GET', '/orders/' . rawurlencode($orderid));
    }

    /**
     * Le uma cobranca.
     *
     * @param string $chargeid
     * @return array
     */
    public function get_charge(string $chargeid): array {
        return $this->request('GET', '/charges/' . rawurlencode($chargeid));
    }

    /**
     * Estorna uma cobranca.
     *
     * O Pagar.me nao separa cancelar de estornar: o DELETE serve para os dois,
     * e o que muda e o estado em que a cobranca estava. Sem $amount o estorno
     * e total.
     *
     * NAO MEDIDO: se o estorno reverte o split, e se o parcial reduz a
     * comissao. No Asaas o parcial devolveu sucesso e deixou o split cheio,
     * e por isso la so existe estorno total. Ate medir, o payment_processor
     * so oferece o total.
     *
     * @param string $chargeid
     * @param float|null $amount Valor parcial, em moeda. Null estorna tudo.
     * @return array
     */
    public function cancel_charge(string $chargeid, ?float $amount = null): array {
        $body = null;
        if ($amount !== null && $amount > 0) {
            $body = ['amount' => self::to_cents($amount)];
        }

        return $this->request('DELETE', '/charges/' . rawurlencode($chargeid), $body);
    }

    /**
     * Cria uma assinatura sem plano.
     *
     * @param array $body
     * @return array
     */
    public function create_subscription(array $body): array {
        return $this->request('POST', '/subscriptions', $body);
    }

    /**
     * Le uma assinatura.
     *
     * @param string $subscriptionid
     * @return array
     */
    public function get_subscription(string $subscriptionid): array {
        return $this->request('GET', '/subscriptions/' . rawurlencode($subscriptionid));
    }

    /**
     * Cancela uma assinatura.
     *
     * @param string $subscriptionid
     * @return array
     */
    public function cancel_subscription(string $subscriptionid): array {
        return $this->request('DELETE', '/subscriptions/' . rawurlencode($subscriptionid));
    }

    /**
     * Troca o cartao de uma assinatura.
     *
     * @param string $subscriptionid
     * @param array $card Dados do cartao ou ['card_token' => '...'].
     * @return array
     */
    public function update_subscription_card(string $subscriptionid, array $card): array {
        return $this->request(
            'PATCH',
            '/subscriptions/' . rawurlencode($subscriptionid) . '/card',
            $card
        );
    }

    /**
     * Cobrancas de uma assinatura.
     *
     * @param string $subscriptionid
     * @return array Lista crua, na ordem em que a API devolveu.
     */
    public function subscription_charges(string $subscriptionid): array {
        $response = $this->request(
            'GET',
            '/charges?subscription_id=' . rawurlencode($subscriptionid) . '&size=30'
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    /**
     * Confere que um recebedor existe e esta ativo.
     *
     * @param string $recipientid
     * @return array
     */
    public function get_recipient(string $recipientid): array {
        return $this->request('GET', '/recipients/' . rawurlencode($recipientid));
    }

    /**
     * Recebedor padrao da conta, que e o do proprio vendedor.
     *
     * Devolve vazio quando nao ha, em vez de estourar: conta recem-criada
     * responde 412 aqui, e isso e configuracao pendente do vendedor, nao
     * falha do plugin.
     *
     * @return array
     */
    public function get_default_recipient(): array {
        try {
            return $this->request('GET', '/recipients/default');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Acha ou cria o comprador.
     *
     * @param array $customer Corpo do comprador.
     * @return string Id do cliente no Pagar.me.
     */
    public function find_or_create_customer(array $customer): string {
        $email = (string) ($customer['email'] ?? '');
        if ($email !== '') {
            $found = $this->request('GET', '/customers?email=' . rawurlencode($email) . '&size=1');
            $first = $found['data'][0] ?? null;
            if (is_array($first) && !empty($first['id'])) {
                return (string) $first['id'];
            }
        }

        $created = $this->request('POST', '/customers', $customer);

        return (string) ($created['id'] ?? '');
    }

    /**
     * Executa a chamada.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @return array
     */
    protected function request(string $method, string $path, ?array $body = null): array {
        $url = self::BASE_URL . $path;

        $curl = $this->make_curl();

        // Basic com a chave como usuario e senha vazia. Nao e Bearer, e nao e
        // header proprio como o access_token do Asaas.
        $curl->setHeader([
            'Authorization: Basic ' . base64_encode($this->apikey . ':'),
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Moodle paygw_pagarme',
        ]);

        $options = [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT,
            'CURLOPT_RETURNTRANSFER' => 1,
        ];

        $payload = $body === null ? '' : json_encode($body);

        switch ($method) {
            case 'GET':
                $response = $curl->get($url, [], $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, $payload, $options);
                break;
            case 'PATCH':
                $response = $curl->patch($url, $payload, $options);
                break;
            default:
                $response = $curl->post($url, $payload, $options);
        }

        return $this->decode($curl, $response);
    }

    /**
     * Cria o curl. E a costura que os testes substituem.
     *
     * @return curl
     */
    protected function make_curl(): curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        return new curl();
    }

    /**
     * Traduz a resposta, ou estoura com o motivo legivel.
     *
     * @param curl $curl
     * @param string|bool $response
     * @return array
     * @throws moodle_exception
     */
    protected function decode(curl $curl, $response): array {
        if ($curl->get_errno()) {
            throw new moodle_exception('errorcurl', 'paygw_pagarme', '', $curl->error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new moodle_exception('errorinvalidresponse', 'paygw_pagarme');
        }

        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new moodle_exception(
                'errorapi',
                'paygw_pagarme',
                '',
                self::describe_errors($decoded, $status)
            );
        }

        return $decoded;
    }

    /**
     * Achata o erro da API numa linha.
     *
     * @param array $decoded
     * @param int $status
     * @return string
     */
    protected static function describe_errors(array $decoded, int $status): string {
        $parts = [];

        if (!empty($decoded['message'])) {
            $parts[] = (string) $decoded['message'];
        }

        // O campo errors vem como mapa de campo para lista de mensagens.
        foreach (($decoded['errors'] ?? []) as $field => $messages) {
            $messages = is_array($messages) ? $messages : [$messages];
            foreach ($messages as $message) {
                $parts[] = is_string($field) && !is_numeric($field)
                    ? $field . ': ' . $message
                    : (string) $message;
            }
        }

        return $status . ' - ' . ($parts ? implode(' | ', $parts) : 'sem detalhe');
    }
}
