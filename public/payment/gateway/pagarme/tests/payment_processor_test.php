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

require_once(__DIR__ . '/fixtures/documented_responses.php');

/**
 * As regras de dinheiro e de acesso.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(payment_processor::class)]
final class payment_processor_test extends \advanced_testcase {
    /**
     * Uma linha da tabela, com valores plausiveis.
     *
     * @param array $extra
     * @return \stdClass
     */
    protected function linha(array $extra = []): \stdClass {
        global $DB;

        $this->resetAfterTest();

        $record = (object) array_merge([
            'orderid' => 'or_' . random_string(8),
            'chargeid' => 'ch_' . random_string(8),
            'subscriptionid' => null,
            'customerid' => 'cus_1',
            'externalreference' => 'mdl-' . random_string(12),
            'component' => 'local_marketplace',
            'paymentarea' => 'offer',
            'itemid' => 7,
            'userid' => 3,
            'accountid' => 1,
            'amount' => 100.00,
            'currency' => 'BRL',
            'feepercent' => 25.00,
            'feeamount' => 25.00,
            'feebase' => 'gross',
            'feesource' => 'company',
            'paymentmethod' => 'credit_card',
            'environment' => 'sandbox',
            'status' => 'paid',
            'paymentid' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $extra);

        $record->id = $DB->insert_record(payment_processor::TABLE, $record);

        return $record;
    }

    // --------------------------------------------------------------------

    public function test_pago_e_pago(): void {
        $this->assertTrue(payment_processor::is_paid('paid'));
        $this->assertTrue(payment_processor::is_paid('PAID'));
        $this->assertTrue(payment_processor::is_paid('overpaid'));
    }

    public function test_pagamento_parcial_nao_libera_acesso(): void {
        // Liberar curso por underpaid seria prejuizo que ninguem percebe.
        $this->assertFalse(payment_processor::is_paid('underpaid'));
        $this->assertFalse(payment_processor::is_paid('pending'));
        $this->assertFalse(payment_processor::is_paid('failed'));
        $this->assertFalse(payment_processor::is_paid('canceled'));
    }

    public function test_evento_nao_e_status(): void {
        // Nome de evento nao e valor de status: order.paid e evento, paid e
        // status. Confundir os dois ja custou uma volta neste projeto.
        $this->assertTrue(payment_processor::is_relevant_event('order.paid'));
        $this->assertTrue(payment_processor::is_relevant_event('charge.paid'));
        $this->assertFalse(payment_processor::is_relevant_event('paid'));
        $this->assertFalse(payment_processor::is_relevant_event('customer.created'));
    }

    // --------------------------------------------------------------------

    public function test_intervalo_pelos_dias(): void {
        $this->assertSame(
            ['interval' => 'month', 'interval_count' => 1],
            payment_processor::interval_for(30)
        );
        $this->assertSame(
            ['interval' => 'year', 'interval_count' => 1],
            payment_processor::interval_for(365)
        );
        $this->assertSame(
            ['interval' => 'week', 'interval_count' => 1],
            payment_processor::interval_for(7)
        );
    }

    public function test_intervalo_sem_dias_cai_no_mensal(): void {
        $this->assertSame(
            ['interval' => 'month', 'interval_count' => 1],
            payment_processor::interval_for(0)
        );
    }

    public function test_intervalo_quebrado_vira_dias(): void {
        $this->assertSame(
            ['interval' => 'day', 'interval_count' => 10],
            payment_processor::interval_for(10)
        );
    }

    public function test_multiplo_de_mes_agrupa(): void {
        $this->assertSame(
            ['interval' => 'month', 'interval_count' => 3],
            payment_processor::interval_for(90)
        );
    }

    // --------------------------------------------------------------------

    public function test_escolhe_a_cobranca_que_vence_primeiro(): void {
        // O Asaas devolve a lista da mais distante para a mais proxima, e
        // pegar a primeira mandava o aluno pagar a fatura do mes seguinte.
        // Aqui a ordem da API nao decide nada.
        $charges = [
            ['id' => 'ch_longe', 'due_at' => '2026-12-01T00:00:00Z'],
            ['id' => 'ch_perto', 'due_at' => '2026-09-15T00:00:00Z'],
            ['id' => 'ch_meio', 'due_at' => '2026-10-20T00:00:00Z'],
        ];

        $this->assertSame('ch_perto', payment_processor::earliest_charge($charges)['id']);
    }

    public function test_sem_cobrancas_nao_ha_o_que_escolher(): void {
        $this->assertSame([], payment_processor::earliest_charge([]));
    }

    public function test_cobranca_sem_vencimento_ainda_e_pagavel(): void {
        $charges = [['id' => 'ch_1']];

        $this->assertSame('ch_1', payment_processor::earliest_charge($charges)['id']);
    }

    // --------------------------------------------------------------------

    public function test_forma_de_pagamento_cai_no_pix(): void {
        $this->resetAfterTest();

        $this->assertSame('pix', payment_processor::payment_method());

        set_config('paymentmethod', 'inventada', 'paygw_pagarme');
        $this->assertSame('pix', payment_processor::payment_method());

        set_config('paymentmethod', 'boleto', 'paygw_pagarme');
        $this->assertSame('boleto', payment_processor::payment_method());
    }

    public function test_validade_do_qrcode_respeita_o_limite_da_api(): void {
        $this->resetAfterTest();

        // A API so aceita entre 15 e 60 minutos, e valor fora disso e
        // recusado - com a cobranca ja criada.
        set_config('pixexpiresin', '5', 'paygw_pagarme');
        $this->assertSame(15 * MINSECS, payment_processor::pix_expires_in());

        set_config('pixexpiresin', '600', 'paygw_pagarme');
        $this->assertSame(60 * MINSECS, payment_processor::pix_expires_in());

        set_config('pixexpiresin', '30', 'paygw_pagarme');
        $this->assertSame(30 * MINSECS, payment_processor::pix_expires_in());
    }

    public function test_prazo_do_boleto_nunca_e_zero(): void {
        $this->resetAfterTest();

        $this->assertSame(3, payment_processor::due_days());

        set_config('duedays', '0', 'paygw_pagarme');
        $this->assertSame(3, payment_processor::due_days());
    }

    public function test_endereco_do_webhook(): void {
        $this->resetAfterTest();

        $this->assertStringContainsString(
            '/payment/gateway/pagarme/webhook.php',
            payment_processor::webhook_url()->out(false)
        );
    }

    // --------------------------------------------------------------------

    public function test_venda_avulsa_paga_pode_estornar(): void {
        $this->assertSame('', payment_processor::refund_blocker($this->linha()));
    }

    public function test_cobranca_pendente_nao_estorna(): void {
        $record = $this->linha(['status' => 'pending', 'paymentid' => null]);

        $this->assertSame('errorrefundnotpaid', payment_processor::refund_blocker($record));
    }

    public function test_estorno_nao_se_repete(): void {
        $record = $this->linha(['status' => 'canceled']);

        $this->assertSame('errorrefundalready', payment_processor::refund_blocker($record));
    }

    public function test_boleto_nao_estorna(): void {
        // No Asaas boleto nao estorna em circunstancia nenhuma. Ate medir
        // aqui, o botao nao aparece - errar para o lado de nao oferecer custa
        // um clique, e o contrario custa erro cru na cara do gerente.
        $record = $this->linha(['paymentmethod' => 'boleto']);

        $this->assertSame('errorrefundmethod', payment_processor::refund_blocker($record));
    }

    public function test_cartao_e_pix_estornam(): void {
        $this->assertSame('', payment_processor::refund_blocker($this->linha(['paymentmethod' => 'pix'])));
        $this->assertSame(
            '',
            payment_processor::refund_blocker($this->linha(['paymentmethod' => 'credit_card']))
        );
    }

    public function test_primeiro_ciclo_pode_estornar(): void {
        $record = $this->linha(['subscriptionid' => 'sub_1']);

        $this->assertSame('', payment_processor::refund_blocker($record));
    }

    public function test_ciclo_do_meio_nao_estorna(): void {
        // Estornar um mes do meio devolveria o dinheiro e deixaria a
        // assinatura cobrando os seguintes.
        $this->linha(['subscriptionid' => 'sub_1']);
        $segundo = $this->linha(['subscriptionid' => 'sub_1']);

        $this->assertSame('errorrefundnotfirstcycle', payment_processor::refund_blocker($segundo));
    }

    public function test_ciclo_anterior_nao_pago_nao_bloqueia(): void {
        $this->linha(['subscriptionid' => 'sub_2', 'status' => 'pending', 'paymentid' => null]);
        $segundo = $this->linha(['subscriptionid' => 'sub_2']);

        $this->assertSame('', payment_processor::refund_blocker($segundo));
    }

    // --------------------------------------------------------------------

    public function test_ciclo_seguinte_copia_o_contexto(): void {
        global $DB;

        $anterior = $this->linha([
            'subscriptionid' => 'sub_9',
            'feepercent' => 30.00,
            'feebase' => 'net',
            'feesource' => 'plan',
        ]);

        $novo = payment_processor::adopt_subscription_cycle('ch_novo', 'sub_9');

        $this->assertNotNull($novo);
        $this->assertSame('pending', $novo->status);
        $this->assertNull($novo->paymentid);
        $this->assertSame(0, (int) $novo->feeamount);
        $this->assertSame((int) $anterior->itemid, (int) $novo->itemid);
        $this->assertSame((int) $anterior->userid, (int) $novo->userid);

        // Os termos vem da linha anterior, e nao sao resolvidos de novo: se a
        // comissao mudar no meio da assinatura, o ciclo ja vendido continua
        // valendo o que valia.
        $this->assertSame('30.00', (string) $novo->feepercent);
        $this->assertSame('net', $novo->feebase);
        $this->assertSame('plan', $novo->feesource);

        // A referencia e unica, entao nao pode ser a mesma.
        $this->assertNotSame($anterior->externalreference, $novo->externalreference);
        $this->assertSame(2, $DB->count_records(payment_processor::TABLE, ['subscriptionid' => 'sub_9']));
    }

    public function test_assinatura_desconhecida_nao_vira_linha(): void {
        global $DB;

        $this->resetAfterTest();

        $antes = $DB->count_records(payment_processor::TABLE);
        $resultado = payment_processor::adopt_subscription_cycle('ch_x', 'sub_que_nao_existe');

        // O webhook e publico. Adotar assinatura desconhecida deixaria
        // qualquer POST criar venda.
        $this->assertNull($resultado);
        $this->assertSame($antes, $DB->count_records(payment_processor::TABLE));
    }

    // --------------------------------------------------------------------

    public function test_a_order_leva_o_code_do_item(): void {
        $this->resetAfterTest();

        // A falta do code nao vira erro 4xx: vira cobranca failed dentro de um
        // 200, com "The item Code is required" no gateway_response.
        $body = payment_processor::order_body(
            $this->linha(['paymentmethod' => 'pix']),
            ['email' => 'a@b.test'],
            []
        );

        $this->assertArrayHasKey('code', $body['items'][0]);
        $this->assertNotSame('', $body['items'][0]['code']);
    }

    public function test_order_sem_split_nao_manda_o_campo(): void {
        $this->resetAfterTest();

        $body = payment_processor::order_body($this->linha(), ['email' => 'a@b.test'], []);

        $this->assertArrayNotHasKey('split', $body['payments'][0]);
    }

    public function test_order_com_split_manda_as_duas_regras(): void {
        $this->resetAfterTest();

        $split = pagarme_client::build_split('rp_a', 'rp_b', 25.0, 100.00);
        $body = payment_processor::order_body($this->linha(), ['email' => 'a@b.test'], $split);

        $this->assertCount(2, $body['payments'][0]['split']);
    }

    public function test_assinatura_sem_limite_nao_manda_cycles(): void {
        $this->resetAfterTest();

        $body = payment_processor::subscription_body(
            $this->linha(),
            ['email' => 'a@b.test'],
            [],
            (object) ['days' => 30, 'maxcycles' => 0]
        );

        // Zero em maxcycles e "sem fim". Mandar zero pediria uma assinatura
        // de zero ciclos, que e o oposto.
        $this->assertArrayNotHasKey('cycles', $body);
        $this->assertSame('month', $body['interval']);
    }

    public function test_assinatura_com_limite_manda_cycles(): void {
        $this->resetAfterTest();

        $body = payment_processor::subscription_body(
            $this->linha(),
            ['email' => 'a@b.test'],
            [],
            (object) ['days' => 30, 'maxcycles' => 12]
        );

        $this->assertSame(12, $body['cycles']);
    }

    public function test_assinatura_cobra_no_comeco_do_ciclo(): void {
        $this->resetAfterTest();

        // Curso com prazo de acesso exige prepaid: primeiro paga, depois
        // assiste.
        $body = payment_processor::subscription_body(
            $this->linha(),
            ['email' => 'a@b.test'],
            [],
            (object) ['days' => 30, 'maxcycles' => 0]
        );

        $this->assertSame('prepaid', $body['billing_type']);
    }

    // --------------------------------------------------------------------

    public function test_url_da_transacao_prefere_a_pagina(): void {
        $this->assertSame('https://exemplo.test/boleto', payment_processor::transaction_url([
            'last_transaction' => [
                'url' => 'https://exemplo.test/boleto',
                'qr_code_url' => 'https://exemplo.test/qr.png',
            ],
        ]));
    }

    public function test_url_da_transacao_cai_no_qrcode(): void {
        $this->assertSame('https://exemplo.test/qr.png', payment_processor::transaction_url([
            'last_transaction' => ['qr_code_url' => 'https://exemplo.test/qr.png'],
        ]));
    }

    public function test_transacao_sem_url_devolve_vazio(): void {
        $this->assertSame('', payment_processor::transaction_url([]));
    }

    public function test_o_pix_quer_a_imagem_do_qrcode_e_nao_a_pagina(): void {
        // A ordem de preferencia generica devolveria 'url', e a pagina do Pix
        // e NOSSA - o que ela precisa e da imagem para renderizar.
        $charge = ['last_transaction' => [
            'url' => 'https://exemplo.test/qualquer',
            'qr_code_url' => 'https://exemplo.test/qr.png',
        ]];

        $this->assertSame(
            'https://exemplo.test/qr.png',
            payment_processor::checkout_url_for('pix', $charge)
        );
    }

    public function test_o_boleto_quer_a_pagina(): void {
        $charge = ['last_transaction' => [
            'url' => 'https://exemplo.test/boleto',
            'pdf' => 'https://exemplo.test/boleto.pdf',
        ]];

        $this->assertSame(
            'https://exemplo.test/boleto',
            payment_processor::checkout_url_for('boleto', $charge)
        );
    }

    // --------------------------------------------------------------------

    public function test_evento_de_order_traz_a_cobranca_de_dentro(): void {
        $this->assertSame('ch_de_dentro', payment_processor::charge_id_from_event(
            'order.paid',
            ['id' => 'or_1', 'charges' => [['id' => 'ch_de_dentro']]]
        ));
    }

    public function test_evento_de_charge_traz_o_proprio_id(): void {
        $this->assertSame('ch_1', payment_processor::charge_id_from_event(
            'charge.paid',
            ['id' => 'ch_1']
        ));
    }

    public function test_evento_de_order_sem_cobranca_nao_inventa_id(): void {
        // Um order.paid sem charges nao pode devolver o id da ORDER: procurar
        // uma linha por or_... nao acha nada, e no pior caso acha a errada.
        $this->assertSame('', payment_processor::charge_id_from_event(
            'order.paid',
            ['id' => 'or_1']
        ));
    }

    public function test_evento_sem_dados_devolve_vazio(): void {
        $this->assertSame('', payment_processor::charge_id_from_event('charge.paid', []));
    }

    public function test_id_de_assinatura_sai_do_evento(): void {
        $this->assertSame('sub_1', payment_processor::subscription_id_from_event([
            'id' => 'ch_1',
            'subscription' => ['id' => 'sub_1'],
        ]));
        $this->assertSame('', payment_processor::subscription_id_from_event(['id' => 'ch_1']));
    }

    // --------------------------------------------------------------------

    public function test_cobranca_sem_id_nao_procura_linha(): void {
        // O cartao cria a linha ANTES de existir cobranca, entao ha linhas com
        // chargeid vazio. Procurar por '' acharia uma delas - e possivelmente
        // a de outro aluno.
        $this->linha(['chargeid' => '', 'status' => 'pending', 'paymentid' => null]);
        $this->linha(['chargeid' => '', 'status' => 'pending', 'paymentid' => null]);

        $this->assertFalse(payment_processor::process_notification(''));
    }

    public function test_a_reconciliacao_alcanca_o_que_ficou_processando(): void {
        // Medido em 09/09/2026: uma cobranca de cartao ficou em 'processing' e
        // nunca saiu de la. Varrer so 'pending' deixaria essa linha parada
        // para sempre.
        $this->assertTrue(payment_processor::is_sweepable('pending'));
        $this->assertTrue(payment_processor::is_sweepable('processing'));
        $this->assertFalse(payment_processor::is_sweepable('paid'));
        $this->assertFalse(payment_processor::is_sweepable('canceled'));
    }

    // --------------------------------------------------------------------

    public function test_assinatura_com_split_nao_e_atendida(): void {
        // MEDIDO em 11/09/2026: POST /subscriptions com split responde 400 em
        // QUATRO formatos diferentes - v5 padrao, estilo v4, sem options, e
        // com options alternativas. Dentro de items[] o 200 volta com o split
        // descartado: nenhum payable e gerado.
        //
        // Sem split, uma venda recorrente renderia comissao ZERO sem acusar
        // erro. Recusar na porta e a unica saida honesta.
        $this->assertFalse(payment_processor::supports_recurring());
    }

    public function test_o_motivo_da_recusa_e_nomeado(): void {
        // A mensagem vai para o gerente, nao para o aluno: ela tem que dizer
        // o que fazer, e nao so que deu errado.
        $this->assertSame('errorrecurringunsupported', payment_processor::recurring_blocker());
    }

    public function test_a_resposta_documentada_do_pix_da_o_qrcode(): void {
        $order = documented_responses::pix_pendente();
        $charge = $order['charges'][0];

        $this->assertStringContainsString(
            'qrcode.png',
            payment_processor::checkout_url_for('pix', $charge)
        );
        $this->assertStringStartsWith(
            '00020101',
            (string) $charge['last_transaction']['qr_code']
        );
    }

    public function test_o_status_da_transacao_nao_e_o_status_da_cobranca(): void {
        // Na resposta documentada a cobranca esta 'pending' e a transacao,
        // 'waiting_payment'. Ler o campo errado faria o Pix parecer recusado.
        $charge = documented_responses::pix_pendente()['charges'][0];

        $this->assertSame('pending', $charge['status']);
        $this->assertSame('waiting_payment', $charge['last_transaction']['status']);
        $this->assertFalse(payment_processor::is_paid((string) $charge['status']));
    }

    public function test_a_forma_de_pagamento_pode_vir_com_maiuscula(): void {
        // A documentacao devolve "Pix", com inicial maiuscula, onde a
        // requisicao manda "pix". Comparacao sensivel a caixa quebraria aqui.
        $charge = documented_responses::pix_pendente()['charges'][0];

        $this->assertSame('Pix', $charge['payment_method']);
        $this->assertSame(
            'pix',
            strtolower((string) $charge['payment_method'])
        );
    }

    public function test_a_cobranca_documentada_paga_libera_acesso(): void {
        $charge = documented_responses::cobranca_paga_com_split();

        $this->assertTrue(payment_processor::is_paid((string) $charge['status']));
    }

    // --------------------------------------------------------------------

    public function test_telefone_do_usuario_e_quebrado_em_ddd_e_numero(): void {
        $user = (object) ['phone1' => '(11) 98765-4321', 'phone2' => ''];

        $this->assertSame([
            'country_code' => '55',
            'area_code' => '11',
            'number' => '987654321',
        ], payment_processor::buyer_phone($user));
    }

    public function test_telefone_com_codigo_do_pais_colado_e_aparado(): void {
        $user = (object) ['phone1' => '5511987654321', 'phone2' => ''];

        $this->assertSame('11', payment_processor::buyer_phone($user)['area_code']);
        $this->assertSame('987654321', payment_processor::buyer_phone($user)['number']);
    }

    public function test_telefone_sem_ddd_nao_e_usado(): void {
        // Sem DDD nao da para montar o objeto que a API espera, e inventar um
        // seria gravar dado errado no cadastro do vendedor.
        $user = (object) ['phone1' => '98765432', 'phone2' => ''];

        $this->assertSame([], payment_processor::buyer_phone($user));
    }

    public function test_usuario_sem_telefone_nao_produz_telefone(): void {
        $user = (object) ['phone1' => '', 'phone2' => ''];

        $this->assertSame([], payment_processor::buyer_phone($user));
    }

    public function test_o_celular_tem_precedencia_sobre_o_fixo(): void {
        $user = (object) ['phone1' => '1133334444', 'phone2' => '11987654321'];

        $this->assertSame('987654321', payment_processor::buyer_phone($user)['number']);
    }
}
