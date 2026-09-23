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
 * Testes da navegacao do portal.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

/**
 * Testes da navegacao do portal.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\portalnav::class)]
final class portalnav_test extends \advanced_testcase {
    /**
     * Curso com aula, material e forum - sem certificado.
     *
     * @return \stdClass
     */
    private function course(): \stdClass {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);

        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Aula um']);
        $generator->create_module('resource', ['course' => $course->id, 'section' => 1, 'name' => 'Apostila']);
        $generator->create_module('forum', ['course' => $course->id, 'section' => 1, 'name' => 'Duvidas']);

        return $course;
    }

    /**
     * Monta o nav para um pedido de destino.
     *
     * @param \stdClass $course
     * @param string $request
     * @return portalnav
     */
    private function nav(\stdClass $course, string $request): portalnav {
        $format = course_get_format($course);

        return new portalnav($format, new catalog($format), $request, $format->get_selected_cm());
    }

    /**
     * Sem pedido, o destino e a aula.
     *
     * @return void
     */
    public function test_padrao_e_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->course(), '')->current());
    }

    /**
     * Pedido desconhecido cai em aulas, sem erro.
     *
     * @return void
     */
    public function test_pedido_desconhecido_cai_em_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->course(), 'inventado')->current());
    }

    /**
     * Pedido de destino que o curso nao tem tambem cai em aulas.
     *
     * @return void
     */
    public function test_destino_sem_conteudo_cai_em_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->course(), catalog::CERTIFICADO)->current());
    }

    /**
     * Destino existente e respeitado.
     *
     * @return void
     */
    public function test_destino_existente_vale(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::MATERIAL, $this->nav($this->course(), catalog::MATERIAL)->current());
    }

    /**
     * O menu so lista o que existe, e marca o corrente.
     *
     * @return void
     */
    public function test_menu_so_tem_o_que_existe(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $destinations = $this->nav($this->course(), catalog::MATERIAL)->destinations();
        $keys = array_column($destinations, 'key');

        $this->assertSame([catalog::AULA, catalog::MATERIAL, catalog::FORUM], $keys);
        $this->assertNotContains(catalog::CERTIFICADO, $keys);

        foreach ($destinations as $destination) {
            $this->assertSame($destination['key'] === catalog::MATERIAL, $destination['active']);
            $this->assertStringContainsString('ldgview=' . $destination['key'], $destination['url']);
        }
    }

    /**
     * Trocar de destino nao pode perder a aula em foco.
     *
     * Sem isto, ir em Materiais e voltar para Aulas jogaria o aluno na primeira
     * aula do curso - e ele estava na decima.
     *
     * @return void
     */
    public function test_a_aula_em_foco_viaja_junto(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->course();
        $format = course_get_format($course);
        $lesson = $format->get_selected_cm();

        $this->assertNotNull($lesson);

        $destinations = (new portalnav($format, new catalog($format), catalog::MATERIAL, $lesson))->destinations();

        foreach ($destinations as $destination) {
            $this->assertStringContainsString('lesson=' . $lesson->id, $destination['url']);
        }
    }

    /**
     * Aba do forum some quando o unico item esta bloqueado.
     *
     * has() so olha o balde: mostraria a aba e levaria o aluno a um embed de
     * "acesso negado". Destino de item unico filtra por has_visible().
     *
     * @return void
     */
    public function test_forum_bloqueado_nao_vira_aba(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enableavailability = 1;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Aula um']);
        $generator->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Duvidas',
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ]);

        $this->setUser($student);
        $keys = array_column($this->nav($course, catalog::FORUM)->destinations(), 'key');

        $this->assertNotContains(catalog::FORUM, $keys);
        $this->assertContains(catalog::AULA, $keys);
    }

    /**
     * Aula e material nao somem por bloqueio item a item: tem lista propria
     * com cadeado. So forum e certificado filtram por visibilidade.
     *
     * @return void
     */
    public function test_abas_de_lista_nao_filtram_por_item(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enableavailability = 1;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Aula um',
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ]);
        $generator->create_module('resource', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Apostila',
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ]);

        $this->setUser($student);
        $keys = array_column($this->nav($course, '')->destinations(), 'key');

        $this->assertContains(catalog::AULA, $keys);
        $this->assertContains(catalog::MATERIAL, $keys);
    }
}
