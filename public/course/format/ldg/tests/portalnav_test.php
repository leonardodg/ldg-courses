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
    private function curso(): \stdClass {
        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);

        $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Aula um']);
        $gerador->create_module('resource', ['course' => $curso->id, 'section' => 1, 'name' => 'Apostila']);
        $gerador->create_module('forum', ['course' => $curso->id, 'section' => 1, 'name' => 'Duvidas']);

        return $curso;
    }

    /**
     * Monta o nav para um pedido de destino.
     *
     * @param \stdClass $curso
     * @param string $pedido
     * @return portalnav
     */
    private function nav(\stdClass $curso, string $pedido): portalnav {
        $format = course_get_format($curso);

        return new portalnav($format, new catalog($format), $pedido, $format->get_selected_cm());
    }

    /**
     * Sem pedido, o destino e a aula.
     *
     * @return void
     */
    public function test_padrao_e_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->curso(), '')->current());
    }

    /**
     * Pedido desconhecido cai em aulas, sem erro.
     *
     * @return void
     */
    public function test_pedido_desconhecido_cai_em_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->curso(), 'inventado')->current());
    }

    /**
     * Pedido de destino que o curso nao tem tambem cai em aulas.
     *
     * @return void
     */
    public function test_destino_sem_conteudo_cai_em_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->curso(), catalog::CERTIFICADO)->current());
    }

    /**
     * Destino existente e respeitado.
     *
     * @return void
     */
    public function test_destino_existente_vale(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::MATERIAL, $this->nav($this->curso(), catalog::MATERIAL)->current());
    }

    /**
     * O menu so lista o que existe, e marca o corrente.
     *
     * @return void
     */
    public function test_menu_so_tem_o_que_existe(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $destinos = $this->nav($this->curso(), catalog::MATERIAL)->destinations();
        $chaves = array_column($destinos, 'key');

        $this->assertSame([catalog::AULA, catalog::MATERIAL, catalog::FORUM], $chaves);
        $this->assertNotContains(catalog::CERTIFICADO, $chaves);

        foreach ($destinos as $destino) {
            $this->assertSame($destino['key'] === catalog::MATERIAL, $destino['active']);
            $this->assertStringContainsString('ldgview=' . $destino['key'], $destino['url']);
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

        $curso = $this->curso();
        $format = course_get_format($curso);
        $aula = $format->get_selected_cm();

        $this->assertNotNull($aula);

        $destinos = (new portalnav($format, new catalog($format), catalog::MATERIAL, $aula))->destinations();

        foreach ($destinos as $destino) {
            $this->assertStringContainsString('lesson=' . $aula->id, $destino['url']);
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

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);
        $aluno = $gerador->create_user();
        $gerador->enrol_user($aluno->id, $curso->id, 'student');

        $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Aula um']);
        $gerador->create_module('forum', [
            'course' => $curso->id,
            'section' => 1,
            'name' => 'Duvidas',
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ]);

        $this->setUser($aluno);
        $chaves = array_column($this->nav($curso, catalog::FORUM)->destinations(), 'key');

        $this->assertNotContains(catalog::FORUM, $chaves);
        $this->assertContains(catalog::AULA, $chaves);
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

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);
        $aluno = $gerador->create_user();
        $gerador->enrol_user($aluno->id, $curso->id, 'student');

        $gerador->create_module('page', [
            'course' => $curso->id,
            'section' => 1,
            'name' => 'Aula um',
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ]);
        $gerador->create_module('resource', [
            'course' => $curso->id,
            'section' => 1,
            'name' => 'Apostila',
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ]);

        $this->setUser($aluno);
        $chaves = array_column($this->nav($curso, '')->destinations(), 'key');

        $this->assertContains(catalog::AULA, $chaves);
        $this->assertContains(catalog::MATERIAL, $chaves);
    }
}
