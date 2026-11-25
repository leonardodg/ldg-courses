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
 * Conta de pagamento por pais.
 *
 * A regressao que estes testes existem para impedir e concreta: antes disto,
 * account::get_record(['contextid' => ...]) devolvia a PRIMEIRA conta da
 * categoria da empresa. Uma empresa com conta brasileira e argentina passava a
 * receber conforme a ordem dos ids - e o erro nao aparece em nenhum lugar
 * antes do dinheiro cair na conta errada.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\company_account::class)]
#[CoversMethod(\local_marketplace\company::class, 'get_payment_account')]
#[CoversMethod(\local_marketplace\company::class, 'get_payment_accounts')]
#[CoversMethod(\local_marketplace\api::class, 'create_payment_account')]
final class company_account_test extends \advanced_testcase {
    /** @var company */
    protected $company;

    /**
     * Empresa de teste, ja provisionada com a conta do pais padrao.
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
    }

    /**
     * A empresa nasce com conta no pais padrao, e vinculada.
     *
     * @return void
     */
    public function test_company_is_created_with_default_country_account(): void {
        $default = api::default_country();

        $account = $this->company->get_payment_account($default);

        $this->assertNotNull($account);
        $this->assertSame([$default], $this->company->get_countries());
    }

    /**
     * Duas contas em paises diferentes convivem, cada uma resolvida pelo seu.
     *
     * @return void
     */
    public function test_two_countries_resolve_to_different_accounts(): void {
        $br = api::create_payment_account($this->company, 'BR');
        $ar = api::create_payment_account($this->company, 'AR');

        $this->assertNotEquals($br->get('id'), $ar->get('id'));
        $this->assertEquals($br->get('id'), $this->company->get_payment_account('BR')->get('id'));
        $this->assertEquals($ar->get('id'), $this->company->get_payment_account('AR')->get('id'));
    }

    /**
     * Pedir a conta duas vezes para o mesmo pais devolve a mesma.
     *
     * Criar a segunda deixaria a empresa com duas contas concorrendo pelo mesmo
     * mercado, e o indice unico recusaria o vinculo no meio do caminho - depois
     * de a conta do core ja ter sido criada.
     *
     * @return void
     */
    public function test_creating_twice_returns_the_same_account(): void {
        $first = api::create_payment_account($this->company, 'AR');
        $second = api::create_payment_account($this->company, 'AR');

        $this->assertEquals($first->get('id'), $second->get('id'));
        $this->assertCount(1, company_account::get_records(['companyid' => $this->company->get('id'), 'country' => 'AR']));
    }

    /**
     * Pais sem conta devolve nulo, e nao a conta de outro pais.
     *
     * @return void
     */
    public function test_country_without_account_returns_null(): void {
        $this->assertNull($this->company->get_payment_account('MX'));
    }

    /**
     * Minusculas resolvem para a mesma conta.
     *
     * @return void
     */
    public function test_lookup_is_case_insensitive(): void {
        $account = api::create_payment_account($this->company, 'AR');

        $this->assertEquals($account->get('id'), $this->company->get_payment_account('ar')->get('id'));
    }

    /**
     * Pais fora da lista nao vira vinculo.
     *
     * @return void
     */
    public function test_unsupported_country_is_rejected(): void {
        $link = new company_account();
        $link->set('companyid', (int) $this->company->get('id'));
        $link->set('country', 'US');
        $link->set('accountid', 999999);

        $this->assertNotTrue($link->validate());
        $this->assertArrayHasKey('country', $link->get_errors());
    }

    /**
     * A mesma conta do core nao pode ser vinculada duas vezes.
     *
     * @return void
     */
    public function test_account_cannot_be_linked_twice(): void {
        $account = api::create_payment_account($this->company, 'BR');

        $duplicate = new company_account();
        $duplicate->set('companyid', (int) $this->company->get('id'));
        $duplicate->set('country', 'AR');
        $duplicate->set('accountid', (int) $account->get('id'));

        $this->assertNotTrue($duplicate->validate());
        $this->assertArrayHasKey('accountid', $duplicate->get_errors());
    }

    /**
     * As moedas da empresa saem dos paises em que ela tem conta.
     *
     * @return void
     */
    public function test_currencies_follow_the_countries(): void {
        api::create_payment_account($this->company, 'BR');
        api::create_payment_account($this->company, 'AR');

        $currencies = $this->company->get_currencies();

        $this->assertSame('BRL', $currencies['BR']);
        $this->assertSame('ARS', $currencies['AR']);
    }

    /**
     * Sem gateway habilitado a empresa nao vende, em pais nenhum.
     *
     * A conta nasce sem gateway de proposito: e isso que mantem o portao
     * fechado ate o vendedor concluir o vinculo.
     *
     * @return void
     */
    public function test_cannot_sell_without_a_gateway(): void {
        $this->assertFalse($this->company->can_sell());
        $this->assertFalse($this->company->can_sell('BR'));
    }
}
