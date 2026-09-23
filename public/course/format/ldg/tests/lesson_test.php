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
 * Testes da duracao das aulas.
 *
 * @package    format_ldg
 * @category   test
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

/**
 * Testes da duracao das aulas.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\lesson::class)]
final class lesson_test extends \advanced_testcase {
    /**
     * Grava e le de volta.
     *
     * @return void
     */
    public function test_grava_e_le(): void {
        $this->resetAfterTest();

        lesson::store_duration(42, 750);

        $this->assertSame(750, lesson::duration_for(42));
    }

    /**
     * Gravar de novo atualiza em vez de duplicar.
     *
     * O indice unico em cmid ja impediria a segunda linha, mas com um erro de
     * banco na cara do professor. Este teste garante que o caminho e a
     * atualizacao.
     *
     * @return void
     */
    public function test_gravar_de_novo_atualiza(): void {
        global $DB;

        $this->resetAfterTest();

        lesson::store_duration(42, 750);
        lesson::store_duration(42, 900);

        $this->assertSame(900, lesson::duration_for(42));
        $this->assertSame(1, $DB->count_records('format_ldg_lesson', ['cmid' => 42]));
    }

    /**
     * Gravar nulo APAGA a linha.
     *
     * Sem duracao conhecida nao ha o que guardar, e linha vazia so atrapalha
     * quem for ler a tabela.
     *
     * @return void
     */
    public function test_nulo_apaga(): void {
        global $DB;

        $this->resetAfterTest();

        lesson::store_duration(42, 750);
        lesson::store_duration(42, null);

        $this->assertNull(lesson::duration_for(42));
        $this->assertSame(0, $DB->count_records('format_ldg_lesson', ['cmid' => 42]));
    }

    /**
     * Aula sem duracao devolve nulo, e nao zero.
     *
     * A diferenca chega ate a tela: nulo nao mostra nada, zero mostraria uma
     * aula de duracao nenhuma.
     *
     * @return void
     */
    public function test_sem_duracao_devolve_nulo(): void {
        $this->resetAfterTest();

        $this->assertNull(lesson::duration_for(999));
    }

    /**
     * A leitura em lote traz todas de uma vez.
     *
     * @return void
     */
    public function test_leitura_em_lote(): void {
        $this->resetAfterTest();

        lesson::store_duration(1, 100);
        lesson::store_duration(2, 200);

        $batch = lesson::durations_for([1, 2, 3]);

        $this->assertSame(100, $batch[1]);
        $this->assertSame(200, $batch[2]);
        $this->assertArrayNotHasKey(3, $batch, 'Aula sem duracao nao entra no resultado.');
    }

    /**
     * Lote vazio nao consulta o banco.
     *
     * @return void
     */
    public function test_lote_vazio(): void {
        $this->resetAfterTest();

        $this->assertSame([], lesson::durations_for([]));
    }

    /**
     * Duracao negativa e recusada.
     *
     * @return void
     */
    public function test_duracao_negativa_e_recusada(): void {
        $this->resetAfterTest();

        $record = new lesson(0, (object) ['cmid' => 7, 'duration' => -1]);

        $this->assertFalse($record->is_valid());
        $this->assertArrayHasKey('duration', $record->get_errors());
    }

    /**
     * Apagar o curso leva as duracoes junto.
     *
     * @return void
     */
    public function test_apagar_curso_limpa_as_duracoes(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $page = $this->getDataGenerator()->get_plugin_generator('mod_page')->create_instance([
            'course' => $course->id,
        ]);

        lesson::store_duration($page->cmid, 500);
        $this->assertSame(500, lesson::duration_for($page->cmid));

        lesson::delete_for_course($course->id);

        $this->assertSame(0, $DB->count_records('format_ldg_lesson'));
    }
}
