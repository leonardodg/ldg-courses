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
 * Servicos AJAX do paygw_pagarme.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_pagarme_create_charge' => [
        'classname' => 'paygw_pagarme\external\create_charge',
        'methodname' => 'execute',
        'description' => 'Cria a cobranca no Pagar.me e devolve para onde mandar o aluno.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'paygw_pagarme_charge_status' => [
        'classname' => 'paygw_pagarme\external\charge_status',
        'methodname' => 'execute',
        'description' => 'Situacao da cobranca, para a pagina do Pix perguntar enquanto o webhook nao chega.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'paygw_pagarme_submit_card' => [
        'classname' => 'paygw_pagarme\external\submit_card',
        'methodname' => 'execute',
        'description' => 'Recebe o token do cartao e cria a cobranca.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
