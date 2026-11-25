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
 * A barra de navegacao entre aulas.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\output\courseformat\content;

use cm_info;
use core\output\named_templatable;
use core\output\renderer_base;
use core_courseformat\base as course_format;
use format_ldg\catalog;
use renderable;
use stdClass;

/**
 * Aula anterior, onde estou, proxima aula.
 *
 * E o ATALHO que sobra quando o aluno esconde as laterais, e nao um enfeite
 * embaixo do quadro. Por isso duas regras:
 *
 * - na ponta o botao nao aparece, em vez de aparecer desabilitado ou levando
 *   para a propria aula. Botao que nao leva a lugar nenhum e pior que a
 *   ausencia dele;
 * - a sequencia e so de AULA. O material e o forum tem destino proprio no
 *   portal, e conta-los aqui faria "aula 3 de 9" quando ha cinco aulas.
 *
 * A ordem vem do catalogo, que ja varreu o curso uma vez e ja respeita
 * visibilidade - nao se refaz a varredura.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lessonnav implements named_templatable, renderable {
    /** @var course_format */
    protected course_format $format;

    /** @var catalog */
    protected catalog $catalog;

    /** @var cm_info|null */
    protected ?cm_info $selected;

    /**
     * Construtor.
     *
     * @param course_format $format
     * @param catalog $catalog
     * @param cm_info|null $selected
     */
    public function __construct(course_format $format, catalog $catalog, ?cm_info $selected = null) {
        $this->format = $format;
        $this->catalog = $catalog;
        $this->selected = $selected;
    }

    /**
     * Template desta barra.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_ldg/local/content/lessonnav';
    }

    /**
     * Monta os dados.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $lessons = array_values($this->catalog->get(catalog::AULA));

        if ($this->selected === null || empty($lessons)) {
            return (object) ['hasnav' => false];
        }

        $position = null;

        foreach ($lessons as $i => $cm) {
            if ($cm->id == $this->selected->id) {
                $position = $i;
                break;
            }
        }

        // A aula em foco pode nao estar na lista - materia, forum ou certificado
        // aberto pelo destino proprio. Ali a barra nao faz sentido.
        if ($position === null) {
            return (object) ['hasnav' => false];
        }

        $prev = $lessons[$position - 1] ?? null;
        $next = $lessons[$position + 1] ?? null;

        $section = $this->format->get_modinfo()->get_section_info($this->selected->sectionnum);

        return (object) [
            'hasnav' => true,
            'hasprev' => ($prev !== null),
            'prev' => $prev ? $this->export_lesson($prev) : null,
            'hasnext' => ($next !== null),
            'next' => $next ? $this->export_lesson($next) : null,
            'position' => (object) [
                'index' => $position + 1,
                'total' => count($lessons),
                'module' => $this->format->get_section_name($section),
                'label' => get_string('lessonposition', 'format_ldg', (object) [
                    'index' => $position + 1,
                    'total' => count($lessons),
                ]),
            ],
        ];
    }

    /**
     * Uma aula vizinha.
     *
     * @param cm_info $cm
     * @return stdClass
     */
    protected function export_lesson(cm_info $cm): stdClass {
        return (object) [
            'cmid' => $cm->id,
            'name' => $cm->get_formatted_name(),
            'url' => $this->format->get_view_url(null, ['lesson' => $cm->id])->out(false),
        ];
    }
}
