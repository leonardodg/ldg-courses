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

/**
 * As regras que decidem dinheiro e acesso.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_asaas\payment_processor::class)]
final class payment_processor_test extends \advanced_testcase {
    /**
     * A comissao sai do valor BRUTO, e o netValue da resposta e ignorado.
     *
     * Foi o contrario ate a comissao passar a ser sobre o bruto: enquanto o
     * split ia como percentualValue, o Asaas dividia o liquido e a estimativa
     * tinha que acompanhar. Agora o split vai como fixedValue calculado sobre o
     * cheio, e estimar sobre o liquido criaria divergencia com o que foi
     * efetivamente enviado ao gateway.
     *
     * @return void
     */
    public function test_fee_uses_gross_value(): void {
        $fee = payment_processor::fee_from(['netValue' => 99.01], 100.0, 25.0);

        $this->assertEqualsWithDelta(25.00, $fee, 0.001);
    }

    /**
     * Com split na resposta, vale o split - e nao o percentual.
     *
     * @return void
     */
    public function test_fee_reads_the_real_split(): void {
        $payment = [
            'netValue' => 97.52,
            'split' => [
                ['walletId' => 'plataforma', 'status' => 'AWAITING_CREDIT', 'totalValue' => 24.38],
            ],
        ];

        $this->assertEqualsWithDelta(24.38, payment_processor::fee_from($payment, 100.0, 25.0, 'plataforma'), 0.001);
    }

    /**
     * Split CANCELLED da comissao ZERO, e nao o percentual.
     *
     * Este teste existe por causa de um furo concreto: se o vendedor der baixa
     * manual na cobranca (receiveInCash), o Asaas cancela o split - dinheiro
     * que nao passou por ele nao tem como ser dividido - e a plataforma recebe
     * nada. Calculando o percentual, o relatorio anunciaria R$ 25,00 de
     * comissao que nunca chegariam.
     *
     * Relatorio financeiro que discorda do extrato e pior que nenhum.
     *
     * @return void
     */
    public function test_cancelled_split_means_zero_commission(): void {
        $payment = [
            'netValue' => 100.0,
            'split' => [
                ['walletId' => 'plataforma', 'status' => 'CANCELLED', 'totalValue' => 25.00],
            ],
        ];

        $this->assertEqualsWithDelta(0.0, payment_processor::fee_from($payment, 100.0, 25.0, 'plataforma'), 0.001);
    }

    /**
     * Split recusado tambem nao conta.
     *
     * @return void
     */
    public function test_refused_split_means_zero_commission(): void {
        $payment = [
            'split' => [['walletId' => 'plataforma', 'status' => 'REFUSED', 'totalValue' => 25.00]],
        ];

        $this->assertEqualsWithDelta(0.0, payment_processor::fee_from($payment, 100.0, 25.0, 'plataforma'), 0.001);
    }

    /**
     * Split de outra carteira nao vira comissao nossa.
     *
     * @return void
     */
    public function test_other_wallets_are_not_our_commission(): void {
        $payment = [
            'netValue' => 97.52,
            'split' => [
                ['walletId' => 'de-outra-pessoa', 'status' => 'DONE', 'totalValue' => 40.00],
                ['walletId' => 'plataforma', 'status' => 'DONE', 'totalValue' => 24.38],
            ],
        ];

        $this->assertEqualsWithDelta(24.38, payment_processor::fee_from($payment, 100.0, 25.0, 'plataforma'), 0.001);
    }

    /**
     * Sem split na resposta, o percentual sobre o BRUTO e a estimativa certa.
     *
     * E o caso da criacao da cobranca, quando o split ainda nao existe. O
     * numero tem que bater com o fixedValue que o build_split acabou de montar,
     * senao a tela mostra uma comissao e o gateway recebe outra.
     *
     * @return void
     */
    public function test_without_split_falls_back_to_percentage(): void {
        $this->assertEqualsWithDelta(
            25.00,
            payment_processor::fee_from(['netValue' => 97.52], 100.0, 25.0, 'plataforma'),
            0.001
        );
    }

    /**
     * Sem netValue na resposta, cai no valor cheio.
     *
     * Erra por centavos, o que e melhor do que nao registrar comissao nenhuma.
     *
     * @return void
     */
    public function test_fee_falls_back_to_gross(): void {
        $this->assertEqualsWithDelta(25.0, payment_processor::fee_from([], 100.0, 25.0), 0.001);
        $this->assertEqualsWithDelta(25.0, payment_processor::fee_from(['netValue' => 0], 100.0, 25.0), 0.001);
    }

    /**
     * Comissao zero da zero, e nao o padrao.
     *
     * @return void
     */
    public function test_zero_commission_gives_zero_fee(): void {
        $this->assertEqualsWithDelta(0.0, payment_processor::fee_from(['netValue' => 99.01], 100.0, 0.0), 0.001);
    }

    /**
     * Evento de webhook nao e status de cobranca.
     *
     * A confusao custou uma volta: existe o STATUS RECEIVED_IN_CASH, mas nao
     * existe o EVENTO PAYMENT_RECEIVED_IN_CASH. O Asaas recusa quem tenta
     * cadastra-lo com "O evento [PAYMENT_RECEIVED_IN_CASH] e invalido", e a
     * baixa manual chega como PAYMENT_RECEIVED.
     *
     * @return void
     */
    public function test_relevant_events_are_not_statuses(): void {
        $this->assertTrue(payment_processor::is_relevant_event('PAYMENT_RECEIVED'));
        $this->assertTrue(payment_processor::is_relevant_event('PAYMENT_CONFIRMED'));

        $this->assertFalse(
            payment_processor::is_relevant_event('PAYMENT_RECEIVED_IN_CASH'),
            'este evento nao existe no Asaas'
        );
        $this->assertFalse(
            payment_processor::is_relevant_event('RECEIVED_IN_CASH'),
            'isto e um status, nao um evento'
        );
        $this->assertFalse(payment_processor::is_relevant_event('PAYMENT_CREATED'));
        $this->assertFalse(payment_processor::is_relevant_event('PAYMENT_OVERDUE'));
        $this->assertFalse(
            payment_processor::is_relevant_event('PAYMENT_REFUNDED'),
            'estorno nao revoga acesso por automacao'
        );
        $this->assertFalse(payment_processor::is_relevant_event(''));
    }

    /**
     * Quais status liberam o acesso.
     *
     * CONFIRMED e o cartao autorizado com o credito ainda por cair. Segurar o
     * curso ate a liquidacao puniria o aluno por um prazo bancario.
     *
     * @return void
     */
    public function test_paid_statuses(): void {
        $this->assertTrue(payment_processor::is_paid('RECEIVED'));
        $this->assertTrue(payment_processor::is_paid('CONFIRMED'));
        $this->assertTrue(payment_processor::is_paid('received'), 'aceita minusculas');

        $this->assertFalse(payment_processor::is_paid('PENDING'));
        $this->assertFalse(payment_processor::is_paid('OVERDUE'));
        $this->assertFalse(payment_processor::is_paid('REFUNDED'));
        $this->assertFalse(payment_processor::is_paid(''));
    }

    /**
     * A forma de cobranca invalida cai em "deixa o aluno escolher".
     *
     * @return void
     */
    public function test_billing_type_falls_back(): void {
        $this->resetAfterTest();

        unset_config('billingtype', 'paygw_asaas');
        $this->assertSame('UNDEFINED', payment_processor::billing_type());

        set_config('billingtype', 'CARTAO_MAGICO', 'paygw_asaas');
        $this->assertSame('UNDEFINED', payment_processor::billing_type());

        set_config('billingtype', 'pix', 'paygw_asaas');
        $this->assertSame('PIX', payment_processor::billing_type(), 'normaliza para maiusculas');
    }

    /**
     * Vencimento zero ou negativo cai no padrao.
     *
     * Uma cobranca com vencimento no passado e recusada pelo Asaas, e o aluno
     * seria quem descobriria.
     *
     * @return void
     */
    public function test_due_days_never_zero(): void {
        $this->resetAfterTest();

        unset_config('duedays', 'paygw_asaas');
        $this->assertSame(3, payment_processor::due_days());

        set_config('duedays', 0, 'paygw_asaas');
        $this->assertSame(3, payment_processor::due_days());

        set_config('duedays', -5, 'paygw_asaas');
        $this->assertSame(3, payment_processor::due_days());

        set_config('duedays', 10, 'paygw_asaas');
        $this->assertSame(10, payment_processor::due_days());
    }

    /**
     * O retorno ao Moodle vem ligado, e pode ser desligado.
     *
     * O padrao e ligado porque e a experiencia certa para o aluno. O
     * interruptor existe porque o Asaas so aceita URL de retorno de uma conta
     * com SITE cadastrado - sem isso ele recusa a cobranca INTEIRA, e nao so o
     * retorno. Um vendedor que nao consiga cadastrar o dominio precisa
     * continuar vendendo.
     *
     * @return void
     */
    public function test_callback_defaults_to_on(): void {
        $this->resetAfterTest();

        unset_config('usecallback', 'paygw_asaas');
        $this->assertTrue(payment_processor::use_callback());

        set_config('usecallback', 0, 'paygw_asaas');
        $this->assertFalse(payment_processor::use_callback());

        set_config('usecallback', 1, 'paygw_asaas');
        $this->assertTrue(payment_processor::use_callback());
    }

    /**
     * A URL do webhook e a que o administrador cadastra no Asaas.
     *
     * @return void
     */
    public function test_webhook_url(): void {
        $this->assertStringEndsWith(
            '/payment/gateway/asaas/webhook.php',
            payment_processor::webhook_url()->out(false)
        );
    }

    /**
     * Com base liquida, a estimativa usa o netValue da resposta.
     *
     * Tem que acompanhar o split enviado: mandamos percentualValue, entao a
     * estimativa que mostramos precisa ser do liquido tambem.
     *
     * @return void
     */
    public function test_base_liquida_estima_sobre_o_net(): void {
        $fee = payment_processor::fee_from(['netValue' => 97.52], 100.0, 25.0, '', 'net');

        $this->assertEqualsWithDelta(24.38, $fee, 0.001);
    }

    /**
     * Base liquida sem netValue ainda na resposta superestima, de proposito.
     *
     * E o momento da criacao da cobranca. O numero certo chega pelo webhook com
     * o split real; ate la e melhor prometer menos ao vendedor do que mais.
     *
     * @return void
     */
    public function test_base_liquida_sem_net_usa_o_cheio(): void {
        $fee = payment_processor::fee_from([], 100.0, 25.0, '', 'net');

        $this->assertEqualsWithDelta(25.00, $fee, 0.001);
    }

    /**
     * O intervalo em dias vira o ciclo nomeado mais proximo.
     *
     * A traducao e lossy e nao ha como nao ser: o marketplace conta acesso em
     * dias, o Asaas so aceita nomes. Escolher o MAIS PROXIMO, e nao o teto ou o
     * piso, evita erro sistematico para um dos lados.
     *
     * @return void
     */
    public function test_ciclo_pelo_intervalo_em_dias(): void {
        $this->assertSame('MONTHLY', payment_processor::cycle_for(30));
        $this->assertSame('WEEKLY', payment_processor::cycle_for(7));
        $this->assertSame('YEARLY', payment_processor::cycle_for(365));
        $this->assertSame('BIWEEKLY', payment_processor::cycle_for(15));
        $this->assertSame('BIMONTHLY', payment_processor::cycle_for(45), '45 fica mais perto de 60 que de 30');
    }

    /**
     * Sem intervalo definido, cai no mensal.
     *
     * Zero aqui e configuracao incompleta, e nao "cobrar uma vez": uma
     * assinatura sem periodicidade nao e assinatura.
     *
     * @return void
     */
    public function test_ciclo_sem_intervalo_cai_no_mensal(): void {
        $this->assertSame('MONTHLY', payment_processor::cycle_for(0));
        $this->assertSame('MONTHLY', payment_processor::cycle_for(-5));
    }
    /**
     * O ciclo seguinte da assinatura ganha linha propria, copiando o contexto.
     *
     * Do ciclo 2 em diante quem cria a cobranca e o Asaas, sozinho, e o webhook
     * fala de algo que o Moodle nunca viu. Sem esta adocao, o aluno pagaria a
     * mensalidade e o acesso nao seria estendido.
     *
     * @return void
     */
    public function test_ciclo_seguinte_copia_o_contexto(): void {
        global $DB;

        $this->resetAfterTest();

        $first = (object) [
            'asaaspaymentid' => 'pay_ciclo1',
            'subscriptionid' => 'sub_1',
            'externalreference' => 'mdl-7-9-primeiro',
            'customerid' => 'cus_1',
            'component' => 'local_marketplace',
            'paymentarea' => 'offer',
            'itemid' => 9,
            'userid' => 7,
            'accountid' => 3,
            'amount' => 100.0,
            'currency' => 'BRL',
            'feeamount' => 24.38,
            'feepercent' => 25.0,
            'feebase' => 'net',
            'feesource' => 'company',
            'billingtype' => 'PIX',
            'environment' => 'sandbox',
            'status' => 'RECEIVED',
            'timecreated' => time() - DAYSECS,
            'timemodified' => time() - DAYSECS,
        ];
        $DB->insert_record(payment_processor::TABLE, $first);

        $new = payment_processor::adopt_subscription_cycle('pay_ciclo2', 'sub_1');

        $this->assertNotNull($new);
        $this->assertSame('pay_ciclo2', $new->asaaspaymentid);
        $this->assertSame('sub_1', $new->subscriptionid);
        $this->assertSame('PENDING', $new->status, 'o ciclo novo nasce pendente');
        $this->assertNull($new->paymentid);
        $this->assertEquals(0, $new->feeamount, 'a comissao do ciclo novo ainda nao aconteceu');

        // Contexto copiado.
        $this->assertSame('local_marketplace', $new->component);
        $this->assertEquals(9, $new->itemid);
        $this->assertEquals(7, $new->userid);
        $this->assertEquals(3, $new->accountid);
        $this->assertSame('sandbox', $new->environment);

        // Termos vem da LINHA, e nao de nova resolucao - ver docs/adr/0007.
        $this->assertEquals(25.0, $new->feepercent);
        $this->assertSame('net', $new->feebase);
        $this->assertSame('company', $new->feesource);

        // Referencia propria: ela e UNIQUE, e repetir quebraria o insert bem no
        // meio da renovacao.
        $this->assertNotSame($first->externalreference, $new->externalreference);
        $this->assertSame(2, $DB->count_records(payment_processor::TABLE, ['subscriptionid' => 'sub_1']));
    }

    /**
     * Assinatura que nao e nossa nao cria linha nenhuma.
     *
     * O webhook e publico: adotar cobranca de uma assinatura desconhecida
     * criaria venda a partir de um POST de qualquer um.
     *
     * @return void
     */
    public function test_assinatura_desconhecida_nao_vira_linha(): void {
        global $DB;

        $this->resetAfterTest();

        $this->assertNull(payment_processor::adopt_subscription_cycle('pay_x', 'sub_de_outro'));
        $this->assertSame(0, $DB->count_records(payment_processor::TABLE));
    }

    /**
     * A cobranca escolhida e a que vence primeiro, e nao a primeira da lista.
     *
     * Medido no sandbox em 09/09/2026: uma assinatura semanal nasce com CINCO
     * cobrancas pendentes, e a lista volta da mais distante para a mais
     * proxima. Pegar a primeira do array mandava o aluno pagar a fatura de
     * daqui a um mes, deixando a de hoje vencer atras dele.
     *
     * @return void
     */
    public function test_escolhe_a_cobranca_que_vence_primeiro(): void {
        $charges = [
            ['id' => 'pay_d', 'dueDate' => '2026-10-07'],
            ['id' => 'pay_c', 'dueDate' => '2026-09-30'],
            ['id' => 'pay_a', 'dueDate' => '2026-09-09'],
            ['id' => 'pay_b', 'dueDate' => '2026-09-16'],
        ];

        $this->assertSame('pay_a', payment_processor::earliest_charge($charges)['id']);
    }

    /**
     * Sem cobrancas, devolve vazio - e o start_payment vira erro proprio.
     *
     * @return void
     */
    public function test_sem_cobrancas_nao_ha_o_que_escolher(): void {
        $this->assertSame([], payment_processor::earliest_charge([]));
        $this->assertSame([], payment_processor::earliest_charge([['id' => 'sem_data']]));
    }

    /**
     * Monta uma linha da tabela do gateway para os testes de estorno.
     *
     * @param array $extra
     * @return \stdClass
     */
    protected function row(array $extra = []): \stdClass {
        global $DB;

        $base = (object) array_merge([
            'asaaspaymentid' => 'pay_' . random_string(8),
            'subscriptionid' => '',
            'externalreference' => 'mdl-1-2-' . random_string(8),
            'customerid' => 'cus_1',
            'component' => 'local_marketplace',
            'paymentarea' => 'offer',
            'itemid' => 9,
            'userid' => 7,
            'accountid' => 3,
            'amount' => 100.0,
            'currency' => 'BRL',
            'feeamount' => 25.0,
            'feepercent' => 25.0,
            'feebase' => 'gross',
            'feesource' => 'company',
            'billingtype' => 'CREDIT_CARD',
            'environment' => 'sandbox',
            'status' => 'CONFIRMED',
            'paymentid' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $extra);
        $base->id = $DB->insert_record(payment_processor::TABLE, $base);

        return $base;
    }

    /**
     * Venda avulsa paga pode ser estornada.
     *
     * @return void
     */
    public function test_venda_avulsa_paga_pode_estornar(): void {
        $this->resetAfterTest();

        $this->assertSame('', payment_processor::refund_blocker($this->row()));
        $this->assertSame('', payment_processor::refund_blocker($this->row(['status' => 'RECEIVED'])));
    }

    /**
     * Cobranca ainda nao paga nao se estorna.
     *
     * O Asaas responde "e possivel estornar somente cobrancas confirmadas ou
     * recebidas", e logo depois do pagamento leva cerca de meio minuto para
     * liberar. Mensagem propria em vez de erro cru no meio da tela.
     *
     * @return void
     */
    public function test_cobranca_pendente_nao_estorna(): void {
        $this->resetAfterTest();

        $this->assertSame(
            'errorrefundnotpaid',
            payment_processor::refund_blocker($this->row(['status' => 'PENDING']))
        );
    }

    /**
     * Estorno nao se repete.
     *
     * @return void
     */
    public function test_estorno_nao_se_repete(): void {
        $this->resetAfterTest();

        $this->assertSame(
            'errorrefundalready',
            payment_processor::refund_blocker($this->row(['status' => 'REFUNDED']))
        );
    }

    /**
     * O primeiro ciclo de uma assinatura pode ser estornado.
     *
     * @return void
     */
    public function test_primeiro_ciclo_pode_estornar(): void {
        $this->resetAfterTest();

        $first = $this->row(['subscriptionid' => 'sub_1']);

        $this->assertSame('', payment_processor::refund_blocker($first));
    }

    /**
     * Do segundo ciclo em diante nao ha estorno, so cancelamento.
     *
     * Medido no sandbox em 09/09/2026: estornar um ciclo do meio devolve o
     * dinheiro daquele mes e NAO para a assinatura - as cobrancas futuras
     * seguem pendentes, e o aluno continua sendo cobrado depois de
     * reembolsado. E o pior desfecho possivel, e por isso o caminho nao
     * existe.
     *
     * @return void
     */
    public function test_ciclo_do_meio_nao_estorna(): void {
        $this->resetAfterTest();

        $this->row(['subscriptionid' => 'sub_1', 'paymentid' => 1]);
        $second = $this->row(['subscriptionid' => 'sub_1', 'paymentid' => 2]);

        $this->assertSame(
            'errorrefundnotfirstcycle',
            payment_processor::refund_blocker($second)
        );
    }

    /**
     * Ciclo anterior que nunca foi pago nao bloqueia o estorno.
     *
     * Uma linha pendente e checkout abandonado, e nao mes usado. Contar ela
     * transformaria o primeiro pagamento de verdade em "ciclo do meio".
     *
     * @return void
     */
    public function test_ciclo_anterior_nao_pago_nao_bloqueia(): void {
        $this->resetAfterTest();

        $this->row(['subscriptionid' => 'sub_1', 'status' => 'PENDING', 'paymentid' => null]);
        $paid = $this->row(['subscriptionid' => 'sub_1', 'paymentid' => 5]);

        $this->assertSame('', payment_processor::refund_blocker($paid));
    }

    /**
     * Boleto nao tem estorno, nem depois de baixa manual.
     *
     * Medido em 09/09/2026: o Asaas recusa pela FORMA DE PAGAMENTO - "somente e
     * possivel estornar cobrancas cuja a forma de pagamento seja cartao de
     * credito ou Pix" -, e continua recusando com o status RECEIVED_IN_CASH.
     *
     * Sem esta regra o botao aparecia numa venda por boleto e o clique morria
     * com erro cru da API, diante de quem esta resolvendo um problema de
     * dinheiro com um aluno.
     *
     * @return void
     */
    public function test_boleto_nao_estorna(): void {
        $this->resetAfterTest();

        $this->assertSame(
            'errorrefundbillingtype',
            payment_processor::refund_blocker($this->row(['billingtype' => 'BOLETO']))
        );
        $this->assertSame(
            'errorrefundbillingtype',
            payment_processor::refund_blocker($this->row([
                'billingtype' => 'BOLETO',
                'status' => 'RECEIVED_IN_CASH',
            ])),
            'nem baixa manual libera o estorno de boleto'
        );
    }

    /**
     * Cartao e Pix estornam.
     *
     * @return void
     */
    public function test_cartao_e_pix_estornam(): void {
        $this->resetAfterTest();

        $this->assertSame('', payment_processor::refund_blocker($this->row(['billingtype' => 'CREDIT_CARD'])));
        $this->assertSame('', payment_processor::refund_blocker($this->row(['billingtype' => 'PIX'])));
    }
}
