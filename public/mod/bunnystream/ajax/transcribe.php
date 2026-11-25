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
 * Pede transcricao automatica de audio para a Bunny.
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

$courseid = ajax_helper::require_courseid();
require_login($courseid, false);
$cfg = ajax_helper::require_manage($courseid);
$body = ajax_helper::read_json_body();
$guid = trim((string) ($body['guid'] ?? ''));
$language = strtolower(trim((string) ($body['language'] ?? 'en')));
$force = !empty($body['force']);

if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}
if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/', $language)) {
    ajax_helper::fail('bad_language', 400);
}

$client = new bunny_client($cfg);
try {
    $client->transcribe($guid, $language, $force);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
ajax_helper::json(['ok' => true, 'language' => $language]);
