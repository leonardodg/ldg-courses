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
 * Atualizacoes do paygw_mercadopago.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executa os passos de atualizacao.
 *
 * @param int $oldversion Versao instalada.
 * @return bool
 */
function xmldb_paygw_mercadopago_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090110) {
        // Foto dos termos da comissao na propria linha da cobranca.
        //
        // As cobrancas que ja existem ficam com os defaults, e isso e uma
        // aproximacao e nao um fato sobre elas: foram criadas quando a base nao
        // era configuravel. Ver docs/adr/0007-comissao-sobre-o-bruto.md.
        $table = new xmldb_table('paygw_mercadopago');

        // O Mercado Pago nunca guardou o percentual, so o valor. Sem ele a
        // linha nao explica como chegou naquele marketplace_fee.
        $field = new xmldb_field('feepercent', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0', 'feeamount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $after = 'feepercent';

        $field = new xmldb_field('feebase', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'gross', $after);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('feesource', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'site', 'feebase');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090110, 'paygw', 'mercadopago');
    }

    if ($oldversion < 2026090810) {
        // Apaga a comissao padrao que este plugin nunca leu.
        //
        // O campo existia em settings.php, nao era lido por linha nenhuma de
        // codigo, e o valor ja foi migrado para local_marketplace pelo upgrade
        // de la - a comissao e regra do marketplace, nao do gateway. Tirar so
        // da tela deixaria a linha em config_plugins para sempre, e a proxima
        // pessoa a encontrar acharia que ela significa alguma coisa.
        unset_config('defaultfeepercent', 'paygw_mercadopago');

        upgrade_plugin_savepoint(true, 2026090810, 'paygw', 'mercadopago');
    }

    if ($oldversion < 2026091600) {
        // A assinatura entra na tabela, com UMA LINHA POR CICLO.
        //
        // Cada ciclo e um pagamento proprio, com a sua comissao fotografada no
        // momento em que foi cobrado - juntar tudo numa linha so faria a
        // mudanca de comissao reescrever o passado, que e exatamente o que o
        // ADR-0007 proibe. O que liga os ciclos do mesmo aluno e o
        // subscriptionid.
        //
        // NAO ha guarda table_exists() aqui, e a ausencia e a regra: a tabela e
        // deste plugin, declarada no install.xml, entao ela existe. A guarda so
        // faria um nome errado passar calado, e o upgrade terminar com sucesso
        // sem ter criado nada. Ver docs/dev/padrao-de-implementacao.md.
        $table = new xmldb_table('paygw_mercadopago');

        // Toda linha que existe hoje veio do Checkout Pro, entao o default
        // descreve o passado com precisao - nao e aproximacao.
        $field = new xmldb_field('apptype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'preferences', 'paymentid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('subscriptionid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'apptype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('cycles', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'subscriptionid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Identificadores do Mercado Pago, e so isso. O numero do cartao nao
        // entra neste banco, e nao vai entrar: quem guarda o cartao e o
        // gateway.
        $field = new xmldb_field('mpcustomerid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'cycles');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('mpcardid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'mpcustomerid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('paymentmethod', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'mpcardid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Toda leitura de assinatura parte daqui: o ciclo seguinte, a fatura em
        // aberto e o cancelamento procuram pelo subscriptionid, e nao pelo id
        // da linha.
        $index = new xmldb_index('subscriptionid', XMLDB_INDEX_NOTUNIQUE, ['subscriptionid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026091600, 'paygw', 'mercadopago');
    }

    if ($oldversion < 2026091650) {
        // O estado da ASSINATURA, que e coisa diferente do status da cobranca.
        //
        // No Asaas, cancelar e pedir ao gateway que pare de cobrar. Aqui quem
        // cobra o ciclo somos nos, entao cancelar e parar de disparar - e isso
        // precisa estar escrito em algum lugar que a tarefa leia.
        //
        // Toda linha que existe hoje e de assinatura viva ou de venda avulsa,
        // e 'active' descreve as duas sem mentir: a avulsa nunca e consultada
        // por este campo.
        $table = new xmldb_table('paygw_mercadopago');

        $field = new xmldb_field(
            'subscriptionstatus',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'active',
            'paymentmethod'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091650, 'paygw', 'mercadopago');
    }

    return true;
}
