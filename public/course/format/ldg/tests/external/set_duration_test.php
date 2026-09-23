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

/**
 * Testes do servico que grava a duracao.
 *
 * @package    format_ldg
 * @category   test
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\external;

use externallib_advanced_testcase;
use format_ldg\lesson;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Testes do servico que grava a duracao.
 *
 * O que mais importa aqui nao e o caminho feliz: e a recusa. O cmid vem do
 * cliente, e um servico que confie nele deixa qualquer pessoa escrever em curso
 * alheio.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\external\set_duration::class)]
final class set_duration_test extends externallib_advanced_testcase {
    /**
     * Professor grava a duracao.
     *
     * @return void
     */
    public function test_professor_grava(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $page = $this->getDataGenerator()->get_plugin_generator('mod_page')->create_instance([
            'course' => $course->id,
        ]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);

        $result = set_duration::execute($page->cmid, 750);

        $this->assertSame(750, $result['duration']);
        $this->assertSame(750, lesson::duration_for($page->cmid));
        $this->assertNotEmpty($result['formatted']);
    }

    /**
     * Zero limpa a duracao.
     *
     * @return void
     */
    public function test_zero_limpa(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $page = $this->getDataGenerator()->get_plugin_generator('mod_page')->create_instance([
            'course' => $course->id,
        ]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);

        set_duration::execute($page->cmid, 750);
        $result = set_duration::execute($page->cmid, 0);

        $this->assertSame(0, $result['duration']);
        $this->assertSame('', $result['formatted']);
        $this->assertNull(lesson::duration_for($page->cmid));
    }

    /**
     * Aluno NAO pode gravar.
     *
     * @return void
     */
    public function test_aluno_e_recusado(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $page = $this->getDataGenerator()->get_plugin_generator('mod_page')->create_instance([
            'course' => $course->id,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($student);

        // O aluno ESTA matriculado, entao ele passa pela validacao de contexto
        // e e barrado pela permissao - diferente do professor de outro curso,
        // que nem chega la.
        $this->expectException(\required_capability_exception::class);

        set_duration::execute($page->cmid, 750);
    }

    /**
     * Cmid que nao existe e recusado antes de qualquer escrita.
     *
     * @return void
     */
    public function test_cmid_inexistente(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);

        set_duration::execute(999999, 750);
    }

    /**
     * Professor de UM curso nao escreve no curso de OUTRO.
     *
     * Este e o teste que justifica o servico existir do jeito que existe. Sem a
     * validacao de contexto, ter permissao em qualquer lugar valeria como ter
     * permissao em todo lugar.
     *
     * A recusa vem do validate_context, e ANTES do require_capability - por
     * isso a excecao e de login, e nao de permissao. E a barreira mais forte
     * das duas: ele nem chega a ser perguntado sobre o que pode fazer.
     *
     * @return void
     */
    public function test_professor_de_outro_curso_e_recusado(): void {
        $this->resetAfterTest();

        $mine = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $other = $this->getDataGenerator()->create_course(['format' => 'ldg']);

        $page = $this->getDataGenerator()->get_plugin_generator('mod_page')->create_instance([
            'course' => $other->id,
        ]);

        $teacher = $this->getDataGenerator()->create_and_enrol($mine, 'editingteacher');

        $this->setUser($teacher);

        $this->expectException(\core\exception\require_login_exception::class);

        set_duration::execute($page->cmid, 750);
    }
}
