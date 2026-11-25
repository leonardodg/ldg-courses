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
 * Atualizacoes do theme_ldg.
 *
 * @package    theme_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executa os passos de atualizacao.
 *
 * @param int $oldversion Versao instalada.
 * @return bool
 */
function xmldb_theme_ldg_upgrade($oldversion) {
    if ($oldversion < 2026090200) {
        // O forcedarkmode deixou de existir quando os dois modos de cor viraram
        // de primeira classe, substituido por defaultcolormode mais
        // enablecolormodetoggle. A linha antiga ficou no config_plugins: nao
        // faz nada, mas aparece na configuracao exportada e engana quem for
        // depurar por que o tema esta escuro.
        unset_config('forcedarkmode', 'theme_ldg');

        upgrade_plugin_savepoint(true, 2026090200, 'theme', 'ldg');
    }

    if ($oldversion < 2026090210) {
        // Os dois passos do install.php, para o ambiente que JA tem o tema.
        //
        // O install.php so roda em instalacao nova: sem repetir aqui, producao
        // e o ambiente local ficariam sem o tour e sem o item de acessibilidade
        // no menu - e nada indicaria o motivo.
        require_once(__DIR__ . '/install.php');

        theme_ldg_register_in_user_tours();
        theme_ldg_add_accessibility_to_user_menu();

        upgrade_plugin_savepoint(true, 2026090210, 'theme', 'ldg');
    }

    return true;
}
