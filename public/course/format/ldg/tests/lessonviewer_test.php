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
 * Testes do quadro da aula em foco.
 *
 * @package    format_ldg
 * @category   test
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\output\courseformat\content;

/**
 * Testes do quadro da aula em foco.
 *
 * Destino Forum/Certificado embute o primeiro item do balde sem lista propria
 * que mostre cadeado por item. Sem o cadeado aqui, o aluno via a pagina crua de
 * "acesso negado" que o modulo embutido desenha dentro do quadro.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\output\courseformat\content\lessonviewer::class)]
final class lessonviewer_test extends \advanced_testcase {
    /**
     * Monta um curso com uma aula bloqueada por data futura.
     *
     * @param array $extra Campos extras da aula bloqueada.
     * @return array [curso, aula bloqueada, aluno]
     */
    private function course_with_locked(array $extra = []): array {
        global $CFG;

        $CFG->enableavailability = 1;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $locked = $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Aula trancada',
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ] + $extra);

        return [$course, $locked, $student];
    }

    /**
     * Aula bloqueada sai com cadeado e sem quadro.
     *
     * @return void
     */
    public function test_bloqueada_mostra_cadeado_e_nao_embuti(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$course, $locked, $student] = $this->course_with_locked();
        $this->setUser($student);

        $format = course_get_format($course);
        $cm = $format->get_modinfo()->get_cm($locked->cmid);
        $viewer = new lessonviewer($format, $cm);
        $data = $viewer->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data->haslesson);
        $this->assertTrue($data->locked);
        $this->assertFalse($data->hasframe, 'Conteudo bloqueado nao pode ir para o iframe.');
        $this->assertNotEmpty($data->lockinfo, 'O cadeado diz POR QUE esta fechado.');
        $this->assertArrayNotHasKey('frameurl', (array) $data);
    }

    /**
     * Aula liberada embute o quadro normalmente.
     *
     * @return void
     */
    public function test_liberada_embuti_o_quadro(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$course, , $student] = $this->course_with_locked();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $free = $generator->create_module('page', [
            'course' => $course->id, 'section' => 1, 'name' => 'Aula livre',
        ]);

        // Admin ve o bloqueado tambem; o que importa e a aula livre.
        $format = course_get_format($course);
        $cm = $format->get_modinfo()->get_cm($free->cmid);
        $data = (new lessonviewer($format, $cm))->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data->haslesson);
        $this->assertFalse($data->locked);
        $this->assertTrue($data->hasframe);
        $this->assertStringContainsString('ldgembed=1', $data->frameurl);
    }

    /**
     * Sem aula em foco, o template nao pede cadeado nem quadro.
     *
     * @return void
     */
    public function test_sem_aula_so_diz_que_nao_ha(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $format = course_get_format($course);
        $data = (new lessonviewer($format, null))->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data->haslesson);
    }
}
