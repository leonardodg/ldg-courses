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

namespace local_marketplace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Registro de venda neutro de gateway.
 *
 * O que estes testes protegem: o relatorio lia a tabela do paygw_mercadopago
 * direto. Com um segundo gateway ele mentiria por omissao - a venda existiria,
 * o aluno estaria matriculado, e o total simplesmente nao contaria aquele
 * dinheiro. Sem erro e sem aviso, que e o pior tipo de bug de relatorio.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\sale::class)]
#[CoversMethod(\local_marketplace\api::class, 'record_sale')]
final class sale_test extends \advanced_testcase {
    /** @var company */
    protected $company;

    /** @var offer */
    protected $offer;

    /** @var \stdClass */
    protected $buyer;

    /**
     * Empresa, oferta e comprador.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        $this->company = api::create_company((object) [
            'name' => 'Empresa Teste',
            'shortname' => 'com' . random_int(1000, 9999),
        ], (int) $owner->id);

        $this->offer = new offer();
        $this->offer->set('companyid', (int) $this->company->get('id'));
        $this->offer->set('name', 'Oferta');
        $this->offer->set('price', 100.0);
        $this->offer->set('country', 'BR');
        $this->offer->set('status', offer::STATUS_PUBLISHED);
        $this->offer->create();

        $this->buyer = $this->getDataGenerator()->create_user();
    }

    /**
     * Grava um pagamento do core e devolve o id.
     *
     * @param string $gateway
     * @param float $amount
     * @return int
     */
    protected function make_payment(string $gateway, float $amount = 100.0): int {
        $account = $this->company->get_payment_account('BR');

        return \core_payment\helper::save_payment(
            (int) $account->get('id'),
            'local_marketplace',
            payment\service_provider::PAYMENT_AREA,
            (int) $this->offer->get('id'),
            (int) $this->buyer->id,
            $amount,
            'BRL',
            $gateway
        );
    }

    /**
     * A venda registrada aparece no relatorio da empresa.
     *
     * @return void
     */
    public function test_recorded_sale_shows_up_for_the_company(): void {
        $paymentid = $this->make_payment('mercadopago');
        api::record_sale('local_marketplace', $paymentid, (int) $this->offer->get('id'), 25.0, 'mp-123');

        $sales = sale::get_for_company((int) $this->company->get('id'));

        $this->assertCount(1, $sales);
        $row = reset($sales);
        $this->assertEquals(100.0, (float) $row->amount);
        $this->assertEquals(25.0, (float) $row->feeamount);
        $this->assertSame('mercadopago', $row->gateway);
        $this->assertSame('mp-123', $row->externalid);
    }

    /**
     * Vendas de gateways diferentes entram no mesmo relatorio.
     *
     * E o ponto inteiro desta tabela.
     *
     * @return void
     */
    public function test_sales_from_several_gateways_are_counted_together(): void {
        api::record_sale('local_marketplace', $this->make_payment('mercadopago'), (int) $this->offer->get('id'), 25.0);
        api::record_sale('local_marketplace', $this->make_payment('asaas'), (int) $this->offer->get('id'), 25.0);
        api::record_sale('local_marketplace', $this->make_payment('pagarme'), (int) $this->offer->get('id'), 25.0);

        $sales = sale::get_for_company((int) $this->company->get('id'));

        $this->assertCount(3, $sales);
        $gateways = array_map(fn($row) => $row->gateway, $sales);
        sort($gateways);
        $this->assertSame(['asaas', 'mercadopago', 'pagarme'], $gateways);
    }

    /**
     * Registrar a mesma venda duas vezes nao dobra o faturamento.
     *
     * O webhook do gateway reenvia notificacao. Sem esta garantia, cada reenvio
     * viraria uma venda a mais no relatorio.
     *
     * @return void
     */
    public function test_recording_twice_is_idempotent(): void {
        $paymentid = $this->make_payment('asaas');

        $first = api::record_sale('local_marketplace', $paymentid, (int) $this->offer->get('id'), 25.0);
        $second = api::record_sale('local_marketplace', $paymentid, (int) $this->offer->get('id'), 25.0);

        $this->assertEquals($first->get('id'), $second->get('id'));
        $this->assertCount(1, sale::get_for_company((int) $this->company->get('id')));
    }

    /**
     * Pagamento de outro componente nao vira venda do marketplace.
     *
     * O itemid do enrol_fee pode coincidir com o id de uma oferta. Sem o guarda
     * de componente, aquele pagamento apareceria no relatorio de uma empresa
     * que nao vendeu nada.
     *
     * @return void
     */
    public function test_other_components_are_ignored(): void {
        $paymentid = $this->make_payment('asaas');

        $this->assertNull(api::record_sale('enrol_fee', $paymentid, (int) $this->offer->get('id'), 25.0));
        $this->assertCount(0, sale::get_for_company((int) $this->company->get('id')));
    }

    /**
     * Oferta apagada nao gera venda orfa.
     *
     * @return void
     */
    public function test_missing_offer_records_nothing(): void {
        $paymentid = $this->make_payment('asaas');

        $this->assertNull(api::record_sale('local_marketplace', $paymentid, 999999, 25.0));
    }

    /**
     * A paymentarea 'plan' (assinatura SaaS) NUNCA grava sale - mesmo
     * quando o itemid (um companyid) coincide, por acaso, com um offerid
     * real. sale pressupoe split entre empresa e plataforma, que nao existe
     * quando a plataforma e a unica parte.
     *
     * @return void
     */
    public function test_paymentarea_plan_nunca_grava_venda_mesmo_com_id_coincidente(): void {
        $paymentid = $this->make_payment('mercadopago');

        // O "companyid" usado aqui e de proposito o MESMO id da oferta de
        // teste - e o cenario de colisao que a guarda existe para cobrir.
        $result = api::record_sale(
            'local_marketplace',
            $paymentid,
            (int) $this->offer->get('id'),
            25.0,
            '',
            null,
            payment\service_provider::PAYMENT_AREA_PLAN
        );

        $this->assertNull($result);
        $this->assertCount(0, sale::get_for_company((int) $this->company->get('id')));
    }

    /**
     * O aluno ve as proprias compras, de qualquer gateway.
     *
     * @return void
     */
    public function test_buyer_sees_their_own_payments(): void {
        api::record_sale('local_marketplace', $this->make_payment('asaas'), (int) $this->offer->get('id'), 25.0);

        $mine = sale::get_for_user((int) $this->buyer->id);
        $others = sale::get_for_user((int) $this->getDataGenerator()->create_user()->id);

        $this->assertCount(1, $mine);
        $this->assertCount(0, $others);
    }

    /**
     * O filtro por periodo corta pelo pagamento, nao pelo registro.
     *
     * @return void
     */
    public function test_period_filter_uses_the_payment_date(): void {
        global $DB;

        $paymentid = $this->make_payment('asaas');
        api::record_sale('local_marketplace', $paymentid, (int) $this->offer->get('id'), 25.0);

        // Empurra o pagamento para 60 dias atras.
        $DB->set_field('payments', 'timecreated', time() - (60 * DAYSECS), ['id' => $paymentid]);

        $companyid = (int) $this->company->get('id');
        $this->assertCount(0, sale::get_for_company($companyid, time() - (30 * DAYSECS)));
        $this->assertCount(1, sale::get_for_company($companyid, time() - (90 * DAYSECS)));
    }

    /**
     * A venda guarda os termos APLICADOS, e eles nao mudam depois.
     *
     * E o ponto inteiro de fotografar taxa e base na linha. Sem isso, uma venda
     * de seis meses atras so poderia ser explicada relendo a configuracao de
     * hoje - e a empresa, o plano e o padrao do site mudam. O relatorio passaria
     * a contar uma historia diferente da do extrato do gateway, e a que muda
     * seria a nossa.
     *
     * @return void
     */
    public function test_os_termos_ficam_fotografados(): void {
        $paymentid = $this->make_payment('asaas');

        $sale = api::record_sale(
            'local_marketplace',
            $paymentid,
            (int) $this->offer->get('id'),
            9.90,
            'ext-1',
            new commission(9.9, commission::BASE_GROSS, commission::SOURCE_PLAN)
        );

        // Muda TUDO o que poderia influenciar uma nova resolucao.
        set_config('commissionbase', commission::BASE_NET, 'local_marketplace');
        set_config('defaultfeepercent', 40, 'local_marketplace');
        $this->company->set('commissionpct', 30.0);
        $this->company->set('commissionbase', commission::BASE_NET);
        $this->company->update();

        $reread = sale::get_record(['id' => $sale->get('id')]);

        $this->assertEquals(9.9, $reread->get('feepercent'));
        $this->assertSame(commission::BASE_GROSS, $reread->get('feebase'));
        $this->assertSame(commission::SOURCE_PLAN, $reread->get('feesource'));
    }

    /**
     * Sem termos informados, a venda resolve os de agora.
     *
     * E o caminho do chamador antigo. Funciona, mas registra a configuracao do
     * momento do registro e nao a da cobranca - por isso os gateways passam os
     * termos que eles de fato aplicaram.
     *
     * @return void
     */
    public function test_sem_termos_resolve_os_atuais(): void {
        set_config('commissionbase', commission::BASE_NET, 'local_marketplace');
        $this->company->set('commissionpct', 12.0);
        $this->company->update();

        $paymentid = $this->make_payment('asaas');
        $sale = api::record_sale('local_marketplace', $paymentid, (int) $this->offer->get('id'), 12.0);

        $this->assertEquals(12.0, $sale->get('feepercent'));
        $this->assertSame(commission::BASE_NET, $sale->get('feebase'));
        $this->assertSame(commission::SOURCE_COMPANY, $sale->get('feesource'));
    }

    /**
     * Estornar revoga o direito e derruba a matricula.
     *
     * E a diferenca entre estorno e cancelamento: cancelar para de cobrar e
     * deixa o aluno usar o que ja pagou; estornar devolve o dinheiro daquele
     * periodo, entao o periodo tambem volta. Sem derrubar a matricula, o aluno
     * receberia o dinheiro e continuaria no curso ate o cron passar.
     *
     * @return void
     */
    public function test_estorno_revoga_direito_e_matricula(): void {
        global $DB;

        $paymentid = $this->make_payment('asaas');
        api::record_sale('local_marketplace', $paymentid, (int) $this->offer->get('id'), 25.0, 'pay_x');

        payment\service_provider::deliver_order(
            payment\service_provider::PAYMENT_AREA,
            (int) $this->offer->get('id'),
            $paymentid,
            (int) $this->buyer->id
        );

        $before = entitlement::get_active_for_user((int) $this->buyer->id);
        $this->assertCount(1, $before, 'a venda precisa ter gerado direito');

        $revoked = api::record_refund($paymentid);

        $this->assertTrue($revoked);
        $this->assertSame([], entitlement::get_active_for_user((int) $this->buyer->id));

        $all = entitlement::get_records(['userid' => (int) $this->buyer->id]);
        $this->assertSame(entitlement::STATUS_CANCELLED, reset($all)->get('status'));

        // A venda FICA no historico: apagar esconderia o dinheiro que entrou e
        // saiu, e o relatorio precisa dos dois lados.
        $this->assertTrue($DB->record_exists('local_marketplace_sale', ['paymentid' => $paymentid]));
    }

    /**
     * Estornar duas vezes nao explode, e nao revoga de novo.
     *
     * @return void
     */
    public function test_estorno_repetido_nao_revoga_de_novo(): void {
        $paymentid = $this->make_payment('asaas');
        api::record_sale('local_marketplace', $paymentid, (int) $this->offer->get('id'), 25.0, 'pay_y');
        payment\service_provider::deliver_order(
            payment\service_provider::PAYMENT_AREA,
            (int) $this->offer->get('id'),
            $paymentid,
            (int) $this->buyer->id
        );

        $this->assertTrue(api::record_refund($paymentid));
        $this->assertFalse(api::record_refund($paymentid), 'ja estava revogado');
    }

    /**
     * Pagamento que nao e de venda do marketplace nao revoga nada.
     *
     * @return void
     */
    public function test_estorno_de_pagamento_desconhecido_nao_faz_nada(): void {
        $this->assertFalse(api::record_refund(999999));
    }
}
