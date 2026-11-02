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

namespace theme_ldg;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Todo editor_scss declarado precisa existir, em todo tema instalado.
 *
 * O theme_ldg e o theme_moove declaravam ['editor'] sem ter scss/editor.scss. O
 * aviso de file_get_contents ficou invisivel ate o MDL-90110 (5.2.4+) compilar o
 * CSS do editor na inicializacao do behat: ali ele reprovou dez plugins em
 * 02/11/2026. O defeito cabe em qualquer tema, entao a varredura e por todos, e
 * nao so pelo nosso.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class editor_scss_test extends \advanced_testcase {
    /**
     * Le o que cada tema declarou, sem compilar: compilar custa minutos por tema.
     *
     * @return void
     */
    public function test_todo_editor_scss_declarado_existe_no_tema(): void {
        $missing = [];
        foreach (\core_component::get_plugin_list('theme') as $name => $dir) {
            foreach (\core\output\theme_config::load($name)->editor_scss ?? [] as $file) {
                if (!is_file("{$dir}/scss/{$file}.scss")) {
                    $missing[] = "theme_{$name}: scss/{$file}.scss";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    /**
     * O que o core compila vem do Boost, por heranca, e nao de arquivo nosso.
     *
     * @return void
     */
    public function test_o_tema_herda_o_css_do_editor_do_boost(): void {
        $theme = \core\output\theme_config::load('ldg');

        $this->assertEmpty($theme->editor_scss);
        $this->assertSame(['editor'], \core\output\theme_config::load('boost')->editor_scss);
    }
}
