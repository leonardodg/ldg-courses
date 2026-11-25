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
 * Tarefa de restore da atividade de video.
 *
 * @package   mod_ldgvideo
 * @category  backup
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @author     LeoDG <callme@leodg.dev>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ldgvideo/backup/moodle2/restore_ldgvideo_stepslib.php');

/**
 * Traz a atividade de video de volta: ajustes, passos e regras de link.
 */
class restore_ldgvideo_activity_task extends restore_activity_task {
    /**
     * Ajustes proprios da atividade no restore.
     */
    protected function define_my_settings() {
        // Nao ha ajuste proprio nesta atividade.
    }

    /**
     * Os passos do restore desta atividade.
     */
    protected function define_my_steps() {
        // So ha uma estrutura para trazer de volta.
        $this->add_step(new restore_ldgvideo_activity_structure_step('ldgvideo_structure', 'ldgvideo.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = [];

        // So a descricao tem link para decodificar. A area 'content' do
        // mod_page nao existe aqui: o video mora fora da plataforma.
        $contents[] = new restore_decode_content('ldgvideo', ['intro'], 'ldgvideo');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('LDGVIDEOVIEWBYID', '/mod/ldgvideo/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('LDGVIDEOINDEX', '/mod/ldgvideo/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * os logs da atividade. Devolve um array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('ldgvideo', 'add', 'view.php?id={course_module}', '{ldgvideo}');
        $rules[] = new restore_log_rule('ldgvideo', 'update', 'view.php?id={course_module}', '{ldgvideo}');
        $rules[] = new restore_log_rule('ldgvideo', 'view', 'view.php?id={course_module}', '{ldgvideo}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('ldgvideo', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
