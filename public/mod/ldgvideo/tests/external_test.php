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

namespace mod_ldgvideo;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A listagem da WS nao passa por cm que a pessoa nao pode ver.
 *
 * @package    mod_ldgvideo
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_ldgvideo_external::class)]
final class external_test extends \advanced_testcase {
    /**
     * Quem nao tem a capability nao leva o item.
     *
     * Sob o core atual, get_all_instances_in_courses ja filtra por
     * uservisible - e o uservisible e calculado com EXATAMENTE esta capability
     * (cm_info::is_user_access_restricted_by_capability). O item entao nem
     * chega ao loop com aviso. A checagem explicita em external.php e defesa
     * em profundidade para o dia em que as duas regras divergiram; o que este
     * teste prova e a propriedade de seguranca: sem capability, sem dado.
     *
     * @return void
     */
    public function test_sem_capability_nao_devolve_o_video(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('ldgvideo', [
            'course' => $course->id,
            'name' => 'Aula 1',
            'videourl' => 'https://vimeo.com/226053498',
        ]);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Proibe no CONTEXTO DO CM: CAP_PROHIBIT local vence o allow herdado
        // do papel em nivel de curso/system.
        $roles = get_user_roles(\context_course::instance($course->id), $student->id, true);
        $this->assertNotEmpty($roles);
        $role = array_values($roles)[0];
        assign_capability(
            'mod/ldgvideo:view',
            CAP_PROHIBIT,
            $role->roleid,
            \context_module::instance($cm->cmid)->id,
            true
        );

        $this->setUser($student);

        $this->assertFalse(has_capability(
            'mod/ldgvideo:view',
            \context_module::instance($cm->cmid),
            $student
        ));

        $result = \mod_ldgvideo_external::get_ldgvideos_by_courses([$course->id]);

        $this->assertSame([], $result['ldgvideos']);
        // Nenhum dos dois: nem o video, nem um aviso a mais vindo do core
        // (util::validate_courses nao tem o que avisar aqui).
        $this->assertSame([], $result['warnings']);
    }

    /**
     * Com a capability, o video volta normalmente, sem aviso.
     *
     * @return void
     */
    public function test_com_capability_devolve_o_video(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('ldgvideo', [
            'course' => $course->id,
            'name' => 'Aula 1',
            'videourl' => 'https://vimeo.com/226053498',
        ]);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $result = \mod_ldgvideo_external::get_ldgvideos_by_courses([$course->id]);

        $this->assertCount(1, $result['ldgvideos']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame('https://vimeo.com/226053498', $result['ldgvideos'][0]->videourl);
    }
}
