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
 * Funcoes do tema LDG.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Conteudo SCSS principal.
 *
 * Reaproveita a cadeia do Boost - que vem com o Moodle - e acrescenta os tokens
 * do design system DEPOIS, para que vencam as variaveis do pai. Nao
 * reimplementamos a cadeia dele: os import paths do compilador ja incluem
 * theme/boost/scss, entao os imports relativos de dentro do default.scss dele
 * continuam resolvendo.
 *
 * @param theme_config $theme O objeto de configuracao do tema.
 * @return string
 */
function theme_ldg_get_main_scss_content($theme) {
    global $CFG;

    require_once($CFG->dirroot . '/theme/boost/lib.php');

    $scss = theme_boost_get_main_scss_content($theme);
    $scss .= "\n" . file_get_contents($CFG->dirroot . '/theme/ldg/scss/ldg/_tokens.scss');
    $scss .= "\n" . file_get_contents($CFG->dirroot . '/theme/ldg/scss/default.scss');

    return $scss;
}

/**
 * Variaveis SCSS a prepender.
 *
 * ATENCAO: o theme_boost_get_pre_scss TAMBEM roda, e ANTES deste, recebendo a
 * config deste tema - o theme_config junta os callbacks dos pais e o proprio
 * numa lista so. Como as duas funcoes emitem '$var: valor;' sem !default, a
 * ultima atribuicao vence, e a ultima e esta. Nada a desfazer, mas toda vez que
 * este tema declarar um setting com nome igual a um do Boost e preciso
 * perguntar
 * "o callback dele vai reagir a isso?".
 *
 * @param theme_config $theme O objeto de configuracao do tema.
 * @return string
 */
function theme_ldg_get_pre_scss($theme) {
    $scss = '';

    // Raio de 8px em tudo, e "pill" so em chip. Vem antes das cores porque o
    // Bootstrap deriva raio de botao, campo e card deste valor.
    $scss .= '$border-radius: 0.5rem;' . "\n";
    $scss .= '$border-radius-sm: 0.25rem;' . "\n";
    $scss .= '$border-radius-lg: 1rem;' . "\n";
    $scss .= '$border-radius-xl: 1.5rem;' . "\n";
    $scss .= '$line-height-base: 1.6;' . "\n";
    $scss .= '$headings-font-weight: 600;' . "\n";

    // As duas paletas entram como VARIAVEL do Bootstrap, e nao como regra CSS.
    //
    // O Bootstrap gera os blocos :root e [data-bs-theme="dark"] em tempo de
    // compilacao a partir destas variaveis. Sobrescrever as custom properties
    // depois funcionaria para o que o nosso CSS usa, mas nao para o que os
    // componentes do proprio Bootstrap derivam delas - alerta, dropdown,
    // offcanvas e modal continuariam com o cinza padrao dele.

    // Modo claro. O fundo NAO e branco puro e o texto NAO e cinza: os dois
    // puxam para o azul escuro da marca (#0E192B, amostrado da band), que e o
    // que faz o azul predominar tambem na versao clara.
    $scss .= '$body-bg: #f4f6fa;' . "\n";
    $scss .= '$body-color: #0e192b;' . "\n";
    $scss .= '$body-secondary-bg: #ffffff;' . "\n";
    $scss .= '$body-secondary-color: #5a6b82;' . "\n";
    $scss .= '$body-tertiary-bg: #e9edf3;' . "\n";
    $scss .= '$body-emphasis-color: #0e192b;' . "\n";
    $scss .= '$headings-color: #0e192b;' . "\n";
    $scss .= '$border-color: #d5dce6;' . "\n";

    // No fundo claro, #007AFF da 3.6:1 - reprova no minimo de 4.5:1 da WCAG AA
    // para texto. O tom mais escuro da 5.9:1 e continua sendo o mesmo azul.
    $scss .= '$link-color: #0062cc;' . "\n";
    $scss .= '$link-hover-color: #004a99;' . "\n";

    // Modo escuro.
    $scss .= '$enable-dark-mode: true;' . "\n";
    $scss .= '$body-bg-dark: #121212;' . "\n";
    $scss .= '$body-color-dark: #ffffff;' . "\n";
    $scss .= '$body-secondary-bg-dark: #1e1e1e;' . "\n";
    $scss .= '$body-secondary-color-dark: #b0b3b8;' . "\n";
    $scss .= '$body-tertiary-bg-dark: #2a2a2a;' . "\n";
    $scss .= '$body-tertiary-color-dark: #b0b3b8;' . "\n";
    $scss .= '$body-emphasis-color-dark: #ffffff;' . "\n";
    $scss .= '$headings-color-dark: #ffffff;' . "\n";
    $scss .= '$border-color-dark: #3a3b3c;' . "\n";
    $scss .= '$border-color-translucent-dark: rgba(255, 255, 255, 0.15);' . "\n";

    // O link NAO usa o azul da marca no fundo escuro. #007AFF sobre #121212 da
    // 3.8:1 de contraste, abaixo do minimo de 4.5:1 da WCAG AA para texto. O
    // tom mais claro da 5.1:1 e continua sendo a mesma familia de cor.
    $scss .= '$link-color-dark: #3394ff;' . "\n";
    $scss .= '$link-hover-color-dark: #66aeff;' . "\n";

    $configurable = [
        // Chave do setting => [nome da variavel SCSS, ...].
        'brandcolor' => ['brand-primary', 'primary'],
        'secondarymenucolor' => 'secondary-menu-color',
        'fontsite' => 'font-family-sans-serif',
    ];

    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }

        foreach ((array) $targets as $target) {
            if ($target === 'font-family-sans-serif') {
                $scss .= '$' . $target . ': "' . $value . '", sans-serif;' . "\n";
            } else {
                $scss .= '$' . $target . ': ' . $value . ";\n";
            }
        }
    }

    // O SCSS cru do admin vem depois das variaveis do tema, de proposito: e a
    // valvula de escape de quem precisa corrigir algo sem esperar por deploy.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * SCSS extra, acrescentado no fim da compilacao.
 *
 * O setting da imagem de fundo do login se chama 'loginbg'. O nome nasceu
 * diferente do 'loginbgimg' de um tema de terceiro de quem este ja foi filho,
 * para que o callback dele encontrasse vazio e nao emitisse o CSS do fundo duas
 * vezes. A dependencia acabou, o nome ficou - trocar agora exigiria migrar o
 * arquivo ja enviado no config_plugins, e nao ha ganho nisso.
 *
 * De quebra isso contorna um bug do Moove: aquele mesmo `return ''` descarta o
 * setting 'scss' do administrador sempre que nao ha imagem de login.
 *
 * @param theme_config $theme O objeto de configuracao do tema.
 * @return string
 */
function theme_ldg_get_extra_scss($theme) {
    $content = '';

    $bg = $theme->setting_file_url('loginbg', 'loginbg');
    if (empty($bg)) {
        // A tela de login nunca nasce sem imagem: o hero embarcado e o padrao,
        // e nao um placeholder cinza que alguem precisa lembrar de trocar.
        $bg = (new moodle_url('/theme/ldg/pix/login_hero.jpg'))->out(false);
    }

    // O degrade escuro por cima nao e enfeite: sem ele o painel de login perde
    // contraste sobre as regioes claras da imagem, e o texto fica ilegivel.
    $content .= 'body.pagelayout-login #page .login-layout-left {';
    $content .= 'background-image: linear-gradient(rgba(18, 18, 18, 0.55), rgba(18, 18, 18, 0.85)),';
    $content .= " url('$bg');";
    $content .= 'background-size: cover; background-position: center; position: relative;';
    $content .= '}';

    // O SCSS cru do admin vem por ULTIMO: e a valvula de escape, tem que vencer.
    if (!empty($theme->settings->scss)) {
        $content .= "\n" . $theme->settings->scss;
    }

    return $content;
}

/**
 * CSS pre-compilado.
 *
 * Rede de seguranca para quando a compilacao do SCSS nao esta disponivel -
 * instalacao, upgrade, cache frio sem permissao de escrita. Devolve o CSS do
 * Moove, entao o site sai NO AR mas SEM os tokens da marca. E degradacao
 * proposital: pagina feia e melhor que pagina sem estilo nenhum.
 *
 * @return string
 */
function theme_ldg_get_precompiled_css() {
    global $CFG;

    // O CSS de emergencia e o do BOOST, que vem com o Moodle.
    //
    // Apontava para theme/moove/style/moodle.css, e isso deixou de ser um
    // fallback no momento em que o Moove deixou de ser dependencia: se ele nao
    // estivesse instalado, a funcao que existe para o site nao ficar sem estilo
    // seria justamente a que quebraria a pagina.
    return file_get_contents($CFG->dirroot . '/theme/boost/style/moodle.css');
}

/**
 * Preferencias de usuario que este tema pode gravar por AJAX.
 *
 * Sem esta funcao o core_user_update_user_preferences RECUSA a preferencia, e o
 * menu volta ao estado anterior no proximo carregamento - sem erro visivel na
 * tela, so um 400 no console. E o mesmo mecanismo que o theme_boost usa para
 * as suas 'drawer-open-*'.
 *
 * @return array
 */
function theme_ldg_user_preferences(): array {
    return [
        'theme_ldg-navmenu-collapsed' => [
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // As duas da barra de acessibilidade. Sem declarar aqui, o
        // core_user_update_user_preferences RECUSA a gravacao vinda do
        // navegador, e a escolha se perde no proximo carregamento.
        //
        // PARAM_ALPHANUMEXT aceita letra, numero, hifen e sublinhado - que e
        // exatamente o formato das classes (fontsize-inc-3, sitecolor-color-2)
        // e nada alem disso.
        // O alternador de modo de cor grava aqui. A chave e a mesma que o
        // util\settings::color_mode() le no servidor.
        'dark-mode-on' => [
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // Liga ou desliga a barra de acessibilidade, por usuario.
        'theme_ldg-accessibilitybar' => [
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'accessibilitystyles_fontsizeclass' => [
            'type' => PARAM_ALPHANUMEXT,
            'null' => NULL_ALLOWED,
            'default' => '',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'accessibilitystyles_sitecolorclass' => [
            'type' => PARAM_ALPHANUMEXT,
            'null' => NULL_ALLOWED,
            'default' => '',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Serve os arquivos das configuracoes do tema.
 *
 * A lista de fileareas e completa de proposito. O theme_moove_pluginfile
 * esquece o 'logodark', e o sintoma e uma logo que simplesmente nao carrega no
 * modo escuro, sem erro em log nenhum.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return mixed
 */
function theme_ldg_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    $fileareas = ['logo', 'logodark', 'favicon', 'loginbg'];

    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, $fileareas, true)) {
        $theme = \theme_ldg\util\settings::theme_config();

        // Por padrao os arquivos de tema devem poder ser cacheados por
        // navegador e por proxy.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }

        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }

    send_file_not_found();
}

/**
 * Acrescenta a acessibilidade a pagina de preferencias do usuario.
 *
 * O callback e o mecanismo nativo: o /user/preferences.php monta a tela a
 * partir dos nos que os plugins registram aqui. Entrar por ele significa que a
 * opcao aparece onde o usuario ja procura preferencia, sem inventar um menu.
 *
 * @param navigation_node $navigation No de configuracoes do usuario.
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 * @return void
 */
function theme_ldg_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext) {
    global $USER;

    // So para a propria pessoa: a barra e preferencia dela, e um administrador
    // ligando isso na conta de outro seria mexer na tela de quem nao pediu.
    if (empty($USER->id) || $USER->id != $user->id || isguestuser()) {
        return;
    }

    $navigation->add(
        get_string('accessibility', 'theme_ldg'),
        new moodle_url('/theme/ldg/accessibility.php'),
        navigation_node::TYPE_SETTING,
        null,
        'theme_ldg_accessibility'
    );
}
