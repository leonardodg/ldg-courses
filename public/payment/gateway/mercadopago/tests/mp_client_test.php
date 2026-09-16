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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_mp_client.php');

/**
 * Cliente do Mercado Pago.
 *
 * Duas camadas. As partes puras - PKCE, mapa de moeda, URL de autorizacao - sao
 * justamente os pontos onde um erro nao aparece em desenvolvimento: o Mercado
 * Pago recusa em producao, com mensagem generica. A camada HTTP entra pela
 * costura make_curl(), e cobre o que antes so era exercitavel batendo na API:
 * o corpo enviado e o mapeamento de erro.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_mercadopago\mp_client::class)]
final class mp_client_test extends \advanced_testcase {
    /**
     * O roteiro da fixture e estatico, entao atravessa teste se nao for limpo.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        fake_mp_client::reset();
    }

    /**
     * O verifier respeita o tamanho exigido pela RFC 7636.
     *
     * O Mercado Pago exige entre 43 e 128 caracteres.
     *
     * @return void
     */
    public function test_code_verifier_length_and_charset(): void {
        $verifier = mp_client::create_code_verifier();

        $this->assertGreaterThanOrEqual(43, strlen($verifier));
        $this->assertLessThanOrEqual(128, strlen($verifier));
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9\-._~]+$/',
            $verifier,
            'so caracteres unreserved, senao precisaria de escape na URL'
        );
    }

    /**
     * Dois verifiers seguidos nao se repetem.
     *
     * Um verifier previsivel anula o PKCE: quem capturasse o codigo de
     * autorizacao conseguiria adivinhar o par e trocar por token.
     *
     * @return void
     */
    public function test_code_verifier_is_not_predictable(): void {
        $this->assertNotSame(mp_client::create_code_verifier(), mp_client::create_code_verifier());
    }

    /**
     * O challenge e base64url do SHA-256, sem padding.
     *
     * Vetor da propria RFC 7636, secao A.
     *
     * @return void
     */
    public function test_code_challenge_matches_rfc_vector(): void {
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $expected = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

        $this->assertSame($expected, mp_client::create_code_challenge($verifier));
    }

    /**
     * O challenge nao carrega +, / nem =.
     *
     * base64 comum faria o Mercado Pago recusar a autorizacao.
     *
     * @return void
     */
    public function test_code_challenge_is_url_safe(): void {
        $challenge = mp_client::create_code_challenge(mp_client::create_code_verifier());

        $this->assertStringNotContainsString('+', $challenge);
        $this->assertStringNotContainsString('/', $challenge);
        $this->assertStringNotContainsString('=', $challenge);
    }

    /**
     * Cada site tem a moeda do seu pais.
     *
     * @return void
     */
    public function test_currency_for_site(): void {
        $this->assertSame('BRL', mp_client::currency_for_site('MLB'));
        $this->assertSame('ARS', mp_client::currency_for_site('MLA'));
        $this->assertSame('BRL', mp_client::currency_for_site('mlb'), 'aceita minusculas');
        $this->assertSame('', mp_client::currency_for_site('XXX'), 'site desconhecido nao inventa moeda');
    }

    /**
     * A URL de autorizacao vai para o dominio do PAIS.
     *
     * O OAuth do Mercado Pago nao tem dominio unico. Mandar um vendedor
     * argentino para .com.br nao da erro claro: a tela apenas nao reconhece a
     * conta dele.
     *
     * @return void
     */
    public function test_authorization_url_uses_country_domain(): void {
        $br = mp_client::build_authorization_url('123', 'https://x.test/cb', 'st', 'ch', 'MLB');
        $ar = mp_client::build_authorization_url('123', 'https://x.test/cb', 'st', 'ch', 'MLA');

        $this->assertStringStartsWith('https://auth.mercadopago.com.br/authorization?', $br);
        $this->assertStringStartsWith('https://auth.mercadopago.com.ar/authorization?', $ar);
    }

    /**
     * Site desconhecido cai no Brasil em vez de gerar URL invalida.
     *
     * @return void
     */
    public function test_authorization_url_falls_back_to_brazil(): void {
        $url = mp_client::build_authorization_url('123', 'https://x.test/cb', 'st', 'ch', 'XXX');

        $this->assertStringStartsWith('https://auth.mercadopago.com.br/authorization?', $url);
    }

    /**
     * A autorizacao leva o challenge e declara S256.
     *
     * Sem o metodo declarado, o servidor assume "plain" e o hash enviado nao
     * casaria com o verifier na troca.
     *
     * @return void
     */
    public function test_authorization_url_carries_pkce(): void {
        $url = mp_client::build_authorization_url('cid', 'https://x.test/cb', 'state123', 'chall123', 'MLB');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('cid', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('state123', $query['state']);
        $this->assertSame('chall123', $query['code_challenge']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('https://x.test/cb', $query['redirect_uri']);
    }

    /**
     * A preferencia sai com o marketplace_fee intacto.
     *
     * E o unico campo deste plugin que move dinheiro. O corpo e montado pelo
     * payment_processor, mas quem o serializa e o manda e daqui - um cliente que
     * perdesse a chave no caminho produziria venda sem comissao, sem erro
     * nenhum.
     *
     * @return void
     */
    public function test_create_preference_body(): void {
        fake_mp_client::$nextresponse = ['id' => 'pref-1', 'init_point' => 'https://mp.test/x'];

        $client = new fake_mp_client('token-do-vendedor');
        $response = $client->create_preference([
            'external_reference' => 'mdl-1-2-abc',
            'marketplace_fee' => 25.0,
        ]);

        $this->assertSame('pref-1', $response['id']);
        $this->assertSame([['POST', 'https://api.mercadopago.com/checkout/preferences']], fake_mp_client::$calls);
        $this->assertSame('mdl-1-2-abc', fake_mp_client::$lastbody['external_reference']);

        // Comparacao frouxa de proposito: o corpo e observado depois da ida e
        // volta pelo JSON, e 25.0 volta de la como inteiro. O que importa e o
        // numero - o tipo PHP depois do decode e do teste, nao do Mercado Pago.
        $this->assertEquals(25.0, fake_mp_client::$lastbody['marketplace_fee']);
    }

    /**
     * Erro da API carrega a mensagem do Mercado Pago.
     *
     * "HTTP 400" nao diz se o problema foi a comissao, a moeda ou o token. A
     * mensagem original e o que permite descobrir sem repetir a compra.
     *
     * @return void
     */
    public function test_erro_da_api_carrega_a_mensagem(): void {
        fake_mp_client::$nextstatus = 400;
        fake_mp_client::$nextresponse = ['message' => 'invalid marketplace_fee'];

        $client = new fake_mp_client('token');

        try {
            $client->create_preference(['marketplace_fee' => 999.0]);
            $this->fail('status fora de 2xx tem que virar excecao');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorapi', $e->errorcode);
            $this->assertStringContainsString('400: invalid marketplace_fee', $e->getMessage());
        }
    }

    /**
     * O "cause" do Mercado Pago viaja junto, porque o "message" sozinho repete
     * a mesma frase para causas diferentes.
     *
     * Medido em 16/09/2026: "invalid parameter in payment method" saiu de mais
     * de uma causa distinta no /v1/customers/{id}/cards, e so o "message" nao
     * dava para saber qual. O "cause" tem o code numerico que distingue.
     *
     * @return void
     */
    public function test_erro_da_api_carrega_a_causa(): void {
        fake_mp_client::$nextstatus = 400;
        fake_mp_client::$nextresponse = [
            'message' => 'invalid parameter in payment method',
            'cause' => [
                ['code' => '2034', 'description' => 'card_token_id not found'],
            ],
        ];

        $client = new fake_mp_client('token');

        try {
            $client->create_preference(['marketplace_fee' => 999.0]);
            $this->fail('status fora de 2xx tem que virar excecao');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('invalid parameter in payment method', $e->getMessage());
            $this->assertStringContainsString('2034: card_token_id not found', $e->getMessage());
        }
    }

    /**
     * Resposta que nao e JSON vira excecao, e nao array vazio.
     *
     * O Mercado Pago em manutencao devolve HTML. Decodificar para null e seguir
     * daria "preferencia sem init_point" la na frente, longe da causa.
     *
     * @return void
     */
    public function test_resposta_nao_json_e_recusada(): void {
        fake_mp_client::$rawresponse = '<html>502 Bad Gateway</html>';

        $client = new fake_mp_client('token');

        $this->expectException(\moodle_exception::class);
        $client->get_payment('123');
    }

    /**
     * Falha de transporte e reportada antes de qualquer decodificacao.
     *
     * Timeout devolve corpo vazio com status 0. Sem olhar o errno primeiro, o
     * erro relatado seria "resposta invalida" em vez de "nao alcancou o
     * Mercado Pago".
     *
     * @return void
     */
    public function test_falha_de_transporte_e_reportada(): void {
        fake_mp_client::$nexterrno = 28;
        fake_mp_client::$nextstatus = 0;

        $client = new fake_mp_client('token');

        try {
            $client->get_me();
            $this->fail('erro de curl tem que virar excecao');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorcurl', $e->errorcode);
        }
    }

    /**
     * O test_token so vai no modo de teste.
     *
     * Sem ele, a aplicacao entra como producao mesmo com vendedor e comprador
     * de teste, e o checkout morre com "uma das partes e de teste" sem dizer
     * qual. Mandar sempre seria pior: emitiria token de teste em producao.
     *
     * @return void
     */
    public function test_exchange_code_manda_test_token(): void {
        fake_mp_client::$nextresponse = ['access_token' => 'APP_USR-x', 'user_id' => 1];

        fake_mp_client::exchange_code('cid', 'segredo', 'code', 'https://x.test/cb', 'verifier', true);
        $this->assertSame('true', fake_mp_client::$lastbody['test_token']);

        fake_mp_client::reset();
        fake_mp_client::$nextresponse = ['access_token' => 'APP_USR-x', 'user_id' => 1];

        fake_mp_client::exchange_code('cid', 'segredo', 'code', 'https://x.test/cb', 'verifier', false);
        $this->assertArrayNotHasKey('test_token', fake_mp_client::$lastbody);
    }

    /**
     * A troca leva o verifier do PKCE, e vai para o endpoint de OAuth.
     *
     * @return void
     */
    public function test_exchange_code_manda_o_verifier(): void {
        fake_mp_client::$nextresponse = ['access_token' => 'APP_USR-x'];

        fake_mp_client::exchange_code('cid', 'segredo', 'code-123', 'https://x.test/cb', 'verifier-abc', false);

        $this->assertSame([['POST', 'https://api.mercadopago.com/oauth/token']], fake_mp_client::$calls);
        $this->assertSame('authorization_code', fake_mp_client::$lastbody['grant_type']);
        $this->assertSame('code-123', fake_mp_client::$lastbody['code']);
        $this->assertSame('verifier-abc', fake_mp_client::$lastbody['code_verifier']);
    }

    /**
     * A renovacao manda o refresh_token, e nenhum codigo de autorizacao.
     *
     * O token do vendedor expira em cerca de seis meses. Um corpo errado aqui
     * so apareceria meio ano depois, no checkout daquele vendedor.
     *
     * @return void
     */
    public function test_refresh_token_body(): void {
        fake_mp_client::$nextresponse = ['access_token' => 'APP_USR-novo'];

        fake_mp_client::refresh_token('cid', 'segredo', 'refresh-abc');

        $this->assertSame('refresh_token', fake_mp_client::$lastbody['grant_type']);
        $this->assertSame('refresh-abc', fake_mp_client::$lastbody['refresh_token']);
        $this->assertArrayNotHasKey('code', fake_mp_client::$lastbody);
        $this->assertArrayNotHasKey('code_verifier', fake_mp_client::$lastbody);
    }

    /**
     * O id do pagamento e escapado no caminho.
     *
     * O id vem do corpo de uma notificacao, ou seja, de fora. Concatenar sem
     * escapar deixaria a URL da API ser reescrita por quem POSTasse no webhook.
     *
     * @return void
     */
    public function test_get_payment_escapa_o_id(): void {
        fake_mp_client::$nextresponse = ['id' => 1, 'status' => 'approved'];

        $client = new fake_mp_client('token');
        $client->get_payment('12/34');

        $this->assertSame([['GET', 'https://api.mercadopago.com/v1/payments/12%2F34']], fake_mp_client::$calls);
    }

    /**
     * A busca da reconciliacao vai pela referencia, e escapa o valor.
     *
     * E por referencia, e nao por id, porque uma linha pendente ainda nao tem
     * id de pagamento: a preferencia existe, o pagamento so passa a existir
     * quando o aluno paga.
     *
     * @return void
     */
    public function test_busca_por_referencia(): void {
        fake_mp_client::$nextresponse = ['results' => [['id' => 9, 'status' => 'approved']]];

        $client = new fake_mp_client('token');
        $encontrados = $client->search_by_reference('mdl-1-2-a b');

        $this->assertCount(1, $encontrados);
        $this->assertSame(9, $encontrados[0]['id']);
        $this->assertSame(
            [['GET', 'https://api.mercadopago.com/v1/payments/search?external_reference=mdl-1-2-a%20b']],
            fake_mp_client::$calls
        );
    }

    /**
     * Ninguem pagou ainda: lista vazia, e nao erro.
     *
     * A reconciliacao roda de hora em hora sobre transacoes que podem nunca ter
     * sido pagas. Tratar ausencia como falha encheria o log de ruido e
     * esconderia a falha de verdade.
     *
     * @return void
     */
    public function test_busca_sem_resultado_devolve_lista_vazia(): void {
        fake_mp_client::$nextresponse = ['paging' => ['total' => 0], 'results' => []];

        $client = new fake_mp_client('token');

        $this->assertSame([], $client->search_by_reference('mdl-1-2-nunca-pago'));
    }

    /**
     * Resposta sem a chave results nao quebra a reconciliacao.
     *
     * @return void
     */
    public function test_busca_sem_a_chave_results(): void {
        fake_mp_client::$nextresponse = ['paging' => ['total' => 0]];

        $client = new fake_mp_client('token');

        $this->assertSame([], $client->search_by_reference('mdl-1-2-abc'));
    }

    /**
     * A assinatura nasce num POST /preapproval.
     *
     * @return void
     */
    public function test_a_assinatura_e_criada_no_endpoint_de_preapproval(): void {
        fake_mp_client::$nextresponse = ['id' => 'abc123', 'status' => 'pending'];

        $resposta = (new fake_mp_client('token'))->create_preapproval(['reason' => 'Curso']);

        $this->assertSame('abc123', $resposta['id']);
        $this->assertSame('POST', fake_mp_client::$calls[0][0]);
        $this->assertStringEndsWith('/preapproval', fake_mp_client::$calls[0][1]);
        $this->assertSame('Curso', fake_mp_client::$lastbody['reason']);
    }

    /**
     * Cancelar e pausar a assinatura sao PUT, e nao POST.
     *
     * O mp_client so sabia GET e POST. Mandar POST no /preapproval/{id} cria
     * outra assinatura em vez de alterar a existente - e o aluno passaria a ser
     * cobrado duas vezes, sem erro nenhum na tela.
     *
     * @return void
     */
    public function test_alterar_assinatura_usa_put(): void {
        fake_mp_client::$nextresponse = ['id' => 'abc123', 'status' => 'cancelled'];

        (new fake_mp_client('token'))->cancel_preapproval('abc123');

        $this->assertSame('PUT', fake_mp_client::$calls[0][0]);
        $this->assertStringEndsWith('/preapproval/abc123', fake_mp_client::$calls[0][1]);
        $this->assertSame('cancelled', fake_mp_client::$lastbody['status']);
    }

    /**
     * O id da assinatura e escapado na URL.
     *
     * @return void
     */
    public function test_o_id_da_assinatura_e_escapado(): void {
        fake_mp_client::$nextresponse = ['id' => 'x'];

        (new fake_mp_client('token'))->get_preapproval('a b/c');

        $this->assertStringEndsWith('/preapproval/a%20b%2Fc', fake_mp_client::$calls[0][1]);
    }

    /**
     * A cobranca do ciclo leva o application_fee.
     *
     * E o unico numero deste plugin que move dinheiro na assinatura, e a razao
     * de make_curl() ser sobrescrevivel: sem esta costura ele so seria
     * exercitavel batendo na API de verdade.
     *
     * Medido em 16/09/2026: diferente do preapproval, que aceita cinco formatos
     * de campo de taxa e descarta todos, o /v1/payments HONRA este campo - ele
     * volta em fee_details. Ver docs/data-validation/mercadopago-assinatura.md.
     *
     * @return void
     */
    public function test_a_cobranca_do_ciclo_leva_a_comissao(): void {
        fake_mp_client::$nextresponse = ['id' => 1352076103, 'status' => 'approved'];

        (new fake_mp_client('token'))->create_payment([
            'transaction_amount' => 5.0,
            'application_fee' => 1.25,
            'token' => 'tok',
        ]);

        $this->assertStringEndsWith('/v1/payments', fake_mp_client::$calls[0][1]);
        $this->assertEquals(1.25, fake_mp_client::$lastbody['application_fee']);
    }

    /**
     * O cliente e o cartao guardado vivem no Mercado Pago.
     *
     * @return void
     */
    public function test_o_cartao_e_guardado_na_conta_do_vendedor(): void {
        fake_mp_client::$nextresponse = ['id' => '1789552967889', 'last_four_digits' => '3311'];

        (new fake_mp_client('token'))->save_card('3694589152-7R6', 'cardtoken');

        $this->assertStringEndsWith('/v1/customers/3694589152-7R6/cards', fake_mp_client::$calls[0][1]);
        $this->assertSame('cardtoken', fake_mp_client::$lastbody['token']);
    }

    /**
     * A bandeira e o emissor vao no corpo quando conhecidos.
     *
     * Medido em 16/09/2026: com so a bandeira, uma compra real ainda voltou
     * "Cannot resolve the payment method of card, check the payment_method_id
     * and issuer_id" - o proprio Mercado Pago apontando o campo que faltava.
     *
     * @return void
     */
    public function test_bandeira_e_emissor_vao_no_corpo(): void {
        fake_mp_client::$nextresponse = ['id' => '1'];

        (new fake_mp_client('token'))->save_card('cus', 'cardtoken', 'visa', '25');

        $this->assertSame('visa', fake_mp_client::$lastbody['payment_method_id']);

        // NUMERO, e nao string - medido em 16/09/2026: issuer_id como string
        // ("25") faz o Mercado Pago devolver "400 the body must be a Json
        // Object", uma mensagem que nao aponta o campo de verdade.
        $this->assertSame(25, fake_mp_client::$lastbody['issuer_id']);
    }

    /**
     * Sem bandeira ou emissor, os campos ficam de fora - ausente e diferente
     * de vazio para este endpoint (ver decode() e o teste da causa 128).
     *
     * @return void
     */
    public function test_sem_bandeira_ou_emissor_os_campos_ficam_de_fora(): void {
        fake_mp_client::$nextresponse = ['id' => '1'];

        (new fake_mp_client('token'))->save_card('cus', 'cardtoken');

        $this->assertArrayNotHasKey('payment_method_id', fake_mp_client::$lastbody);
        $this->assertArrayNotHasKey('issuer_id', fake_mp_client::$lastbody);
    }

    /**
     * Quando o Mercado Pago recusa o cartao, o erro leva o que foi tentado.
     *
     * "Cannot resolve the payment method of card, check the payment_method_id
     * and issuer_id" nao diz que valores foram mandados. Sem isso, "ainda
     * falha" vira outra rodada de curl so para redescobrir o que o codigo ja
     * sabia na hora da chamada - custou duas rodadas de prova real em
     * 16/09/2026.
     *
     * @return void
     */
    public function test_erro_do_savecard_leva_o_que_foi_tentado(): void {
        fake_mp_client::$nextstatus = 400;
        fake_mp_client::$nextresponse = [
            'message' => 'invalid parameter in payment method',
            'cause' => [
                ['code' => '127', 'description' => 'Cannot resolve the payment method of card'],
            ],
        ];

        $client = new fake_mp_client('token');

        try {
            $client->save_card('cus', 'cardtoken', 'visa', '25');
            $this->fail('status fora de 2xx tem que virar excecao');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('127: Cannot resolve', $e->getMessage());
            $this->assertStringContainsString('payment_method_id=visa', $e->getMessage());
            $this->assertStringContainsString('issuer_id=25', $e->getMessage());
        }
    }

    /**
     * O emissor vem do endpoint DEDICADO, e nao do campo "issuer" generico da
     * busca por BIN.
     *
     * Medido em 16/09/2026: uma compra real com a bandeira certa E o emissor
     * generico que a busca por BIN devolvia ("default": true) ainda voltou
     * "Cannot resolve the payment method of card". O endpoint que resolve o
     * emissor de verdade e outro - GET /payment_methods/card_issuers, o mesmo
     * que o SDK oficial expoe como mp.getIssuers().
     *
     * @return void
     */
    public function test_guess_payment_method_usa_o_endpoint_de_emissores(): void {
        fake_mp_client::$responsequeue = [
            [
                'results' => [
                    ['id' => 'pix', 'payment_type_id' => 'bank_transfer'],
                    ['id' => 'visa', 'payment_type_id' => 'credit_card', 'issuer' => ['id' => 25, 'default' => true]],
                ],
            ],
            [
                ['id' => '12749', 'name' => 'Santander'],
            ],
        ];

        $metodo = fake_mp_client::guess_payment_method('publickey', '453998');

        $this->assertSame('visa', $metodo['id']);
        // NAO 25 (o generico da primeira chamada) - 12749, o que o endpoint
        // dedicado devolveu.
        $this->assertSame('12749', $metodo['issuerid']);

        $this->assertCount(2, fake_mp_client::$calls);
        $this->assertStringContainsString('/payment_methods/search', fake_mp_client::$calls[0][1]);
        $this->assertStringContainsString('bins=453998', fake_mp_client::$calls[0][1]);
        $this->assertStringContainsString('/payment_methods/card_issuers', fake_mp_client::$calls[1][1]);
        $this->assertStringContainsString('payment_method_id=visa', fake_mp_client::$calls[1][1]);
        $this->assertStringContainsString('bin=453998', fake_mp_client::$calls[1][1]);
    }

    /**
     * Sem cartao no resultado, nao ha bandeira nem emissor para descobrir - e
     * a segunda chamada nem acontece.
     *
     * @return void
     */
    public function test_guess_payment_method_sem_cartao_nao_busca_emissor(): void {
        fake_mp_client::$nextresponse = [
            'results' => [
                ['id' => 'pix', 'payment_type_id' => 'bank_transfer'],
            ],
        ];

        $metodo = fake_mp_client::guess_payment_method('publickey', '453998');

        $this->assertSame([], $metodo);
        $this->assertCount(1, fake_mp_client::$calls);
    }
}
