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
 * Poll de status do video durante o encoding (autor).
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
$cfg = ajax_helper::require_view($courseid);

$guid = optional_param('guid', '', PARAM_ALPHANUMEXT);
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

// Piso de 30s entre chamadas: pula a ida a Bunny se a linha esta fresca ou
// ja chegou num estado terminal.
if (!bunny_client::is_terminal_status($row->status)) {
    $age = time() - (int) $row->timemodified;
    if ($age > 30) {
        try {
            $client = new bunny_client($cfg);
            $meta = $client->get_video($guid);
            if ($meta) {
                $row->status = bunny_client::map_status($meta['status'] ?? null);
                $thumb = bunny_client::thumbnail_url($cfg->cdn_hostname, $guid, $meta['thumbnailFileName'] ?? null);
                if ($thumb) {
                    $row->thumbnail_url = $thumb;
                }
                $length = $meta['length'] ?? null;
                if (is_numeric($length) && $length > 0) {
                    $row->duration_sec = (int) round($length);
                }
                $row->timemodified = time();
                $DB->update_record('bunnystream_videos', $row);
            }
        } catch (\Throwable $e) {
            // Devolve a linha em cache - melhor que estourar o poll do autor.
            debugging('[bunny:video_get] sync_failed_returning_cached: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

ajax_helper::json(ajax_helper::serialize_video($row));
