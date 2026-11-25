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
 * Callbacks de biblioteca do mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declara os recursos opcionais que este modulo suporta.
 *
 * @param string $feature FEATURE_xx constant do core.
 * @return mixed
 */
function bunnystream_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_MOD_PURPOSE:
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        default:
            return null;
    }
}

/**
 * Sem capabilities extras alem das declaradas em db/access.php.
 *
 * @return array
 */
function bunnystream_get_extra_capabilities() {
    return [];
}

/**
 * Cria uma instancia nova da atividade.
 *
 * @param stdClass $data
 * @param mixed $mform
 * @return int
 */
function bunnystream_add_instance(stdClass $data, $mform = null) {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    $data->status = $data->status ?? 'pending';
    $data->video_style = $data->video_style ?? 'default';
    if (!isset($data->completion_percent) || (int) $data->completion_percent <= 0) {
        $data->completion_percent = (int) (get_config('mod_bunnystream', 'completion_percent') ?: 90);
    }
    // O core le $moduleinfo->cmidnumber mesmo sem idnumber informado.
    if (!property_exists($data, 'cmidnumber')) {
        $data->cmidnumber = '';
    }
    $id = $DB->insert_record('bunnystream', $data);
    bunnystream_grade_item_update((object) array_merge((array) $data, ['id' => $id]));

    return $id;
}

/**
 * Atualiza uma instancia existente.
 *
 * @param stdClass $data
 * @param mixed $mform
 * @return bool
 */
function bunnystream_update_instance(stdClass $data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    if (!property_exists($data, 'cmidnumber')) {
        $data->cmidnumber = '';
    }
    $DB->update_record('bunnystream', $data);
    bunnystream_grade_item_update($data);

    return true;
}

/**
 * Apaga uma instancia.
 *
 * Nao apaga o video na Bunny: o video pode ser reutilizado, e apagar a
 * atividade por engano nao pode levar o conteudo junto. Apagar de verdade e
 * uma acao explicita no editor, que fala com a API.
 *
 * @param int $id
 * @return bool
 */
function bunnystream_delete_instance($id) {
    global $DB;
    $activity = $DB->get_record('bunnystream', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $DB->delete_records('bunnystream_progress', ['bunnystreamid' => $id]);
    $DB->delete_records('bunnystream', ['id' => $id]);
    bunnystream_grade_item_delete($activity);

    return true;
}

/**
 * O aluno assistiu o suficiente para a atividade contar como concluida?
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param int $userid
 * @param mixed $type
 * @return mixed
 */
function bunnystream_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    $activity = $DB->get_record('bunnystream', ['id' => $cm->instance], '*', MUST_EXIST);
    $threshold = (int) $activity->completion_percent;
    if ($threshold <= 0) {
        return $type;
    }
    $progress = $DB->get_record('bunnystream_progress', [
        'bunnystreamid' => $activity->id,
        'userid'        => $userid,
    ]);
    if (!$progress) {
        return false;
    }

    return (int) $progress->max_percent >= $threshold;
}

/**
 * Cria/atualiza o item de nota da atividade.
 *
 * @param stdClass $activity
 * @param mixed $grades
 * @return mixed
 */
function bunnystream_grade_item_update(stdClass $activity, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $params = [
        'itemname' => $activity->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => 100,
        'grademin'  => 0,
    ];

    return grade_update('mod/bunnystream', $activity->course, 'mod', 'bunnystream', $activity->id, 0, $grades, $params);
}

/**
 * Remove o item de nota da atividade.
 *
 * @param stdClass $activity
 * @return mixed
 */
function bunnystream_grade_item_delete(stdClass $activity) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/bunnystream', $activity->course, 'mod', 'bunnystream', $activity->id, 0, null, ['deleted' => 1]);
}

/**
 * Atualiza a nota de um usuario (ou de todos) a partir do progresso gravado.
 *
 * @param stdClass $activity
 * @param int $userid
 * @return void
 */
function bunnystream_update_grades(stdClass $activity, $userid = 0) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $params = ['bunnystreamid' => $activity->id];
    if ($userid) {
        $params['userid'] = $userid;
    }
    $rows = $DB->get_records('bunnystream_progress', $params);
    $grades = [];
    foreach ($rows as $r) {
        $grades[$r->userid] = (object) [
            'userid'   => $r->userid,
            'rawgrade' => (float) $r->max_percent,
        ];
    }
    if (!empty($grades)) {
        bunnystream_grade_item_update($activity, $grades);
    } else if ($userid) {
        bunnystream_grade_item_update($activity, (object) ['userid' => $userid, 'rawgrade' => null]);
    }
}

/**
 * Reseta o progresso no reset de curso.
 *
 * @param stdClass $data
 * @return array
 */
function bunnystream_reset_userdata($data) {
    global $DB;
    $status = [];
    if (!empty($data->reset_bunnystream_progress)) {
        $activities = $DB->get_records('bunnystream', ['course' => $data->courseid], '', 'id');
        foreach ($activities as $a) {
            $DB->delete_records('bunnystream_progress', ['bunnystreamid' => $a->id]);
        }
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_bunnystream'),
            'item'      => get_string('modulenameplural', 'mod_bunnystream'),
            'error'     => false,
        ];
    }

    return $status;
}
