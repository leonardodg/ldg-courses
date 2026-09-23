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
 * API externa do modulo de video.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_course\external\helper_for_get_mods_by_courses;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;


/**
 * As funcoes que o aplicativo movel e outros clientes chamam.
 *
 * Herdada do mod_page e podada junto com ele: o que ali devolvia o HTML do
 * conteudo, os arquivos dele e a revisao de cache, aqui devolve o ENDERECO do
 * video e a proporcao. Nao ha arquivo para devolver.
 *
 * @package    mod_ldgvideo
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ldgvideo_external extends external_api {
    /**
     * Parametros do view_ldgvideo.
     *
     * @return external_function_parameters
     */
    public static function view_ldgvideo_parameters() {
        return new external_function_parameters([
            'ldgvideoid' => new external_value(PARAM_INT, 'Instance id of the video activity'),
        ]);
    }

    /**
     * Marca a visualizacao: dispara o evento e conta para a conclusao.
     *
     * @param int $ldgvideoid
     * @return array
     */
    public static function view_ldgvideo($ldgvideoid) {
        global $DB;

        $params = self::validate_parameters(self::view_ldgvideo_parameters(), [
            'ldgvideoid' => $ldgvideoid,
        ]);

        $video = $DB->get_record('ldgvideo', ['id' => $params['ldgvideoid']], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($video, 'ldgvideo');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/ldgvideo:view', $context);

        ldgvideo_view($video, $course, $cm, $context);

        return [
            'status' => true,
            'warnings' => [],
        ];
    }

    /**
     * Retorno do view_ldgvideo.
     *
     * @return external_single_structure
     */
    public static function view_ldgvideo_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'status: true if success'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parametros do get_ldgvideos_by_courses.
     *
     * @return external_function_parameters
     */
    public static function get_ldgvideos_by_courses_parameters() {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'),
                'Array of course ids',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * As atividades de video de uma lista de cursos.
     *
     * Sem lista, devolve as de todos os cursos em que a pessoa esta.
     *
     * @param array $courseids
     * @return array
     */
    public static function get_ldgvideos_by_courses($courseids = []) {
        $warnings = [];
        $returned = [];

        $params = self::validate_parameters(
            self::get_ldgvideos_by_courses_parameters(),
            ['courseids' => $courseids]
        );

        $mycourses = [];
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        if (!empty($params['courseids'])) {
            [$courses, $warnings] = util::validate_courses($params['courseids'], $mycourses);

            $videos = get_all_instances_in_courses('ldgvideo', $courses);

            foreach ($videos as $video) {
                // Checagem EXPLICITA por cm, a mesma guarda de view.php /
                // view_ldgvideo(). Confiar so na filtragem de
                // get_all_instances_in_courses acopla a seguranca da WS a um
                // helper do core que pode mudar o criterio sem aviso.
                $context = \context_module::instance((int) $video->coursemodule);
                self::validate_context($context);

                if (!has_capability('mod/ldgvideo:view', $context)) {
                    $warnings[] = [
                        'item' => 'ldgvideo',
                        'itemid' => (int) $video->id,
                        'warningcode' => 'nopermissions',
                        'message' => get_string('errornopermissions', 'ldgvideo'),
                    ];
                    continue;
                }

                helper_for_get_mods_by_courses::format_name_and_intro($video, 'mod_ldgvideo');
                $returned[] = $video;
            }
        }

        return [
            'ldgvideos' => $returned,
            'warnings' => $warnings,
        ];
    }

    /**
     * Retorno do get_ldgvideos_by_courses.
     *
     * @return external_single_structure
     */
    public static function get_ldgvideos_by_courses_returns() {
        return new external_single_structure([
            'ldgvideos' => new external_multiple_structure(
                new external_single_structure(array_merge(
                    helper_for_get_mods_by_courses::standard_coursemodule_elements_returns(),
                    [
                        'videourl' => new external_value(PARAM_URL, 'The external video address'),
                        'aspectratio' => new external_value(PARAM_TEXT, 'Aspect ratio: 16:9, 9:16 or 4:3'),
                        'displayoptions' => new external_value(PARAM_RAW, 'Serialised display options'),
                        'timemodified' => new external_value(PARAM_INT, 'Last time the activity was modified'),
                    ]
                ))
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
