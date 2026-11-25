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
 * Atualizacoes do paygw_asaas.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executa os passos de atualizacao.
 *
 * @param int $oldversion Versao instalada.
 * @return bool
 */
function xmldb_paygw_asaas_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090110) {
        // Foto dos termos da comissao na propria linha da cobranca.
        //
        // As cobrancas que ja existem ficam com os defaults, e isso e uma
        // aproximacao e nao um fato sobre elas: foram criadas quando a base nao
        // era configuravel. Ver docs/adr/0007-comissao-sobre-o-bruto.md.
        $table = new xmldb_table('paygw_asaas');

        $after = 'feeamount';

        $field = new xmldb_field('feebase', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'gross', $after);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('feesource', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'site', 'feebase');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090110, 'paygw', 'asaas');
    }

    if ($oldversion < 2026090910) {
        // A assinatura que gerou a cobranca.
        //
        // Cada ciclo continua sendo uma LINHA propria - cada um e um pagamento
        // com a sua comissao, e juntar tudo numa linha so faria o relatorio
        // perder de vista quanto entrou em cada mes. Este campo e o que liga as
        // linhas, e o que permite ao webhook do ciclo 2 em diante descobrir de
        // quem e uma cobranca que nunca vimos.
        $table = new xmldb_table('paygw_asaas');

        $field = new xmldb_field('subscriptionid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'asaaspaymentid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('subscriptionid', XMLDB_INDEX_NOTUNIQUE, ['subscriptionid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026090910, 'paygw', 'asaas');
    }

    return true;
}
