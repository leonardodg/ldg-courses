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
 * Paridade de section_progress com o cmsummary do core.
 *
 * @package    format_ldg
 * @category   test
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

use completion_info;
use core_courseformat\output\local\content\section\cmsummary;

/**
 * Paridade de section_progress com o cmsummary do core.
 *
 * section_progress e COPIA de cmsummary::calculate_section_stats(), que e
 * protected. Se o Moodle mudar a regra de contagem, nada quebra por la - a
 * nossa conta so passa a divergir em silencio. Este teste e o unico capaz de
 * pegar a divergencia: compara os DOIS lados na MESMA secao, para o MESMO
 * usuario.
 *
 * Falhar aqui depois de um upgrade e sinal de que a regra mudou no core, e nao
 * de que o teste esta ruim.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\section_progress::class)]
final class section_progress_core_parity_test extends \advanced_testcase {
    /**
     * Curso com conclusao ligada e um aluno matriculado.
     *
     * @return array [curso, aluno]
     */
    private function make_course(): array {
        global $CFG;

        $CFG->enablecompletion = 1;
        $CFG->enableavailability = 1;

        $generator = $this->getDataGenerator();
        $curso = $generator->create_course(['format' => 'ldg', 'enablecompletion' => 1, 'numsections' => 3]);
        $aluno = $generator->create_user();
        $generator->enrol_user($aluno->id, $curso->id);

        return [$curso, $aluno];
    }

    /**
     * Page com conclusao manual.
     *
     * @param \stdClass $curso
     * @param int $section
     * @param array $extra
     * @return \stdClass
     */
    private function make_manual(\stdClass $curso, int $section, array $extra = []): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_page')->create_instance([
            'course' => $curso->id,
            'section' => $section,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ] + $extra);
    }

    /**
     * complete/total/percentage/showcompletion batem com o core.
     *
     * @param \stdClass $curso
     * @param \stdClass $aluno
     * @param int $sectionnum
     * @return void
     */
    private function assert_parity(\stdClass $curso, \stdClass $aluno, int $sectionnum): void {
        $format = course_get_format($curso);
        $modinfo = $format->get_modinfo();
        $section = $modinfo->get_section_info($sectionnum);
        $completion = new completion_info($format->get_course());

        $nosso = section_progress::for_section($section, $modinfo, $completion);

        $resumo = new cmsummary($format, $section);
        $metodo = new \ReflectionMethod($resumo, 'calculate_section_stats');
        $metodo->setAccessible(true);
        [, $corecomplete, $coretotal, $showcompletion] = $metodo->invoke($resumo);

        $this->assertSame($coretotal, $nosso->total, 'Total divergiu do cmsummary do core.');
        $this->assertSame($corecomplete, $nosso->complete, 'Concluidas divergiram do cmsummary do core.');
        $this->assertSame(
            $showcompletion,
            $nosso->has_tracking(),
            'has_tracking() divergiu do showcompletion do core.'
        );
    }

    /**
     * Secao mista (com e sem conclusao) bate com o core.
     *
     * @return void
     */
    public function test_parity_secao_mista(): void {
        $this->resetAfterTest();

        [$curso, $aluno] = $this->make_course();
        $a = $this->make_manual($curso, 1);
        $this->make_manual($curso, 1);
        $this->getDataGenerator()->get_plugin_generator('mod_page')->create_instance([
            'course' => $curso->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        $this->setUser($aluno);
        $completion = new completion_info(get_course($curso->id));
        $modinfo = get_fast_modinfo($curso, $aluno->id);
        $completion->update_state($modinfo->get_cm($a->cmid), COMPLETION_COMPLETE, $aluno->id);

        $this->assert_parity($curso, $aluno, 1);
    }

    /**
     * Aula bloqueada fora da conta: os DOIS lados tiram do denominador.
     *
     * @return void
     */
    public function test_parity_com_aula_bloqueada(): void {
        $this->resetAfterTest();

        [$curso, $aluno] = $this->make_course();
        $visivel = $this->make_manual($curso, 1);
        $this->make_manual($curso, 1, [
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ]);

        $this->setUser($aluno);
        $completion = new completion_info(get_course($curso->id));
        $modinfo = get_fast_modinfo($curso, $aluno->id);
        $completion->update_state($modinfo->get_cm($visivel->cmid), COMPLETION_COMPLETE, $aluno->id);

        $this->assert_parity($curso, $aluno, 1);
    }

    /**
     * Secao sem conclusao nenhuma: os dois lados dizem que nao ha progresso.
     *
     * @return void
     */
    public function test_parity_sem_conclusao(): void {
        $this->resetAfterTest();

        [$curso, $aluno] = $this->make_course();
        $this->getDataGenerator()->get_plugin_generator('mod_page')->create_instance([
            'course' => $curso->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        $this->setUser($aluno);
        $this->assert_parity($curso, $aluno, 1);

        $format = course_get_format($curso);
        $modinfo = $format->get_modinfo();
        $progresso = section_progress::for_section(
            $modinfo->get_section_info(1),
            $modinfo,
            new completion_info($format->get_course())
        );
        $this->assertFalse($progresso->has_tracking());
    }

    /**
     * Visitante: os dois lados nao mostram progresso.
     *
     * @return void
     */
    public function test_parity_visitante(): void {
        $this->resetAfterTest();

        [$curso, $aluno] = $this->make_course();
        $this->make_manual($curso, 1);

        // O core monta o cmsummary como o visitante (sem conclusao util).
        $this->setGuestUser();
        $this->assert_parity($curso, $aluno, 1);
    }

    /**
     * Secao 0 (abertura) e secoes vazias tambem batem.
     *
     * @return void
     */
    public function test_parity_secoes_vazias(): void {
        $this->resetAfterTest();

        [$curso, $aluno] = $this->make_course();
        $this->setUser($aluno);

        foreach ([0, 2, 3] as $num) {
            $this->assert_parity($curso, $aluno, $num);
        }
    }
}
