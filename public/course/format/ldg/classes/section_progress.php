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
 * Progresso de uma secao.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

use cm_info;
use completion_info;
use course_modinfo;
use section_info;

/**
 * Quantas aulas de um modulo a pessoa concluiu.
 *
 * O Moodle sabe responder isso para o CURSO inteiro, por
 * \core_completion\progress::get_course_progress_percentage(). Por SECAO, nao:
 * o que existe e
 * core_courseformat\output\local\content\section\cmsummary::calculate_section_stats(),
 * que e protected e devolve um array posicional junto com outras contas.
 *
 * Entao a conta esta replicada aqui - e e importante saber que e COPIA. A regra
 * do core, seguida a risco:
 *
 * - so entra atividade com uservisible (aula bloqueada nao conta no
 *   denominador; se contasse, quem nao comprou veria o progresso travar sem
 *   entender por que)
 * - so entra atividade com conclusao ligada
 * - COMPLETE e COMPLETE_PASS contam como concluida; COMPLETE_FAIL nao
 * - visitante e quem nao esta autenticado nao tem progresso
 *
 * Se o Moodle mudar essa regra, a nossa diverge em silencio. O teste desta
 * classe existe para avisar - ele vale mais que os outros do plugin.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_progress {
    /** @var int Aulas concluidas. */
    public readonly int $complete;

    /** @var int Aulas que contam para o progresso. */
    public readonly int $total;

    /**
     * Construtor.
     *
     * @param int $complete
     * @param int $total
     */
    protected function __construct(int $complete, int $total) {
        $this->complete = $complete;
        $this->total = $total;
    }

    /**
     * Calcula o progresso de uma secao.
     *
     * @param section_info $section
     * @param course_modinfo $modinfo modinfo JA construido para o usuario certo
     * @param completion_info $completion
     * @return self
     */
    public static function for_section(
        section_info $section,
        course_modinfo $modinfo,
        completion_info $completion
    ): self {
        // Visitante nao tem conclusao para mostrar, e perguntar por ele devolve
        // dado do usuario zero.
        if (!isloggedin() || isguestuser()) {
            return new self(0, 0);
        }

        $complete = 0;
        $total = 0;

        foreach ($modinfo->sections[$section->sectionnum] ?? [] as $cmid) {
            $cm = $modinfo->cms[$cmid];

            if (!$cm->uservisible || $completion->is_enabled($cm) == COMPLETION_TRACKING_NONE) {
                continue;
            }

            $total++;

            if (self::is_complete($completion, $cm)) {
                $complete++;
            }
        }

        return new self($complete, $total);
    }

    /**
     * A atividade esta concluida para esta pessoa.
     *
     * COMPLETE_PASS conta; COMPLETE_FAIL nao. Quem tentou e nao passou nao
     * concluiu - e mostrar como concluido seria mentir sobre o proprio curso.
     *
     * @param completion_info $completion
     * @param cm_info $cm
     * @return bool
     */
    public static function is_complete(completion_info $completion, cm_info $cm): bool {
        $data = $completion->get_data($cm, true);

        return $data->completionstate == COMPLETION_COMPLETE
            || $data->completionstate == COMPLETION_COMPLETE_PASS;
    }

    /**
     * A secao tem alguma aula com conclusao ligada.
     *
     * Sem isto a barra apareceria zerada em modulo que simplesmente nao
     * acompanha conclusao, o que se le como "voce nao fez nada".
     *
     * @return bool
     */
    public function has_tracking(): bool {
        return $this->total > 0;
    }

    /**
     * Percentual concluido, de 0 a 100.
     *
     * @return int
     */
    public function percentage(): int {
        if (!$this->has_tracking()) {
            return 0;
        }

        return (int) round(($this->complete / $this->total) * 100);
    }

    /**
     * Todas as aulas do modulo estao concluidas.
     *
     * @return bool
     */
    public function is_complete_section(): bool {
        return $this->has_tracking() && $this->complete === $this->total;
    }
}
