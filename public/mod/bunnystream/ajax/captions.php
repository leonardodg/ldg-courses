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
 * GET lista legendas; POST envia um .vtt novo.
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
        ajax_helper::json(['captions' => $client->list_captions($guid)]);
    } catch (\Throwable $e) {
        ajax_helper::fail($e->getMessage(), 502);
    }
}

// POST — envia uma legenda.
$courseid = ajax_helper::require_courseid();
require_login($courseid, false);
$cfg = ajax_helper::require_manage($courseid);
$guid = required_param('guid', PARAM_ALPHANUMEXT);
$srclang = strtolower(trim(required_param('srclang', PARAM_RAW)));
$label = substr(trim(optional_param('label', '', PARAM_TEXT)), 0, 60);

if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/', $srclang)) {
    ajax_helper::fail('bad_srclang', 400);
}
if (empty($_FILES['vtt'])) {
    ajax_helper::fail('missing_file', 400);
}
$file = $_FILES['vtt'];
if ($file['size'] > 1 * 1024 * 1024) {
    ajax_helper::fail('vtt_too_large', 413);
}
$contenttype = strtolower($file['type'] ?? '');
if ($contenttype && !in_array($contenttype, ['text/vtt', 'text/plain', 'application/octet-stream'], true)) {
    ajax_helper::fail('unsupported_caption_type', 400);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

$bytes = file_get_contents($file['tmp_name']);
if ($bytes === false) {
    ajax_helper::fail('read_failed', 500);
}

$client = new bunny_client($cfg);
try {
    $client->upload_caption($guid, $srclang, $label ?: strtoupper($srclang), $bytes);
    $captions = $client->list_captions($guid);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
ajax_helper::json(['ok' => true, 'captions' => $captions]);
