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
 * Testes da barra de navegacao entre aulas.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\output\courseformat\content;

use format_ldg\catalog;

/**
 * Testes da barra de navegacao entre aulas.
 *
 * E o atalho que sobra quando as laterais estao escondidas, entao ele nao pode
 * mentir: botao que leva a lugar nenhum na ponta, ou contagem que inclui
 * material, seriam pior que nao ter barra.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\output\courseformat\content\lessonnav::class)]
final class lessonnav_test extends \advanced_testcase {
    /**
     * Monta um curso com tres aulas e devolve o nav de uma delas.
     *
     * @param int $which Indice da aula em foco, comecando em zero.
     * @return \stdClass
     */
    private function lesson_nav(int $which): \stdClass {
        global $PAGE;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg', 'numsections' => 2]);

        $cms = [];
        $cms[] = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Aula um']);
        $cms[] = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Aula dois']);
        $cms[] = $generator->create_module('page', ['course' => $course->id, 'section' => 2, 'name' => 'Aula tres']);

        $format = course_get_format($course);
        $focus = $format->get_modinfo()->get_cm($cms[$which]->cmid);
        $nav = new lessonnav($format, new catalog($format), $focus);

        return $nav->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * No meio ha anterior e proxima, e a posicao conta o curso inteiro.
     *
     * @return void
     */
    public function test_no_meio_tem_os_dois_lados(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $d = $this->lesson_nav(1);

        $this->assertTrue($d->hasprev);
        $this->assertSame('Aula um', $d->prev->name);
        $this->assertTrue($d->hasnext);
        $this->assertSame('Aula tres', $d->next->name);
        $this->assertSame(2, $d->position->index);
        $this->assertSame(3, $d->position->total);
    }

    /**
     * A primeira nao tem anterior; a ultima nao tem proxima.
     *
     * Sem isto a barra mostraria um botao que leva a lugar nenhum - e ela e o
     * atalho que sobra quando as laterais estao escondidas.
     *
     * @return void
     */
    public function test_pontas_nao_inventam_vizinho(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $first = $this->lesson_nav(0);
        $last = $this->lesson_nav(2);

        $this->assertFalse($first->hasprev);
        $this->assertTrue($first->hasnext);
        $this->assertTrue($last->hasprev);
        $this->assertFalse($last->hasnext);
    }

    /**
     * Material e forum nao entram na sequencia: a barra e de AULAS.
     *
     * @return void
     */
    public function test_so_aula_entra_na_sequencia(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);
        $a = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Aula um']);
        $generator->create_module('resource', ['course' => $course->id, 'section' => 1, 'name' => 'Apostila']);
        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Aula dois']);

        $format = course_get_format($course);
        $focus = $format->get_modinfo()->get_cm($a->cmid);
        $nav = new lessonnav($format, new catalog($format), $focus);
        $d = $nav->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame('Aula dois', $d->next->name);
        $this->assertSame(2, $d->position->total);
    }

    /**
     * Sem aula em foco - curso sem aula nenhuma - a barra nao aparece.
     *
     * @return void
     */
    public function test_sem_aula_nao_ha_barra(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $format = course_get_format($course);
        $nav = new lessonnav($format, new catalog($format), null);
        $d = $nav->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($d->hasnav);
    }

    /**
     * O nome do modulo da aula em foco vai junto, para o aluno se situar.
     *
     * @return void
     */
    public function test_diz_em_qual_modulo_esta(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $d = $this->lesson_nav(2);

        $this->assertNotEmpty($d->position->module);
    }
}
