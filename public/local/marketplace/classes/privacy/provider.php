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

namespace local_marketplace\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Dados pessoais guardados pelo marketplace.
 *
 * Duas tabelas guardam userid: quem vende por uma empresa, e o que cada aluno
 * comprou. As duas vivem no contexto do PROPRIO usuario - nao no da empresa -
 * porque sao fatos sobre a pessoa, e e assim que o pedido de dados dela os
 * alcanca.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Descreve o que e guardado.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_marketplace_member',
            [
                'companyid' => 'privacy:metadata:member:companyid',
                'userid' => 'privacy:metadata:member:userid',
                'memberrole' => 'privacy:metadata:member:memberrole',
                'timecreated' => 'privacy:metadata:member:timecreated',
            ],
            'privacy:metadata:member'
        );

        $collection->add_database_table(
            'local_marketplace_entitlement',
            [
                'userid' => 'privacy:metadata:entitlement:userid',
                'offerid' => 'privacy:metadata:entitlement:offerid',
                'companyid' => 'privacy:metadata:entitlement:companyid',
                'timestart' => 'privacy:metadata:entitlement:timestart',
                'timeend' => 'privacy:metadata:entitlement:timeend',
                'status' => 'privacy:metadata:entitlement:status',
                'cycles' => 'privacy:metadata:entitlement:cycles',
            ],
            'privacy:metadata:entitlement'
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

        $has = $DB->record_exists('local_marketplace_member', ['userid' => $userid])
            || $DB->record_exists('local_marketplace_entitlement', ['userid' => $userid]);

        if ($has) {
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
     * Exporta.
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

            $memberships = $DB->get_records('local_marketplace_member', ['userid' => $userid]);
            if ($memberships) {
                $rows = [];
                foreach ($memberships as $m) {
                    $company = $DB->get_record('local_marketplace_company', ['id' => $m->companyid], 'name');
                    $rows[] = (object) [
                        'company' => $company ? $company->name : $m->companyid,
                        'role' => $m->memberrole,
                        'since' => \core_privacy\local\request\transform::datetime($m->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_marketplace'), get_string('members', 'local_marketplace')],
                    (object) ['memberships' => $rows]
                );
            }

            $entitlements = $DB->get_records('local_marketplace_entitlement', ['userid' => $userid]);
            if ($entitlements) {
                $rows = [];
                foreach ($entitlements as $e) {
                    $offer = $DB->get_record('local_marketplace_offer', ['id' => $e->offerid], 'name');
                    $rows[] = (object) [
                        'offer' => $offer ? $offer->name : $e->offerid,
                        'start' => \core_privacy\local\request\transform::datetime($e->timestart),
                        'end' => $e->timeend
                            ? \core_privacy\local\request\transform::datetime($e->timeend)
                            : get_string('accesslifetime', 'local_marketplace'),
                        'status' => $e->status,
                        'payments' => $e->cycles,
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_marketplace'), get_string('mysubscriptions', 'local_marketplace')],
                    (object) ['entitlements' => $rows]
                );
            }
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

        $DB->delete_records('local_marketplace_member', ['userid' => $context->instanceid]);
        $DB->delete_records('local_marketplace_entitlement', ['userid' => $context->instanceid]);
    }

    /**
     * Apaga os dados de uma pessoa.
     *
     * Apagar o direito de acesso remove o acesso: o enrol_marketplace trabalha
     * por diferenca e vai desmatricular na proxima execucao. E o comportamento
     * correto de um pedido de exclusao, mas precisa estar dito - a pessoa perde
     * os cursos que comprou.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || $context->instanceid != $userid) {
                continue;
            }
            $DB->delete_records('local_marketplace_member', ['userid' => $userid]);
            $DB->delete_records('local_marketplace_entitlement', ['userid' => $userid]);
        }
    }

    /**
     * Apaga os dados de uma lista de pessoas.
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
            $DB->delete_records('local_marketplace_member', ['userid' => $userid]);
            $DB->delete_records('local_marketplace_entitlement', ['userid' => $userid]);
        }
    }
}
