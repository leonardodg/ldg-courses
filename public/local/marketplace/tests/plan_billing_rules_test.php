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

use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * recurrence_for() e commission_terms_for() para a paymentarea 'plan' -
 * a assinatura SaaS que a empresa paga a PLATAFORMA.
 *
 * As duas funcoes ja existiam para a venda de curso (paymentarea 'offer'),
 * indexadas so por component+itemid, tratando SEMPRE itemid como offerid.
 * Sem o parametro $paymentarea, um companyid que por acaso coincidisse com
 * um offerid real devolveria a recorrencia/comissao de uma oferta que nao
 * tem nada a ver com a cobranca do plano - risco medido antes de escrever
 * este codigo, nao hipotetico.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\local_marketplace\api::class, 'recurrence_for')]
#[CoversMethod(\local_marketplace\api::class, 'commission_terms_for')]
final class plan_billing_rules_test extends \advanced_testcase {
    /**
     * Empresa com plano pago: recorrencia de 30 dias, sem limite de ciclos.
     *
     * @return void
     */
    public function test_recurrence_for_plan_com_mensalidade(): void {
        $this->resetAfterTest();

        $company = $this->criar_empresa_com_plano(50.0);

        $recorrencia = api::recurrence_for(
            'local_marketplace',
            (int) $company->get('id'),
            payment\service_provider::PAYMENT_AREA_PLAN
        );

        $this->assertNotNull($recorrencia);
        $this->assertSame(30, $recorrencia->days);
        $this->assertSame(0, $recorrencia->maxcycles, '0 = ate cancelar, sem limite de ciclos');
    }

    /**
     * Empresa no tier gratis (mensalidade zero) nao tem recorrencia - nao
     * ha o que cobrar automaticamente.
     *
     * @return void
     */
    public function test_recurrence_for_plan_sem_mensalidade_e_nulo(): void {
        $this->resetAfterTest();

        $company = $this->criar_empresa_com_plano(0.0);

        $recorrencia = api::recurrence_for(
            'local_marketplace',
            (int) $company->get('id'),
            payment\service_provider::PAYMENT_AREA_PLAN
        );

        $this->assertNull($recorrencia);
    }

    /**
     * Empresa sem plano nenhum: idem, nulo.
     *
     * @return void
     */
    public function test_recurrence_for_plan_sem_plano_e_nulo(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa sem plano',
            'shortname' => 'semplano' . random_int(100000, 999999),
        ], (int) $owner->id);

        $recorrencia = api::recurrence_for(
            'local_marketplace',
            (int) $company->get('id'),
            payment\service_provider::PAYMENT_AREA_PLAN
        );

        $this->assertNull($recorrencia);
    }

    /**
     * A assinatura SaaS nao tem split: comissao 0%, sempre - a plataforma e
     * a UNICA parte, entao nao ha o padrao do site (25%) a aplicar.
     *
     * @return void
     */
    public function test_commission_terms_for_plan_e_sempre_zero(): void {
        $this->resetAfterTest();

        set_config('defaultfeepercent', 25.0, 'local_marketplace');

        $company = $this->criar_empresa_com_plano(50.0);

        $termos = api::commission_terms_for(
            'local_marketplace',
            (int) $company->get('id'),
            payment\service_provider::PAYMENT_AREA_PLAN
        );

        $this->assertSame(0.0, $termos->percent);
    }

    /**
     * Cria uma empresa com um plano pago, com a mensalidade informada.
     *
     * @param float $monthlyfee
     * @return company
     */
    protected function criar_empresa_com_plano(float $monthlyfee): company {
        $this->setAdminUser();

        $plan = new plan(0, (object) [
            'shortname' => 'plano_regras_' . random_int(100000, 999999),
            'name' => 'Plano de teste',
            'monthlyfee' => $monthlyfee,
            'commissionpct' => 10,
        ]);
        $plan->create();

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa com plano',
            'shortname' => 'complano' . random_int(100000, 999999),
        ], (int) $owner->id);
        $company->set('planid', (int) $plan->get('id'));
        $company->update();

        return $company;
    }
}
