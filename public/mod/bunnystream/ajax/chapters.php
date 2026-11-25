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
 * GET le os capitulos; POST/PUT substitui a lista inteira.
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

global $DB;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $courseid = ajax_helper::require_courseid();
    require_login($courseid, false);
    $cfg = ajax_helper::require_view($courseid);
    $guid = required_param('guid', PARAM_ALPHANUMEXT);
    $client = new bunny_client($cfg);
    try {
        ajax_helper::json(['chapters' => $client->get_chapters($guid)]);
    } catch (\Throwable $e) {
        ajax_helper::fail($e->getMessage(), 502);
    }
}

// POST/PUT — substitui os capitulos.
$courseid = ajax_helper::require_courseid();
require_login($courseid, false);
$cfg = ajax_helper::require_manage($courseid);
$body = ajax_helper::read_json_body();
$guid = trim((string) ($body['guid'] ?? ''));
$raw = $body['chapters'] ?? null;
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}
if (!is_array($raw)) {
    ajax_helper::fail('chapters_must_be_array', 400);
}
if (count($raw) > 50) {
    ajax_helper::fail('too_many_chapters', 400);
}

$cleaned = [];
foreach ($raw as $i => $ch) {
    if (!is_array($ch)) {
        ajax_helper::fail("chapter_{$i}_not_object", 400);
    }
    $title = substr(trim((string) ($ch['title'] ?? '')), 0, 120);
    $start = max(0, (int) ($ch['start'] ?? 0));
    $end   = max(0, (int) ($ch['end'] ?? 0));
    if ($title === '') {
        ajax_helper::fail("chapter_{$i}_needs_title", 400);
    }
    if ($end && $end < $start) {
        ajax_helper::fail("chapter_{$i}_end_before_start", 400);
    }
    $cleaned[] = ['title' => $title, 'start' => $start, 'end' => $end];
}

usort($cleaned, fn($a, $b) => $a['start'] - $b['start']);

$activity = $DB->get_record('bunnystream', ['guid' => $guid]);
$duration = $activity ? (int) $activity->duration_sec : 0;
foreach ($cleaned as $i => &$ch) {
    if (!$ch['end']) {
        if (isset($cleaned[$i + 1])) {
            $ch['end'] = $cleaned[$i + 1]['start'];
        } else if ($duration) {
            $ch['end'] = $duration;
        } else {
            $ch['end'] = $ch['start'] + 60;
        }
    }
}
unset($ch);

$client = new bunny_client($cfg);
try {
    $client->set_chapters($guid, $cleaned);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
ajax_helper::json(['ok' => true, 'chapters' => $cleaned]);
