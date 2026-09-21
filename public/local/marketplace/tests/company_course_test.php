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
 * Curso na categoria da empresa, ou numa subcategoria dela.
 *
 * A regressao que estes testes existem para impedir: owns_course() e
 * for_course() faziam igualdade exata contra course.category, e uma empresa
 * que organiza os proprios cursos em subcategorias sob a categoria raiz
 * ficava sem conseguir vincular oferta a esses cursos, e sem o paywall do
 * availability_marketplace funcionando neles.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\local_marketplace\company::class, 'owns_course')]
#[CoversMethod(\local_marketplace\company::class, 'for_course')]
final class company_course_test extends \advanced_testcase {
    /** @var company */
    protected $company;

    /**
     * Empresa de teste.
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
            'shortname' => 'teste' . random_int(1000, 9999),
            'cnpj' => null,
            'themename' => null,
            'hostname' => null,
        ], (int) $owner->id);
    }

    /**
     * Curso direto na categoria da empresa e reconhecido.
     *
     * @return void
     */
    public function test_curso_na_propria_categoria(): void {
        $course = $this->getDataGenerator()->create_course([
            'category' => $this->company->get('categoryid'),
        ]);

        $this->assertTrue($this->company->owns_course((int) $course->id));
        $this->assertSame(
            (int) $this->company->get('id'),
            (int) company::for_course((int) $course->id)->get('id')
        );
    }

    /**
     * Curso numa SUBCATEGORIA da empresa tambem e reconhecido.
     *
     * Antes da correcao, owns_course() so comparava por igualdade exata e
     * devolvia falso aqui - o curso ficava fora do alcance de qualquer
     * oferta e de qualquer restricao de disponibilidade da propria empresa.
     *
     * @return void
     */
    public function test_curso_em_subcategoria_da_empresa(): void {
        global $DB;

        $sub = \core_course_category::create([
            'name' => 'Turma 2026',
            'parent' => (int) $this->company->get('categoryid'),
        ]);
        $course = $this->getDataGenerator()->create_course(['category' => $sub->id]);

        $this->assertTrue($this->company->owns_course((int) $course->id));
        $this->assertSame(
            (int) $this->company->get('id'),
            (int) company::for_course((int) $course->id)->get('id')
        );
    }

    /**
     * Curso de outra categoria, sem relacao nenhuma, nao e reconhecido.
     *
     * @return void
     */
    public function test_curso_fora_da_empresa_nao_e_reconhecido(): void {
        $outracategoria = \core_course_category::create(['name' => 'Sem dono']);
        $course = $this->getDataGenerator()->create_course(['category' => $outracategoria->id]);

        $this->assertFalse($this->company->owns_course((int) $course->id));
        $this->assertNull(company::for_course((int) $course->id));
    }
}
