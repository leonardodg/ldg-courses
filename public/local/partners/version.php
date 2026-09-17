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
 * Captacao de empresas parceiras: landing publica e fila de candidaturas.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_partners';
// O bump nao e so pelo passo de banco: styles.css de plugin nao e invalidado
// pelo purge_caches, e so a versao nova faz o CSS editado chegar a tela.
$plugin->version   = 2026091701;
$plugin->requires  = 2026042000; // Moodle 5.2.
$plugin->supported = [502, 502];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// Depende do nucleo para ler os planos e, na aprovacao, para provisionar a
// empresa. NAO declara o theme_ldg: a dependencia entre os dois e do TEMA para
// ca, e e opcional - o tema testa class_exists e segue sem a landing.
$plugin->dependencies = [
    'local_marketplace' => 2026083110,
];
