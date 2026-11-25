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
 * Testes de leitura de duracao na lista de aulas.
 *
 * @package    format_ldg
 * @category   test
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\output\courseformat\content;

use format_ldg\lesson;

/**
 * Testes de leitura de duracao na lista de aulas.
 *
 * durations_for() e UMA consulta por pagina, com todos os cmids. Chamar por
 * secao reintroduz o N+1 que ja mordeu este projeto - o docblock do metodo
 * afirma o contrato, e este teste e o que o cobra.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\lesson::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\output\courseformat\content\lessonlist::class)]
final class lessonlist_durations_test extends \advanced_testcase {
    /**
     * Curso com aulas em tres secoes e duracao gravada em cada uma.
     *
     * @return array [curso, cmids por secao]
     */
    private function course_with_durations(): array {
        global $CFG;

        $CFG->enablecompletion = 1;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg', 'numsections' => 3, 'enablecompletion' => 1]);

        $bysection = [];
        $duration = 100;

        for ($section = 1; $section <= 3; $section++) {
            $page = $generator->create_module('page', [
                'course' => $course->id,
                'section' => $section,
                'name' => 'Aula ' . $section,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
            lesson::store_duration($page->cmid, $duration);
            $bysection[$section] = $page->cmid;
            $duration += 100;
        }

        return [$course, $bysection];
    }

    /**
     * export_for_template() consulta format_ldg_lesson UMA vez, com tudo.
     *
     * Contagem por leituras do banco no intervalo da exportacao: com tres
     * secoes, o caminho por secao fariam tres consultas de duracao; o caminho
     * correto faz uma. Qualquer outra leitura na mesma janela so ENCOLHE a
     * folga - nunca a infla - entao o teto de 1 e estreito de proposito.
     *
     * @return void
     */
    public function test_duracoes_vem_de_uma_consulta_por_pagina(): void {
        global $CFG, $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $bysection] = $this->course_with_durations();

        $format = course_get_format($course);
        $list = new lessonlist($format, $format->get_selected_cm());

        // Aquece caches de estrutura que NAO sao da duracao (campos de tabela,
        // modinfo ja construida): a contagem comeca depois disso.
        $reflection = new \ReflectionMethod($list, 'export_for_template');
        $reflection->invoke($list, $PAGE->get_renderer('core'));

        $before = $DB->perf_get_reads();
        $data = $list->export_for_template($PAGE->get_renderer('core'));
        $reads = $DB->perf_get_reads() - $before;

        $this->assertLessThanOrEqual(
            1,
            $reads,
            'A exportacao inteira (tres secoes) nao pode gastar mais de uma leitura - durations_for() e uma por pagina.'
        );

        $durations = [];
        foreach ($data->modules as $module) {
            foreach ($module->lessons as $lesson) {
                $this->assertTrue($lesson->hasduration, 'Aula ' . $lesson->name . ' ficou sem duracao.');
                $durations[$lesson->cmid] = $lesson->duration;
            }
        }

        $this->assertCount(3, $durations);
        $this->assertSame(100, $durations[$bysection[1]]);
        $this->assertSame(200, $durations[$bysection[2]]);
        $this->assertSame(300, $durations[$bysection[3]]);
    }

    /**
     * As tres secoes aparecem, cada uma com a sua aula e duracao.
     *
     * @return void
     */
    public function test_duracoes_por_secao_na_mesma_lista(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $bysection] = $this->course_with_durations();

        $format = course_get_format($course);
        $data = (new lessonlist($format, null))->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(3, $data->modules);

        $modulenum = array_column($data->modules, 'num');
        $this->assertSame([1, 2, 3], $modulenum);

        foreach ($data->modules as $i => $module) {
            $section = $i + 1;
            $lesson = $module->lessons[0];
            $this->assertSame((int) $bysection[$section], (int) $lesson->cmid);
            $this->assertTrue($lesson->hasduration);
        }
    }
}
