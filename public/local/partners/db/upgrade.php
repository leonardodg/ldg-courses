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
 * Atualizacoes do local_partners.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executa os passos de atualizacao.
 *
 * @param int $oldversion Versao instalada.
 * @return bool
 */
function xmldb_local_partners_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090100) {
        $table = new xmldb_table('local_partners_application');

        // Quem enviou, quando veio de alguem autenticado. Toda candidatura que
        // ja existe fica com userid nulo, que e a verdade: elas vieram do
        // formulario publico, antes de este caminho existir.
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'companyid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('confirmtoken', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timeconfirmed', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'confirmtoken');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('confirmtoken', XMLDB_INDEX_NOTUNIQUE, ['confirmtoken']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // As candidaturas que ja estao na fila NAO viram 'unconfirmed'. Elas
        // entraram quando confirmar nao era exigido, e retroagir a regra
        // esconderia da fila gente que esta esperando resposta.

        upgrade_plugin_savepoint(true, 2026090100, 'local', 'partners');
    }

    if ($oldversion < 2026091000) {
        $table = new xmldb_table('local_partners_application');

        // O terceiro campo do construtor depois do default e o ANTERIOR, e ele
        // precisa bater com a ordem do install.xml - senao o
        // check_database_schema acusa a divergencia no proximo ambiente novo.
        $field = new xmldb_field('country', XMLDB_TYPE_CHAR, '2', null, null, null, null, 'planid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('learnersband', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'country');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('termsaccepted', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'learnersband');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // As tres ficam NULAS nas candidaturas que ja existem, e e de proposito.
        // Pais e faixa ninguem declarou; o aceite, principalmente, nao se
        // retroage: preencher com 1 seria inventar um consentimento que ninguem
        // deu, e com 0 seria registrar uma recusa que tambem nao houve.

        upgrade_plugin_savepoint(true, 2026091000, 'local', 'partners');
    }

    return true;
}
