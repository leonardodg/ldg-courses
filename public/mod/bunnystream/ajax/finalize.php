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
 * Confirma o upload TUS: busca o status atual na Bunny e persiste.
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

$courseid = ajax_helper::require_courseid();
require_login($courseid, false);
$cfg = ajax_helper::require_manage($courseid);
$body = ajax_helper::read_json_body();
$guid = trim((string) ($body['guid'] ?? ''));
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

$client = new bunny_client($cfg);
try {
    $meta = $client->get_video($guid);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
if (!$meta) {
    ajax_helper::fail('not_found_on_bunny', 404);
}

$row->status = bunny_client::map_status($meta['status'] ?? null);
$thumb = bunny_client::thumbnail_url($cfg->cdn_hostname, $guid, $meta['thumbnailFileName'] ?? null);
if ($thumb) {
    $row->thumbnail_url = $thumb;
}
$length = $meta['length'] ?? null;
if (is_numeric($length) && $length > 0) {
    $row->duration_sec = (int) round($length);
}
if (!empty($meta['title'])) {
    $row->title = substr($meta['title'], 0, 250);
}
$row->timemodified = time();
$DB->update_record('bunnystream_videos', $row);

ajax_helper::json(ajax_helper::serialize_video($row));
