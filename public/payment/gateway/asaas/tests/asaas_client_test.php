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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

// O transporte falso nao e autocarregado: vive em tests/fixtures.
require_once(__DIR__ . '/fixtures/fake_asaas_client.php');

/**
 * A camada HTTP, sem rede.
 *
 * Isto so e possivel por causa da costura make_curl(). O cliente equivalente do
 * Mercado Pago instancia curl inline, e por isso a montagem de corpo e o
 * mapeamento de erro dele nunca foram testados - o unico jeito de exercitar
 * seria bater na API de verdade.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_asaas\asaas_client::class)]
final class asaas_client_test extends \advanced_testcase {
    /**
     * O split e um valor FIXO, calculado sobre o bruto.
     *
     * @return void
     */
    public function test_build_split(): void {
        $split = asaas_client::build_split('wallet-abc', 25.0, 100.0);

        $this->assertCount(1, $split);
        $this->assertSame('wallet-abc', $split[0]['walletId']);
        $this->assertEqualsWithDelta(25.0, $split[0]['fixedValue'], 0.0001);
        // Nunca percentualValue: o Asaas o aplica sobre o netValue.
        $this->assertArrayNotHasKey('percentualValue', $split[0]);
    }

    /**
     * A comissao e sobre o BRUTO, e nao sobre o que sobra da taxa do gateway.
     *
     * O caso que motivou a mudanca: R$ 100,00 a 9,9%. Com percentualValue o
     * Asaas dividiria o netValue de R$ 97,52 e devolveria R$ 9,65 - a
     * plataforma pagando parte da taxa do gateway sem ter combinado isso.
     *
     * @return void
     */
    public function test_split_incide_sobre_o_bruto(): void {
        $split = asaas_client::build_split('wallet-abc', 9.9, 100.0);

        $this->assertEqualsWithDelta(9.90, $split[0]['fixedValue'], 0.0001);
    }

    /**
     * O valor sai com duas casas, que e o que o Asaas aceita.
     *
     * @return void
     */
    public function test_split_arredonda_para_centavos(): void {
        $split = asaas_client::build_split('wallet-abc', 9.9, 49.90);

        // 49,90 x 9,9% = 4,9401.
        $this->assertEqualsWithDelta(4.94, $split[0]['fixedValue'], 0.0001);
    }

    /**
     * Comissao que arredonda para zero NAO vira split.
     *
     * Centavo de comissao em venda de centavos: mandar fixedValue zerado faz o
     * Asaas recusar o corpo, e a venda inteira falharia por causa do
     * arredondamento.
     *
     * @return void
     */
    public function test_comissao_que_zera_no_arredondamento(): void {
        $this->assertSame([], asaas_client::build_split('wallet-abc', 0.1, 0.04));
    }

    /**
     * Valor de cobranca invalido nao produz split.
     *
     * @return void
     */
    public function test_valor_invalido_nao_produz_split(): void {
        $this->assertSame([], asaas_client::build_split('wallet-abc', 25.0, 0.0));
        $this->assertSame([], asaas_client::build_split('wallet-abc', 25.0, -10.0));
    }

    /**
     * Comissao zero NAO vira split.
     *
     * Um parceiro isento e caso real - a empresa pode ter commissionpct 0. Um
     * split de 0% seria recusado pelo Asaas, e a compra inteira falharia por
     * causa de uma isencao negociada.
     *
     * @return void
     */
    public function test_zero_commission_produces_no_split(): void {
        $this->assertSame([], asaas_client::build_split('wallet-abc', 0.0, 100.0));
        $this->assertSame([], asaas_client::build_split('wallet-abc', -5.0, 100.0));
    }

    /**
     * Sem carteira da plataforma nao ha split.
     *
     * Acontece quando o administrador ainda nao configurou a carteira. Melhor
     * cobrar sem comissao - dinheiro na conta do vendedor, conciliavel depois -
     * do que recusar a venda.
     *
     * @return void
     */
    public function test_missing_wallet_produces_no_split(): void {
        $this->assertSame([], asaas_client::build_split('', 25.0, 100.0));
        $this->assertSame([], asaas_client::build_split('   ', 25.0, 100.0));
    }

    /**
     * Percentual acima de 100 nao leva mais do que a cobranca inteira.
     *
     * @return void
     */
    public function test_split_is_capped_at_one_hundred(): void {
        $split = asaas_client::build_split('wallet-abc', 150.0, 80.0);

        $this->assertEqualsWithDelta(80.0, $split[0]['fixedValue'], 0.0001);
    }

    /**
     * O prefixo da chave diz o ambiente.
     *
     * @return void
     */
    public function test_environment_of_key(): void {
        $sandbox = '$aact_hmlg_chave-de-exemplo-nao-usada';
        $production = '$aact_chave-de-exemplo-nao-usada';

        $this->assertSame(asaas_client::ENV_SANDBOX, asaas_client::environment_of_key($sandbox));
        $this->assertSame(asaas_client::ENV_PRODUCTION, asaas_client::environment_of_key($production));
        $this->assertSame(asaas_client::ENV_SANDBOX, asaas_client::environment_of_key('  ' . $sandbox . '  '));
    }

    /**
     * Cada ambiente tem a propria base, e nunca a do outro.
     *
     * Uma chave de homologacao apontando para a base de producao devolve 401 -
     * e o inverso e pior: dado de teste indo para a base real.
     *
     * @return void
     */
    public function test_base_urls_are_distinct(): void {
        $this->assertStringContainsString('sandbox', asaas_client::BASE_URL[asaas_client::ENV_SANDBOX]);
        $this->assertStringNotContainsString('sandbox', asaas_client::BASE_URL[asaas_client::ENV_PRODUCTION]);
    }

    /**
     * Ambiente desconhecido cai em homologacao, e nao em producao.
     *
     * Se um valor invalido chegar aqui, errar para o lado do sandbox custa um
     * teste que nao funciona; errar para producao custa uma cobranca real.
     *
     * @return void
     */
    public function test_unknown_environment_falls_back_to_sandbox(): void {
        $client = new asaas_client('chave', 'seja-la-o-que-for');

        $this->assertSame(asaas_client::ENV_SANDBOX, $client->get_environment());
    }

    /**
     * A URL de retorno tem que bater com o dominio cadastrado no Asaas.
     *
     * O Asaas recusa a cobranca INTEIRA quando nao bate - "E necessario enviar
     * uma URL que use o mesmo dominio cadastrado nas suas Minha Conta na aba
     * Informacoes". Como a cobranca nasce na conta do vendedor e o retorno
     * aponta para a plataforma, e o dominio DA PLATAFORMA que precisa estar
     * cadastrado na conta dele.
     *
     * @return void
     */
    public function test_same_host(): void {
        $this->assertTrue(asaas_client::same_host('https://cursos.exemplo.com', 'https://cursos.exemplo.com/moodle'));
        $this->assertTrue(asaas_client::same_host('cursos.exemplo.com', 'https://cursos.exemplo.com'), 'aceita sem esquema');
        $this->assertTrue(asaas_client::same_host('https://www.exemplo.com', 'https://exemplo.com'), 'www nao diferencia');
        $this->assertTrue(asaas_client::same_host('HTTPS://Exemplo.COM', 'https://exemplo.com'), 'maiusculas nao diferenciam');

        $this->assertFalse(asaas_client::same_host('https://outro.com', 'https://exemplo.com'));
        $this->assertFalse(asaas_client::same_host('https://sub.exemplo.com', 'https://exemplo.com'), 'subdominio e outro host');
        $this->assertFalse(asaas_client::same_host('', 'https://exemplo.com'), 'conta sem site nao passa');
        $this->assertFalse(asaas_client::same_host('   ', 'https://exemplo.com'));
    }

    /**
     * O corpo enviado carrega split, referencia e retorno.
     *
     * @return void
     */
    public function test_create_payment_body(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['id' => 'pay_1', 'status' => 'PENDING', 'invoiceUrl' => 'https://x/i/1'];

        $client->create_payment([
            'customer' => 'cus_1',
            'billingtype' => 'PIX',
            'value' => 100.0,
            'duedate' => '2026-09-01',
            'description' => 'Curso',
            'externalreference' => 'mdl-1-2-abc',
            'returnurl' => 'https://moodle.test/return.php?ref=mdl-1-2-abc',
            'splitwalletid' => 'wallet-plataforma',
            'splitpercent' => 25.0,
        ]);

        $body = $client->lastbody;

        $this->assertSame('cus_1', $body['customer']);
        $this->assertSame('PIX', $body['billingType']);
        $this->assertSame(100.0, $body['value']);
        $this->assertSame('mdl-1-2-abc', $body['externalReference']);
        $this->assertSame('wallet-plataforma', $body['split'][0]['walletId']);
        $this->assertTrue($body['callback']['autoRedirect']);
    }

    /**
     * Sem comissao, a chave split nao vai no corpo.
     *
     * Um split vazio nao e "sem comissao": e um corpo invalido, e o Asaas
     * recusa a cobranca inteira.
     *
     * @return void
     */
    public function test_create_payment_omits_empty_split(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['id' => 'pay_1', 'invoiceUrl' => 'https://x/i/1'];

        $client->create_payment([
            'customer' => 'cus_1',
            'billingtype' => 'PIX',
            'value' => 100.0,
            'duedate' => '2026-09-01',
            'externalreference' => 'mdl-1-2-abc',
            'splitwalletid' => 'wallet-plataforma',
            'splitpercent' => 0.0,
        ]);

        $this->assertArrayNotHasKey('split', $client->lastbody);
    }

    /**
     * A descricao e cortada em 500, que e o limite do Asaas.
     *
     * @return void
     */
    public function test_description_is_truncated(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['id' => 'pay_1', 'invoiceUrl' => 'https://x/i/1'];

        $client->create_payment([
            'customer' => 'cus_1',
            'billingtype' => 'PIX',
            'value' => 10.0,
            'duedate' => '2026-09-01',
            'description' => str_repeat('a', 900),
            'externalreference' => 'mdl-1-2-abc',
        ]);

        $this->assertSame(500, \core_text::strlen($client->lastbody['description']));
    }

    /**
     * A carteira do vendedor e descoberta, nao digitada.
     *
     * @return void
     */
    public function test_wallet_id_is_read_from_the_list(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['data' => [['id' => '11111111-2222-3333-4444-555555555555']]];

        $this->assertSame('11111111-2222-3333-4444-555555555555', $client->get_wallet_id());
    }

    /**
     * Conta sem carteira devolve vazio em vez de inventar um id.
     *
     * @return void
     */
    public function test_wallet_id_empty_when_there_is_none(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['data' => []];

        $this->assertSame('', $client->get_wallet_id());
    }

    /**
     * Cliente ja existente e reaproveitado.
     *
     * Criar um cliente novo a cada compra encheria a conta do vendedor de
     * duplicatas do mesmo aluno, atrapalhando a conciliacao dele.
     *
     * @return void
     */
    public function test_existing_customer_is_reused(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['data' => [['id' => 'cus_ja_existe']]];

        $id = $client->find_or_create_customer('Aluno', 'aluno@example.com', '24971563792');

        $this->assertSame('cus_ja_existe', $id);
        $this->assertCount(1, $client->calls, 'nao deveria ter chamado o POST de criacao');
    }

    /**
     * Cliente novo nasce sem as notificacoes do Asaas.
     *
     * MEDIDO em 14/09/2026, numa venda real de R$ 5,00: alem da taxa de Pix,
     * o extrato levou R$ 0,99 de PAYMENT_MESSAGING_NOTIFICATION_FEE - o Asaas
     * cobra para avisar o comprador por e-mail, coisa que o Moodle ja faz.
     *
     * Cliente novo nasce com oito regras de notificacao ligadas, entao o
     * flag precisa ir na CRIACAO: desligar depois ja pagou a primeira.
     *
     * @return void
     */
    public function test_new_customer_is_created_without_notifications(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['data' => []];

        $client->find_or_create_customer('Aluno', 'aluno@example.com', '24971563792');

        $this->assertTrue(
            $client->lastbody['notificationDisabled'] ?? false,
            'sem isto o Asaas cobra R$ 0,99 de mensageria por venda'
        );
    }

    /**
     * O erro do Asaas vira mensagem legivel, e nao "erro na API".
     *
     * @return void
     */
    public function test_api_error_carries_the_description(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nextstatus = 400;
        $client->nextresponse = ['errors' => [[
            'code' => 'invalid_action',
            'description' => 'Nao e permitido split para sua propria carteira.',
        ]]];

        try {
            $client->get_payment('pay_1');
            $this->fail('deveria ter lancado');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('invalid_action', $e->getMessage());
            $this->assertStringContainsString('propria carteira', $e->getMessage());
        }
    }

    /**
     * Resposta que nao e JSON nao passa como sucesso.
     *
     * @return void
     */
    public function test_non_json_response_is_rejected(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->rawresponse = '<html>gateway timeout</html>';

        $this->expectException(\moodle_exception::class);
        $client->get_payment('pay_1');
    }

    /**
     * Falha de rede nao vira resposta vazia.
     *
     * @return void
     */
    public function test_curl_failure_is_reported(): void {
        $client = new fake_asaas_client('chave', asaas_client::ENV_SANDBOX);
        $client->nexterrno = 28;

        $this->expectException(\moodle_exception::class);
        $client->get_payment('pay_1');
    }

    /**
     * Base LIQUIDO volta a usar percentualValue, e e o certo.
     *
     * Aqui o campo percentual nao e armadilha, e a unica saida: o liquido
     * depende da taxa da forma de pagamento, que so o Asaas conhece, e na
     * criacao da cobranca ele ainda nao existe. Calcular por conta propria
     * exigiria adivinhar a taxa deles.
     *
     * @return void
     */
    public function test_base_liquida_usa_percentual(): void {
        $split = asaas_client::build_split('wallet-abc', 9.9, 100.0, 'net');

        $this->assertEqualsWithDelta(9.9, $split[0]['percentualValue'], 0.0001);
        $this->assertArrayNotHasKey('fixedValue', $split[0]);
    }

    /**
     * As duas bases produzem numeros diferentes, que e o motivo de existirem.
     *
     * @return void
     */
    public function test_as_bases_divergem(): void {
        $bruto = asaas_client::build_split('w', 9.9, 100.0, 'gross');
        $liquido = asaas_client::build_split('w', 9.9, 100.0, 'net');

        // Sobre o bruto o numero e fechado em reais; sobre o liquido ele so
        // sera conhecido quando o Asaas descontar a taxa dele.
        $this->assertSame(9.90, $bruto[0]['fixedValue']);
        $this->assertArrayNotHasKey('fixedValue', $liquido[0]);
    }

    /**
     * Base desconhecida cai no bruto, e nao derruba a cobranca.
     *
     * @return void
     */
    public function test_base_desconhecida_cai_no_bruto(): void {
        $split = asaas_client::build_split('wallet-abc', 10.0, 50.0, 'qualquer');

        $this->assertEqualsWithDelta(5.0, $split[0]['fixedValue'], 0.0001);
    }

    /**
     * A assinatura leva o split, e ele vale para todo ciclo.
     *
     * Medido no sandbox em 08/09/2026: o Asaas guarda o split na assinatura com
     * status ACTIVE e a cobranca do ciclo nasce com o valor calculado. E o
     * oposto do preapproval do Mercado Pago, que aceita o campo da comissao e o
     * descarta calado - ver docs/adr/0001.
     *
     * @return void
     */
    public function test_assinatura_leva_split(): void {
        $client = new fake_asaas_client('$aact_hmlg_x', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['id' => 'sub_1', 'status' => 'ACTIVE'];

        $client->create_subscription([
            'customer' => 'cus_1',
            'billingtype' => 'PIX',
            'value' => 100.0,
            'nextduedate' => '2026-10-01',
            'cycle' => 'MONTHLY',
            'externalreference' => 'mdl-1-2-abc',
            'splitwalletid' => 'w-plataforma',
            'splitpercent' => 25.0,
            'splitbase' => 'net',
        ]);

        $this->assertSame([['POST', '/subscriptions']], $client->calls);
        $this->assertSame('MONTHLY', $client->lastbody['cycle']);
        $this->assertSame('2026-10-01', $client->lastbody['nextDueDate']);
        $this->assertSame(25.0, $client->lastbody['split'][0]['percentualValue']);
    }

    /**
     * Sem limite de ciclos, a chave nao vai.
     *
     * Zero e "sem limite" no marketplace. Mandar maxPayments = 0 seria uma
     * assinatura que nao cobra nunca.
     *
     * @return void
     */
    public function test_assinatura_sem_limite_nao_manda_maxpayments(): void {
        $client = new fake_asaas_client('$aact_hmlg_x', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['id' => 'sub_1'];

        $client->create_subscription([
            'customer' => 'cus_1',
            'billingtype' => 'PIX',
            'value' => 100.0,
            'nextduedate' => '2026-10-01',
            'cycle' => 'MONTHLY',
            'externalreference' => 'r',
            'maxpayments' => 0,
            'splitwalletid' => 'w',
            'splitpercent' => 25.0,
            'splitbase' => 'gross',
        ]);

        $this->assertArrayNotHasKey('maxPayments', $client->lastbody);
    }

    /**
     * Com limite, a chave vai.
     *
     * @return void
     */
    public function test_assinatura_com_limite_manda_maxpayments(): void {
        $client = new fake_asaas_client('$aact_hmlg_x', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['id' => 'sub_1'];

        $client->create_subscription([
            'customer' => 'cus_1',
            'billingtype' => 'PIX',
            'value' => 100.0,
            'nextduedate' => '2026-10-01',
            'cycle' => 'MONTHLY',
            'externalreference' => 'r',
            'maxpayments' => 12,
            'splitwalletid' => 'w',
            'splitpercent' => 25.0,
            'splitbase' => 'gross',
        ]);

        $this->assertSame(12, $client->lastbody['maxPayments']);
    }

    /**
     * Cancelar assinatura usa DELETE de verdade.
     *
     * O request() so conhecia GET e POST, entao um DELETE sairia como POST -
     * criaria coisa nenhuma, devolveria erro, e o aluno que cancelou seguiria
     * sendo cobrado.
     *
     * @return void
     */
    public function test_cancelar_assinatura_usa_delete(): void {
        $client = new fake_asaas_client('$aact_hmlg_x', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['deleted' => true, 'id' => 'sub_1'];

        $client->cancel_subscription('sub_1');

        $this->assertSame([['DELETE', '/subscriptions/sub_1']], $client->calls);
    }

    /**
     * As cobrancas de uma assinatura saem da chave data.
     *
     * A resposta de /subscriptions nao traz invoiceUrl - ela descreve a
     * assinatura. A primeira cobranca ja existe, e e para ela que o aluno vai.
     *
     * @return void
     */
    public function test_cobrancas_da_assinatura(): void {
        $client = new fake_asaas_client('$aact_hmlg_x', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['data' => [['id' => 'pay_1', 'invoiceUrl' => 'https://x.test/f']]];

        $cobrancas = $client->subscription_payments('sub_1');

        $this->assertCount(1, $cobrancas);
        $this->assertSame('pay_1', $cobrancas[0]['id']);
        $this->assertSame([['GET', '/subscriptions/sub_1/payments']], $client->calls);
    }

    /**
     * Sem cobrancas, lista vazia e nao erro.
     *
     * @return void
     */
    public function test_assinatura_sem_cobrancas(): void {
        $client = new fake_asaas_client('$aact_hmlg_x', asaas_client::ENV_SANDBOX);
        $client->nextresponse = ['data' => []];

        $this->assertSame([], $client->subscription_payments('sub_1'));
    }
}
