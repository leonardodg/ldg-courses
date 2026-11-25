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
 * Servicos web do marketplace.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_marketplace_get_offers' => [
        'classname' => 'local_marketplace\external\get_offers',
        'methodname' => 'execute',
        'description' => 'Ofertas publicadas de uma empresa, para pagina de venda externa.',
        'type' => 'read',
        'ajax' => true,
        // Exige token. O vendedor gera o dele e usa na pagina externa; sem
        // isso qualquer um varreria o catalogo inteiro da plataforma.
        'loginrequired' => true,
    ],
];

$services = [
    'Marketplace storefront' => [
        'functions' => ['local_marketplace_get_offers'],
        'restrictedusers' => 1,
        'enabled' => 0,
        'shortname' => 'marketplace_storefront',
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
