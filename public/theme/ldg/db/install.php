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
 * Instalacao do theme_ldg.
 *
 * ATENCAO: este arquivo so roda em INSTALACAO NOVA. Todo passo daqui precisa do
 * equivalente em db/upgrade.php, senao o ambiente que ja tem o tema - inclusive
 * producao - fica de fora e o sintoma nao aparece em lugar nenhum.
 *
 * @package    theme_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executa a instalacao.
 *
 * @return void
 */
function xmldb_theme_ldg_install() {
    theme_ldg_register_in_user_tours();
    theme_ldg_add_accessibility_to_user_menu();
}

/**
 * Faz os tours configurados para o Boost valerem tambem neste tema.
 *
 * O tour do Moodle e filtrado por tema. Quem configurou um tour para o Boost -
 * inclusive os que vem com o Moodle - nao o veria aqui, e o sintoma seria "o
 * tour sumiu", sem erro nenhum a que se agarrar.
 *
 * So acrescenta a tours que JA valem para o Boost: um tour restrito a outro
 * tema continua restrito, porque restringir foi decisao de alguem.
 *
 * @return void
 */
function theme_ldg_register_in_user_tours() {
    global $DB;

    if (!$DB->get_manager()->table_exists('tool_usertours_tours')) {
        return;
    }

    $tours = $DB->get_records('tool_usertours_tours');

    foreach ($tours as $tour) {
        $configdata = json_decode($tour->configdata);

        if (empty($configdata->filtervalues->theme) || !is_array($configdata->filtervalues->theme)) {
            continue;
        }

        $themes = $configdata->filtervalues->theme;

        if (!in_array('boost', $themes, true) || in_array('ldg', $themes, true)) {
            continue;
        }

        $configdata->filtervalues->theme[] = 'ldg';

        $DB->update_record('tool_usertours_tours', (object) [
            'id' => $tour->id,
            'configdata' => json_encode($configdata),
        ]);
    }
}

/**
 * Acrescenta a acessibilidade ao menu do usuario.
 *
 * O menu do usuario e montado a partir de $CFG->customusermenuitems, no formato
 * "chave,componente|url". O tema de onde a barra veio fixava o item dentro do
 * user_menu.mustache; por configuracao e melhor - nao exige override de
 * template do core, e o administrador continua dono da lista.
 *
 * Idempotente: se a linha ja existe, nada acontece. O administrador que remover
 * o item de proposito nao o ve voltar no proximo upgrade, porque a checagem e
 * pela URL e nao pela posicao.
 *
 * @return void
 */
function theme_ldg_add_accessibility_to_user_menu() {
    $url = '/theme/ldg/accessibility.php';
    $current = (string) get_config('core', 'customusermenuitems');

    if (strpos($current, $url) !== false) {
        return;
    }

    $item = 'accessibility,theme_ldg|' . $url;
    $lines = preg_split('/\R/', trim($current), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    // Entra no TOPO, como no tema de referencia: acessibilidade que exige
    // procurar no fim de uma lista longa serve menos a quem mais precisa dela.
    array_unshift($lines, $item);

    set_config('customusermenuitems', implode("\n", $lines));
}
