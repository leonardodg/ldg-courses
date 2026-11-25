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
 * Page external functions and service definitions.
 *
 * @package    mod_ldgvideo
 * @category   external
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @author     LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_ldgvideo_view_ldgvideo' => [
        'classname'     => 'mod_ldgvideo_external',
        'methodname'    => 'view_ldgvideo',
        'description'   => 'Simulate the view.php web interface page: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/ldgvideo:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_ldgvideo_get_ldgvideos_by_courses' => [
        'classname'     => 'mod_ldgvideo_external',
        'methodname'    => 'get_ldgvideos_by_courses',
        'description'   => 'Returns a list of video activities in the given courses. '
            . 'With no course list, returns every video the user can view.',
        'type'          => 'read',
        'capabilities'  => 'mod/ldgvideo:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
