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
 * Hooks do local_partners.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // Meta tags de compartilhamento da landing.
        //
        // Prioridade baixa de proposito: nao ha pressa, e o unico requisito e
        // rodar depois de o $PAGE ter URL definida.
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \local_partners\hook_callbacks::class . '::before_standard_head_html_generation',
        'priority' => 0,
    ],
    [
        // O titulo da landing quando ela serve a home.
        //
        // Precisa ser ESTE hook, e nao o de <head>: o template do tema resolve
        // `page_title` antes de `standard_head_html`, entao um set_title() feito
        // la nao tem efeito - e falha em silencio.
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_partners\hook_callbacks::class . '::before_http_headers',
        'priority' => 0,
    ],
];
