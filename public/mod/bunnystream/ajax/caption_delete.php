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
 * Remove uma legenda.
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
$guid = required_param('guid', PARAM_ALPHANUMEXT);
$srclang = strtolower(trim(required_param('srclang', PARAM_RAW)));
if ($srclang === '') {
    ajax_helper::fail('missing_srclang', 400);
}

$client = new bunny_client($cfg);
try {
    $client->delete_caption($guid, $srclang);
    $captions = $client->list_captions($guid);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
ajax_helper::json(['ok' => true, 'captions' => $captions]);
