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
 * Upgrade do modulo de video.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Sem historico ainda: o plugin nasceu em 04/09/2026.
 *
 * O arquivo existe vazio de proposito. O Moodle nao exige db/upgrade.php, mas
 * quem chega para escrever o primeiro passo acha o lugar pronto em vez de ter
 * que descobrir o formato do savepoint.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_ldgvideo_upgrade($oldversion) {
    return true;
}
