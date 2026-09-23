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
    private function curso_com_duracoes(): array {
        global $CFG;

        $CFG->enablecompletion = 1;

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg', 'numsections' => 3, 'enablecompletion' => 1]);

        $porsecao = [];
        $duracao = 100;

        for ($secao = 1; $secao <= 3; $secao++) {
            $page = $gerador->create_module('page', [
                'course' => $curso->id,
                'section' => $secao,
                'name' => 'Aula ' . $secao,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
            lesson::store_duration($page->cmid, $duracao);
            $porsecao[$secao] = $page->cmid;
            $duracao += 100;
        }

        return [$curso, $porsecao];
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

        [$curso, $porsecao] = $this->curso_com_duracoes();

        $format = course_get_format($curso);
        $lista = new lessonlist($format, $format->get_selected_cm());

        // Aquece caches de estrutura que NAO sao da duracao (campos de tabela,
        // modinfo ja construida): a contagem comeca depois disso.
        $reflexo = new \ReflectionMethod($lista, 'export_for_template');
        $reflexo->invoke($lista, $PAGE->get_renderer('core'));

        $antes = $DB->perf_get_reads();
        $dados = $lista->export_for_template($PAGE->get_renderer('core'));
        $consultas = $DB->perf_get_reads() - $antes;

        $this->assertLessThanOrEqual(
            1,
            $consultas,
            'A exportacao inteira (tres secoes) nao pode gastar mais de uma leitura - durations_for() e uma por pagina.'
        );

        $duracoes = [];
        foreach ($dados->modules as $modulo) {
            foreach ($modulo->lessons as $aula) {
                $this->assertTrue($aula->hasduration, 'Aula ' . $aula->name . ' ficou sem duracao.');
                $duracoes[$aula->cmid] = $aula->duration;
            }
        }

        $this->assertCount(3, $duracoes);
        $this->assertSame(100, $duracoes[$porsecao[1]]);
        $this->assertSame(200, $duracoes[$porsecao[2]]);
        $this->assertSame(300, $duracoes[$porsecao[3]]);
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

        [$curso, $porsecao] = $this->curso_com_duracoes();

        $format = course_get_format($curso);
        $dados = (new lessonlist($format, null))->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(3, $dados->modules);

        $modulonum = array_column($dados->modules, 'num');
        $this->assertSame([1, 2, 3], $modulonum);

        foreach ($dados->modules as $i => $modulo) {
            $secao = $i + 1;
            $aula = $modulo->lessons[0];
            $this->assertSame((int) $porsecao[$secao], (int) $aula->cmid);
            $this->assertTrue($aula->hasduration);
        }
    }
}
