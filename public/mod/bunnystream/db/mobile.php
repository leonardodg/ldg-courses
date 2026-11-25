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
 * Registro do componente no Moodle Mobile App.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_bunnystream' => [
        'handlers' => [
            'view' => [
                'displaydata'   => ['icon' => $CFG->wwwroot . '/mod/bunnystream/pix/icon.png', 'class' => ''],
                'delegate'      => 'CoreCourseModuleDelegate',
                'method'        => 'mobile_view',
                'init'          => 'mobile_init',
                'offlinefunctions' => [],
                'styles'        => [
                    'url'     => $CFG->wwwroot . '/mod/bunnystream/styles/mobile.css',
                    'version' => 1,
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'mod_bunnystream'],
            ['video', 'mod_bunnystream'],
            ['error_no_video', 'mod_bunnystream'],
        ],
    ],
];
