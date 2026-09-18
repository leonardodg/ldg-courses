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

namespace block_marketplace;

use local_marketplace\api;
use local_marketplace\company;
use local_marketplace\company_account;
use local_marketplace\plan;
use core_payment\account;
use core_payment\account_gateway;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/marketplace/classes/onboarding.php');

/**
 * O checklist de ativacao e derivado do estado que ja existe - nenhuma
 * tabela nova. Estes testes provam a derivacao, sem tocar em pagina nenhuma.
 *
 * @package    block_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(onboarding::class)]
final class onboarding_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Cria uma empresa de teste com dados padrao.
     *
     * @param array $overrides
     * @return company
     */
    private function make_company(array $overrides = []): company {
        // Garanta que o gateway de teste esta habilitado.
        \core\plugininfo\paygw::enable_plugin('paypal', 1);

        return api::create_company((object) array_merge([
            'name' => 'Empresa de teste',
            'shortname' => 'onboardteste' . random_int(100000, 999999),
            'cnpj' => null,
            'themename' => null,
            'hostname' => null,
        ], $overrides), 2);
    }

    /**
     * Conta vinculada, habilitada, com gateway ligado - o que account::is_available() exige.
     *
     * api::create_company() ja cria e vincula uma conta para o pais padrao
     * (BR) - reaproveita essa conta em vez de tentar vincular outra: ha
     * indice unico companyid+country em local_marketplace_company_account.
     *
     * @param company $company
     * @param string $country
     * @return account
     */
    private function make_available_account(company $company, string $country = 'BR'): account {
        $account = $company->get_payment_accounts()[$country] ?? null;
        if ($account === null) {
            throw new \coding_exception('Empresa sem conta para o pais ' . $country);
        }

        $gateway = new account_gateway(0);
        $gateway->set('accountid', (int) $account->get('id'));
        $gateway->set('gateway', 'paypal');
        $gateway->set('enabled', true);
        $gateway->save();

        return $account;
    }

    public function test_empresa_nova_tem_gateway_e_plano_pendentes(): void {
        $company = $this->make_company();

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_PENDING, $states[onboarding::STEP_GATEWAY]);
        $this->assertSame(onboarding::STATE_PENDING, $states[onboarding::STEP_PLAN]);
    }

    public function test_documento_e_opcional_quando_cnpj_e_nulo(): void {
        $company = $this->make_company();

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_OPTIONAL, $states[onboarding::STEP_DOCUMENT]);
    }

    public function test_documento_fica_concluido_quando_cnpj_existe(): void {
        $company = $this->make_company(['cnpj' => '11222333000181']);

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_DONE, $states[onboarding::STEP_DOCUMENT]);
    }

    public function test_plano_fica_concluido_quando_planid_esta_definido(): void {
        $plan = new plan(0, (object) [
            'shortname' => 'start_50_teste',
            'name' => 'Start 1080p',
            'monthlyfee' => 50,
            'commissionpct' => 10,
        ]);
        $plan->create();
        $company = $this->make_company();
        $company->set('planid', (int) $plan->get('id'));
        $company->update();

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_DONE, $states[onboarding::STEP_PLAN]);
    }

    public function test_conta_habilitada_com_gateway_ligado_conclui_a_etapa(): void {
        $company = $this->make_company();
        $this->make_available_account($company);

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_DONE, $states[onboarding::STEP_GATEWAY]);
    }

    public function test_progress_conta_so_as_etapas_obrigatorias(): void {
        $company = $this->make_company(); // CNPJ null: documento e opcional, fora do total.

        $progress = onboarding::progress($company);

        $this->assertSame(0, $progress['done']);
        $this->assertSame(2, $progress['total']);
        $this->assertFalse($progress['complete']);
    }

    public function test_progress_fica_completo_com_gateway_e_plano(): void {
        $plan = new plan(0, (object) [
            'shortname' => 'start_free_teste',
            'name' => 'Start Free',
            'monthlyfee' => 0,
            'commissionpct' => 10,
        ]);
        $plan->create();
        $company = $this->make_company();
        $company->set('planid', (int) $plan->get('id'));
        $company->update();
        $this->make_available_account($company);

        $progress = onboarding::progress($company);

        $this->assertSame(2, $progress['done']);
        $this->assertSame(100, $progress['percent']);
        $this->assertTrue($progress['complete']);
    }
}
