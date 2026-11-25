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
 * Tarefa de restauracao do mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/bunnystream/backup/moodle2/restore_bunnystream_stepslib.php');

/**
 * Tarefa de restauracao do mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_bunnystream_activity_task extends restore_activity_task {
    /**
     * Sem settings proprias.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Define os passos da restauracao.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_bunnystream_activity_structure_step('bunnystream_structure', 'bunnystream.xml'));
    }

    /**
     * Conteudo a decodificar apos a restauracao.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('bunnystream', ['intro'], 'bunnystream')];
    }

    /**
     * Regras de decodificacao de links internos.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [new restore_decode_rule('BUNNYSTREAMVIEWBYID', '/mod/bunnystream/view.php?id=$1', 'course_module')];
    }
}
