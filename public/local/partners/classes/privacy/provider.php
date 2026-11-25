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

namespace local_partners\privacy;

use context;
use core\context\system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_partners\application;

/**
 * Provedor de privacidade do local_partners.
 *
 * A candidatura guarda nome, e-mail, telefone e o IP de quem enviou. Ela chega
 * por dois caminhos, e a privacidade e diferente em cada um:
 *
 * - VISITANTE ANONIMO: nao ha userid a que associar, entao esse dado nao entra
 *   na exportacao de ninguem. Quem o remove e a tarefa purge_unconfirmed, para
 *   o que nunca foi confirmado, e o administrador, para o resto.
 * - USUARIO AUTENTICADO: a candidatura grava o userid de quem enviou, e a
 *   partir dai ela E dado pessoal dele - exportavel e apagavel.
 *
 * Alem desses, aparece quem REVISOU. Apagar a revisao tira so a autoria: a fila
 * e registro do negocio e nao some porque um revisor pediu remocao.
 *
 * @package    local_partners
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
        $collection->add_database_table('local_partners_application', [
            'companyname' => 'privacy:metadata:application:companyname',
            'cnpj' => 'privacy:metadata:application:cnpj',
            'contactname' => 'privacy:metadata:application:contactname',
            'contactemail' => 'privacy:metadata:application:contactemail',
            'contactphone' => 'privacy:metadata:application:contactphone',
            'message' => 'privacy:metadata:application:message',
            'country' => 'privacy:metadata:application:country',
            'learnersband' => 'privacy:metadata:application:learnersband',
            'termsaccepted' => 'privacy:metadata:application:termsaccepted',
            'submitterip' => 'privacy:metadata:application:submitterip',
            'userid' => 'privacy:metadata:application:userid',
            'reviewerid' => 'privacy:metadata:application:reviewerid',
            'timecreated' => 'privacy:metadata:application:timecreated',
        ], 'privacy:metadata:application');

        return $collection;
    }

    /**
     * Contextos em que o usuario tem dado.
     *
     * A fila vive no contexto do sistema: a candidatura ainda nao tem categoria
     * nem curso a que pertencer.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $has = $DB->record_exists_select(
            'local_partners_application',
            'reviewerid = :reviewerid OR userid = :userid',
            ['reviewerid' => $userid, 'userid' => $userid]
        );

        if ($has) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Usuarios com dado num contexto.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }

        $userlist->add_from_sql(
            'reviewerid',
            'SELECT reviewerid FROM {local_partners_application} WHERE reviewerid IS NOT NULL',
            []
        );

        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_partners_application} WHERE userid IS NOT NULL',
            []
        );
    }

    /**
     * Exporta o que o usuario revisou.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \core\context\system) {
                continue;
            }

            $records = $DB->get_records('local_partners_application', ['reviewerid' => $userid]);

            foreach ($records as $record) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:reviews', 'local_partners'), $record->id],
                    (object) [
                        'companyname' => $record->companyname,
                        'status' => $record->status,
                        'reviewnote' => $record->reviewnote,
                        'timereviewed' => $record->timereviewed
                            ? \core_privacy\local\request\transform::datetime($record->timereviewed)
                            : null,
                    ]
                );
            }

            // O que ele ENVIOU. Sai completo, e nao resumido como a revisao: a
            // revisao e o trabalho dele sobre o dado de outra pessoa, esta e a
            // propria pessoa pedindo de volta o que digitou.
            $sent = $DB->get_records('local_partners_application', ['userid' => $userid]);

            foreach ($sent as $record) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:applications', 'local_partners'), $record->id],
                    (object) [
                        'companyname' => $record->companyname,
                        'cnpj' => $record->cnpj,
                        'contactname' => $record->contactname,
                        'contactemail' => $record->contactemail,
                        'contactphone' => $record->contactphone,
                        'website' => $record->website,
                        'message' => $record->message,
                        'status' => $record->status,
                        'country' => $record->country,
                        'learnersband' => $record->learnersband,
                        'termsaccepted' => $record->termsaccepted
                            ? \core_privacy\local\request\transform::datetime($record->termsaccepted)
                            : null,
                        'submitterip' => $record->submitterip,
                        'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    ]
                );
            }
        }
    }

    /**
     * Desfaz o vinculo entre um usuario e as candidaturas que ele enviou.
     *
     * Nao e um delete simples porque as candidaturas nao sao todas iguais:
     *
     * - AINDA NAO APROVADA: nada no site depende dela. A linha inteira sai, que
     *   e o que a pessoa pediu.
     * - APROVADA: existe uma EMPRESA criada a partir dela, com categoria,
     *   cursos e vendas. Apagar a linha apagaria a origem de um vinculo
     *   comercial que continua existindo. Fica a linha, sem o dado pessoal:
     *   nome, e-mail, telefone, mensagem e IP saem; razao social, CNPJ e a
     *   empresa criada ficam, porque sao dados da PESSOA JURIDICA e nao de quem
     *   preencheu o formulario.
     *
     * Pelo mesmo criterio, PAIS e FAIXA DE ALUNOS ficam: descrevem a operacao,
     * e nao a pessoa.
     *
     * O ACEITE DOS TERMOS tambem fica, e por uma razao mais forte: ele e o
     * registro de um ato juridico. Apaga-lo destruiria a prova de que a empresa
     * consentiu - justamente o documento que a lei de protecao de dados espera
     * que exista. Quem apagar isto por engano nao vai descobrir o estrago no
     * dia em que apagar.
     *
     * @param string $select Trecho de WHERE que identifica os usuarios.
     * @param array $params
     * @return void
     */
    private static function forget_submitters(string $select, array $params): void {
        global $DB;

        $DB->delete_records_select(
            'local_partners_application',
            "($select) AND status <> :approved",
            $params + ['approved' => application::STATUS_APPROVED]
        );

        $DB->execute(
            'UPDATE {local_partners_application}
                SET userid = NULL, contactname = :name, contactemail = :email,
                    contactphone = NULL, message = NULL, submitterip = NULL, confirmtoken = NULL
              WHERE ' . $select,
            $params + ['name' => '', 'email' => '']
        );
    }

    /**
     * Remove a autoria de todas as revisoes de um contexto.
     *
     * A candidatura NAO e apagada: ela e registro do negocio, e apagar a fila
     * porque um revisor pediu remocao dos dados dele destruiria o historico de
     * quem se candidatou. So o vinculo com o revisor sai.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof \core\context\system) {
            return;
        }

        $DB->set_field_select('local_partners_application', 'reviewerid', null, 'reviewerid IS NOT NULL');

        self::forget_submitters('userid IS NOT NULL', []);
    }

    /**
     * Remove a autoria das revisoes de um usuario.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \core\context\system) {
                $userid = $contextlist->get_user()->id;

                $DB->set_field('local_partners_application', 'reviewerid', null, [
                    'reviewerid' => $userid,
                ]);

                self::forget_submitters('userid = :userid', ['userid' => $userid]);
            }
        }
    }

    /**
     * Remove a autoria das revisoes de varios usuarios.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }

        $userids = $userlist->get_userids();

        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->set_field_select('local_partners_application', 'reviewerid', null, "reviewerid $insql", $params);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'sub');

        self::forget_submitters("userid $insql", $params);
    }
}
