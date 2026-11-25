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

namespace paygw_pagarme\privacy;

use core_privacy\local\metadata\collection;

/**
 * O que este plugin guarda, e para onde manda.
 *
 * So declara. Nao exporta nem apaga, de proposito: um registro de pagamento e
 * obrigacao fiscal do vendedor, e apagar a linha nao apagaria a cobranca no
 * Pagar.me - so faria o Moodle e o extrato discordarem.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\data_provider {
    /**
     * Descreve os dados.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('paygw_pagarme', [
            'userid' => 'privacy:metadata:paygw_pagarme:userid',
            'amount' => 'privacy:metadata:paygw_pagarme:amount',
            'currency' => 'privacy:metadata:paygw_pagarme:currency',
            'chargeid' => 'privacy:metadata:paygw_pagarme:chargeid',
            'customerid' => 'privacy:metadata:paygw_pagarme:customerid',
            'status' => 'privacy:metadata:paygw_pagarme:status',
            'timecreated' => 'privacy:metadata:paygw_pagarme:timecreated',
        ], 'privacy:metadata:paygw_pagarme');

        $collection->add_external_location_link('pagarme', [
            'name' => 'privacy:metadata:pagarme:name',
            'email' => 'privacy:metadata:pagarme:email',
            'document' => 'privacy:metadata:pagarme:document',
            'value' => 'privacy:metadata:pagarme:value',
        ], 'privacy:metadata:pagarme');

        return $collection;
    }
}
