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
 * Configuracoes do local_partners.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_partners_settings', get_string('pluginname', 'local_partners'));

    $settings->add(new admin_setting_configcheckbox(
        'local_partners/enablelanding',
        get_string('enablelanding', 'local_partners'),
        get_string('enablelanding_desc', 'local_partners'),
        1
    ));

    // Limite de taxa. E a camada de anti-spam que SEMPRE vale: nao depende de
    // chave de terceiro nem de o administrador lembrar de configurar nada.
    $settings->add(new admin_setting_configtext(
        'local_partners/maxperhour',
        get_string('maxperhour', 'local_partners'),
        get_string('maxperhour_desc', 'local_partners'),
        3,
        PARAM_INT
    ));

    // Confirmacao de e-mail para quem NAO esta autenticado.
    //
    // E a defesa anti-robo que as outras nao substituem: limite de taxa e
    // captcha custam tempo ao robo, esta custa um endereco de e-mail REAL e
    // funcional por candidatura. Depende de o site conseguir enviar e-mail -
    // com SMTP quebrado, ligar isto trava a fila inteira.
    //
    // Nao vale para usuario autenticado: o site ja confirmou o e-mail dele.
    $settings->add(new admin_setting_configcheckbox(
        'local_partners/requireemailconfirmation',
        get_string('requireemailconfirmation', 'local_partners'),
        get_string('requireemailconfirmation_desc', 'local_partners'),
        1
    ));

    // Prazo de validade da candidatura que nunca foi confirmada.
    //
    // Sem isto, todo envio anonimo abandonado - robo que passou pelo captcha,
    // pessoa que errou o proprio e-mail - fica guardado para sempre com nome,
    // telefone e IP. Zero desliga a limpeza.
    $settings->add(new admin_setting_configtext(
        'local_partners/unconfirmedretentiondays',
        get_string('unconfirmedretentiondays', 'local_partners'),
        get_string('unconfirmedretentiondays_desc', 'local_partners'),
        \local_partners\task\purge_unconfirmed::DEFAULT_DAYS,
        PARAM_INT
    ));

    // Interruptor do reCAPTCHA.
    //
    // O captcha e o DO CORE: elemento 'recaptcha' do moodleform, chaves globais
    // em $CFG->recaptchapublickey/privatekey e a recaptchalib_v2. Este setting
    // nao reimplementa nada - so decide se este formulario o usa.
    //
    // Existe porque as chaves sao do SITE inteiro: sem um interruptor local,
    // desligar o captcha aqui obrigaria a apagar chave que a tela de cadastro
    // de usuario do Moodle tambem usa.
    $settings->add(new admin_setting_configcheckbox(
        'local_partners/enablerecaptcha',
        get_string('enablerecaptcha', 'local_partners'),
        get_string(
            \local_partners\api::recaptcha_keys_present() ? 'enablerecaptcha_desc' : 'enablerecaptcha_nokeys',
            'local_partners',
            (new moodle_url('/admin/settings.php', ['section' => 'manageauths']))->out()
        ),
        1
    ));

    // Descoberta por buscador. Os dois valores sao PROPRIOS DE CADA PROPRIEDADE
    // cadastrada no Google, e nao do codigo: trocar de propriedade nao pode
    // exigir deploy.
    $settings->add(new admin_setting_configtext(
        'local_partners/searchconsoletoken',
        get_string('searchconsoletoken', 'local_partners'),
        get_string('searchconsoletoken_desc', 'local_partners'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_partners/analyticsid',
        get_string('analyticsid', 'local_partners'),
        get_string('analyticsid_desc', 'local_partners'),
        '',
        PARAM_ALPHANUMEXT
    ));

    // Documentos legais do rodape. Cada link so aparece quando tem destino:
    // link legal que nao leva a lugar nenhum e pior que link ausente, porque ele
    // PROMETE um documento. O de termos cai na politica do site quando existir.
    foreach (['termsurl' => 'footerterms', 'privacyurl' => 'footerprivacy', 'cookiesurl' => 'footercookies'] as $name => $label) {
        $settings->add(new admin_setting_configtext(
            'local_partners/' . $name,
            get_string($name, 'local_partners'),
            get_string($name . '_desc', 'local_partners', get_string($label, 'local_partners')),
            '',
            PARAM_URL
        ));
    }

    $ADMIN->add('localplugins', $settings);

    // A escolha da home fica na tela de Configuracoes da pagina inicial, junto
    // das opcoes do Moodle, e nao escondida nas configuracoes deste plugin: e
    // ali que o administrador vai procurar.
    //
    // Nao da para acrescentar uma opcao a lista nativa de conteudo da home.
    // O admin_setting_courselist_frontpage monta as escolhas em load_choices()
    // com constantes fixas (FRONTPAGENEWS, FRONTPAGEALLCOURSELIST e companhia)
    // e nao expoe hook nenhum - estender aquilo exigiria editar o core, que e
    // decisao fechada contra. Entao o campo e nosso, e vive ao lado dos deles.
    //
    // O locate() e feito com guarda: a arvore de administracao e montada em
    // ordem, e um ambiente que carregue este arquivo antes do
    // admin/settings/frontpage.php simplesmente nao ganha o campo, em vez de
    // derrubar a tela inteira com "call to a member function on null".
    $frontpage = $ADMIN->locate('frontpagesettings');

    if ($frontpage instanceof admin_settingpage) {
        $frontpage->add(new admin_setting_configselect(
            'local_partners/frontpagemode',
            get_string('frontpagemode', 'local_partners'),
            get_string('frontpagemode_desc', 'local_partners'),
            'default',
            [
                'default' => get_string('frontpagemodedefault', 'local_partners'),
                'landing' => get_string('frontpagemodelanding', 'local_partners'),
            ]
        ));
    }

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_partners_applications',
        get_string('applications', 'local_partners'),
        new moodle_url('/local/partners/admin/applications.php'),
        'local/partners:review'
    ));
}
