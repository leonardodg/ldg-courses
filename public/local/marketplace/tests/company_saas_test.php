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

/**
 * O vencimento da mensalidade do PLANO - a assinatura SaaS que a empresa
 * parceira paga a plataforma, e nao a venda de curso.
 *
 * Sem status separado de proposito: inadimplente e so "planexpiry no
 * passado", do mesmo jeito que entitlement::timeend ja decide vencimento.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\company::class)]
final class company_saas_test extends \advanced_testcase {
    /**
     * Empresa que nunca assinou plano nenhum nao esta inadimplente - nao ha
     * o que vencer.
     *
     * @return void
     */
    public function test_sem_planexpiry_nao_esta_inadimplente(): void {
        $this->resetAfterTest();

        $company = $this->create_company();

        $this->assertFalse($company->is_plan_overdue());
    }

    /**
     * Vencimento no futuro nao e inadimplencia.
     *
     * @return void
     */
    public function test_planexpiry_no_futuro_nao_e_inadimplente(): void {
        $this->resetAfterTest();

        $company = $this->create_company(['planexpiry' => time() + DAYSECS]);

        $this->assertFalse($company->is_plan_overdue());
    }

    /**
     * Vencimento no passado e inadimplencia.
     *
     * @return void
     */
    public function test_planexpiry_no_passado_e_inadimplente(): void {
        $this->resetAfterTest();

        $company = $this->create_company(['planexpiry' => time() - DAYSECS]);

        $this->assertTrue($company->is_plan_overdue());
    }

    /**
     * Estender soma ao vencimento ATUAL quando ele ainda esta no futuro -
     * quem paga adiantado nao perde o que sobrou do ciclo anterior.
     *
     * @return void
     */
    public function test_extend_plan_soma_ao_vencimento_futuro(): void {
        $this->resetAfterTest();

        $future = time() + (10 * DAYSECS);
        $company = $this->create_company(['planexpiry' => $future]);

        $company->extend_plan(30 * DAYSECS);

        $this->assertEqualsWithDelta($future + (30 * DAYSECS), (int) $company->get('planexpiry'), 2);
    }

    /**
     * Estender um vencimento ja passado conta a partir de AGORA - quem
     * ficou dias sem pagar nao ganha esses dias de volta.
     *
     * @return void
     */
    public function test_extend_plan_soma_a_partir_de_agora_quando_ja_venceu(): void {
        $this->resetAfterTest();

        $company = $this->create_company(['planexpiry' => time() - (10 * DAYSECS)]);

        $before = time();
        $company->extend_plan(30 * DAYSECS);
        $after = time();

        $new = (int) $company->get('planexpiry');
        $this->assertGreaterThanOrEqual($before + (30 * DAYSECS), $new);
        $this->assertLessThanOrEqual($after + (30 * DAYSECS), $new);
    }

    /**
     * Estender sem planexpiry previo (nunca assinou) conta a partir de
     * agora - mesma regra do vencido, ja que NULO trata como 0 no calculo.
     *
     * @return void
     */
    public function test_extend_plan_sem_expiry_previo_conta_a_partir_de_agora(): void {
        $this->resetAfterTest();

        $company = $this->create_company();

        $before = time();
        $company->extend_plan(30 * DAYSECS);

        $new = (int) $company->get('planexpiry');
        $this->assertGreaterThanOrEqual($before + (30 * DAYSECS), $new);
    }

    /**
     * Cria uma empresa de teste.
     *
     * @param array $overrides
     * @return company
     */
    protected function create_company(array $overrides = []): company {
        $company = new company(0, (object) array_merge([
            'name' => 'Empresa SaaS de teste',
            'shortname' => 'saasteste' . random_int(100000, 999999),
        ], $overrides));
        $company->create();

        return $company;
    }
}
