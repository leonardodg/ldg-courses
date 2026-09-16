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
 * Configuracao das APLICACOES da plataforma no Mercado Pago.
 *
 * Estes valores sao do dono da plataforma, nao do vendedor: e a aplicacao
 * registrada no painel do Mercado Pago que autoriza a comissao. O token de cada
 * vendedor fica na conta de pagamento dele, obtido por OAuth - e ha um token
 * POR APLICACAO, porque cada uma exige a sua autorizacao.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use paygw_mercadopago\application;

if ($ADMIN->fulltree) {
    $callbackurl = (new moodle_url('/payment/gateway/mercadopago/oauth_callback.php'))->out(false);
    $webhookurl = (new moodle_url('/payment/gateway/mercadopago/webhook.php'))->out(false);

    $settings->add(new admin_setting_heading(
        'paygw_mercadopago/appheading',
        get_string('appheading', 'paygw_mercadopago'),
        get_string('appheading_desc', 'paygw_mercadopago', (object) [
            'callback' => $callbackurl,
            'webhook' => $webhookurl,
        ])
    ));

    // Um bloco por tipo de aplicacao, gerado da constante.
    //
    // Escrever os tres a mao convidaria a divergencia: o dia em que um tipo
    // ganhasse campo novo, faltaria em dois lugares e ninguem veria - a tela
    // simplesmente nao mostraria o campo, e o vinculo iria para uma
    // configuracao que ninguem le.
    foreach (application::TYPES as $type) {
        $rotulo = get_string('apptype_' . $type, 'paygw_mercadopago');

        // O nome da aplicacao entra em CADA rotulo, e nao so no cabecalho.
        //
        // Sao tres blocos com os mesmos tres campos: com o rotulo cru, a tela
        // teria tres "Client ID" indistinguiveis fora do contexto visual - o
        // que e ruim para quem usa leitor de tela, ambiguo para o behat, e
        // convida a colar a credencial da aplicacao errada. Errar isso nao da
        // erro: da OAuth que falha com mensagem generica.
        $nomear = static fn(string $chave): string => get_string(
            'settingforapp',
            'paygw_mercadopago',
            (object) ['setting' => get_string($chave, 'paygw_mercadopago'), 'app' => $rotulo]
        );

        $settings->add(new admin_setting_heading(
            'paygw_mercadopago/apptype_' . $type,
            $rotulo,
            get_string('apptype_' . $type . '_desc', 'paygw_mercadopago')
        ));

        $settings->add(new admin_setting_configtext(
            'paygw_mercadopago/' . application::config_key($type, 'clientid'),
            $nomear('clientid'),
            get_string('clientid_desc', 'paygw_mercadopago'),
            '',
            PARAM_ALPHANUMEXT
        ));

        // Configpasswordunmask esconde o valor na tela e no log de alteracoes.
        $settings->add(new admin_setting_configpasswordunmask(
            'paygw_mercadopago/' . application::config_key($type, 'clientsecret'),
            $nomear('clientsecret'),
            get_string('clientsecret_desc', 'paygw_mercadopago'),
            ''
        ));

        // Publica no sentido literal: vai para o HTML e qualquer um a le.
        // Por isso configtext, e nao configpasswordunmask - esconder na tela de
        // administracao um valor que aparece no fonte da pagina do aluno seria
        // teatro.
        $settings->add(new admin_setting_configtext(
            'paygw_mercadopago/' . application::config_key($type, 'publickey'),
            $nomear('publickey'),
            get_string('publickey_desc', 'paygw_mercadopago'),
            '',
            PARAM_RAW_TRIMMED
        ));

        // A assinatura secreta e POR APLICACAO, e nao do site: cada uma tem a
        // sua no painel. Uma so para todas faria a validacao do webhook
        // recusar as notificacoes das outras duas, o que aparece como venda
        // que nao entrega - o pior desfecho possivel.
        $settings->add(new admin_setting_configpasswordunmask(
            'paygw_mercadopago/' . application::config_key($type, 'webhooksecret'),
            $nomear('webhooksecret'),
            get_string('webhooksecret_desc', 'paygw_mercadopago'),
            ''
        ));
    }

    $settings->add(new admin_setting_heading(
        'paygw_mercadopago/commonheading',
        get_string('commonheading', 'paygw_mercadopago'),
        get_string('commonheading_desc', 'paygw_mercadopago')
    ));

    // O pais da aplicacao decide em que dominio o vendedor autoriza e quais
    // contas podem ser vinculadas. Nao e cosmetico: o split so acontece entre
    // contas do MESMO pais, porque a comissao cai na conta da plataforma e uma
    // conta so guarda a moeda do proprio pais. Nao ha cambio no meio.
    //
    // E e UM para as tres aplicacoes, e nao um por aplicacao: um por tipo
    // permitiria justamente a mistura de paises que o Mercado Pago recusa.
    $sites = [];
    foreach (\paygw_mercadopago\mp_client::SITE_CURRENCY as $siteid => $currency) {
        $sites[$siteid] = $siteid . ' - ' . $currency;
    }
    $settings->add(new admin_setting_configselect(
        'paygw_mercadopago/platformsite',
        get_string('platformsite', 'paygw_mercadopago'),
        get_string('platformsite_desc', 'paygw_mercadopago'),
        'MLB',
        $sites
    ));

    // Onde o aluno digita o cartao. A pagina e nossa nos TRES modos; o que
    // muda e o caminho que o numero percorre, e com ele o enquadramento do
    // projeto no PCI DSS.
    //
    // O padrao e o modo que NAO toca no cartao, e isso importa: quem instala o
    // plugin sem ler a documentacao nao pode acabar em escopo sem ter
    // escolhido isso.
    //
    // Gerado do proprio conjunto, e com o enquadramento PCI no rotulo: a
    // escolha e feita nesta tela, e o custo dela precisa estar visivel aqui, e
    // nao num documento que ninguem abre na hora de escolher.
    $modoscartao = [];
    foreach (\paygw_mercadopago\card_capture::MODES as $modo) {
        $modoscartao[$modo] = get_string('cardcapture' . $modo, 'paygw_mercadopago', (object) [
            'scope' => \paygw_mercadopago\card_capture::scope_of($modo),
        ]);
    }

    $settings->add(new admin_setting_configselect(
        'paygw_mercadopago/cardcapture',
        get_string('cardcapture', 'paygw_mercadopago'),
        get_string('cardcapture_desc', 'paygw_mercadopago')
            . (\paygw_mercadopago\card_capture::is_blocked()
                ? \html_writer::div(
                    get_string('cardcaptureblocked', 'paygw_mercadopago'),
                    'alert alert-danger mt-2'
                )
                : ''),
        \paygw_mercadopago\card_capture::MODE_BRICK,
        $modoscartao
    ));

    // Vale para o SITE inteiro, e nao por conta, porque o ambiente e uma
    // propriedade do conjunto: comprador, vendedor e aplicacao precisam estar
    // todos do mesmo lado. Uma chave por vendedor permitiria a mistura que o
    // Mercado Pago recusa.
    $settings->add(new admin_setting_configcheckbox(
        'paygw_mercadopago/testmode',
        get_string('testmode', 'paygw_mercadopago'),
        get_string('testmode_desc', 'paygw_mercadopago'),
        0
    ));

    // Nao ha campo de comissao aqui, e a ausencia e deliberada. Ele existiu,
    // nao era lido por ninguem, e o db/upgrade.php do local_marketplace ja
    // migrou o valor para local_marketplace/defaultfeepercent - que e onde a
    // comissao mora, porque ela e regra do marketplace e nao do gateway. Um
    // campo de admin que nao muda nada e pior que campo ausente: ele mente.
}
