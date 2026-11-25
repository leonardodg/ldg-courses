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
 * Recebe o percentual assistido do player e grava como maximo por usuario.
 *
 * Nao fala com a Bunny (sem cfg, sem courseid) - so grava local e recalcula
 * conclusao/nota.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;

global $DB, $USER;

require_login(null, false);
$body = ajax_helper::read_json_body();
$sesskey = optional_param('sesskey', '', PARAM_RAW) ?: ($body['sesskey'] ?? '');
if (!confirm_sesskey($sesskey ?: null)) {
    ajax_helper::fail('invalid_sesskey', 403);
}
$instance = (int) ($body['instance'] ?? 0);
$cmid     = (int) ($body['cmid'] ?? 0);
$percent  = max(0, min(100, (int) ($body['percent'] ?? 0)));
if (!$instance || !$cmid) {
    ajax_helper::fail('missing_ids', 400);
}

$cm = get_coursemodule_from_id('bunnystream', $cmid, 0, false, MUST_EXIST);
$activity = $DB->get_record('bunnystream', ['id' => $instance], '*', MUST_EXIST);
require_capability('mod/bunnystream:view', context_module::instance($cm->id));

$existing = $DB->get_record('bunnystream_progress', [
    'bunnystreamid' => $activity->id,
    'userid'        => $USER->id,
]);

if ($existing) {
    if ($percent > (int) $existing->max_percent) {
        $existing->max_percent = $percent;
        $existing->timemodified = time();
        $DB->update_record('bunnystream_progress', $existing);
    }
} else {
    $DB->insert_record('bunnystream_progress', (object) [
        'bunnystreamid' => $activity->id,
        'userid'        => $USER->id,
        'max_percent'   => $percent,
        'timemodified'  => time(),
    ]);
}

require_once(__DIR__ . '/../lib.php');
bunnystream_update_grades($activity, $USER->id);

if ($percent >= (int) $activity->completion_percent) {
    $course = $DB->get_record('course', ['id' => $activity->course]);
    $completion = new \completion_info($course);
    if ($completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
    }
}

ajax_helper::json(['ok' => true, 'max_percent' => $percent]);
