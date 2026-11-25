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
 * Servicos do format_ldg.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'format_ldg_set_duration' => [
        'classname' => 'format_ldg\external\set_duration',
        'description' => 'Grava a duracao de uma aula do formato LDG.',
        'type' => 'write',
        // Chamado pela edicao inline na propria pagina do curso, entao precisa
        // estar disponivel para AJAX. NAO entra em nenhum servico externo: nao
        // ha caso de uso fora da tela, e expor a escrita para token de
        // integracao seria superficie a mais sem ganho.
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
];
