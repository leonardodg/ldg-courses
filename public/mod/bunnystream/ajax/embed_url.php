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
 * Assina (ou nao) a URL de embed para um guid ja existente.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\config;
use mod_bunnystream\not_configured_exception;
use mod_bunnystream\token;

global $DB;

$courseid = ajax_helper::require_courseid();
require_login($courseid, false);

$guid = optional_param('guid', '', PARAM_ALPHANUMEXT);
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

try {
    $cfg = config::for_course($courseid);
    $url = token::embed_url_for($row->library_id, $guid, $cfg->security_key, [
        'autoplay' => 'true', 'preload' => 'true', 'responsive' => 'true',
    ]);
} catch (not_configured_exception $e) {
    $url = token::unsigned_embed($row->library_id, $guid, [
        'autoplay' => 'true', 'preload' => 'true', 'responsive' => 'true',
    ]);
}

ajax_helper::json(['url' => $url]);
