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
 * Passo de estrutura do backup do mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Estrutura de backup de uma atividade.
 *
 * Nao inclui local_marketplace_library de proposito: a chave da empresa nao
 * viaja no backup, e restaurar num curso de OUTRA empresa manteria o
 * library_id antigo - limitacao conhecida, igual a do plugin de referencia
 * (que tambem nao lida com troca de tenant na restauracao).
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_bunnystream_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define a estrutura do XML de backup.
     *
     * @return backup_activity_structure_step
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $activity = new backup_nested_element('bunnystream', ['id'], [
            'course', 'name', 'intro', 'introformat', 'guid', 'library_id', 'title',
            'duration_sec', 'thumbnail_url', 'status', 'video_style', 'completion_percent',
            'timecreated', 'timemodified',
        ]);

        $progresslist = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'userid', 'max_percent', 'timemodified',
        ]);

        $activity->add_child($progresslist);
        $progresslist->add_child($progress);

        $activity->set_source_table('bunnystream', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $progress->set_source_table('bunnystream_progress', ['bunnystreamid' => backup::VAR_PARENTID]);
            $progress->annotate_ids('user', 'userid');
        }

        return $this->prepare_activity_structure($activity);
    }
}
