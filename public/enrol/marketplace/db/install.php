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
 * Habilita o plugin na instalacao.
 *
 * @package    enrol_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Liga o plugin assim que ele e instalado.
 *
 * Plugins de matricula nascem DESABILITADOS no Moodle. Para um plugin que so
 * age quando existe um direito de acesso, ficar desligado nao e um estado
 * seguro - e um estado invisivel: a compra acontece, o direito e gravado, e
 * a matricula simplesmente nao ocorre, sem erro em lugar nenhum.
 *
 * Habilitar aqui evita que um ambiente novo (a VPS, por exemplo) suba com o
 * marketplace pela metade porque alguem esqueceu de marcar o olho na tela de
 * plugins de matricula.
 *
 * @return void
 */
function xmldb_enrol_marketplace_install() {
    $enabled = array_filter(explode(',', (string) get_config('moodle', 'enrol_plugins_enabled')));

    if (!in_array('marketplace', $enabled, true)) {
        $enabled[] = 'marketplace';
        set_config('enrol_plugins_enabled', implode(',', $enabled));
        core_plugin_manager::reset_caches();
    }
}
