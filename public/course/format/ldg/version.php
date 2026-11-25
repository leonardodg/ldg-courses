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
 * Versao do format_ldg.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'format_ldg';
$plugin->release      = '0.1.0';
$plugin->version      = 2026090318;
$plugin->requires     = 2026042000;
$plugin->supported    = [502, 502];

// BETA, e nao STABLE. A tela do portal esta completa, com 19 testes de unidade
// e 6 cenarios de behat passando, mas nada disso foi visto num NAVEGADOR ainda,
// e o celular nao foi testado no aparelho. STABLE se declara depois de alguem
// usar, e nao depois de o teste passar.
$plugin->maturity     = MATURITY_BETA;
