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
 * Entrada do marketplace na administracao do site.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('root', new admin_category(
        'local_marketplace_cat',
        get_string('pluginname', 'local_marketplace')
    ), 'users');

    $settings = new admin_settingpage(
        'local_marketplace_settings',
        get_string('settings', 'local_marketplace')
    );

    // A comissao padrao mora AQUI, e nao nas settings de um gateway.
    //
    // Ate a versao anterior o nucleo lia get_config('paygw_mercadopago', ...),
    // o que fazia a comissao do marketplace inteiro depender de um plugin de
    // fornecedor estar instalado - e nao tinha resposta definida quando
    // houvesse tres gateways, cada um com o proprio padrao.
    $settings->add(new admin_setting_configtext(
        'local_marketplace/defaultfeepercent',
        get_string('defaultfeepercent', 'local_marketplace'),
        get_string('defaultfeepercent_desc', 'local_marketplace'),
        '25',
        PARAM_FLOAT
    ));

    // Base de calculo da comissao, para quem nao declarar a propria.
    //
    // E o ultimo degrau: plano e empresa podem ter base propria, e quando tem,
    // ela vem junto com a taxa deles. Este valor vale para o degrau que so
    // definiu a taxa e para a venda que nao passa por empresa nenhuma.
    //
    // ATENCAO: "liquido" nao e aplicavel em todo gateway. O Mercado Pago cobra
    // por valor absoluto (marketplace_fee) e a taxa dele so e conhecida depois,
    // entao ali a venda sai sobre o bruto e registra isso. O que a venda grava
    // e o que foi APLICADO, e nao o que esta configurado aqui.
    $settings->add(new admin_setting_configselect(
        'local_marketplace/commissionbase',
        get_string('commissionbase', 'local_marketplace'),
        get_string('commissionbase_desc', 'local_marketplace'),
        \local_marketplace\commission::BASE_GROSS,
        [
            \local_marketplace\commission::BASE_GROSS => get_string('commissionbasegross', 'local_marketplace'),
            \local_marketplace\commission::BASE_NET => get_string('commissionbasenet', 'local_marketplace'),
        ]
    ));

    // Pais em que a primeira conta de uma empresa nova e provisionada. Nao
    // limita nada: a empresa pode receber conta em outros paises depois, e e a
    // OFERTA que diz onde cada plano vende.
    $countries = [];
    foreach (\local_marketplace\country::codes() as $code) {
        $countries[$code] = \local_marketplace\country::describe($code);
    }
    $settings->add(new admin_setting_configselect(
        'local_marketplace/defaultcountry',
        get_string('defaultcountry', 'local_marketplace'),
        get_string('defaultcountry_desc', 'local_marketplace'),
        \local_marketplace\country::DEFAULT_COUNTRY,
        $countries
    ));

    // Chave de CONTA da Bunny, da plataforma - so serve para criar libraries
    // novas (Frente B, ADR-0014). Nao e a chave de nenhuma library: aquela
    // fica cifrada em local_marketplace_library, uma por empresa. Vazia,
    // create_video_library() fica em espera e a empresa nasce sem library -
    // e o estado de hoje, antes de a conta Bunny existir.
    $settings->add(new admin_setting_encryptedpassword(
        'local_marketplace/bunnyaccountapikey',
        get_string('bunnyaccountapikey', 'local_marketplace'),
        get_string('bunnyaccountapikey_desc', 'local_marketplace')
    ));

    $ADMIN->add('local_marketplace_cat', $settings);

    // Os planos vem antes das empresas na lista de propositio: e neles que se
    // define a comissao padrao de uma classe de empresas, e o cadastro de
    // empresa ja oferece a escolha.
    $ADMIN->add('local_marketplace_cat', new admin_externalpage(
        'local_marketplace_plans',
        get_string('plans', 'local_marketplace'),
        new moodle_url('/local/marketplace/admin/plans.php'),
        'local/marketplace:manageall'
    ));

    $ADMIN->add('local_marketplace_cat', new admin_externalpage(
        'local_marketplace_companies',
        get_string('companies', 'local_marketplace'),
        new moodle_url('/local/marketplace/admin/companies.php'),
        'local/marketplace:createcompany'
    ));
}
