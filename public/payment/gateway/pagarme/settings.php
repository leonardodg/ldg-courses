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
 * Configuracao de site do paygw_pagarme.
 *
 * A secao e paymentgatewaypagarme, e nao paygw_pagarme: o core monta o nome
 * assim, e errar aqui produz "Section error" numa tela que parece nao existir.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use paygw_pagarme\pagarme_client;
use paygw_pagarme\payment_processor;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'paygw_pagarme_environmentheading',
        get_string('environmentheading', 'paygw_pagarme'),
        get_string('environmentheading_desc', 'paygw_pagarme', payment_processor::webhook_url()->out(false))
    ));

    $settings->add(new admin_setting_configselect(
        'paygw_pagarme/environment',
        get_string('environment', 'paygw_pagarme'),
        get_string('environment_desc', 'paygw_pagarme'),
        pagarme_client::ENV_SANDBOX,
        [
            pagarme_client::ENV_SANDBOX => get_string('environmentsandbox', 'paygw_pagarme'),
            pagarme_client::ENV_PRODUCTION => get_string('environmentproduction', 'paygw_pagarme'),
        ]
    ));

    // Um bloco por ambiente, lado a lado. Assim a credencial de homologacao
    // nunca tem como ser usada em producao por engano, e alternar nao exige
    // redigitar nada.
    foreach ([pagarme_client::ENV_SANDBOX, pagarme_client::ENV_PRODUCTION] as $environment) {
        $label = get_string('environment' . $environment, 'paygw_pagarme');

        $settings->add(new admin_setting_heading(
            'paygw_pagarme_heading_' . $environment,
            $label,
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'paygw_pagarme/webhookuser_' . $environment,
            get_string('webhookuser', 'paygw_pagarme'),
            get_string('webhookuser_desc', 'paygw_pagarme'),
            '',
            PARAM_RAW_TRIMMED
        ));

        $settings->add(new admin_setting_configpasswordunmask(
            'paygw_pagarme/webhookpassword_' . $environment,
            get_string('webhookpassword', 'paygw_pagarme'),
            get_string('webhookpassword_desc', 'paygw_pagarme'),
            ''
        ));
    }

    $settings->add(new admin_setting_heading(
        'paygw_pagarme_chargeheading',
        get_string('chargeheading', 'paygw_pagarme'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'paygw_pagarme/paymentmethod',
        get_string('paymentmethod', 'paygw_pagarme'),
        get_string('paymentmethod_desc', 'paygw_pagarme'),
        'pix',
        [
            'pix' => get_string('methodpix', 'paygw_pagarme'),
            'boleto' => get_string('methodboleto', 'paygw_pagarme'),
            'credit_card' => get_string('methodcreditcard', 'paygw_pagarme'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_pagarme/pixexpiresin',
        get_string('pixexpiresin', 'paygw_pagarme'),
        get_string('pixexpiresin_desc', 'paygw_pagarme'),
        '30',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_pagarme/duedays',
        get_string('duedays', 'paygw_pagarme'),
        get_string('duedays_desc', 'paygw_pagarme'),
        '3',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_pagarme/documentfield',
        get_string('documentfield', 'paygw_pagarme'),
        get_string('documentfield_desc', 'paygw_pagarme'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_pagarme/publickey',
        get_string('publickey', 'paygw_pagarme'),
        get_string('publickey_desc', 'paygw_pagarme'),
        '',
        PARAM_RAW_TRIMMED
    ));
}
