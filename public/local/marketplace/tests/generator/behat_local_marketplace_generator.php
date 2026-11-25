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
 * Liga o gerador do marketplace ao "the following ... exist" do Behat.
 *
 * Com isto um cenario monta empresa, oferta e direito em tres linhas de tabela,
 * em vez de percorrer telas que, no caso do direito, nem existem.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_marketplace_generator extends behat_generator_base {
    /**
     * As entidades que o gherkin pode criar.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'companies' => [
                'singular' => 'company',
                'datagenerator' => 'company',
                'required' => ['shortname'],
                // O dono vira ownerid: o gherkin fala em username, e e o que
                // o cenario tem em maos depois de "the following users exist".
                'switchids' => ['user' => 'ownerid'],
            ],
            'offers' => [
                'singular' => 'offer',
                'datagenerator' => 'offer',
                'required' => ['company', 'name'],
            ],
            'entitlements' => [
                'singular' => 'entitlement',
                'datagenerator' => 'entitlement',
                'required' => ['user', 'offer'],
            ],
        ];
    }
}
