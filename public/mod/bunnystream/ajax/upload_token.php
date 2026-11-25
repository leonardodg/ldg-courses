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
 * Cria o video na Bunny e assina o upload TUS. Precisa de courseid: o video
 * ainda nao existe, entao a unica pista de qual empresa/library usar e o
 * curso onde a atividade esta sendo editada.
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
use mod_bunnystream\token;

global $USER, $DB;

$courseid = ajax_helper::require_courseid();
require_login($courseid, false);
$cfg = ajax_helper::require_manage($courseid);

$body = ajax_helper::read_json_body();
$rawtitle = trim((string) ($body['title'] ?? 'Untitled video'));

if (strpos($rawtitle, '.') !== false) {
    $parts = explode('.', $rawtitle);
    $ext = array_pop($parts);
    $stem = implode('.', $parts);
    if ($stem && $ext && ctype_alnum($ext) && strlen($ext) >= 1 && strlen($ext) <= 6) {
        $rawtitle = $stem;
    }
}
$title = substr($rawtitle, 0, 250) ?: 'Untitled video';

if (!ajax_helper::rate_ok('mint:' . $USER->id, 60)) {
    ajax_helper::fail('error_too_many_uploads', 429);
}

$client = new bunny_client($cfg);
try {
    $guid = $client->create_video($title);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}

$row = (object) [
    'guid'         => $guid,
    'library_id'   => $cfg->library_id,
    'title'        => $title,
    'status'       => bunny_client::STATUS_PENDING,
    'created_by'   => $USER->id,
    'timecreated'  => time(),
    'timemodified' => time(),
];
try {
    $DB->insert_record('bunnystream_videos', $row);
} catch (\Throwable $e) {
    try {
        $client->delete_video($guid);
    } catch (\Throwable $e2) {
        // Limpeza manual - nao ha mais o que fazer daqui.
        unset($e2);
    }
    ajax_helper::fail('Could not record upload. Please try again.', 500);
}

$signature = token::sign_tus($cfg->library_id, $cfg->api_key, $guid);
ajax_helper::json([
    'guid'       => $guid,
    'library_id' => $cfg->library_id,
    'expires'    => $signature['expires'],
    'signature'  => $signature['signature'],
]);
