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
 * Upgrade do paygw_pagarme.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Aplica os passos de upgrade.
 *
 * @param int $oldversion Versao instalada.
 * @return bool
 */
function xmldb_paygw_pagarme_upgrade($oldversion) {
    // O plugin nasceu em 2026090900. Passos entram aqui a partir da primeira
    // mudanca de schema depois disso - db/install.php so roda em instalacao
    // nova, e nao alcanca quem ja tem o plugin.
    return true;
}
