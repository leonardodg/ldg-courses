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
 * Passo de estrutura da restauracao do mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restaura a atividade a partir do XML de backup.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_bunnystream_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define os caminhos do XML a processar.
     *
     * @return \restore_path_element[]
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('bunnystream', '/activity/bunnystream');
        if ($userinfo) {
            $paths[] = new restore_path_element('bunnystream_progress', '/activity/bunnystream/progresses/progress');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Processa a linha da atividade.
     *
     * @param array $data
     * @return void
     */
    protected function process_bunnystream($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timecreated = time();
        $data->timemodified = time();
        $newid = $DB->insert_record('bunnystream', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Processa uma linha de progresso.
     *
     * @param array $data
     * @return void
     */
    protected function process_bunnystream_progress($data) {
        global $DB;
        $data = (object) $data;
        $data->bunnystreamid = $this->get_new_parentid('bunnystream');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->userid) {
            $DB->insert_record('bunnystream_progress', $data);
        }
    }

    /**
     * Executa apos a restauracao da estrutura.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_bunnystream', 'intro', null);
    }
}
