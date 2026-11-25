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
 * Callbacks de hook do formato.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

use core\hook\output\before_http_headers;
use moodle_page;

/**
 * Troca o layout da pagina do curso pelo do portal do aluno.
 *
 * O course/view.php do core crava set_pagelayout('course') e so consulta o
 * formato ANTES disso, entao o formato nao escolhe o proprio layout pelos
 * caminhos normais. Este listener corre no before_http_headers, despachado na
 * primeira linha do core_renderer::header() - antes de o pagelayout virar
 * arquivo, e com a pagina ainda em STATE_BEFORE_HEADER.
 *
 * NAO use o \core_course\hook\before_course_viewed, que tem o nome mais obvio:
 * o course/view.php o despacha SEIS LINHAS ANTES do set_pagelayout, e o layout
 * definido la e sobrescrito sem erro nenhum.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /** @var string O layout que o tema precisa declarar para o portal existir. */
    public const LAYOUT = 'ldgportal';

    /**
     * A decisao, separada do estado global para poder ser testada.
     *
     * Os layouts vem por PARAMETRO, e nao de $page->theme, porque o
     * moodle-plugin-ci roda o formato sem o theme_ldg instalado - um teste que
     * dependesse do tema seria pulado justamente onde precisa valer.
     *
     * @param moodle_page $page
     * @param array $layouts Os layouts declarados pelo tema ativo.
     * @return bool
     */
    public static function should_use_portal(moodle_page $page, array $layouts): bool {
        if ($page->pagelayout !== 'course') {
            return false;
        }

        $course = $page->course;

        if (empty($course->id) || $course->id == SITEID || $course->format !== 'ldg') {
            return false;
        }

        // Tema que nao declara o layout cairia no 'standard' com um debugging()
        // na cara de quem usa outro tema. Melhor nao trocar.
        if (!array_key_exists(self::LAYOUT, $layouts)) {
            return false;
        }

        // Professor editando volta para o chrome do Moodle: arrastar atividade,
        // renomear e o menu de acoes vivem nos ganchos que o core poe naquela
        // marcacao, e nenhum deles funciona dentro do portal.
        return !course_get_format($course)->show_editor();
    }

    /**
     * Ponto de entrada do hook.
     *
     * @param before_http_headers $hook
     * @return void
     */
    public static function before_http_headers(before_http_headers $hook): void {
        $page = $hook->renderer->get_page();

        if (!self::should_use_portal($page, (array) $page->theme->layouts)) {
            return;
        }

        $page->set_pagelayout(self::LAYOUT);
    }
}
