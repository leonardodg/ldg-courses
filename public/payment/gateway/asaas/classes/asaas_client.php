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

use curl;
use moodle_exception;

/**
 * Unico ponto de contato com a API do Asaas.
 *
 * Todo HTTP do plugin passa por aqui. Nao usamos SDK: o Asaas nao publica um
 * oficial para PHP, e um de terceiro traria uma dependencia de composer dentro
 * de um plugin do Moodle, que o moodle-plugin-ci nao instala.
 *
 * A costura de transporte - make_curl() - existe de proposito. O cliente
 * equivalente do Mercado Pago instancia curl inline, e por isso o
 * payment_processor dele tem zero cobertura de teste ate hoje: nao ha onde
 * entrar. Aqui um teste estende a classe, devolve um transporte falso e
 * exercita a montagem do corpo, o split e o mapeamento de erro sem rede.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asaas_client {
    /** @var string Ambiente de homologacao. Chaves comecam com aact_hmlg_. */
    const ENV_SANDBOX = 'sandbox';

    /** @var string Ambiente real. */
    const ENV_PRODUCTION = 'production';

    /**
     * Base da API por ambiente.
     *
     * Contas, dados, configuracoes e chaves NAO sao compartilhados entre os
     * dois. Uma chave de homologacao na base de producao devolve 401, e o
     * contrario tambem - por isso ambiente e chave andam sempre juntos, no
     * construtor, em vez de a base ser um detalhe global.
     *
     * @var array<string, string>
     */
    const BASE_URL = [
        self::ENV_SANDBOX => 'https://api-sandbox.asaas.com/v3',
        self::ENV_PRODUCTION => 'https://api.asaas.com/v3',
    ];

    /** @var int Segundos de espera por resposta. */
    const TIMEOUT = 20;

    /** @var int Segundos de espera pela conexao. */
    const CONNECT_TIMEOUT = 10;

    /** @var string Chave de API da conta que fala com a API. */
    protected string $apikey;

    /** @var string sandbox|production */
    protected string $environment;

    /**
     * Construtor.
     *
     * @param string $apikey Chave da conta - do VENDEDOR na criacao de cobranca.
     * @param string $environment sandbox|production
     */
    public function __construct(string $apikey, string $environment = self::ENV_SANDBOX) {
        $this->apikey = $apikey;
        $this->environment = isset(self::BASE_URL[$environment]) ? $environment : self::ENV_SANDBOX;
    }

    /**
     * Ambiente deste cliente.
     *
     * @return string
     */
    public function get_environment(): string {
        return $this->environment;
    }

    /**
     * O ambiente que a propria chave declara.
     *
     * A chave de homologacao carrega o prefixo aact_hmlg_. Conferir isso ANTES
     * de chamar a API transforma o erro mais provavel da tela de configuracao -
     * colar a chave do ambiente errado - numa mensagem que diz o que houve, em
     * vez de um 401 generico depois de uma ida a rede.
     *
     * @param string $apikey
     * @return string sandbox|production
     */
    public static function environment_of_key(string $apikey): string {
        return str_starts_with(trim($apikey), '$aact_hmlg_') || str_starts_with(trim($apikey), 'aact_hmlg_')
            ? self::ENV_SANDBOX
            : self::ENV_PRODUCTION;
    }

    /**
     * Dados da conta dona da chave.
     *
     * Serve para validar a chave na hora de salvar a configuracao: uma chave
     * invalida tem que ser recusada ali, e nao no checkout do primeiro aluno.
     *
     * @return array
     */
    public function get_my_account(): array {
        return $this->request('GET', '/myAccount');
    }

    /**
     * Carteira da conta dona da chave.
     *
     * O walletId e o que identifica a conta como recebedora de um split. Ele e
     * descoberto aqui e nao digitado: pedir para o vendedor copiar um UUID a
     * mao seria um campo a mais para errar, e o erro so apareceria no primeiro
     * split - dinheiro indo para a carteira de outra pessoa.
     *
     * @return string UUID, ou vazio se a conta nao tem carteira.
     */
    public function get_wallet_id(): string {
        $response = $this->request('GET', '/wallets?limit=1');
        $first = $response['data'][0] ?? [];

        return (string) ($first['id'] ?? '');
    }

    /**
     * Acha o cliente pelo CPF/CNPJ ou cria um novo.
     *
     * O Asaas exige um cliente para cada cobranca, e criar um a cada compra
     * encheria a conta do vendedor de duplicatas do mesmo aluno - o que
     * atrapalha a conciliacao dele, que e quem olha aquele painel.
     *
     * @param string $name
     * @param string $email
     * @param string $cpfcnpj Opcional: sem ele o Asaas aceita so nome e e-mail.
     * @return string Id do cliente, ex.: cus_000008896431.
     */
    public function find_or_create_customer(string $name, string $email, string $cpfcnpj = ''): string {
        if ($cpfcnpj !== '') {
            $found = $this->request('GET', '/customers?cpfCnpj=' . urlencode($cpfcnpj) . '&limit=1');
            $existing = $found['data'][0]['id'] ?? '';
            if ($existing !== '') {
                return (string) $existing;
            }
        }

        // Cliente novo nasce com OITO regras de notificacao ligadas, e o Asaas
        // cobra "taxa de mensageria" por cada aviso que manda ao comprador.
        //
        // Medido em 14/09/2026, numa venda real de R$ 5,00: alem dos R$ 1,99
        // de taxa de Pix, o extrato levou R$ 0,99 de
        // PAYMENT_MESSAGING_NOTIFICATION_FEE - para o Asaas mandar um e-mail
        // que o Moodle ja manda.
        //
        // E ela NAO aparece no netValue da cobranca, so no extrato: quem
        // calcular o liquido do vendedor pelo netValue erra para mais, em
        // R$ 0,99, toda vez. O flag vai na CRIACAO porque desligar depois ja
        // pagou a primeira.
        $body = ['name' => $name, 'email' => $email, 'notificationDisabled' => true];
        if ($cpfcnpj !== '') {
            $body['cpfCnpj'] = $cpfcnpj;
        }
        $created = $this->request('POST', '/customers', $body);

        return (string) ($created['id'] ?? '');
    }

    /**
     * Cria a cobranca.
     *
     * Quem chama e o VENDEDOR, com a chave dele - por isso o dinheiro cai na
     * conta dele, ele aparece como recebedor no proprio payload do Pix e e ele
     * quem emite a nota. O split leva a comissao para a carteira da plataforma;
     * o liquido nao destinado a recebedores fica com quem criou a cobranca.
     *
     * @param array $params customer, billingtype, value, duedate, description,
     *                      externalreference, splitwalletid, splitpercent
     * @return array Resposta crua da API.
     */
    public function create_payment(array $params): array {
        $body = [
            'customer' => $params['customer'],
            'billingType' => $params['billingtype'],
            'value' => round((float) $params['value'], 2),
            'dueDate' => $params['duedate'],
            'externalReference' => $params['externalreference'],
        ];

        if (!empty($params['description'])) {
            $body['description'] = \core_text::substr((string) $params['description'], 0, 500);
        }

        // Para onde o Asaas devolve o aluno depois de pagar. Sem isto ele fica
        // parado na fatura sem saber que ja pode voltar para o curso. A pagina
        // de retorno nao confirma nada - so conta o que o webhook registrou.
        if (!empty($params['returnurl'])) {
            $body['callback'] = [
                'successUrl' => (string) $params['returnurl'],
                'autoRedirect' => true,
            ];
        }

        $body['split'] = self::build_split(
            (string) ($params['splitwalletid'] ?? ''),
            (float) ($params['splitpercent'] ?? 0),
            (float) $body['value'],
            (string) ($params['splitbase'] ?? 'gross')
        );
        if (!$body['split']) {
            // Um split vazio nao e "sem comissao", e um corpo invalido: o Asaas
            // recusa a chave presente sem conteudo.
            unset($body['split']);
        }

        return $this->request('POST', '/payments', $body);
    }

    /**
     * Cria uma assinatura, que cobra sozinha a cada ciclo.
     *
     * O SPLIT VALE PARA TODA COBRANCA GERADA, e nao so para a primeira - foi
     * medido no sandbox em 08/09/2026: a assinatura guarda o split com
     * status ACTIVE, e a cobranca do ciclo nasce com o valor calculado
     * (percentualValue 25 -> totalValue R$ 24,75). E o oposto do preapproval
     * do Mercado Pago, que aceita o campo da comissao e o descarta calado -
     * ver docs/adr/0001.
     *
     * ATENCAO A BASE. Com base liquida vai percentualValue, e o Asaas o aplica
     * sobre o netValue de CADA ciclo - entao a comissao acompanha a taxa do
     * meio de pagamento sozinha. Com base bruta vai fixedValue, calculado por
     * nos, e ele CONGELA: mudar o preco da oferta depois nao muda o valor da
     * comissao das assinaturas que ja existem. Isso e coerente com o ADR-0007,
     * que fotografa os termos na venda, mas precisa estar consciente - o jeito
     * de mudar e cancelar e recriar a assinatura.
     *
     * @param array $params customer, billingtype, value, nextduedate, cycle,
     *                      description, externalreference, maxpayments,
     *                      returnurl, splitwalletid, splitpercent, splitbase
     * @return array Resposta crua da API.
     */
    public function create_subscription(array $params): array {
        $body = [
            'customer' => $params['customer'],
            'billingType' => $params['billingtype'],
            'value' => round((float) $params['value'], 2),
            'nextDueDate' => $params['nextduedate'],
            'cycle' => $params['cycle'],
            'externalReference' => $params['externalreference'],
        ];

        if (!empty($params['description'])) {
            $body['description'] = \core_text::substr((string) $params['description'], 0, 500);
        }

        // Zero e "sem limite" no marketplace, e a chave simplesmente nao vai:
        // mandar maxPayments = 0 seria uma assinatura que nao cobra nunca.
        if (!empty($params['maxpayments'])) {
            $body['maxPayments'] = (int) $params['maxpayments'];
        }

        if (!empty($params['returnurl'])) {
            $body['callback'] = [
                'successUrl' => (string) $params['returnurl'],
                'autoRedirect' => true,
            ];
        }

        $body['split'] = self::build_split(
            (string) ($params['splitwalletid'] ?? ''),
            (float) ($params['splitpercent'] ?? 0),
            (float) $body['value'],
            (string) ($params['splitbase'] ?? 'gross')
        );
        if (!$body['split']) {
            unset($body['split']);
        }

        return $this->request('POST', '/subscriptions', $body);
    }

    /**
     * Cobrancas que uma assinatura ja gerou.
     *
     * A primeira nasce junto com a assinatura, e e para ela que o aluno e
     * mandado no checkout - a resposta de /subscriptions nao traz invoiceUrl.
     *
     * @param string $subscriptionid
     * @return array Lista de cobrancas, da mais antiga para a mais nova.
     */
    public function subscription_payments(string $subscriptionid): array {
        $resposta = $this->request(
            'GET',
            '/subscriptions/' . rawurlencode($subscriptionid) . '/payments'
        );

        return $resposta['data'] ?? [];
    }

    /**
     * Linha digitavel de um boleto.
     *
     * O boleto do ciclo ja nasce pronto - medido em 09/09/2026, as cobrancas de
     * uma assinatura vem com bankSlipUrl e linha digitavel desde a criacao, sem
     * ninguem pedir. Copiar o numero e mais rapido que abrir o PDF, e no celular
     * e a diferenca entre pagar e adiar.
     *
     * Devolve vazio para qualquer coisa que nao seja boleto: o Asaas responde
     * erro, e nao ha por que transformar isso em excecao no meio de uma lista.
     *
     * @param string $paymentid
     * @return string Vazio quando nao ha linha digitavel.
     */
    public function identification_field(string $paymentid): string {
        try {
            $resposta = $this->request(
                'GET',
                '/payments/' . rawurlencode($paymentid) . '/identificationField'
            );
        } catch (\Throwable $e) {
            return '';
        }

        return (string) ($resposta['identificationField'] ?? '');
    }

    /**
     * Estorna uma cobranca, por inteiro.
     *
     * O ASAAS REVERTE O SPLIT SOZINHO: medido no sandbox em 09/09/2026, o
     * estorno total leva a cobranca a REFUNDED e o split a CANCELLED, com
     * motivo PAYMENT_REFUNDED. A comissao da plataforma volta junto, e nao ha
     * acerto a fazer entre as contas.
     *
     * NAO EXISTE ESTORNO PARCIAL aqui, e a ausencia e deliberada. Pedir 40 de
     * uma cobranca de 100 devolveu resposta de sucesso, deixou o status em
     * CONFIRMED e manteve o split VIVO com o valor cheio - a plataforma
     * ficaria com a comissao inteira de uma venda parcialmente devolvida. Se um
     * dia isso for preciso, precisa ser medido de novo antes.
     *
     * A cobranca precisa estar CONFIRMED ou RECEIVED. Logo depois do pagamento
     * o proprio Asaas recusa com "tente novamente em alguns instantes" - leva
     * cerca de meio minuto para liberar.
     *
     * @param string $paymentid
     * @return array Resposta crua da API.
     */
    public function refund_payment(string $paymentid): array {
        return $this->request(
            'POST',
            '/payments/' . rawurlencode($paymentid) . '/refund',
            ['description' => 'Estorno solicitado pela plataforma']
        );
    }

    /**
     * Cancela uma assinatura.
     *
     * Cancelar para de COBRAR; nao revoga o acesso ja pago, que vale ate o fim
     * do ciclo. Revogar direito e decisao de negocio, e vive no
     * entitlement::revoke().
     *
     * @param string $subscriptionid
     * @return array
     */
    public function cancel_subscription(string $subscriptionid): array {
        return $this->request('DELETE', '/subscriptions/' . rawurlencode($subscriptionid));
    }

    /**
     * Monta o array de split.
     *
     * Estatico e puro para poder ser testado sem rede - e o pedaco do corpo
     * onde um erro custa dinheiro real de alguem.
     *
     * O CAMPO depende da base de calculo, e e a coisa mais importante desta
     * funcao:
     *
     * - base BRUTO -> fixedValue, calculado por nos. O percentualValue nao
     *   serve, porque o Asaas o aplica sobre o netValue: em R$ 100,00 a 9,9%
     *   devolveria R$ 9,65 em vez de R$ 9,90, e a plataforma pagaria parte da
     *   taxa do gateway sem ter escolhido isso.
     * - base LIQUIDO -> percentualValue, deixando o Asaas dividir. E o unico
     *   jeito correto: o liquido depende da taxa da forma de pagamento, que so
     *   e conhecida por ele, e no momento de criar a cobranca ainda nao existe.
     *
     * LIMITE, que vale para as duas: o Asaas recusa a cobranca INTEIRA se o
     * split nao couber no liquido. Com comissao de ate 10% e taxa de ate 3%
     * sobra folga larga; o caso que aperta e ticket muito baixo, em que a taxa
     * fixa come quase tudo - R$ 2,00 no Pix nao tem de onde tirar comissao.
     * Quem define preco minimo e a regra de negocio, nao esta funcao.
     *
     * @param string $walletid Carteira da plataforma.
     * @param float $percent Percentual da comissao.
     * @param float $grossvalue Valor cheio da cobranca.
     * @param string $base Base de calculo: 'gross' ou 'net'.
     * @return array Vazio quando nao ha split a fazer.
     */
    public static function build_split(
        string $walletid,
        float $percent,
        float $grossvalue,
        string $base = 'gross'
    ): array {
        $walletid = trim($walletid);
        if ($walletid === '' || $percent <= 0 || $grossvalue <= 0) {
            return [];
        }

        $percent = min($percent, 100.0);

        if ($base === 'net') {
            return [[
                'walletId' => $walletid,
                'percentualValue' => round($percent, 4),
            ]];
        }

        $fixed = round($grossvalue * ($percent / 100), 2);

        // Arredondamento para baixo pode zerar a comissao de centavos. Sem
        // valor nao ha split: mandar zero faz o Asaas recusar o corpo.
        if ($fixed <= 0) {
            return [];
        }

        // Teto absoluto. Nao substitui o limite do liquido descrito acima, que
        // so o Asaas conhece - impede o caso obvio de pedir mais do que a
        // cobranca inteira.
        $fixed = min($fixed, round($grossvalue, 2));

        return [[
            'walletId' => $walletid,
            'fixedValue' => $fixed,
        ]];
    }

    /**
     * Duas URLs apontam para o mesmo dominio?
     *
     * Existe por causa de uma exigencia do Asaas que so aparece na pratica: a
     * URL de retorno tem que usar O MESMO dominio cadastrado na conta que emite
     * a cobranca. A mensagem crua e "E necessario enviar uma URL que use o
     * mesmo dominio cadastrado nas suas Minha Conta na aba Informacoes".
     *
     * Como a cobranca nasce na conta do VENDEDOR e o retorno aponta para a
     * PLATAFORMA, isto significa que cada vendedor precisa cadastrar o dominio
     * da plataforma na conta Asaas dele - e nao o site proprio, que e o que
     * qualquer um faria por instinto.
     *
     * Comparacao por host e sem "www.", porque cadastrar com ou sem o prefixo
     * e a mesma intencao e o Asaas nao normaliza.
     *
     * @param string $one
     * @param string $two
     * @return bool
     */
    public static function same_host(string $one, string $two): bool {
        $host = static function (string $url): string {
            $url = trim($url);
            if ($url === '') {
                return '';
            }
            // O parse_url so acha o host quando ha esquema; "meusite.com"
            // sozinho seria lido como caminho.
            if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
                $url = 'https://' . $url;
            }
            $parsed = strtolower((string) parse_url($url, PHP_URL_HOST));

            return preg_replace('/^www\./', '', $parsed) ?? '';
        };

        $first = $host($one);

        return $first !== '' && $first === $host($two);
    }

    /**
     * Consulta uma cobranca.
     *
     * @param string $paymentid Ex.: pay_lumzx8nkxdrrf4rh.
     * @return array
     */
    public function get_payment(string $paymentid): array {
        return $this->request('GET', '/payments/' . urlencode($paymentid));
    }

    /**
     * QR Code do Pix de uma cobranca.
     *
     * @param string $paymentid
     * @return array payload (copia e cola) e encodedImage (PNG em base64).
     */
    public function get_pix_qrcode(string $paymentid): array {
        return $this->request('GET', '/payments/' . urlencode($paymentid) . '/pixQrCode');
    }

    /**
     * Uma chamada a API.
     *
     * @param string $method
     * @param string $path Comeca com barra.
     * @param array|null $body
     * @return array
     */
    protected function request(string $method, string $path, ?array $body = null): array {
        $url = self::BASE_URL[$this->environment] . $path;

        $curl = $this->make_curl();
        $curl->setHeader([
            'access_token: ' . $this->apikey,
            'Content-Type: application/json',
            'Accept: application/json',
            // O Asaas pede identificacao do integrador nos headers.
            'User-Agent: Moodle paygw_asaas',
        ]);

        $options = [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT,
            'CURLOPT_RETURNTRANSFER' => 1,
        ];

        if ($method === 'GET') {
            $response = $curl->get($url, [], $options);
        } else if ($method === 'DELETE') {
            // DELETE de verdade, e nao POST com outro nome. Cancelar assinatura
            // e o unico caminho que para de COBRAR o aluno; mandar POST em
            // /subscriptions/{id} criaria coisa nenhuma e devolveria erro, e o
            // aluno seguiria sendo cobrado depois de cancelar.
            $response = $curl->delete($url, [], $options);
        } else {
            $response = $curl->post($url, json_encode($body ?? []), $options);
        }

        return $this->decode($curl, $response, $url);
    }

    /**
     * Instancia o transporte.
     *
     * A costura de teste. Nao ha logica aqui de proposito: quem estende so
     * precisa devolver algo que se comporte como \curl.
     *
     * @return curl
     */
    protected function make_curl(): curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        return new curl();
    }

    /**
     * Interpreta a resposta.
     *
     * Nada falha em silencio. O Asaas devolve erro em {"errors":[{"code":...,
     * "description":...}]}, e a descricao vem em portugues e legivel - vale
     * mais para quem esta configurando do que um "erro na API".
     *
     * @param curl $curl
     * @param string|bool $response
     * @param string $url
     * @return array
     */
    protected function decode(curl $curl, $response, string $url): array {
        if ($curl->get_errno()) {
            throw new moodle_exception('errorcurl', 'paygw_asaas', '', $curl->error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new moodle_exception('errorinvalidresponse', 'paygw_asaas');
        }

        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new moodle_exception('errorapi', 'paygw_asaas', '', self::describe_errors($decoded, $status));
        }

        return $decoded;
    }

    /**
     * Transforma o corpo de erro do Asaas numa linha legivel.
     *
     * @param array $decoded
     * @param int $status
     * @return string
     */
    protected static function describe_errors(array $decoded, int $status): string {
        $parts = [];
        foreach ($decoded['errors'] ?? [] as $error) {
            $code = (string) ($error['code'] ?? '');
            $description = (string) ($error['description'] ?? '');
            $parts[] = trim($code . ': ' . $description, ': ');
        }

        return $status . ' - ' . ($parts ? implode(' | ', $parts) : 'sem detalhe');
    }
}
