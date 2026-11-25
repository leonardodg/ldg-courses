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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Validacao de CNPJ.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\cnpj::class)]
final class cnpj_test extends \advanced_testcase {
    /**
     * Casos de validacao.
     *
     * @return array
     */
    public static function cnpj_provider(): array {
        return [
            'valido sem pontuacao' => ['11222333000181', true],
            'valido com pontuacao' => ['11.222.333/0001-81', true],
            'segundo digito errado' => ['11222333000180', false],
            'primeiro digito errado' => ['11222333000171', false],
            'curto demais' => ['1122233300018', false],
            'longo demais' => ['112223330001811', false],
            'so zeros' => ['00000000000000', false],
            'todos iguais' => ['11111111111111', false],
            'vazio' => ['', false],
            'com letras' => ['1122233300018A', false],
        ];
    }

    /**
     * O modulo 11 aceita e recusa o que deve.
     *
     * @param string $value
     * @param bool $expected
     * @return void
     */
    #[DataProvider('cnpj_provider')]
    public function test_is_valid(string $value, bool $expected): void {
        $this->assertSame($expected, cnpj::is_valid($value));
    }

    /**
     * A normalizacao deixa so digitos.
     *
     * @return void
     */
    public function test_normalise(): void {
        $this->assertSame('11222333000181', cnpj::normalise('11.222.333/0001-81'));
        $this->assertSame('11222333000181', cnpj::normalise(' 11222333000181 '));
        $this->assertSame('', cnpj::normalise('abc'));
    }

    /**
     * O campo continua opcional na empresa.
     *
     * Pessoa fisica vende sem CNPJ, e essa decisao nao mudou ao ganhar
     * validacao - o que mudou e que catorze digitos quaisquer deixaram de
     * passar.
     *
     * @return void
     */
    public function test_empresa_sem_cnpj_continua_valida(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Sem CNPJ',
            'shortname' => 'semcnpj' . random_int(100, 999),
        ], (int) $owner->id);

        $this->assertNull($company->get('cnpj'));
    }

    /**
     * CNPJ invalido na empresa e recusado.
     *
     * @return void
     */
    public function test_empresa_com_cnpj_invalido_e_recusada(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $company = new company(0, (object) [
            'name' => 'CNPJ ruim',
            'shortname' => 'cnpjruim' . random_int(100, 999),
            'cnpj' => '11222333000180',
        ]);

        $this->expectException(\core\invalid_persistent_exception::class);
        $company->create();
    }
}
