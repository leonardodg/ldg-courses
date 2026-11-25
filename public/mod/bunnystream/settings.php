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
 * Configuracao do mod_bunnystream.
 *
 * Nao ha campo de library/API key aqui: no plugin de referencia essa
 * credencial era global, uma instalacao = uma library. Aqui e por EMPRESA
 * (local_marketplace_library), provisionada automaticamente na criacao da
 * empresa - ver local/marketplace/classes/api.php::create_video_library().
 * A chave de CONTA da plataforma (usada so para criar libraries novas) mora
 * em local_marketplace/settings.php, nao aqui.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_heading(
        'mod_bunnystream/heading',
        get_string('settings_heading', 'mod_bunnystream'),
        get_string('settings_heading_desc', 'mod_bunnystream')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_bunnystream/completion_percent',
        get_string('setting_completion_percent', 'mod_bunnystream'),
        get_string('setting_completion_percent_desc', 'mod_bunnystream'),
        '90',
        PARAM_INT,
        5
    ));
}
