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
 * Apaga o video da Bunny e desanexa das atividades que apontavam para ele.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

global $DB, $USER;

$courseid = ajax_helper::require_courseid();
require_login($courseid, false);
$cfg = ajax_helper::require_manage($courseid);

$guid = optional_param('guid', '', PARAM_ALPHANUMEXT);
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}

if (!ajax_helper::rate_ok('delete:' . $USER->id, 120)) {
    ajax_helper::fail('error_too_many_deletes', 429);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

$client = new bunny_client($cfg);
try {
    $client->delete_video($guid);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}

$DB->delete_records('bunnystream_videos', ['guid' => $guid]);

$activities = $DB->get_records('bunnystream', ['guid' => $guid]);
foreach ($activities as $a) {
    $a->guid = null;
    $a->library_id = null;
    $a->title = null;
    $a->duration_sec = 0;
    $a->thumbnail_url = null;
    $a->status = 'pending';
    $a->timemodified = time();
    $DB->update_record('bunnystream', $a);
}

ajax_helper::json(['ok' => true]);
