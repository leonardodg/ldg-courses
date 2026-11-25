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
 * Notificacoes do local_partners.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Candidatura nova na fila.
    //
    // Sem isto a fila so e vista por quem lembra de abrir a tela, e um parceiro
    // esperando resposta e um parceiro perdido.
    'newapplication' => [
        'capability' => 'local/partners:review',
        'defaults' => [
            // MESSAGE_DEFAULT_LOGGEDIN NAO existe no Moodle 5.2 e derruba o
            // upgrade com "Undefined constant". Ja aconteceu neste projeto.
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
