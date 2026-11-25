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

namespace format_ldg;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Nome da seção, com as três formas que o core usa para se referir a uma.
 *
 * O `get_default_section_name()` é público e o core o chama **direto**, sem
 * passar pelo `get_section_name()`. Em `course/editsection.php` ele recebe o
 * registro cru da tabela `course_sections` — que tem `section`, e não
 * `sectionnum`.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\format_ldg::class)]
final class section_name_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;

    /** @var \format_ldg */
    protected $format;

    /**
     * Curso com duas seções.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course([
            'format' => 'ldg',
            'numsections' => 2,
        ]);
        $this->format = course_get_format($this->course);
    }

    /**
     * O registro cru da tabela, que é o que o editsection.php entrega.
     *
     * @param int $num
     * @return \stdClass
     */
    protected function raw_record(int $num): \stdClass {
        global $DB;

        return $DB->get_record('course_sections', [
            'course' => $this->course->id,
            'section' => $num,
        ], '*', MUST_EXIST);
    }

    /**
     * Registro cru do banco não quebra.
     *
     * É a regressão: `stdClass` do `course_sections` tem `section`, e não
     * `sectionnum`. Ler a propriedade errada emitia "Undefined property" na
     * tela de editar seção — e o aviso, sendo saída, ainda derrubava o
     * `redirect()` seguinte, que virava um segundo erro sobre sessão mutada
     * depois de fechada.
     *
     * @return void
     */
    public function test_registro_cru_do_banco(): void {
        $this->assertSame(
            get_string('section0name', 'format_ldg'),
            $this->format->get_default_section_name($this->raw_record(0))
        );
        $this->assertSame(
            get_string('sectionname', 'format_ldg') . ' 1',
            $this->format->get_default_section_name($this->raw_record(1))
        );
    }

    /**
     * `section_info` continua funcionando.
     *
     * @return void
     */
    public function test_section_info(): void {
        $modinfo = $this->format->get_modinfo();

        $this->assertSame(
            get_string('section0name', 'format_ldg'),
            $this->format->get_default_section_name($modinfo->get_section_info(0))
        );
        $this->assertSame(
            get_string('sectionname', 'format_ldg') . ' 2',
            $this->format->get_default_section_name($modinfo->get_section_info(2))
        );
    }

    /**
     * O número puro também é aceito pela assinatura do core.
     *
     * @return void
     */
    public function test_numero_puro(): void {
        $this->assertSame(
            get_string('section0name', 'format_ldg'),
            $this->format->get_default_section_name(0)
        );
        $this->assertSame(
            get_string('sectionname', 'format_ldg') . ' 1',
            $this->format->get_default_section_name(1)
        );
    }

    /**
     * A seção 0 é a abertura do curso, e não "Módulo 0".
     *
     * @return void
     */
    public function test_secao_zero_tem_nome_proprio(): void {
        $zero = $this->format->get_default_section_name($this->raw_record(0));

        $this->assertStringNotContainsString('0', $zero);
        $this->assertNotSame(
            get_string('sectionname', 'format_ldg') . ' 0',
            $zero
        );
    }

    /**
     * Seção com nome próprio devolve o nome, e não o padrão.
     *
     * @return void
     */
    public function test_nome_proprio_vence_o_padrao(): void {
        global $DB;

        $record = $this->raw_record(1);
        $record->name = 'Fundamentos';
        $DB->update_record('course_sections', $record);
        rebuild_course_cache($this->course->id, true);

        $format = course_get_format($this->course);

        $this->assertSame('Fundamentos', $format->get_section_name($format->get_modinfo()->get_section_info(1)));
    }
}
