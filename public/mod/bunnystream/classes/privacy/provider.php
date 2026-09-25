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

namespace mod_bunnystream\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
/**
 * Provider de privacidade do mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Descreve os dados pessoais guardados.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('bunnystream_progress', [
            'userid'        => 'privacy:metadata:bunnystream_progress:userid',
            'bunnystreamid' => 'privacy:metadata:bunnystream_progress:bunnystreamid',
            'max_percent'   => 'privacy:metadata:bunnystream_progress:max_percent',
            'timemodified'  => 'privacy:metadata:bunnystream_progress:timemodified',
        ], 'privacy:metadata:bunnystream_progress');

        $collection->add_external_location_link('bunny.net', [
            'guid' => 'privacy:metadata:bunny_net:guid',
        ], 'privacy:metadata:bunny_net');

        return $collection;
    }

    /**
     * Contextos onde o usuario tem dado pessoal.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modulelevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'bunnystream'
                  JOIN {bunnystream_progress} bp ON bp.bunnystreamid = cm.instance
                 WHERE bp.userid = :userid";
        $contextlist->add_from_sql($sql, ['modulelevel' => CONTEXT_MODULE, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * Usuarios com dado pessoal num contexto.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {bunnystream_progress} WHERE bunnystreamid = :id',
            ['id' => $cm->instance]
        );
    }

    /**
     * Exporta o dado pessoal de um usuario.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $row = $DB->get_record('bunnystream_progress', [
                'bunnystreamid' => $cm->instance,
                'userid' => $contextlist->get_user()->id,
            ]);
            if ($row) {
                writer::with_context($context)->export_data(
                    [get_string('modulename', 'mod_bunnystream')],
                    (object) ['max_percent' => $row->max_percent, 'timemodified' => $row->timemodified]
                );
            }
        }
    }

    /**
     * Apaga o dado pessoal de TODOS os usuarios num contexto.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
        if (!$cm) {
            return;
        }
        $DB->delete_records('bunnystream_progress', ['bunnystreamid' => $cm->instance]);
    }

    /**
     * Apaga o dado pessoal de UM usuario.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('bunnystream_progress', ['bunnystreamid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Apaga o dado pessoal de uma LISTA de usuarios.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['bid'] = $cm->instance;
        $DB->delete_records_select('bunnystream_progress', "bunnystreamid = :bid AND userid {$insql}", $params);
    }
}
