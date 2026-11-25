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
 * Renderiza a pagina do curso.
 *
 * Este arquivo AINDA e obrigatorio no Moodle 5.2, apesar de a logica ter
 * migrado para as output classes: o /course/view.php faz require dele sem
 * nenhuma condicional. Sem o arquivo, o curso quebra com "file not found" e o
 * erro nao aponta para o formato.
 *
 * Ele fica magro de proposito. As variaveis $course, $displaysection e $marker
 * vem do escopo global do view.php - o proprio core documenta essa dependencia
 * em course/view.php:349.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$format = course_get_format($course);
$course = $format->get_course();

// Garante que a secao 0 exista antes de qualquer coisa tentar le-la.
course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_ldg');

$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);

echo $renderer->render($widget);
