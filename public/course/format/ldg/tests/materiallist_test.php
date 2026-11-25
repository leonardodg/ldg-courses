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
 * Testes da lista de materiais.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\output\courseformat\content;

use format_ldg\catalog;

/**
 * Testes da lista de materiais.
 *
 * O que estes testes protegem: material que BAIXA nao pode abrir no quadro
 * embutido. Dentro de um iframe o download dispara e a tela fica em branco - o
 * aluno acha que quebrou, e nenhum teste de servidor comum pegaria isso.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\output\courseformat\content\materiallist::class)]
final class materiallist_test extends \advanced_testcase {
    /**
     * Monta a lista de um curso com um material so.
     *
     * @param string $modname
     * @param array $extra
     * @return \stdClass O primeiro material exportado.
     */
    private function first_material(string $modname, array $extra = []): \stdClass {
        global $PAGE;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);

        $generator->create_module($modname, [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Material',
        ] + $extra);

        $format = course_get_format($course);
        $list = new materiallist($format, new catalog($format));
        $data = $list->export_for_template($PAGE->get_renderer('core'));

        $this->assertNotEmpty($data->materials);

        return reset($data->materials);
    }

    /**
     * Arquivo com download forcado vira link, e nao quadro embutido.
     *
     * @return void
     */
    public function test_download_forcado_nao_abre_no_quadro(): void {
        global $CFG;

        require_once($CFG->libdir . '/resourcelib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $material = $this->first_material('resource', ['display' => RESOURCELIB_DISPLAY_DOWNLOAD]);

        $this->assertTrue($material->isdownload);
        $this->assertFalse($material->inframe);
    }

    /**
     * Pasta abre no quadro, porque tem pagina propria.
     *
     * @return void
     */
    public function test_pasta_abre_no_quadro(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $material = $this->first_material('folder');

        $this->assertFalse($material->isdownload);
        $this->assertTrue($material->inframe);
    }

    /**
     * Material que pede janela nova sai do quadro.
     *
     * @return void
     */
    public function test_janela_nova_sai_do_quadro(): void {
        global $CFG;

        require_once($CFG->libdir . '/resourcelib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $material = $this->first_material('url', [
            'externalurl' => 'https://example.com/manual.pdf',
            'display' => RESOURCELIB_DISPLAY_NEW,
        ]);

        $this->assertTrue($material->opensnewwindow);
        $this->assertFalse($material->inframe);
    }

    /**
     * Link externo NAO e download, mesmo o core dizendo que e.
     *
     * O url_get_final_display_type() poe 'text/html' na lista de download, entao
     * qualquer link para pagina web resolve para DISPLAY_DOWNLOAD - e ali aquilo
     * significa "manda o navegador para a URL". Sem esta distincao, um link para
     * um site aparecia como "(baixa o arquivo)" e ganhava o atributo download,
     * que faria o navegador tentar salvar a pagina.
     *
     * @return void
     */
    public function test_link_externo_nao_e_download(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $material = $this->first_material('url', ['externalurl' => 'https://moodle.org/']);

        $this->assertFalse($material->isdownload);
        $this->assertTrue($material->opensnewwindow);
        $this->assertFalse($material->inframe);
    }

    /**
     * Os materiais vem agrupados pelo modulo a que pertencem.
     *
     * @return void
     */
    public function test_agrupa_por_modulo(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg', 'numsections' => 2]);

        $generator->create_module('folder', ['course' => $course->id, 'section' => 1, 'name' => 'Anexos um']);
        $generator->create_module('folder', ['course' => $course->id, 'section' => 2, 'name' => 'Anexos dois']);

        $format = course_get_format($course);
        $list = new materiallist($format, new catalog($format));
        $data = $list->export_for_template($PAGE->get_renderer('core'));

        $sections = array_column($data->materials, 'section');

        $this->assertCount(2, $data->materials);
        $this->assertNotSame($sections[0], $sections[1]);
    }
}
