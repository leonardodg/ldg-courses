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

use curl;
use moodle_exception;

/**
 * Unico ponto de contato com a API do Mercado Pago.
 *
 * TODA chamada HTTP passa por aqui, de proposito. O Mercado Pago esta
 * migrando o Checkout Pro para a Orders API, e a API de Preferencias que
 * usamos pode ser depreciada. Concentrando as chamadas num arquivo, essa
 * migracao vira uma reescrita local em vez de uma cacada por todo o plugin.
 *
 * Nao usa o SDK oficial (mercadopago/dx-php) porque ele traria uma arvore de
 * dependencias via composer para tres endpoints. O curl do Moodle ja resolve,
 * respeita proxy e timeout do site, e nao acrescenta nada para manter.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mp_client {
    /** @var string Base da API. */
    const API_BASE = 'https://api.mercadopago.com';

    /**
     * Dominio de autorizacao de cada site.
     *
     * O OAuth nao tem um dominio unico: o vendedor autoriza no dominio do PAIS
     * dele. Mandar um vendedor argentino para o dominio .com.br nao da erro
     * claro - a tela simplesmente nao reconhece a conta.
     *
     * @var array<string,string>
     */
    const SITE_AUTH_DOMAIN = [
        'MLA' => 'auth.mercadopago.com.ar',
        'MLB' => 'auth.mercadopago.com.br',
        'MLC' => 'auth.mercadopago.cl',
        'MCO' => 'auth.mercadopago.com.co',
        'MLM' => 'auth.mercadopago.com.mx',
        'MPE' => 'auth.mercadopago.com.pe',
        'MLU' => 'auth.mercadopago.com.uy',
    ];

    /** @var int Timeout em segundos. Pagamento nao pode pendurar a requisicao. */
    const TIMEOUT = 20;

    /** @var string Token usado nas chamadas. */
    protected string $accesstoken;

    /**
     * Constroi o cliente com o token que autentica as chamadas.
     *
     * @param string $accesstoken Token do VENDEDOR para criar preferencia; da
     *                            plataforma para o fluxo OAuth.
     */
    public function __construct(string $accesstoken) {
        $this->accesstoken = $accesstoken;
    }

    /**
     * Gera um code_verifier para o PKCE.
     *
     * O Mercado Pago exige entre 43 e 128 caracteres. random_string() do Moodle
     * produz apenas alfanumericos, que estao dentro do conjunto "unreserved" da
     * RFC 7636 - nao precisam de escape na URL nem no JSON, o que elimina uma
     * classe inteira de erro de codificacao.
     *
     * @return string
     */
    public static function create_code_verifier(): string {
        return random_string(64);
    }

    /**
     * Deriva o code_challenge do verifier, metodo S256.
     *
     * base64url e base64 comum com +/ trocados por -_ e sem o padding '=';
     * mandar base64 puro faz o Mercado Pago recusar a autorizacao.
     *
     * @param string $verifier
     * @return string
     */
    public static function create_code_challenge(string $verifier): string {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * URL para o vendedor autorizar a plataforma.
     *
     * O PKCE e opcional no Mercado Pago, mas mandamos sempre: o servidor so
     * cobra o code_verifier na troca se a autorizacao trouxe o challenge, entao
     * enviar os dois mantem o fluxo coerente com a aplicacao configurada de
     * qualquer jeito - e protege o codigo de autorizacao caso ele vaze no
     * historico do navegador ou num log de proxy.
     *
     * @param string $clientid client_id da aplicacao da plataforma
     * @param string $redirecturi Precisa casar EXATAMENTE com a cadastrada no painel
     * @param string $state Devolvido no callback; usamos para saber qual conta vincular
     * @param string $codechallenge Derivado do verifier guardado na sessao
     * @param string $siteid Site da aplicacao da plataforma
     * @return string
     */
    public static function build_authorization_url(
        string $clientid,
        string $redirecturi,
        string $state,
        string $codechallenge,
        string $siteid
    ): string {
        $domain = self::SITE_AUTH_DOMAIN[strtoupper($siteid)] ?? self::SITE_AUTH_DOMAIN['MLB'];

        return 'https://' . $domain . '/authorization?' . http_build_query([
            'client_id' => $clientid,
            'response_type' => 'code',
            'platform_id' => 'mp',
            'redirect_uri' => $redirecturi,
            'state' => $state,
            'code_challenge' => $codechallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Troca o codigo de autorizacao pelo token do vendedor.
     *
     * @param string $clientid
     * @param string $clientsecret
     * @param string $code Codigo recebido no callback
     * @param string $redirecturi Precisa ser o MESMO usado na autorizacao
     * @param string $codeverifier O verifier cujo challenge foi enviado na autorizacao
     * @param bool $testmode Emite token de teste em vez de token de producao
     * @return array access_token, refresh_token, expires_in, user_id
     */
    public static function exchange_code(
        string $clientid,
        string $clientsecret,
        string $code,
        string $redirecturi,
        string $codeverifier,
        bool $testmode = false
    ): array {
        $body = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientid,
            'client_secret' => $clientsecret,
            'code' => $code,
            'redirect_uri' => $redirecturi,
            'code_verifier' => $codeverifier,
        ];

        // O Mercado Pago recusa pagamento entre ambientes misturados, com
        // "Uma das partes com as quais voce esta tentando efetuar o pagamento
        // e de teste". As tres partes contam: comprador, vendedor e a
        // APLICACAO que cobra a comissao.
        //
        // Sem test_token, a aplicacao entra como producao mesmo que vendedor e
        // comprador sejam usuarios de teste - porque ela pertence a conta real
        // do dono da plataforma. O resultado e um checkout que abre e morre em
        // /fatal/, sem dizer qual das partes esta fora.
        if ($testmode) {
            $body['test_token'] = 'true';
        }

        return self::post_json(self::API_BASE . '/oauth/token', $body);
    }

    /**
     * Renova o token de um vendedor.
     *
     * O token do Mercado Pago expira em cerca de seis meses. Sem renovar, o
     * repasse daquele vendedor para de funcionar - e o sintoma aparece no
     * checkout, diante do aluno.
     *
     * @param string $clientid
     * @param string $clientsecret
     * @param string $refreshtoken
     * @return array
     */
    public static function refresh_token(string $clientid, string $clientsecret, string $refreshtoken): array {
        return self::post_json(self::API_BASE . '/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientid,
            'client_secret' => $clientsecret,
            'refresh_token' => $refreshtoken,
        ]);
    }

    /**
     * Moeda de cada pais onde o Mercado Pago opera.
     *
     * A conta do vendedor e presa a um pais, identificado pelo site_id, e so
     * recebe na moeda dele: uma conta MLB nao recebe ARS. Por isso a moeda da
     * empresa e DESCOBERTA da conta vinculada, nunca digitada - um campo livre
     * so produziria preferencia recusada no checkout, diante do aluno.
     *
     * @var array<string,string>
     */
    const SITE_CURRENCY = [
        'MLA' => 'ARS', // Argentina.
        'MLB' => 'BRL', // Brasil.
        'MLC' => 'CLP', // Chile.
        'MCO' => 'COP', // Colombia.
        'MLM' => 'MXN', // Mexico.
        'MPE' => 'PEN', // Peru.
        'MLU' => 'UYU', // Uruguai.
    ];

    /**
     * Moeda correspondente a um site do Mercado Pago.
     *
     * @param string $siteid
     * @return string Vazio se o site for desconhecido.
     */
    public static function currency_for_site(string $siteid): string {
        return self::SITE_CURRENCY[strtoupper($siteid)] ?? '';
    }

    /**
     * Dados da conta dona do token.
     *
     * Usado logo apos o OAuth para saber em que pais o vendedor recebe.
     *
     * @return array Inclui id, site_id e country_id
     */
    public function get_me(): array {
        return $this->request('GET', '/users/me');
    }

    /**
     * Cria a preferencia de pagamento (Checkout Pro).
     *
     * O marketplace_fee e o que faz a comissao voltar para a plataforma. Vai
     * aqui, na preferencia, e nao no pagamento - e por isso que a integracao
     * declarada no painel do Mercado Pago precisa ser "API de Preferencias".
     *
     * O marketplace_fee e um VALOR ABSOLUTO, e nao um percentual - por isso a
     * plataforma recebe exatamente o que foi combinado sobre o valor bruto,
     * calculado antes de chamar esta funcao. E a mesma base do fixedValue no
     * Asaas: os dois gateways entregam a comissao sobre o bruto.
     *
     * ATENCAO, e coisa diferente: a ordem de deducao e fixa e nao configuravel.
     * A taxa do proprio Mercado Pago sai primeiro, e o marketplace_fee sai do
     * que sobra. Isso nao muda quanto a plataforma recebe - muda quem absorve a
     * taxa do gateway, que e o vendedor. Se a taxa nao deixar saldo para o
     * marketplace_fee, quem recusa e o Mercado Pago.
     *
     * @param array $preference Corpo da preferencia
     * @return array Resposta, com id e init_point
     */
    public function create_preference(array $preference): array {
        return $this->request('POST', '/checkout/preferences', $preference);
    }

    /**
     * Consulta um pagamento.
     *
     * O webhook do Mercado Pago manda so o ID: nunca o status. E de proposito -
     * confiar no corpo da notificacao permitiria a qualquer um POSTar
     * "aprovado" no nosso endpoint. O status tem que vir daqui.
     *
     * @param string $paymentid
     * @return array
     */
    public function get_payment(string $paymentid): array {
        return $this->request('GET', '/v1/payments/' . rawurlencode($paymentid));
    }

    /**
     * Procura pagamentos por referencia externa.
     *
     * Existe por causa da reconciliacao, e a diferenca para o Asaas e
     * estrutural: la a cobranca nasce com id, aqui a preferencia nasce e o
     * PAGAMENTO so existe depois que o aluno paga. Uma linha pendente nao tem
     * mppaymentid para consultar - so tem a referencia que nos mesmos geramos.
     *
     * @param string $reference external_reference gravado na linha
     * @return array Lista de pagamentos, vazia quando ninguem pagou
     */
    public function search_by_reference(string $reference): array {
        $resposta = $this->request(
            'GET',
            '/v1/payments/search?external_reference=' . rawurlencode($reference)
        );

        return $resposta['results'] ?? [];
    }

    /**
     * Cria a assinatura no Mercado Pago.
     *
     * Espelha PreApprovalClient::create() do SDK oficial (lido em 16/09/2026).
     *
     * NAO MANDE CAMPO DE COMISSAO AQUI. Medido em 15/09/2026, com a aplicacao
     * do tipo Assinaturas e contas distintas: marketplace_fee, application_fee
     * e marketplace, na raiz e dentro de auto_recurring, sao aceitos com 201 e
     * DESCARTADOS - nenhum volta no GET seguinte. O recurso nao tem onde
     * guardar comissao. Ver docs/adr/0012 e o roteiro de medicao.
     *
     * Serve a assinatura SEM split: a mensalidade que a empresa paga a
     * plataforma, onde nao ha terceiro e portanto nao ha comissao a reter.
     *
     * @param array $body Corpo da assinatura
     * @return array
     */
    public function create_preapproval(array $body): array {
        return $this->request('POST', '/preapproval', $body);
    }

    /**
     * Consulta uma assinatura.
     *
     * @param string $id
     * @return array
     */
    public function get_preapproval(string $id): array {
        return $this->request('GET', '/preapproval/' . rawurlencode($id));
    }

    /**
     * Altera uma assinatura - e o verbo e PUT.
     *
     * Espelha PreApprovalClient::update(), que e PUT /preapproval/{id}. Mandar
     * POST no mesmo caminho CRIA outra assinatura em vez de alterar a que
     * existe, e o aluno passaria a ser cobrado duas vezes sem erro nenhum na
     * tela. Foi por isso que o request() ganhou PUT.
     *
     * @param string $id
     * @param array $body
     * @return array
     */
    public function update_preapproval(string $id, array $body): array {
        return $this->request('PUT', '/preapproval/' . rawurlencode($id), $body);
    }

    /**
     * Para de cobrar uma assinatura.
     *
     * @param string $id
     * @return array
     */
    public function cancel_preapproval(string $id): array {
        return $this->update_preapproval($id, ['status' => 'cancelled']);
    }

    /**
     * Cria o cliente do aluno na conta do VENDEDOR.
     *
     * O cartao guardado pertence a um cliente, e o cliente pertence a conta que
     * vai receber - por isso esta chamada usa o token do vendedor, e nao o da
     * plataforma.
     *
     * @param string $email
     * @param array $extra Campos opcionais, como first_name
     * @return array
     */
    public function create_customer(string $email, array $extra = []): array {
        return $this->request('POST', '/v1/customers', array_merge($extra, ['email' => $email]));
    }

    /**
     * Procura o cliente pelo e-mail.
     *
     * O Mercado Pago recusa criar dois clientes com o mesmo e-mail na mesma
     * conta, entao o fluxo e sempre "tenta criar, e se ja existir, procura".
     *
     * @param string $email
     * @return array Lista de clientes, vazia quando nao ha
     */
    public function search_customer(string $email): array {
        $resposta = $this->request('GET', '/v1/customers/search?email=' . rawurlencode($email));

        return $resposta['results'] ?? [];
    }

    /**
     * Guarda um cartao no cliente - NO MERCADO PAGO.
     *
     * O que entra aqui e um card_token, e nao o numero do cartao. A diferenca
     * nao e de estilo: medido em 16/09/2026, POST /v1/card_tokens com token de
     * ACESSO devolve 403 unexpected_processing; so a public_key tokeniza. Ou
     * seja, o Mercado Pago recusa que o servidor tokenize com a credencial de
     * servidor - o token nasce no navegador, e o numero do cartao nao passa por
     * aqui.
     *
     * A BANDEIRA E OBRIGATORIA AQUI, e isso e assimetrico com o /v1/payments.
     * Medido em 16/09/2026: o token dos Secure Fields nao carrega
     * payment_method_id, e este endpoint devolve "400 invalid parameter in
     * payment method" sem ela - enquanto a cobranca infere do proprio token.
     * Quem descobre a bandeira e o navegador, pelo BIN.
     *
     * O EMISSOR TAMBEM, para uma parte dos cartoes. Medido em 16/09/2026: uma
     * compra real com bandeira preenchida ainda assim voltou "400 invalid
     * parameter. Cannot resolve the payment method of card, check the
     * payment_method_id and issuer_id" - a propria mensagem do Mercado Pago
     * aponta o campo que faltava. A busca por BIN que descobre a bandeira
     * devolve o emissor no mesmo resultado (`issuer.id`), entao quem chama
     * ja tem os dois a mao.
     *
     * @param string $customerid
     * @param string $cardtoken
     * @param string $paymentmethod Bandeira, ou vazio quando desconhecida
     * @param string $issuerid Emissor, ou vazio quando desconhecido
     * @return array Inclui id e last_four_digits
     */
    public function save_card(
        string $customerid,
        string $cardtoken,
        string $paymentmethod = '',
        string $issuerid = ''
    ): array {
        $body = ['token' => $cardtoken];

        if ($paymentmethod !== '') {
            $body['payment_method_id'] = $paymentmethod;
        }

        if ($issuerid !== '') {
            // NUMERO, E NAO STRING - medido em 16/09/2026: "25" (string) faz o
            // Mercado Pago devolver "400 the body must be a Json Object" (causa
            // 118), uma mensagem que nao tem nada a ver com o defeito real. Com
            // 25 (inteiro) o mesmo corpo passa da validacao de forma. O JSON
            // Object da mensagem sao os OBJETOS do corpo, nao o corpo inteiro -
            // e o emissor e um dos poucos campos aqui que o Mercado Pago espera
            // tipado, e nao como texto.
            $body['issuer_id'] = (int) $issuerid;
        }

        return $this->request(
            'POST',
            '/v1/customers/' . rawurlencode($customerid) . '/cards',
            $body
        );
    }

    /**
     * Cria um pagamento avulso - e e aqui que a comissao funciona.
     *
     * Espelha PaymentClient::create(). O campo e application_fee, e o SDK o
     * documenta como "fee charged by the marketplace to the seller on this
     * payment".
     *
     * Medido em 16/09/2026, pagamento 1352076103: aprovado, com
     * application_fee 1,25 em fee_details ao lado do mercadopago_fee. E o
     * contraste que sustenta o desenho - o preapproval aceita o mesmo campo e
     * descarta; este endpoint o honra.
     *
     * @param array $body Corpo do pagamento
     * @return array
     */
    public function create_payment(array $body): array {
        return $this->request('POST', '/v1/payments', $body);
    }

    /**
     * Estorna um pagamento, por inteiro.
     *
     * Espelha PaymentRefundClient::refund(). Sem corpo, de proposito: corpo com
     * "amount" faz estorno PARCIAL, e estorno parcial nao reduz o split - o que
     * ja foi repassado a plataforma continua repassado, e o vendedor absorveria
     * a diferenca sem que nenhuma tela dissesse isso.
     *
     * @param string $paymentid
     * @return array
     */
    public function refund_payment(string $paymentid): array {
        return $this->request('POST', '/v1/payments/' . rawurlencode($paymentid) . '/refunds', []);
    }

    /**
     * Gera um token a partir de um cartao JA GUARDADO no Mercado Pago.
     *
     * Esta, sim, usa o token de acesso e roda no servidor - e e a diferenca que
     * importa: aqui nao ha dado de cartao nenhum na requisicao, so o id de um
     * cartao que ja pertence a um cliente da conta.
     *
     * NAO PEDE CODIGO DE SEGURANCA, e isso e o que torna a cobranca automatica
     * possivel. Medido em 16/09/2026: POST /v1/card_tokens com apenas
     * {"card_id": ...} devolve token com status active. A frase de que "o
     * Transparente com cartao salvo exige CVV a cada cobranca", que este
     * projeto carregava como fato, nao se sustenta neste ponto do fluxo.
     *
     * @param string $cardid Id do cartao guardado
     * @return array Inclui id e status
     */
    public function tokenize_saved_card(string $cardid): array {
        return $this->request('POST', '/v1/card_tokens', ['card_id' => $cardid]);
    }

    /**
     * Troca dados de cartao por um token - SO no modo de captura nativo.
     *
     * E ESTATICA E NAO USA BEARER de proposito, e as duas coisas dizem a mesma
     * verdade sobre este endpoint: ele autentica pela chave PUBLICA, na query
     * string, porque foi desenhado para ser chamado do NAVEGADOR.
     *
     * Medido em 16/09/2026: chamar /v1/card_tokens com token de ACESSO devolve
     * 403 unexpected_processing. O Mercado Pago recusa que o servidor tokenize
     * com credencial de servidor - o caminho normal e o cartao virar token no
     * navegador, e o numero nunca chegar ate nos.
     *
     * Esta funcao existe porque o modo nativo foi pedido, e ela e a fronteira
     * do escopo PCI DSS do projeto. O numero do cartao passa por aqui e morre
     * no fim da requisicao: nao vai para o banco, nao vai para a sessao e nao
     * entra em log. Ver docs/legal/pci-dss-captura-de-cartao.md.
     *
     * @param string $publickey Chave publica da aplicacao que vai cobrar
     * @param array $card Dados do cartao, no formato da API
     * @return array Inclui id e payment_method_id
     */
    public static function tokenize_card(string $publickey, array $card): array {
        return self::post_json(
            self::API_BASE . '/v1/card_tokens?public_key=' . rawurlencode($publickey),
            $card
        );
    }

    /**
     * Descobre a bandeira e o emissor de um cartao pelo BIN - o que o
     * save_card() exige e o /v1/card_tokens NAO devolve.
     *
     * O PARAMETRO E "bins", NO PLURAL, e isso nao e detalhe: medido em
     * 16/09/2026, "bin" no singular devolve o MESMO catalogo generico do site
     * para BINs diferentes - nao filtra nada, e foi assim que uma correcao
     * anterior mandou a bandeira errada sem que nenhum teste acusasse. O
     * parametro certo, lido do proprio bundle do SDK oficial
     * (`sdk.mercadopago.com/js/v2`), e "bins", e o BIN pode ter 6 ou 8
     * digitos - os dois filtram certo.
     *
     * So CREDIT_CARD e DEBIT_CARD contam: a mesma busca tambem devolve Pix,
     * boleto e Mercado Credito misturados, e nenhum dos tres serve para
     * guardar cartao.
     *
     * @param string $publickey Chave publica da aplicacao que vai cobrar
     * @param string $bin Os 6 a 8 primeiros digitos do cartao
     * @return array{id: string, issuerid: string} Vazio quando nao reconhecido
     */
    public static function guess_payment_method(string $publickey, string $bin): array {
        $resposta = self::get_json(self::API_BASE . '/v1/payment_methods/search?' . http_build_query([
            'marketplace' => 'NONE',
            'status' => 'active',
            'bins' => $bin,
            'public_key' => $publickey,
        ]));

        foreach (($resposta['results'] ?? []) as $metodo) {
            $tipo = (string) ($metodo['payment_type_id'] ?? '');
            if ($tipo === 'credit_card' || $tipo === 'debit_card') {
                return [
                    'id' => (string) ($metodo['id'] ?? ''),
                    'issuerid' => (string) ($metodo['issuer']['id'] ?? ''),
                ];
            }
        }

        return [];
    }

    /**
     * Constroi o transporte HTTP.
     *
     * Existe para ser SOBRESCRITA no teste. Enquanto o curl era instanciado
     * dentro de request() e post_json(), a montagem do corpo e o mapeamento de
     * erro so eram exercitaveis batendo na API de verdade - e foi por isso que
     * o marketplace_fee, o unico numero aqui que move dinheiro, passou a existir
     * sem teste nenhum. E a mesma costura do asaas_client.
     *
     * E estatica porque o post_json() do fluxo OAuth tambem e estatico. A
     * chamada usa static::, e nao self::, senao o late static binding nao
     * alcanca a subclasse falsa e o teste voltaria a bater na rede.
     *
     * @return curl
     */
    protected static function make_curl(): curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        return new curl();
    }

    /**
     * Chamada autenticada com o token da instancia.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @return array
     */
    protected function request(string $method, string $path, ?array $body = null): array {
        $curl = static::make_curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->accesstoken,
            'Content-Type: application/json',
        ]);
        $options = [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ];

        $url = self::API_BASE . $path;

        // O put() do curl do Moodle so trata upload de arquivo quando recebe
        // ['file' => ...]; com uma string ele define CUSTOMREQUEST=PUT e manda
        // o corpo em POSTFIELDS, que e exatamente o que a API espera.
        $response = match ($method) {
            'GET' => $curl->get($url, [], $options),
            'PUT' => $curl->put($url, json_encode($body), $options),
            default => $curl->post($url, json_encode($body), $options),
        };

        return self::decode($curl, $response, $url);
    }

    /**
     * POST sem autenticacao por Bearer, usado no fluxo OAuth.
     *
     * @param string $url
     * @param array $body
     * @return array
     */
    protected static function post_json(string $url, array $body): array {
        $curl = static::make_curl();
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post($url, json_encode($body), [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        return self::decode($curl, $response, $url);
    }

    /**
     * GET sem autenticacao por Bearer - a URL ja carrega a public_key.
     *
     * @param string $url
     * @return array
     */
    protected static function get_json(string $url): array {
        $curl = static::make_curl();
        $response = $curl->get($url, [], [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        return self::decode($curl, $response, $url);
    }

    /**
     * Interpreta a resposta, transformando erro de API em excecao.
     *
     * Erro silencioso aqui viraria "o aluno pagou e nao recebeu acesso", que e
     * o pior desfecho possivel - entao qualquer status fora de 2xx vira
     * excecao, com a mensagem do Mercado Pago preservada para o log.
     *
     * @param curl $curl
     * @param string|bool $response
     * @param string $url
     * @return array
     */
    protected static function decode(curl $curl, $response, string $url): array {
        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);

        if ($curl->get_errno()) {
            throw new moodle_exception('errorcurl', 'paygw_mercadopago', '', $curl->error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new moodle_exception('errorinvalidresponse', 'paygw_mercadopago', '', $url);
        }

        if ($status < 200 || $status >= 300) {
            $message = $decoded['message'] ?? $decoded['error'] ?? 'HTTP ' . $status;

            // O "message" sozinho repete a mesma frase para causas
            // DIFERENTES - "invalid parameter in payment method" saiu tanto de
            // bandeira errada quanto de token ja consumido, em medicoes
            // distintas. O "cause" e onde o Mercado Pago poe o code numerico
            // que distingue um caso do outro, e sem ele cada erro novo vira
            // outra rodada de curl para adivinhar.
            $causas = array_map(
                static fn(array $causa): string => trim(
                    ($causa['code'] ?? '?') . ': ' . ($causa['description'] ?? '')
                ),
                $decoded['cause'] ?? []
            );
            if ($causas) {
                $message .= ' [' . implode('; ', $causas) . ']';
            }

            throw new moodle_exception('errorapi', 'paygw_mercadopago', '', $status . ': ' . $message);
        }

        return $decoded;
    }
}
