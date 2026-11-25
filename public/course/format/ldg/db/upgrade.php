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
 * Atualizacao do format_ldg.
 *
 * O db/install.xml so roda em INSTALACAO NOVA. Quem ja tem o plugin instalado -
 * inclusive o ambiente local onde ele nasceu sem tabela nenhuma - depende
 * inteiramente daqui.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executa a atualizacao.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_format_ldg_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090300) {
        $table = new xmldb_table('format_ldg_lesson');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        $table->add_index('cmid', XMLDB_INDEX_UNIQUE, ['cmid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026090300, 'format', 'ldg');
    }

    if ($oldversion < 2026090306) {
        // O usermodified saiu, e nao por economia de coluna.
        //
        // Ele e uma referencia a user, e o teste de privacidade do core cobra
        // metadata de toda tabela que tenha uma: "the following tables with
        // user fields must be covered with metadata providers". O plugin se
        // declara null_provider - "nao guardo dado pessoal" - e as duas coisas
        // nao podem ser verdade ao mesmo tempo.
        //
        // Entre declarar a metadata e remover a coluna, remover e o que casa
        // com o que a tabela E: ela guarda a duracao do VIDEO, por cmid, e nao
        // o tempo de ninguem. Nenhuma linha de PHP jamais leu ou escreveu este
        // campo - ele nasceu junto com o esqueleto da tabela.
        //
        // A chave estrangeira sai ANTES do campo: dropar o campo com a chave de
        // pe deixa a chave apontando para o nada, e o MariaDB recusa.
        $table = new xmldb_table('format_ldg_lesson');

        $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $dbman->drop_key($table, $key);

        $field = new xmldb_field('usermodified');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090306, 'format', 'ldg');
    }

    return true;
}
