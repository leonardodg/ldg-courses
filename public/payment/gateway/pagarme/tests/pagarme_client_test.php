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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_pagarme_client.php');
require_once(__DIR__ . '/fixtures/documented_responses.php');

/**
 * A camada HTTP, sem rede.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pagarme_client::class)]
final class pagarme_client_test extends \advanced_testcase {
    /** @var string Recebedor do vendedor, nos testes. */
    const SELLER = 'rp_vendedor00000001';

    /** @var string Recebedor da plataforma, nos testes. */
    const PLATFORM = 'rp_plataforma000001';

    // --------------------------------------------------------------------

    public function test_o_ambiente_vem_do_prefixo_da_chave(): void {
        $this->assertSame(
            pagarme_client::ENV_SANDBOX,
            pagarme_client::environment_of_key('sk_test_abc123')
        );
        $this->assertSame(
            pagarme_client::ENV_PRODUCTION,
            pagarme_client::environment_of_key('sk_abc123')
        );
    }

    public function test_chave_desconhecida_e_tratada_como_producao(): void {
        // Errar para producao custa um teste que falha. Errar para
        // homologacao trataria dinheiro real como se fosse de mentira.
        $this->assertSame(
            pagarme_client::ENV_PRODUCTION,
            pagarme_client::environment_of_key('qualquer_coisa')
        );
    }

    public function test_ha_um_endereco_so(): void {
        // O plano original mandava usar sdx-api.pagar.me. Medido em
        // 09/09/2026: aquele host responde 404 e nao existe.
        $this->assertSame('https://api.pagar.me/core/v5', pagarme_client::BASE_URL);
    }

    // --------------------------------------------------------------------

    public function test_converte_moeda_para_centavos(): void {
        $this->assertSame(10000, pagarme_client::to_cents(100.00));
        $this->assertSame(1999, pagarme_client::to_cents(19.99));
        $this->assertSame(1, pagarme_client::to_cents(0.01));
    }

    public function test_arredondamento_nao_perde_centavo(): void {
        // 0.1 + 0.2 em ponto flutuante nao e 0.3. Sem o round, isto viraria
        // 29 centavos.
        $this->assertSame(30, pagarme_client::to_cents(0.1 + 0.2));
    }

    public function test_volta_de_centavos_para_moeda(): void {
        $this->assertSame(100.0, pagarme_client::from_cents(10000));
        $this->assertSame(19.99, pagarme_client::from_cents(1999));
    }

    // --------------------------------------------------------------------

    public function test_split_sobre_o_bruto_vira_flat(): void {
        $split = pagarme_client::build_split(self::SELLER, self::PLATFORM, 25.0, 100.00, 'gross');

        $this->assertCount(2, $split);
        $this->assertSame('flat', $split[0]['type']);
        $this->assertSame('flat', $split[1]['type']);
        $this->assertSame(self::SELLER, $split[0]['recipient_id']);
        $this->assertSame(self::PLATFORM, $split[1]['recipient_id']);
        $this->assertSame(7500, $split[0]['amount']);
        $this->assertSame(2500, $split[1]['amount']);
    }

    public function test_as_duas_regras_somam_o_valor_cheio(): void {
        $split = pagarme_client::build_split(self::SELLER, self::PLATFORM, 33.0, 19.99, 'gross');

        $this->assertSame(
            pagarme_client::to_cents(19.99),
            $split[0]['amount'] + $split[1]['amount']
        );
    }

    public function test_o_valor_do_split_e_inteiro(): void {
        // MEDIDO em 11/09/2026: um amount float (75.0) e recusado com HTTP 400
        // "The request is invalid." sem dizer qual campo. Com 75 inteiro a
        // mesma requisicao passa. Este teste existe para um refactor futuro
        // nao reintroduzir float sem ninguem perceber.
        $split = pagarme_client::build_split(self::SELLER, self::PLATFORM, 33.0, 19.99, 'gross');

        foreach ($split as $regra) {
            $this->assertIsInt($regra['amount'], 'o Pagar.me recusa amount float com 400 sem detalhe');
        }
    }

    public function test_o_vendedor_e_o_responsavel(): void {
        // A documentacao exige que ao menos um recebedor responda pelas tres
        // coisas, e a regra fiscal diz que e quem vende - ele emite a nota.
        $split = pagarme_client::build_split(self::SELLER, self::PLATFORM, 25.0, 100.00, 'gross');

        $this->assertTrue($split[0]['options']['liable']);
        $this->assertTrue($split[0]['options']['charge_processing_fee']);
        $this->assertTrue($split[0]['options']['charge_remainder_fee']);

        $this->assertFalse($split[1]['options']['liable']);
        $this->assertFalse($split[1]['options']['charge_processing_fee']);
        $this->assertFalse($split[1]['options']['charge_remainder_fee']);
    }

    // --------------------------------------------------------------------

    public function test_o_pagarme_nao_consegue_cobrar_sobre_o_liquido(): void {
        // MEDIDO em 11/09/2026: o 'percentage' do Pagar.me incide sobre o
        // BRUTO - 25% de R$ 100,00 deram exatamente R$ 25,00 no extrato. E o
        // oposto do Asaas, onde percentualValue incide sobre o liquido.
        //
        // Entao pedir 'net' aqui nao tem como ser atendido: a taxa so se
        // conhece depois da liquidacao. O split sai igual ao do bruto, e o
        // registro da venda expoe a divergencia.
        $bruto = pagarme_client::build_split(self::SELLER, self::PLATFORM, 25.0, 100.00, 'gross');
        $liquido = pagarme_client::build_split(self::SELLER, self::PLATFORM, 25.0, 100.00, 'net');

        $this->assertSame($bruto, $liquido);
    }

    public function test_a_base_aplicada_e_sempre_bruta(): void {
        // Mesma decisao do paygw_mercadopago: gravamos o que aconteceu, nao o
        // que foi pedido.
        $this->assertSame('gross', pagarme_client::applied_base('gross'));
        $this->assertSame('gross', pagarme_client::applied_base('net'));
        $this->assertSame('gross', pagarme_client::applied_base('inventada'));
    }

    public function test_base_desconhecida_cai_no_bruto(): void {
        $split = pagarme_client::build_split(self::SELLER, self::PLATFORM, 25.0, 100.00, 'inventada');

        $this->assertSame('flat', $split[0]['type']);
    }

    // --------------------------------------------------------------------

    public function test_sem_recebedor_nao_ha_split(): void {
        $this->assertSame([], pagarme_client::build_split('', self::PLATFORM, 25.0, 100.00));
        $this->assertSame([], pagarme_client::build_split(self::SELLER, '', 25.0, 100.00));
    }

    public function test_recebedores_iguais_nao_produzem_split(): void {
        // E a repeticao do erro do Mercado Pago: vendedor e marketplace na
        // mesma conta, o campo aceito, e nada mudando de dono.
        $this->assertSame(
            [],
            pagarme_client::build_split(self::SELLER, self::SELLER, 25.0, 100.00)
        );
    }

    public function test_comissao_zero_nao_produz_split(): void {
        $this->assertSame(
            [],
            pagarme_client::build_split(self::SELLER, self::PLATFORM, 0.0, 100.00)
        );
    }

    public function test_comissao_que_zera_no_arredondamento_some(): void {
        // Um centavo com 1% daria zero. Split de valor zero e recusado pela
        // API, entao a regra inteira precisa sumir.
        $this->assertSame(
            [],
            pagarme_client::build_split(self::SELLER, self::PLATFORM, 1.0, 0.01)
        );
    }

    public function test_comissao_de_cem_por_cento_nao_deixa_resto(): void {
        // O vendedor ficaria com zero, e regra de valor zero e recusada.
        $this->assertSame(
            [],
            pagarme_client::build_split(self::SELLER, self::PLATFORM, 100.0, 100.00)
        );
    }

    public function test_percentual_acima_de_cem_e_limitado(): void {
        $split = pagarme_client::build_split(self::SELLER, self::PLATFORM, 150.0, 100.00, 'net');

        $this->assertSame([], $split);
    }

    // --------------------------------------------------------------------

    public function test_veredito_le_o_erro_do_adquirente(): void {
        // Medido em 09/09/2026: o POST devolveu 200 e a cobranca nasceu
        // failed, com o motivo enterrado no gateway_response.
        [$status, $code, $message] = pagarme_client::charge_verdict([
            'status' => 'failed',
            'last_transaction' => [
                'gateway_response' => [
                    'code' => '500',
                    'errors' => [['message' => 'internal_error |  | Erro desconhecido no proxy']],
                ],
            ],
        ]);

        $this->assertSame('failed', $status);
        $this->assertSame('500', $code);
        $this->assertStringContainsString('proxy', $message);
    }

    public function test_veredito_de_cobranca_boa(): void {
        [$status, $code, $message] = pagarme_client::charge_verdict([
            'status' => 'paid',
            'last_transaction' => ['gateway_response' => ['code' => '200', 'errors' => []]],
        ]);

        $this->assertSame('paid', $status);
        $this->assertSame('200', $code);
        $this->assertSame('', $message);
    }

    public function test_veredito_de_cobranca_vazia_nao_estoura(): void {
        $this->assertSame(['', '', ''], pagarme_client::charge_verdict([]));
    }

    // --------------------------------------------------------------------

    public function test_comissao_e_lida_do_split_de_volta(): void {
        $charge = ['splits' => [
            ['recipient_id' => self::SELLER, 'amount' => 7500],
            ['recipient_id' => self::PLATFORM, 'amount' => 2500],
        ]];

        $this->assertSame(25.0, pagarme_client::commission_from($charge, self::PLATFORM));
    }

    public function test_split_ausente_e_comissao_zero(): void {
        // Um splits nulo foi o que o Pagar.me devolveu quando o recebedor nao
        // existia. Tratar isso como "o gateway nao informou" produziria uma
        // venda registrando comissao que nunca aconteceu.
        $this->assertSame(0.0, pagarme_client::commission_from(['splits' => null], self::PLATFORM));
        $this->assertSame(0.0, pagarme_client::commission_from([], self::PLATFORM));
    }

    public function test_carteira_de_outro_nao_e_nossa_comissao(): void {
        $charge = ['splits' => [['recipient_id' => self::SELLER, 'amount' => 10000]]];

        $this->assertSame(0.0, pagarme_client::commission_from($charge, self::PLATFORM));
    }

    public function test_sem_recebedor_da_plataforma_nao_ha_o_que_somar(): void {
        $charge = ['splits' => [['recipient_id' => self::PLATFORM, 'amount' => 2500]]];

        $this->assertSame(0.0, pagarme_client::commission_from($charge, ''));
    }

    // --------------------------------------------------------------------

    public function test_a_order_leva_o_split(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['id' => 'or_1', 'charges' => [['id' => 'ch_1', 'status' => 'pending']]];

        $client->create_order([
            'items' => [['amount' => 10000, 'code' => 'c1', 'description' => 'Curso', 'quantity' => 1]],
            'customer' => ['email' => 'a@b.test'],
            'payments' => [[
                'payment_method' => 'pix',
                'split' => pagarme_client::build_split(self::SELLER, self::PLATFORM, 25.0, 100.00),
            ]],
        ]);

        $this->assertSame([['POST', '/orders']], $client->calls);
        $this->assertSame(2500, $client->lastbody['payments'][0]['split'][1]['amount']);
    }

    public function test_cancelar_assinatura_usa_delete(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['id' => 'sub_1', 'status' => 'canceled'];

        $client->cancel_subscription('sub_1');

        $this->assertSame([['DELETE', '/subscriptions/sub_1']], $client->calls);
    }

    public function test_o_estorno_e_sempre_total(): void {
        // MEDIDO em 11/09/2026: pedir R$ 40,00 de uma cobranca de R$ 100,00
        // devolve 200, marca canceled_amount 4000, e deixa os DOIS payables
        // intactos - o comprador recebe e nenhum recebedor devolve nada. O
        // dinheiro sai do saldo da conta.
        //
        // Por isso cancel_charge() nao aceita valor: parametro que existe
        // acaba usado, e aqui usar significaria pagar o estorno do proprio
        // bolso sem nada na tela dizendo isso.
        $method = new \ReflectionMethod(pagarme_client::class, 'cancel_charge');

        $this->assertSame(
            1,
            $method->getNumberOfParameters(),
            'cancel_charge nao pode aceitar valor parcial'
        );
    }

    public function test_estorno_total_usa_delete_sem_corpo(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['id' => 'ch_1', 'status' => 'canceled'];

        $client->cancel_charge('ch_1');

        $this->assertSame([['DELETE', '/charges/ch_1']], $client->calls);
        $this->assertSame([], $client->lastbody);
    }

    public function test_trocar_cartao_usa_patch(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['id' => 'sub_1'];

        $client->update_subscription_card('sub_1', ['card_token' => 'token_1']);

        $this->assertSame([['PATCH', '/subscriptions/sub_1/card']], $client->calls);
    }

    public function test_cliente_existente_e_reaproveitado(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['data' => [['id' => 'cus_ja_existe']]];

        $id = $client->find_or_create_customer(['email' => 'a@b.test', 'name' => 'A']);

        $this->assertSame('cus_ja_existe', $id);
        // Uma chamada so: nao criou nada.
        $this->assertCount(1, $client->calls);
        $this->assertSame('GET', $client->calls[0][0]);
    }

    public function test_cliente_novo_e_criado(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['data' => []];

        $client->find_or_create_customer(['email' => 'novo@b.test', 'name' => 'Novo']);

        $this->assertCount(2, $client->calls);
        $this->assertSame(['POST', '/customers'], $client->calls[1]);
    }

    public function test_recebedor_padrao_ausente_devolve_vazio(): void {
        // Conta recem-criada responde 412 aqui. Isso e configuracao pendente
        // do vendedor, e nao pode derrubar o vinculo.
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextstatus = 412;
        $client->nextresponse = ['message' => 'There is no default recipient registered for this account.'];

        $this->assertSame([], $client->get_default_recipient());
    }

    // --------------------------------------------------------------------

    public function test_erro_da_api_carrega_a_mensagem(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextstatus = 412;
        $client->nextresponse = [
            'message' => 'The recipient could not be created : action_forbidden',
        ];

        try {
            $client->create_order([]);
            $this->fail('deveria ter estourado');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('412', $e->getMessage());
            $this->assertStringContainsString('action_forbidden', $e->getMessage());
        }
    }

    public function test_erro_de_validacao_lista_os_campos(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextstatus = 422;
        $client->nextresponse = [
            'message' => 'The request is invalid.',
            'errors' => ['type' => ['The type field is invalid.']],
        ];

        try {
            $client->create_order([]);
            $this->fail('deveria ter estourado');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('type: The type field is invalid.', $e->getMessage());
        }
    }

    public function test_resposta_que_nao_e_json_e_recusada(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->rawresponse = '<html>gateway timeout</html>';

        $this->expectException(\moodle_exception::class);
        $client->get_charge('ch_1');
    }

    public function test_falha_de_curl_e_reportada(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nexterrno = 28;

        $this->expectException(\moodle_exception::class);
        $client->get_charge('ch_1');
    }

    // --------------------------------------------------------------------

    public function test_comissao_lida_da_resposta_documentada(): void {
        // O exemplo de criar-pedido-2 divide 50/50; aqui esta com os valores
        // que o nosso build_split produziria, em CENTAVOS.
        $charge = documented_responses::paid_charge_with_split();

        $this->assertSame(25.0, pagarme_client::commission_from($charge, 'rp_yLnAyVpHbQIqZxwO'));
        $this->assertSame(75.0, pagarme_client::commission_from($charge, 'rp_5yGwpMGckBHVYmb6'));
    }

    public function test_veredito_da_cobranca_paga_documentada(): void {
        [$status, $code, $message] = pagarme_client::charge_verdict(
            documented_responses::paid_charge_with_split()
        );

        $this->assertSame('paid', $status);
        $this->assertSame('201', $code);
        $this->assertSame('', $message);
    }

    public function test_veredito_da_cobranca_que_falhou_de_verdade(): void {
        // Resposta real da conta de homologacao, e o unico fracasso medido.
        [$status, $code, $message] = pagarme_client::charge_verdict(
            documented_responses::charge_that_failed()
        );

        $this->assertSame('failed', $status);
        $this->assertSame('500', $code);
        $this->assertStringContainsString('Erro desconhecido no proxy', $message);
    }

    public function test_a_cobranca_paga_esconde_o_split_que_aconteceu(): void {
        // MEDIDO em 11/09/2026: o split rodou, o extrato dos dois recebedores
        // se moveu, e mesmo assim o GET da cobranca traz splits null. Ler
        // daqui concluiria que a comissao foi zero - e ela foi R$ 25,00.
        $charge = documented_responses::paid_charge_without_splits_on_get();

        $this->assertSame('paid', $charge['status']);
        $this->assertNull($charge['splits']);
        $this->assertSame(0.0, pagarme_client::commission_from($charge, 're_cmtxd5ug80hhv0m9ttcbnek5w'));
    }

    public function test_a_comissao_de_verdade_sai_dos_payables(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['data' => [documented_responses::payable_for_platform()]];

        $this->assertSame(25.0, $client->commission_for_charge(
            'ch_KME2JgJuJnT1XlX7',
            're_cmtxd5ug80hhv0m9ttcbnek5w'
        ));
        $this->assertSame([['GET', '/payables?recipient_id=re_cmtxd5ug80hhv0m9ttcbnek5w&size=100']], $client->calls);
    }

    public function test_payable_de_outra_cobranca_nao_entra_na_conta(): void {
        // O filtro por charge_id nao funciona na API - o payable e listado por
        // recebedor e traz o charge_id dentro. A separacao e nossa.
        $other = documented_responses::payable_for_platform();
        $other['charge_id'] = 'ch_de_outra_venda';

        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['data' => [$other]];

        $this->assertSame(0.0, $client->commission_for_charge(
            'ch_KME2JgJuJnT1XlX7',
            're_cmtxd5ug80hhv0m9ttcbnek5w'
        ));
    }

    public function test_a_comissao_desconta_a_taxa_que_a_plataforma_pagar(): void {
        // Hoje a plataforma tem charge_processing_fee false e fee zero. Se um
        // dia carregar taxa, o que ela RECEBE e amount menos fee - e e isso
        // que precisa ser gravado, nao o bruto da regra.
        $payable = documented_responses::payable_for_platform();
        $payable['fee'] = 300;

        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['data' => [$payable]];

        $this->assertSame(22.0, $client->commission_for_charge(
            'ch_KME2JgJuJnT1XlX7',
            're_cmtxd5ug80hhv0m9ttcbnek5w'
        ));
    }

    public function test_a_taxa_saiu_inteira_do_vendedor(): void {
        // R$ 100,00: vendedor 7500 bruto com 449 de taxa, plataforma 2500 com
        // zero. Soma 70,51 + 25,00 = 95,51, e os 4,49 que faltam sao a taxa.
        $seller = documented_responses::payable_for_seller();
        $platform = documented_responses::payable_for_platform();

        $this->assertSame(449, $seller['fee']);
        $this->assertSame(0, $platform['fee']);
        $this->assertSame(10000, $seller['amount'] + $platform['amount']);
    }

    public function test_sem_payable_a_comissao_e_zero(): void {
        $client = new fake_pagarme_client('sk_test_x');
        $client->nextresponse = ['data' => []];

        $this->assertSame(0.0, $client->commission_for_charge('ch_1', 're_1'));
    }

    public function test_o_pix_recusa_com_ambiente_nao_configurado(): void {
        // MEDIDO em 11/09/2026, depois de os recebedores serem liberados:
        // cartao e boleto passaram, e so o Pix continuou barrado.
        [$status, $code, $message] = pagarme_client::charge_verdict(
            documented_responses::pix_without_environment()
        );

        $this->assertSame('failed', $status);
        $this->assertSame('400', $code);
        $this->assertStringContainsString('Sem ambiente configurado', $message);
    }

    public function test_o_200_com_recebedor_inexistente_nao_engana(): void {
        // A order voltou HTTP 200 e corpo completo. Se o codigo olhasse so o
        // status HTTP, registraria venda de uma cobranca que nunca existiu.
        $order = documented_responses::split_for_nonexistent_recipient();
        $charge = $order['charges'][0];

        [$status, $code, $message] = pagarme_client::charge_verdict($charge);

        $this->assertSame('failed', $status);
        $this->assertSame('404', $code);
        $this->assertStringContainsString('Recipient not found', $message);

        // E o mais importante: comissao ZERO, e nao "o gateway nao informou".
        $this->assertSame(0.0, pagarme_client::commission_from($charge, 'rp_qualquer'));
    }
}
