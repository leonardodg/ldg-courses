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
use local_marketplace\payment\service_provider;

/**
 * Entrega do que foi pago.
 *
 * O Mercado Pago REENVIA webhook - varias vezes, para o mesmo pagamento. Se a
 * entrega nao for idempotente, o aluno ganha meses de acesso a cada reenvio e a
 * contagem de ciclos vira ficcao.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\payment\service_provider::class)]
final class service_provider_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $user;

    /** @var company */
    protected $company;

    /**
     * Cria empresa, curso e aluno.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->user = $this->getDataGenerator()->create_user();
        $this->company = api::create_company((object) [
            'name' => 'Empresa Teste',
            'shortname' => 'teste' . random_int(1000, 9999),
            'cnpj' => null,
            'themename' => null,
            'hostname' => null,
        ], (int) $this->user->id);
    }

    /**
     * Cria uma oferta da empresa de teste.
     *
     * @param string $mode
     * @param int $days
     * @return offer
     */
    protected function make_offer(string $mode, int $days): offer {
        $course = $this->getDataGenerator()->create_course([
            'category' => $this->company->get('categoryid'),
        ]);

        $o = new offer();
        $o->set('companyid', (int) $this->company->get('id'));
        $o->set('name', 'Oferta');
        $o->set('offertype', offer::TYPE_SINGLE);
        $o->set('price', 50.0);
        $o->set('currency', 'BRL');
        $o->set('accessmode', $mode);
        $o->set('accessdays', $days);
        $o->set('status', offer::STATUS_PUBLISHED);
        $o->create();
        $o->add_course((int) $course->id);

        return $o;
    }

    /**
     * A primeira entrega cria o direito com um ciclo.
     *
     * @return void
     */
    public function test_first_delivery_creates_entitlement(): void {
        $offer = $this->make_offer(offer::ACCESS_DAYS, 30);

        service_provider::deliver_order('offer', (int) $offer->get('id'), 1, (int) $this->user->id);

        $active = entitlement::get_active_for_user((int) $this->user->id);
        $this->assertCount(1, $active);

        $ent = reset($active);
        $this->assertSame(1, (int) $ent->get('cycles'));
        $this->assertGreaterThan(time(), (int) $ent->get('timeend'));
    }

    /**
     * Entregar de novo ESTENDE, e nao duplica.
     *
     * Um segundo direito para a mesma oferta faria o aluno aparecer duas vezes
     * no relatorio e quebraria a contagem de ciclos.
     *
     * @return void
     */
    public function test_second_delivery_extends_instead_of_duplicating(): void {
        $offer = $this->make_offer(offer::ACCESS_DAYS, 30);
        $userid = (int) $this->user->id;

        service_provider::deliver_order('offer', (int) $offer->get('id'), 1, $userid);
        $active = entitlement::get_active_for_user($userid);
        $firstend = (int) reset($active)->get('timeend');

        service_provider::deliver_order('offer', (int) $offer->get('id'), 2, $userid);

        $active = entitlement::get_active_for_user($userid);
        $this->assertCount(1, $active, 'nao pode nascer um segundo direito');

        $second = reset($active);
        $this->assertSame(2, (int) $second->get('cycles'));
        $this->assertSame(
            $firstend + (30 * DAYSECS),
            (int) $second->get('timeend'),
            'a renovacao soma ao vencimento atual, nao recomeca de agora'
        );
    }

    /**
     * Renovar antes de vencer nao encurta o que ja foi pago.
     *
     * Somar a partir de "agora" tiraria os dias restantes de quem se antecipa -
     * punindo exatamente o assinante mais organizado.
     *
     * @return void
     */
    public function test_early_renewal_does_not_shorten_paid_time(): void {
        $offer = $this->make_offer(offer::ACCESS_RECURRING, 30);
        $userid = (int) $this->user->id;

        service_provider::deliver_order('offer', (int) $offer->get('id'), 1, $userid);
        $active = entitlement::get_active_for_user($userid);
        $before = (int) reset($active)->get('timeend');

        service_provider::deliver_order('offer', (int) $offer->get('id'), 2, $userid);
        $active = entitlement::get_active_for_user($userid);
        $after = (int) reset($active)->get('timeend');

        $this->assertSame($before + (30 * DAYSECS), $after);
    }

    /**
     * Em oferta vitalicia o ciclo conta, mas a validade nao muda.
     *
     * O numero serve ao relatorio e ao limite de cobrancas, nao a data.
     *
     * @return void
     */
    public function test_lifetime_counts_cycle_without_expiry(): void {
        $offer = $this->make_offer(offer::ACCESS_LIFETIME, 0);
        $userid = (int) $this->user->id;

        service_provider::deliver_order('offer', (int) $offer->get('id'), 1, $userid);
        service_provider::deliver_order('offer', (int) $offer->get('id'), 2, $userid);

        $active = entitlement::get_active_for_user($userid);
        $ent = reset($active);
        $this->assertSame(2, (int) $ent->get('cycles'));
        $this->assertSame(0, (int) $ent->get('timeend'));
    }

    /**
     * O valor cobrado vem do servidor, nunca do navegador.
     *
     * @return void
     */
    public function test_payable_uses_offer_price(): void {
        $offer = $this->make_offer(offer::ACCESS_DAYS, 30);

        $payable = service_provider::get_payable('offer', (int) $offer->get('id'));

        $this->assertEquals(50.0, $payable->get_amount());
        $this->assertSame('BRL', $payable->get_currency());
    }

    /**
     * Pagar a mensalidade atrasada revive o direito, e nao cria um segundo.
     *
     * Quem deixa vencer tem o direito marcado como expired pelo cron. Enquanto
     * o deliver_order procurava so o ATIVO, pagar a atrasada criava um segundo
     * direito para a mesma oferta: o cycles voltava a 1 - e com ele o maxcycles
     * nunca terminaria -, a tela listava a oferta duas vezes, e todo
     * get_record que espera um so quebrava.
     *
     * Visto acontecer em 09/09/2026, na prova do ciclo curto de assinatura no
     * sandbox do Asaas.
     *
     * @return void
     */
    public function test_pagar_atrasada_revive_em_vez_de_duplicar(): void {
        $offer = $this->make_offer(offer::ACCESS_RECURRING, 7);
        $userid = (int) $this->user->id;

        service_provider::deliver_order('offer', (int) $offer->get('id'), 1, $userid);

        // O aluno nao paga: o cron marca vencido.
        $ativos = entitlement::get_active_for_user($userid);
        $ent = reset($ativos);
        $ent->set('timeend', time() - DAYSECS);
        $ent->set('status', entitlement::STATUS_EXPIRED);
        $ent->update();

        service_provider::deliver_order('offer', (int) $offer->get('id'), 2, $userid);

        $todos = entitlement::get_records(['userid' => $userid, 'offerid' => (int) $offer->get('id')]);
        $this->assertCount(1, $todos, 'pagar a atrasada nao pode criar um segundo direito');

        $revivido = reset($todos);
        $this->assertSame(entitlement::STATUS_ACTIVE, $revivido->get('status'));
        $this->assertSame(2, (int) $revivido->get('cycles'), 'o ciclo continua contando de onde parou');
        $this->assertGreaterThan(time(), (int) $revivido->get('timeend'));

        // Quem ficou um dia sem pagar nao ganha o dia de volta: o novo periodo
        // conta de agora, e nao do vencimento que ja passou.
        $this->assertLessThanOrEqual(
            time() + (7 * DAYSECS) + 60,
            (int) $revivido->get('timeend'),
            'o periodo novo conta a partir de agora, e nao do vencimento vencido'
        );
    }

    /**
     * Direito revogado nao volta sozinho com um pagamento novo.
     *
     * Revogar e decisao de negocio - estorno, abuso - tomada em
     * entitlement::revoke(). Reviver em silencio apagaria essa decisao; o
     * pagamento novo cria direito novo, e a revogacao fica no historico.
     *
     * @return void
     */
    public function test_direito_revogado_nao_revive(): void {
        $offer = $this->make_offer(offer::ACCESS_RECURRING, 7);
        $userid = (int) $this->user->id;

        service_provider::deliver_order('offer', (int) $offer->get('id'), 1, $userid);
        $ativos = entitlement::get_active_for_user($userid);
        $ent = reset($ativos);
        $ent->revoke();

        service_provider::deliver_order('offer', (int) $offer->get('id'), 2, $userid);

        $todos = entitlement::get_records(['userid' => $userid, 'offerid' => (int) $offer->get('id')]);
        $this->assertCount(2, $todos, 'o revogado fica no historico, e nasce um novo');
    }

    /**
     * Cancelar pergunta aos gateways INSTALADOS, e nao aos habilitados.
     *
     * Habilitar governa a venda nova. Nao governa o que ja foi vendido: uma
     * assinatura criada enquanto o gateway estava ligado continua cobrando
     * depois que ele e desligado, porque quem cobra e o gateway.
     *
     * Enquanto a lista vinha de get_enabled_plugins(), desligar o Asaas
     * deixava toda assinatura dele cobrando para sempre, e o cancelamento do
     * aluno nao fazia nada - sem erro e sem log. Visto em 09/09/2026.
     *
     * @return void
     */
    public function test_cancelar_alcanca_gateway_desabilitado(): void {
        $this->resetAfterTest();

        // Nenhum gateway habilitado para venda nova.
        set_config('paygw_plugins_sortorder', '');

        $alcancados = \local_marketplace\api::billing_capable_gateways();

        $this->assertNotEmpty($alcancados, 'desligar a venda nao pode esconder quem ja cobra');
        $this->assertContains('asaas', $alcancados);
        $this->assertSame(
            [],
            \core\plugininfo\paygw::get_enabled_plugins(),
            'a premissa do teste: nada habilitado'
        );
    }

    /**
     * A assinatura SaaS (paymentarea 'plan') cobra o valor do PLANO, e a
     * conta que recebe e a da PLATAFORMA - o oposto da venda de curso, onde
     * quem recebe e a empresa.
     *
     * @return void
     */
    public function test_payable_do_plano_usa_a_conta_da_plataforma(): void {
        $plan = new plan(0, (object) [
            'shortname' => 'plano_pago_' . random_int(100000, 999999),
            'name' => 'Plano pago de teste',
            'monthlyfee' => 50.0,
            'commissionpct' => 10,
            'country' => 'BR',
            'currency' => 'BRL',
        ]);
        $plan->create();
        $this->company->set('planid', (int) $plan->get('id'));
        $this->company->update();

        $payable = service_provider::get_payable(service_provider::PAYMENT_AREA_PLAN, (int) $this->company->get('id'));

        $this->assertEquals(50.0, $payable->get_amount());
        $this->assertSame('BRL', $payable->get_currency());

        $contaplataforma = api::get_or_create_platform_account('BR');
        $this->assertSame(
            (int) $contaplataforma->get('id'),
            $payable->get_account_id(),
            'quem recebe a mensalidade e a plataforma, nunca a empresa'
        );
    }

    /**
     * Empresa sem plano, ou no tier gratis (mensalidade zero), nao tem o
     * que cobrar - a paymentarea 'plan' recusa em vez de criar uma
     * cobranca de R$0.
     *
     * @return void
     */
    public function test_payable_do_plano_recusa_sem_mensalidade(): void {
        $this->expectException(\moodle_exception::class);

        service_provider::get_payable(service_provider::PAYMENT_AREA_PLAN, (int) $this->company->get('id'));
    }

    /**
     * Entregar a assinatura SaaS so estende o vencimento da mensalidade -
     * NAO cria entitlement nem sale, porque nao ha oferta nem split
     * nenhum envolvido.
     *
     * @return void
     */
    public function test_deliver_order_do_plano_estende_o_vencimento(): void {
        $plan = new plan(0, (object) [
            'shortname' => 'plano_entrega_' . random_int(100000, 999999),
            'name' => 'Plano de teste',
            'monthlyfee' => 50.0,
            'commissionpct' => 10,
        ]);
        $plan->create();
        $this->company->set('planid', (int) $plan->get('id'));
        $this->company->update();

        $antes = time();
        $resultado = service_provider::deliver_order(
            service_provider::PAYMENT_AREA_PLAN,
            (int) $this->company->get('id'),
            1,
            (int) $this->user->id
        );

        $this->assertTrue($resultado);

        $this->company->read();
        $expiry = (int) $this->company->get('planexpiry');
        $this->assertGreaterThanOrEqual($antes + (30 * DAYSECS), $expiry);

        // Idempotencia por soma: pagar de novo soma outros 30 dias ao
        // vencimento ATUAL, nao recomeca de agora - mesma regra da venda de
        // curso.
        service_provider::deliver_order(
            service_provider::PAYMENT_AREA_PLAN,
            (int) $this->company->get('id'),
            2,
            (int) $this->user->id
        );
        $this->company->read();
        $this->assertEqualsWithDelta($expiry + (30 * DAYSECS), (int) $this->company->get('planexpiry'), 2);

        // Nao cria direito de acesso nenhum: isto e assinatura de EMPRESA,
        // nao de aluno.
        $this->assertCount(0, entitlement::get_active_for_user((int) $this->user->id));
    }

    /**
     * Empresa sem plano nenhum: a entrega nao quebra, so nao faz nada -
     * defesa contra um itemid errado chegando aqui.
     *
     * @return void
     */
    public function test_deliver_order_do_plano_sem_plano_nao_quebra(): void {
        $resultado = service_provider::deliver_order(
            service_provider::PAYMENT_AREA_PLAN,
            (int) $this->company->get('id'),
            1,
            (int) $this->user->id
        );

        $this->assertFalse($resultado);
    }

    /**
     * O sucesso da assinatura SaaS volta para a pagina da propria empresa,
     * nao para curso nenhum.
     *
     * @return void
     */
    public function test_success_url_do_plano_volta_para_a_pagina_da_empresa(): void {
        $url = service_provider::get_success_url(
            service_provider::PAYMENT_AREA_PLAN,
            (int) $this->company->get('id')
        );

        $this->assertStringContainsString('company.php', $url->out(false));
        $this->assertStringContainsString((string) $this->company->get('shortname'), $url->out(false));
    }
}
