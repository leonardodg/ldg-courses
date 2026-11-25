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

namespace theme_ldg\util;

/**
 * Fragmentos de cabecalho compartilhados entre as layouts do tema.
 *
 * drawers.php e ldgportal.php montavam o more-menu da navegacao secundaria e
 * o sitename com o mesmo codigo, copiados um do outro. Duas copias do mesmo
 * bloco e a mesma classe de bug de cromo inconsistente: uma correcao num
 * layout e esquecida no outro.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layouthead {
    /**
     * O nome do site, no formato que os dois templates consomem.
     *
     * format_string com contexto de SITEID e escape false: o valor e o mesmo
     * nos dois layouts de proposito - hardening de XSS no curto do site tem
     * que valer para o portal e para as paginas com drawers ao mesmo tempo.
     *
     * @return string
     */
    public static function sitename(): string {
        global $SITE;

        return format_string($SITE->shortname, true, [
            'context' => \core\context\course::instance(SITEID),
            'escape' => false,
        ]);
    }

    /**
     * Ha filhos na navegacao secundaria do curso?
     *
     * @return bool
     */
    public static function has_secondary_children(): bool {
        global $PAGE;

        if (!$PAGE->has_secondary_navigation()) {
            return false;
        }

        return (bool) $PAGE->secondarynav->get_children_key_list();
    }

    /**
     * O more-menu da navegacao secundaria, no formato do template.
     *
     * @param bool $requiregovern SO para quem pode gerenciar o curso (portal).
     *                           O aluno tem navegacao secundaria propria
     *                           (Curso, Notas...) que o portal nao quer.
     * @return array|false
     */
    public static function secondary_more_menu(bool $requiregovern = false) {
        global $OUTPUT, $PAGE;

        if ($requiregovern && !has_capability('moodle/course:update', $PAGE->context)) {
            return false;
        }

        if (!self::has_secondary_children()) {
            return false;
        }

        $tablistnav = $PAGE->has_tablist_secondary_navigation();
        $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);

        return $moremenu->export_for_template($OUTPUT);
    }
}
