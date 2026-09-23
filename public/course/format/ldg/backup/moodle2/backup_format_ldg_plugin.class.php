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
 * Backup do format_ldg.
 *
 * @package    format_ldg
 * @category   backup
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Leva a duracao das aulas junto com o curso.
 *
 * Esta classe so passou a existir quando a tabela passou a existir. Antes dela,
 * um backup/restore perdia as duracoes EM SILENCIO: o curso voltava inteiro, e
 * so quem soubesse que havia duracao notaria a falta.
 *
 * A duracao e por course module, entao o ponto de conexao e o de MODULO, e nao
 * o de secao ou o de curso. Isso resolve de graca o caso de restaurar so
 * algumas atividades: vem a duracao das que vieram, e nada mais.
 *
 * @package    format_ldg
 * @category   backup
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_format_ldg_plugin extends backup_format_plugin {
    /**
     * Nada a guardar no nivel do curso.
     *
     * As opcoes de formato - hiddensections e coursedisplay - o Moodle ja leva
     * sozinho, por course_format_options.
     *
     * @return backup_plugin_element|null
     */
    protected function define_course_plugin_structure() {
        return null;
    }

    /**
     * A duracao de cada aula.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element(null, $this->get_format_condition(), 'ldg');

        $wrapper = new backup_nested_element($this->get_recommended_name());

        $lesson = new backup_nested_element('lesson', ['id'], ['duration']);

        $plugin->add_child($wrapper);
        $wrapper->add_child($lesson);

        $lesson->set_source_table('format_ldg_lesson', ['cmid' => backup::VAR_MODID]);

        return $plugin;
    }
}
