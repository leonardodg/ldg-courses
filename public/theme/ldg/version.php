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
 * Tema global da plataforma. Filho do Boost.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_ldg';
$plugin->version   = 2026091012;
$plugin->requires  = 2026042000; // Moodle 5.2.
$plugin->supported = [502, 502];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// O pai e o Boost (config.php: $THEME->parents = ['boost']), e e so dele a
// dependencia declarada. Subir o Boost sem reconferir os overrides de
// core_renderer e util\settings e a forma mais provavel de este tema quebrar
// em silencio.
$plugin->dependencies = [
    'theme_boost' => 2026042000,
];
