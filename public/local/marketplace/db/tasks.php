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
 * Tarefas agendadas do marketplace.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Uma vez por dia, de madrugada. O aviso e sobre uma data, nao sobre um
        // instante: rodar de hora em hora nao antecipa nada e so multiplica a
        // chance de incomodar.
        'classname' => 'local_marketplace\task\notify_expiring',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '6',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
