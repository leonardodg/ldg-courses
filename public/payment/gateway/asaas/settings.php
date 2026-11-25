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
 * Configuracao de site do paygw_asaas.
 *
 * Homologacao e producao vivem LADO A LADO, e nao num par de campos que muda de
 * significado conforme um interruptor. Trocar de ambiente passa a ser mudar um
 * select, sem ninguem redigitar carteira nem segredo - e, mais importante, uma
 * credencial de homologacao nunca tem como ser usada em producao por engano.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use paygw_asaas\asaas_client;
use paygw_asaas\payment_processor;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'paygw_asaas/environmentheading',
        get_string('environmentheading', 'paygw_asaas'),
        get_string('environmentheading_desc', 'paygw_asaas', payment_processor::webhook_url()->out(false))
    ));

    $settings->add(new admin_setting_configselect(
        'paygw_asaas/environment',
        get_string('environment', 'paygw_asaas'),
        get_string('environment_desc', 'paygw_asaas'),
        asaas_client::ENV_SANDBOX,
        [
            asaas_client::ENV_SANDBOX => get_string('environmentsandbox', 'paygw_asaas'),
            asaas_client::ENV_PRODUCTION => get_string('environmentproduction', 'paygw_asaas'),
        ]
    ));

    foreach ([asaas_client::ENV_SANDBOX, asaas_client::ENV_PRODUCTION] as $environment) {
        $label = get_string('environment' . $environment, 'paygw_asaas');

        $settings->add(new admin_setting_heading(
            'paygw_asaas/heading_' . $environment,
            $label,
            ''
        ));

        // A carteira da PLATAFORMA, que recebe a comissao. Nao e a do vendedor:
        // a cobranca nasce na conta dele, e o split so tira a nossa parte.
        $settings->add(new admin_setting_configtext(
            'paygw_asaas/platformwalletid_' . $environment,
            get_string('platformwalletid', 'paygw_asaas'),
            get_string('platformwalletid_desc', 'paygw_asaas'),
            '',
            PARAM_RAW_TRIMMED
        ));

        // Segredo que nos definimos ao cadastrar o webhook no painel do Asaas e
        // conferimos no header asaas-access-token. Vazio faz o webhook recusar
        // tudo: um endpoint aberto "ate alguem configurar" e a janela que
        // interessa a quem esta atacando.
        $settings->add(new admin_setting_configpasswordunmask(
            'paygw_asaas/webhooktoken_' . $environment,
            get_string('webhooktoken', 'paygw_asaas'),
            get_string('webhooktoken_desc', 'paygw_asaas'),
            ''
        ));
    }

    $settings->add(new admin_setting_heading(
        'paygw_asaas/chargeheading',
        get_string('chargeheading', 'paygw_asaas'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'paygw_asaas/billingtype',
        get_string('billingtype', 'paygw_asaas'),
        get_string('billingtype_desc', 'paygw_asaas'),
        'UNDEFINED',
        [
            'UNDEFINED' => get_string('billingundefined', 'paygw_asaas'),
            'PIX' => get_string('billingpix', 'paygw_asaas'),
            'BOLETO' => get_string('billingboleto', 'paygw_asaas'),
            'CREDIT_CARD' => get_string('billingcreditcard', 'paygw_asaas'),
        ]
    ));

    // O Asaas so aceita URL de retorno se a conta do vendedor tiver um SITE
    // cadastrado; sem isso ele recusa a cobranca inteira. O interruptor existe
    // para o caso de um vendedor nao conseguir cadastrar o dominio - ele
    // continua vendendo, e o aluno volta pela fatura em vez de voltar sozinho.
    $settings->add(new admin_setting_configcheckbox(
        'paygw_asaas/usecallback',
        get_string('usecallback', 'paygw_asaas'),
        get_string('usecallback_desc', 'paygw_asaas'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_asaas/duedays',
        get_string('duedays', 'paygw_asaas'),
        get_string('duedays_desc', 'paygw_asaas'),
        '3',
        PARAM_INT
    ));

    // O Asaas cria cliente so com nome e e-mail. O documento e opcional de
    // proposito: exigi-lo no meio da compra derrubaria a conversao por um dado
    // que o Moodle nao pede no cadastro.
    $settings->add(new admin_setting_configtext(
        'paygw_asaas/documentfield',
        get_string('documentfield', 'paygw_asaas'),
        get_string('documentfield_desc', 'paygw_asaas'),
        '',
        PARAM_ALPHANUMEXT
    ));
}
