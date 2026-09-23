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
 * Restore do format_ldg.
 *
 * @package    format_ldg
 * @category   backup
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Traz de volta a duracao das aulas.
 *
 * O cmid NAO vem do arquivo. Ele e sempre o do curso NOVO, tirado da tarefa de
 * restauracao - restaurar cria modulos com ids proprios, e gravar o id antigo
 * apontaria a duracao para a atividade errada, ou para nenhuma. Esse e o erro
 * classico de restore, e ele nao da mensagem: os numeros so aparecem trocados.
 *
 * @package    format_ldg
 * @category   backup
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_format_ldg_plugin extends restore_format_plugin {
    /**
     * O que ler de dentro de cada modulo.
     *
     * @return restore_path_element[]
     */
    public function define_module_plugin_structure() {
        return [
            new restore_path_element('format_ldg_lesson', $this->get_pathfor('/lesson')),
        ];
    }

    /**
     * Grava a duracao de uma aula restaurada.
     *
     * @param array|stdClass $data
     * @return void
     */
    public function process_format_ldg_lesson($data) {
        $data = (object) $data;

        $cmid = $this->task->get_moduleid();

        if (empty($cmid)) {
            return;
        }

        $duration = isset($data->duration) ? (int) $data->duration : 0;

        \format_ldg\lesson::store_duration($cmid, $duration > 0 ? $duration : null);
    }
}
