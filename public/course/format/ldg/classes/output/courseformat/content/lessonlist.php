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
 * A lista de aulas do portal.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\output\courseformat\content;

use cm_info;
use completion_info;
use core_availability\info;
use core_courseformat\base as course_format;
use core\output\named_templatable;
use core\output\renderer_base;
use format_ldg\catalog;
use format_ldg\lesson;
use format_ldg\section_progress;
use renderable;
use stdClass;

/**
 * Lista de aulas agrupadas por modulo.
 *
 * E a navegacao do curso inteiro numa coluna so: modulo, aulas dentro dele,
 * conclusao de cada uma e o cadeado de quem ainda nao tem acesso.
 *
 * O que esta classe NAO faz, de proposito:
 *
 * - nao consulta local_marketplace. O bloqueio de quem nao comprou ja chega
 *   pronto em cm_info->uservisible, porque o availability_marketplace roda
 *   antes. Perguntar de novo criaria uma segunda verdade sobre acesso, e
 *   entitlement::user_has_course_access() ainda faz N+1.
 * - nao decide o que e "aula". Usa o mesmo par de testes do core -
 *   is_visible_on_course_page() e is_of_type_that_can_display() - e e o segundo
 *   que mantem o banco de questoes fora da lista, ja que o mod_qbank declara
 *   FEATURE_CAN_DISPLAY => false.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lessonlist implements named_templatable, renderable {
    /** @var course_format */
    protected course_format $format;

    /** @var cm_info|null Aula em foco. */
    protected ?cm_info $selected;

    /**
     * Construtor.
     *
     * @param course_format $format
     * @param cm_info|null $selected
     */
    public function __construct(course_format $format, ?cm_info $selected = null) {
        $this->format = $format;
        $this->selected = $selected;
    }

    /**
     * Template desta lista.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_ldg/local/content/lessonlist';
    }

    /**
     * Monta os dados.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $format = $this->format;
        $course = $format->get_course();
        $modinfo = $format->get_modinfo();
        $completion = new completion_info($course);

        // Uma consulta para o CURSO inteiro, e nao uma por secao - o N+1 que ja
        // mordeu este projeto (ver o docblock de export_lessons() sobre a
        // mesma preocupacao, so que resolvida no nivel errado antes: reaparecia
        // em nivel de secao, com N secoes em vez de N aulas).
        $alllessonids = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$format->is_section_visible($section)) {
                continue;
            }
            foreach ($modinfo->sections[$section->sectionnum] ?? [] as $cmid) {
                if (catalog::classify($modinfo->cms[$cmid]) === catalog::AULA) {
                    $alllessonids[] = $cmid;
                }
            }
        }
        $durations = lesson::durations_for($alllessonids);

        $modules = [];

        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$format->is_section_visible($section)) {
                continue;
            }

            $lessons = $this->export_lessons($section->sectionnum, $modinfo, $completion, $course, $durations);

            // Modulo sem nenhuma aula visivel nao vira um cabecalho vazio: quem
            // le a lista tenta clicar nele e nao acontece nada.
            if (empty($lessons)) {
                continue;
            }

            $progress = section_progress::for_section($section, $modinfo, $completion);

            $modules[] = (object) [
                'num' => (int) $section->sectionnum,
                'name' => $format->get_section_name($section),
                'lessons' => $lessons,
                'hasprogress' => $progress->has_tracking(),
                'complete' => $progress->complete,
                'total' => $progress->total,
                'percentage' => $progress->percentage(),
                'iscomplete' => $progress->is_complete_section(),
                'progresslabel' => get_string('moduleprogress', 'format_ldg', (object) [
                    'complete' => $progress->complete,
                    'total' => $progress->total,
                ]),
            ];
        }

        return (object) [
            'modules' => $modules,
            'hasmodules' => !empty($modules),
            // A duracao e editada aqui mesmo, com a edicao ligada. Uma tela de
            // formulario so para um numero seria caro para quem vai preencher
            // dezenas deles em sequencia.
            'canedit' => $format->show_editor()
                && has_capability('moodle/course:manageactivities', $format->get_context()),
        ];
    }

    /**
     * As aulas de um modulo.
     *
     * @param int $sectionnum
     * @param \course_modinfo $modinfo
     * @param completion_info $completion
     * @param stdClass $course
     * @param array $durations cmid => segundos, ja resolvido para o CURSO
     *                        inteiro por export_for_template() - uma consulta
     *                        por pagina, e nao uma por secao.
     * @return array
     */
    protected function export_lessons(
        int $sectionnum,
        \course_modinfo $modinfo,
        completion_info $completion,
        stdClass $course,
        array $durations
    ): array {
        $lessons = [];

        $candidates = [];

        foreach ($modinfo->sections[$sectionnum] ?? [] as $cmid) {
            $cm = $modinfo->cms[$cmid];

            // A regra sobre o que e aula mora no catalogo, e num lugar so.
            // Material, forum e certificado saem daqui porque tem destino
            // proprio no portal - apareciam nas duas listas.
            if (catalog::classify($cm) === catalog::AULA) {
                $candidates[] = $cm;
            }
        }

        foreach ($candidates as $cm) {
            $tracks = $completion->is_enabled($cm) != COMPLETION_TRACKING_NONE
                && isloggedin() && !isguestuser();

            $duration = $durations[$cm->id] ?? null;

            $lessons[] = (object) [
                'duration' => $duration,
                'hasduration' => $duration !== null,
                // O format_time() do core, e nao uma conta a mao: ele ja traduz
                // e ja escolhe entre segundos, minutos e horas.
                'durationtext' => $duration !== null ? format_time($duration) : '',
                'cmid' => $cm->id,
                'name' => $cm->get_formatted_name(),
                'modname' => $cm->modname,
                'iconurl' => $cm->get_icon_url()->out(false),
                'url' => $this->format->get_view_url(null, ['lesson' => $cm->id])->out(false),
                'trackscompletion' => $tracks,
                'completed' => $tracks && section_progress::is_complete($completion, $cm),
                'locked' => !$cm->uservisible,
                'lockinfo' => $this->lock_info($cm, $course),
                'current' => $this->selected !== null && $this->selected->id == $cm->id,
            ];
        }

        return $lessons;
    }

    /**
     * O texto do cadeado.
     *
     * ATENCAO: availableinfo NAO e sempre string. Com mais de uma condicao ele
     * vem como core_availability_multiple_messages, e passar isso para
     * format_string() estoura TypeError - o certo e info::format_info(), que
     * sabe lidar com os dois casos e ainda resolve os links de dentro, como o da
     * vitrine que o availability_marketplace monta.
     *
     * @param cm_info $cm
     * @param stdClass $course
     * @return string
     */
    protected function lock_info(cm_info $cm, stdClass $course): string {
        if ($cm->uservisible || empty($cm->availableinfo)) {
            return '';
        }

        return info::format_info($cm->availableinfo, $course);
    }
}
