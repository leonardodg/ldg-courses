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
 * Passos do backup da atividade de video.
 *
 * @package   mod_ldgvideo
 * @category  backup
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @author     LeoDG <callme@leodg.dev>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Define all the backup steps that will be used by the backup_ldgvideo_activity_task
 */

/**
 * A estrutura do modulo para o backup.
 */
class backup_ldgvideo_activity_structure_step extends backup_activity_structure_step {
    /**
     * A arvore do backup.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // O videourl e o aspectratio SAO O BACKUP. Sem eles a atividade
        // restaurada abre sem video, e o professor nao tem como saber qual era.
        $video = new backup_nested_element('ldgvideo', ['id'], [
            'name', 'intro', 'introformat', 'videourl', 'aspectratio',
            'displayoptions', 'timemodified',
        ]);

        $video->set_source_table('ldgvideo', ['id' => backup::VAR_ACTIVITYID]);

        // So a descricao tem arquivo. A area 'content' do mod_page nao existe
        // aqui: o video mora fora da plataforma, que e a razao de o modulo
        // existir.
        $video->annotate_files('mod_ldgvideo', 'intro', null);

        return $this->prepare_activity_structure($video);
    }
}
