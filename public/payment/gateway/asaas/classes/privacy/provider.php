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

namespace paygw_asaas\privacy;

use core_privacy\local\metadata\collection;

/**
 * Dados pessoais tocados pelo paygw_asaas.
 *
 * O plugin guarda transacao e manda dado do comprador para o Asaas. Nao ha
 * exportacao nem exclusao aqui de proposito: registro de pagamento e obrigacao
 * fiscal e contabil do vendedor, e apagar a linha nao apagaria a cobranca no
 * Asaas - so faria o Moodle e o extrato do banco discordarem.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\data_provider {
    /**
     * Descreve o que e guardado e o que sai do site.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('paygw_asaas', [
            'userid' => 'privacy:metadata:paygw_asaas:userid',
            'amount' => 'privacy:metadata:paygw_asaas:amount',
            'currency' => 'privacy:metadata:paygw_asaas:currency',
            'asaaspaymentid' => 'privacy:metadata:paygw_asaas:asaaspaymentid',
            'customerid' => 'privacy:metadata:paygw_asaas:customerid',
            'status' => 'privacy:metadata:paygw_asaas:status',
            'timecreated' => 'privacy:metadata:paygw_asaas:timecreated',
        ], 'privacy:metadata:paygw_asaas');

        $collection->add_external_location_link('asaas', [
            'name' => 'privacy:metadata:asaas:name',
            'email' => 'privacy:metadata:asaas:email',
            'cpfCnpj' => 'privacy:metadata:asaas:cpfcnpj',
            'value' => 'privacy:metadata:asaas:value',
        ], 'privacy:metadata:asaas');

        return $collection;
    }
}
