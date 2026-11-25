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
 * Grava a duracao de uma aula.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use format_ldg\lesson;

/**
 * Grava a duracao de uma aula.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_duration extends external_api {
    /**
     * Parametros.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Id do course module da aula'),
            'duration' => new external_value(
                PARAM_INT,
                'Duracao em segundos. Zero ou negativo limpa a duracao.',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Executa.
     *
     * @param int $cmid
     * @param int $duration
     * @return array
     */
    public static function execute(int $cmid, int $duration = 0): array {
        [
            'cmid' => $cmid,
            'duration' => $duration,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'duration' => $duration,
        ]);

        // O cmid vem do cliente, entao nada aqui pode confiar nele antes de
        // passar por get_coursemodule_from_id: um id de outro curso viraria
        // escrita num curso onde a pessoa nao tem nada a fazer.
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);

        // Duracao e conteudo do curso, e nao configuracao da atividade: quem
        // pode gerenciar as atividades do curso pode escrever aqui. Aluno nao.
        require_capability('moodle/course:manageactivities', $context);

        lesson::store_duration($cm->id, $duration > 0 ? $duration : null);

        $stored = lesson::duration_for($cm->id);

        return [
            'cmid' => $cm->id,
            'duration' => $stored ?? 0,
            'formatted' => $stored ? format_time($stored) : '',
        ];
    }

    /**
     * Retorno.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Id do course module'),
            'duration' => new external_value(PARAM_INT, 'Duracao gravada, em segundos. Zero quando nao ha.'),
            'formatted' => new external_value(PARAM_TEXT, 'A mesma duracao ja escrita para leitura.'),
        ]);
    }
}
