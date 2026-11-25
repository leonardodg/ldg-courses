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
 * Envia uma miniatura customizada.
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

$guid = required_param('guid', PARAM_ALPHANUMEXT);

if (empty($_FILES['thumbnail'])) {
    ajax_helper::fail('missing_file', 400);
}
$file = $_FILES['thumbnail'];
$contenttype = strtolower($file['type'] ?? '');
if (!in_array($contenttype, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    ajax_helper::fail('unsupported_image_type', 400);
}
if ($file['size'] > 5 * 1024 * 1024) {
    ajax_helper::fail('image_too_large', 413);
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
    $client->set_thumbnail($guid, $bytes, $contenttype);
    $meta = $client->get_video($guid);
    if ($meta) {
        $thumb = bunny_client::thumbnail_url($cfg->cdn_hostname, $guid, $meta['thumbnailFileName'] ?? null);
        if ($thumb) {
            $row->thumbnail_url = $thumb;
            $row->timemodified = time();
            $DB->update_record('bunnystream_videos', $row);
        }
    }
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}

ajax_helper::json(['ok' => true, 'thumbnail_url' => $row->thumbnail_url ?: null]);
