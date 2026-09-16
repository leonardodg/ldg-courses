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

/**
 * A comissao e o corpo da preferencia.
 *
 * O marketplace_fee e o unico numero deste plugin que move dinheiro, e ficou
 * sem teste enquanto so existia dentro do start_payment(), que precisa de
 * banco, sessao e rede. Aqui ele e exercitado sozinho.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_mercadopago\payment_processor::class)]
final class payment_processor_test extends \advanced_testcase {
    /**
     * A comissao incide sobre o bruto, e sai em moeda.
     *
     * Nao ha escolha de base aqui: o marketplace_fee e valor absoluto, e a taxa
     * do Mercado Pago so e conhecida depois do pagamento. Ver docs/adr/0007.
     *
     * @return void
     */
    public function test_comissao_e_absoluta_sobre_o_bruto(): void {
        $this->assertSame(25.0, payment_processor::fee_for(100.0, 25.0));
        $this->assertSame(12.5, payment_processor::fee_for(50.0, 25.0));
    }

    /**
     * A comissao para em centavos.
     *
     * Mandar mais de duas casas faz o Mercado Pago recusar a preferencia
     * inteira, e a recusa apareceria no checkout, diante do aluno.
     *
     * @return void
     */
    public function test_comissao_arredonda_para_centavos(): void {
        $this->assertSame(9.9, payment_processor::fee_for(100.0, 9.9));
        $this->assertSame(8.33, payment_processor::fee_for(33.33, 25.0));
        $this->assertSame(0.25, payment_processor::fee_for(1.0, 25.0));
    }

    /**
     * A comissao nao passa do bruto.
     *
     * Percentual acima de 100 e configuracao errada, e produziria uma
     * preferencia que o Mercado Pago recusa. E a mesma trava do
     * asaas_client::build_split().
     *
     * @return void
     */
    public function test_comissao_nao_passa_do_bruto(): void {
        $this->assertSame(100.0, payment_processor::fee_for(100.0, 150.0));
    }

    /**
     * Sem comissao a cobrar, o valor e zero e nao um numero inventado.
     *
     * @return void
     */
    public function test_sem_comissao_o_valor_e_zero(): void {
        $this->assertSame(0.0, payment_processor::fee_for(100.0, 0.0));
        $this->assertSame(0.0, payment_processor::fee_for(100.0, -5.0));
        $this->assertSame(0.0, payment_processor::fee_for(0.0, 25.0));
    }

    /**
     * O marketplace_fee chega ao corpo da preferencia.
     *
     * No nivel raiz, e nao dentro de items: o Mercado Pago ignora a chave fora
     * do lugar, e o resultado seria uma venda sem comissao que nao acusa erro.
     *
     * @return void
     */
    public function test_marketplace_fee_vai_na_preferencia(): void {
        $body = payment_processor::build_preference_body(
            100.0,
            'BRL',
            'mdl-1-2-abc',
            25.0,
            'https://exemplo.test',
            false
        );

        $this->assertArrayHasKey('marketplace_fee', $body);
        $this->assertSame(25.0, $body['marketplace_fee']);
    }

    /**
     * O item carrega valor e moeda como o Mercado Pago espera.
     *
     * @return void
     */
    public function test_o_item_carrega_valor_e_moeda(): void {
        $body = payment_processor::build_preference_body(
            100.0,
            'BRL',
            'mdl-1-2-abc',
            25.0,
            'https://exemplo.test',
            false
        );

        $this->assertCount(1, $body['items']);
        $this->assertSame(100.0, $body['items'][0]['unit_price']);
        $this->assertSame('BRL', $body['items'][0]['currency_id']);
        $this->assertSame(1, $body['items'][0]['quantity']);
    }

    /**
     * As quatro URLs saem do wwwroot, e a referencia viaja com elas.
     *
     * Endereco escrito a mao aqui mandaria o aluno de volta para outro site, e
     * o webhook para um endpoint que nao existe - o pagamento aconteceria sem
     * ninguem receber a confirmacao.
     *
     * @return void
     */
    public function test_as_urls_saem_do_wwwroot(): void {
        $body = payment_processor::build_preference_body(
            100.0,
            'BRL',
            'mdl-7-9-xyz',
            25.0,
            'https://exemplo.test',
            false
        );

        $esperada = 'https://exemplo.test/payment/gateway/mercadopago/return.php?ref=mdl-7-9-xyz';

        $this->assertSame($esperada, $body['back_urls']['success']);
        $this->assertSame($esperada, $body['back_urls']['pending']);
        $this->assertSame($esperada, $body['back_urls']['failure']);
        $this->assertSame(
            'https://exemplo.test/payment/gateway/mercadopago/webhook.php',
            $body['notification_url']
        );
        $this->assertSame('mdl-7-9-xyz', $body['external_reference']);
    }

    /**
     * O wallet_purchase fica preso ao modo de teste.
     *
     * Em teste ele existe porque um visitante nao e usuario de teste, e o
     * Mercado Pago recusa a compra. Em producao ele cortaria pagamento sem
     * cadastro, boleto e dinheiro - ou seja, conversao real, para resolver um
     * problema que so existe no sandbox.
     *
     * @return void
     */
    public function test_purpose_so_em_modo_teste(): void {
        $teste = payment_processor::build_preference_body(
            100.0,
            'BRL',
            'mdl-1-2-abc',
            25.0,
            'https://exemplo.test',
            true
        );
        $producao = payment_processor::build_preference_body(
            100.0,
            'BRL',
            'mdl-1-2-abc',
            25.0,
            'https://exemplo.test',
            false
        );

        $this->assertSame('wallet_purchase', $teste['purpose']);
        $this->assertArrayNotHasKey('purpose', $producao);
    }

    /**
     * A cobranca do ciclo leva a comissao no application_fee.
     *
     * E o numero que move dinheiro na assinatura. Medido em 16/09/2026: este
     * campo e HONRADO pelo /v1/payments - volta em fee_details do pagamento
     * aprovado -, ao contrario do preapproval, que aceita cinco formatos do
     * mesmo campo e descarta todos.
     *
     * @return void
     */
    public function test_a_cobranca_do_ciclo_leva_a_comissao(): void {
        $corpo = payment_processor::build_cycle_payment_body(
            100.0,
            'BRL',
            'mdlsub-1-2-abc',
            25.0,
            ['token' => 'tok', 'customerid' => 'cus_1', 'paymentmethod' => 'master'],
            'aluno@exemplo.test',
            'https://exemplo.test',
            'Assinatura'
        );

        $this->assertEquals(100.0, $corpo['transaction_amount']);
        $this->assertEquals(25.0, $corpo['application_fee']);
        $this->assertSame('tok', $corpo['token']);
        $this->assertSame('mdlsub-1-2-abc', $corpo['external_reference']);
    }

    /**
     * Sem comissao, o campo nao e enviado.
     *
     * Mandar application_fee zero nao e o mesmo que nao mandar: e pedir ao
     * Mercado Pago que reparta nada, e uma empresa isenta viraria uma cobranca
     * com repasse de valor zero no extrato. Ausente e mais honesto, e e o que o
     * fee_for() ja sinaliza ao devolver zero.
     *
     * @return void
     */
    public function test_sem_comissao_o_campo_nao_vai_no_corpo(): void {
        $corpo = payment_processor::build_cycle_payment_body(
            100.0,
            'BRL',
            'ref',
            0.0,
            ['token' => 'tok', 'customerid' => 'cus_1', 'paymentmethod' => 'master'],
            'aluno@exemplo.test',
            'https://exemplo.test',
            'Assinatura'
        );

        $this->assertArrayNotHasKey('application_fee', $corpo);
    }

    /**
     * Cartao NOVO cobra so com o e-mail, sem o cliente junto.
     *
     * Medido em 16/09/2026, variando apenas o payer na mesma chamada:
     *
     *   payer: {email}                      -> approved
     *   payer: {type: customer, id, email}  -> REJECTED cc_rejected_other_reason
     *   payer: {id, email}                  -> REJECTED cc_rejected_other_reason
     *
     * Mandar o cliente junto de um token recem-criado faz a cobranca ser
     * RECUSADA, e a recusa chega disfarcada de problema com o cartao - o tipo
     * de defeito que se descobre com o aluno na tela.
     *
     * @return void
     */
    public function test_cartao_novo_cobra_so_com_o_email(): void {
        $corpo = payment_processor::build_cycle_payment_body(
            50.0,
            'BRL',
            'ref',
            5.0,
            ['token' => 'tok', 'paymentmethod' => 'visa'],
            'aluno@exemplo.test',
            'https://exemplo.test',
            'Assinatura'
        );

        $this->assertSame(['email' => 'aluno@exemplo.test'], $corpo['payer']);
        $this->assertArrayNotHasKey('type', $corpo['payer']);
        $this->assertSame('visa', $corpo['payment_method_id']);
    }

    /**
     * Cartao JA GUARDADO cobra com o cliente junto.
     *
     * Do ciclo 2 em diante o token nasce do card_id, e ai o vinculo com o
     * cliente e o que alcanca o cartao guardado.
     *
     * @return void
     */
    public function test_cartao_guardado_cobra_com_o_cliente(): void {
        $corpo = payment_processor::build_cycle_payment_body(
            50.0,
            'BRL',
            'ref',
            5.0,
            ['token' => 'tok', 'customerid' => 'cus_9', 'paymentmethod' => 'visa'],
            'aluno@exemplo.test',
            'https://exemplo.test',
            'Assinatura'
        );

        $this->assertSame('customer', $corpo['payer']['type']);
        $this->assertSame('cus_9', $corpo['payer']['id']);
    }

    /**
     * O ciclo nunca e parcelado.
     *
     * Parcelar uma cobranca que se repete todo mes empilha parcelas em cima de
     * parcelas, e o aluno passa a dever mais do que assinou. Nao ha
     * configuracao para isso de proposito.
     *
     * @return void
     */
    public function test_o_ciclo_nao_e_parcelado(): void {
        $corpo = payment_processor::build_cycle_payment_body(
            100.0,
            'BRL',
            'ref',
            0.0,
            ['token' => 'tok', 'customerid' => 'c', 'paymentmethod' => 'master'],
            'aluno@exemplo.test',
            'https://exemplo.test',
            'Assinatura'
        );

        $this->assertSame(1, $corpo['installments']);
    }

    /**
     * O webhook sai do wwwroot, e nao escrito a mao.
     *
     * @return void
     */
    public function test_o_webhook_do_ciclo_sai_do_wwwroot(): void {
        $corpo = payment_processor::build_cycle_payment_body(
            10.0,
            'BRL',
            'ref',
            1.0,
            ['token' => 'tok', 'customerid' => 'c', 'paymentmethod' => 'master'],
            'aluno@exemplo.test',
            'https://outro.exemplo',
            'Assinatura'
        );

        $this->assertStringStartsWith('https://outro.exemplo/', $corpo['notification_url']);
        $this->assertStringEndsWith('/payment/gateway/mercadopago/webhook.php', $corpo['notification_url']);
    }

    /**
     * A referencia da assinatura se distingue da avulsa pelo prefixo.
     *
     * O webhook chega sem contexto e precisa saber que linha procurar. Duas
     * familias de referencia com o mesmo formato obrigariam a consultar o banco
     * so para descobrir de que tipo era.
     *
     * @return void
     */
    public function test_a_referencia_da_assinatura_tem_prefixo_proprio(): void {
        $avulsa = payment_processor::build_reference(7, 3, false);
        $assinatura = payment_processor::build_reference(7, 3, true);

        $this->assertStringStartsWith('mdl-7-3-', $avulsa);
        $this->assertStringStartsWith('mdlsub-7-3-', $assinatura);
        $this->assertNotSame($assinatura, payment_processor::build_reference(7, 3, true));
    }

    /**
     * Cria uma linha do gateway para os testes de estorno e cancelamento.
     *
     * @param array $campos
     * @return \stdClass
     */
    protected function linha(array $campos = []): \stdClass {
        global $DB;

        $registro = (object) array_merge([
            'preferenceid' => '',
            'externalreference' => 'ref-' . random_string(8),
            'component' => 'local_marketplace',
            'paymentarea' => 'offer',
            'itemid' => 1,
            'userid' => 2,
            'accountid' => 3,
            'amount' => 100.0,
            'currency' => 'BRL',
            'feeamount' => 25.0,
            'feepercent' => 25.0,
            'feebase' => 'gross',
            'feesource' => 'site',
            'status' => 'approved',
            'apptype' => 'bricks',
            'cycles' => 1,
            'subscriptionstatus' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $campos);

        $registro->id = $DB->insert_record(payment_processor::TABLE, $registro);

        return $registro;
    }

    /**
     * Venda aprovada e estornavel.
     *
     * @return void
     */
    public function test_venda_aprovada_pode_ser_estornada(): void {
        $this->resetAfterTest();

        $this->assertSame('', payment_processor::refund_blocker($this->linha()));
    }

    /**
     * O que nao foi pago nao se estorna.
     *
     * @return void
     */
    public function test_o_que_nao_foi_pago_nao_se_estorna(): void {
        $this->resetAfterTest();

        $this->assertSame(
            'errorrefundnotpaid',
            payment_processor::refund_blocker($this->linha(['status' => 'pending']))
        );
        $this->assertSame(
            'errorrefundalready',
            payment_processor::refund_blocker($this->linha(['status' => 'refunded']))
        );
    }

    /**
     * Ciclo do meio de uma assinatura nao se estorna.
     *
     * Estorno parcial NAO reduz o split - o que ja foi repassado a plataforma
     * continua repassado -, entao estornar um ciclo do meio devolveria ao aluno
     * o bruto e deixaria o vendedor no prejuizo da comissao. O bloqueio faz o
     * botao sumir em vez de aparecer e falhar depois do clique.
     *
     * @return void
     */
    public function test_ciclo_do_meio_nao_se_estorna(): void {
        $this->resetAfterTest();

        $assinatura = 'mdlsub-2-1-abc';

        $primeiro = $this->linha(['subscriptionid' => $assinatura, 'cycles' => 1, 'paymentid' => 10]);
        $segundo = $this->linha(['subscriptionid' => $assinatura, 'cycles' => 2, 'paymentid' => 11]);

        $this->assertSame('', payment_processor::refund_blocker($primeiro), 'o primeiro ciclo pode');
        $this->assertSame('errorrefundnotfirstcycle', payment_processor::refund_blocker($segundo));
    }

    /**
     * Cancelar marca a ASSINATURA, e nao a cobranca.
     *
     * Aqui quem cobra o ciclo somos nos, entao cancelar e parar de disparar. No
     * Asaas seria pedir ao gateway que pare - e a diferenca esta na tabela, no
     * comentario da coluna.
     *
     * @return void
     */
    public function test_cancelar_marca_todas_as_linhas_da_assinatura(): void {
        global $DB;

        $this->resetAfterTest();

        $assinatura = 'mdlsub-2-1-xyz';
        $this->linha(['subscriptionid' => $assinatura, 'cycles' => 1]);
        $this->linha(['subscriptionid' => $assinatura, 'cycles' => 2]);
        $outra = $this->linha(['subscriptionid' => 'mdlsub-9-9-zzz', 'cycles' => 1]);

        $this->assertTrue(payment_processor::cancel_subscription($assinatura));

        $marcadas = $DB->get_records(payment_processor::TABLE, ['subscriptionid' => $assinatura]);
        $this->assertCount(2, $marcadas);
        foreach ($marcadas as $linha) {
            $this->assertSame('cancelled', $linha->subscriptionstatus);
        }

        $intacta = $DB->get_record(payment_processor::TABLE, ['id' => $outra->id]);
        $this->assertSame('active', $intacta->subscriptionstatus, 'a assinatura de outro aluno nao e tocada');
    }

    /**
     * Cancelar o que ja esta cancelado nao mente dizendo que fez algo.
     *
     * @return void
     */
    public function test_cancelar_duas_vezes_nao_inventa_cancelamento(): void {
        $this->resetAfterTest();

        $assinatura = 'mdlsub-3-3-aaa';
        $this->linha(['subscriptionid' => $assinatura, 'subscriptionstatus' => 'cancelled']);

        $this->assertFalse(payment_processor::cancel_subscription($assinatura));
    }
}
