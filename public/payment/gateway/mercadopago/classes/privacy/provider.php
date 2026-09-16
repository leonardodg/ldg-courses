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

namespace paygw_mercadopago\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Dados pessoais guardados pelo gateway.
 *
 * A tabela de transacoes guarda userid, e o plugin ENVIA dados ao Mercado Pago
 * - declarado a parte, porque sai do controle do site: apagar a linha daqui nao
 * apaga nada do lado deles.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Descreve o que e guardado e o que e enviado.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'paygw_mercadopago',
            [
                'userid' => 'privacy:metadata:paygw_mercadopago:userid',
                'amount' => 'privacy:metadata:paygw_mercadopago:amount',
                'currency' => 'privacy:metadata:paygw_mercadopago:currency',
                'status' => 'privacy:metadata:paygw_mercadopago:status',
                'mppaymentid' => 'privacy:metadata:paygw_mercadopago:mppaymentid',
                // Os dois sao IDENTIFICADORES devolvidos pelo Mercado Pago, e
                // nao o instrumento. O numero do cartao nao esta nesta tabela,
                // nao esta em tabela nenhuma deste plugin, e nao vai estar -
                // quem guarda o cartao e o gateway. Esta declarado aqui porque
                // e exatamente o tipo de decisao que alguem desfaz por engano
                // seis meses depois.
                'mpcustomerid' => 'privacy:metadata:paygw_mercadopago:mpcustomerid',
                'mpcardid' => 'privacy:metadata:paygw_mercadopago:mpcardid',
                'subscriptionid' => 'privacy:metadata:paygw_mercadopago:subscriptionid',
                'cycles' => 'privacy:metadata:paygw_mercadopago:cycles',
                'timecreated' => 'privacy:metadata:paygw_mercadopago:timecreated',
            ],
            'privacy:metadata:paygw_mercadopago'
        );

        $collection->add_external_location_link(
            'mercadopago.com',
            [
                'amount' => 'privacy:metadata:mercadopago:amount',
                'currency' => 'privacy:metadata:mercadopago:currency',
                'itemname' => 'privacy:metadata:mercadopago:itemname',
            ],
            'privacy:metadata:mercadopago'
        );

        return $collection;
    }

    /**
     * Contextos com dados desta pessoa.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if ($DB->record_exists('paygw_mercadopago', ['userid' => $userid])) {
            $contextlist->add_user_context($userid);
        }

        return $contextlist;
    }

    /**
     * Quem tem dados num contexto.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }

        $userlist->add_user($context->instanceid);
    }

    /**
     * Exporta as transacoes.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || $context->instanceid != $userid) {
                continue;
            }

            $records = $DB->get_records('paygw_mercadopago', ['userid' => $userid], 'timecreated');
            if (!$records) {
                continue;
            }

            $rows = [];
            foreach ($records as $r) {
                $rows[] = (object) [
                    'date' => transform::datetime($r->timecreated),
                    'amount' => $r->amount,
                    'currency' => $r->currency,
                    'status' => $r->status,
                    'mercadopagoid' => $r->mppaymentid,
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'paygw_mercadopago')],
                (object) ['payments' => $rows]
            );
        }
    }

    /**
     * Apaga tudo de um contexto.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_user) {
            return;
        }

        $DB->delete_records('paygw_mercadopago', ['userid' => $context->instanceid]);
    }

    /**
     * Apaga as transacoes de uma pessoa.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && $context->instanceid == $userid) {
                $DB->delete_records('paygw_mercadopago', ['userid' => $userid]);
            }
        }
    }

    /**
     * Apaga as transacoes de uma lista de pessoas.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('paygw_mercadopago', ['userid' => $userid]);
        }
    }
}
