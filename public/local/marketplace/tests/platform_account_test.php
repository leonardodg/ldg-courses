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
 * A conta de pagamento da PROPRIA PLATAFORMA - quem recebe a assinatura
 * SaaS (Start/PRO) que a empresa parceira paga, diferente da conta de
 * cada empresa (que recebe a venda de curso).
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\api::class)]
final class platform_account_test extends \advanced_testcase {
    /**
     * A conta nasce no contexto do SITE, sem empresa nenhuma dona.
     *
     * @return void
     */
    public function test_cria_a_conta_no_contexto_do_site(): void {
        $this->resetAfterTest();

        $account = api::get_or_create_platform_account('BR');

        $this->assertSame(\context_system::instance()->id, (int) $account->get('contextid'));
        $this->assertTrue((bool) $account->get('enabled'));
    }

    /**
     * Chamar duas vezes para o mesmo pais devolve a MESMA conta - nao cria
     * uma segunda toda vez que alguem for cobrar o plano.
     *
     * @return void
     */
    public function test_e_idempotente_por_pais(): void {
        $this->resetAfterTest();

        $primeira = api::get_or_create_platform_account('BR');
        $segunda = api::get_or_create_platform_account('BR');

        $this->assertSame((int) $primeira->get('id'), (int) $segunda->get('id'));
    }

    /**
     * Paises diferentes tem contas diferentes - mesma regra da conta de
     * empresa, uma por pais.
     *
     * @return void
     */
    public function test_paises_diferentes_tem_contas_diferentes(): void {
        $this->resetAfterTest();

        $br = api::get_or_create_platform_account('BR');
        $ar = api::get_or_create_platform_account('AR');

        $this->assertNotSame((int) $br->get('id'), (int) $ar->get('id'));
    }

    /**
     * is_platform_account() reconhece a conta da plataforma, e so ela.
     *
     * @return void
     */
    public function test_is_platform_account_distingue_da_conta_de_empresa(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $plataforma = api::get_or_create_platform_account('BR');

        $company = new company(0, (object) [
            'name' => 'Empresa qualquer',
            'shortname' => 'empresaqualquer' . random_int(100000, 999999),
        ]);
        $company->create();
        $contaempresa = api::create_payment_account($company, 'BR');

        $this->assertTrue(api::is_platform_account((int) $plataforma->get('id')));
        $this->assertFalse(api::is_platform_account((int) $contaempresa->get('id')));
    }

    /**
     * Conta inexistente ou id invalido nunca e a plataforma - sem excecao.
     *
     * @return void
     */
    public function test_is_platform_account_com_id_invalido_devolve_falso(): void {
        $this->resetAfterTest();

        $this->assertFalse(api::is_platform_account(0));
        $this->assertFalse(api::is_platform_account(999999));
    }
}
