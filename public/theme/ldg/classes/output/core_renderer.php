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

namespace theme_ldg\output;

use moodle_url;
use theme_ldg\util\settings;

/**
 * Renderer principal do tema LDG.
 *
 * Herda do BOOST, que e core. A lista abaixo ja foi descrita como "os metodos
 * em que o Moove faz theme_config::load('moove') fixo", e isso deixou de valer
 * quando o tema saiu da heranca dele: nao ha mais pai de terceiro para ler
 * setting errado. Hoje cada override existe por conta propria.
 *
 * Sao DEZ, em tres grupos:
 *
 * MARCA - o Boost nao tem esses settings, entao nao ha o que herdar
 *   get_theme_logo_url()        logo, com fallback para a embarcada em pix/
 *   get_theme_logo_dark_url()   logo do modo escuro
 *   favicon()                   favicon proprio
 *   should_display_logo()       decide o ramo do navbar.mustache
 *   should_display_theme_logo() idem
 *   get_logo()                  idem
 *   get_logo_dark()             idem
 *
 * MODO DE COR - o Boost decide por preferencia de usuario, e visitante anonimo
 * nao tem preferencia
 *   body_attributes()           a classe, o data-bs-theme e o modo embutido
 *   render_darkmode_controls()  o botao de alternar
 *
 * MARCA NO <head>
 *   standard_head_html()        Google Analytics e a fonte do Google Fonts.
 *                               CHAMA o do Boost antes de acrescentar - sem
 *                               isso o core perde o que poe no head.
 *
 * Os quatro do grupo da marca que decidem ramo de template andam JUNTOS. Sem
 * eles o navbar.mustache cai no ramo de "nao ha logo" e imprime o nome do site
 * em texto - o sintoma parece "sumiu a logo", e ninguem procura no renderer.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * HTML do <head>, com o Google Analytics e a fonte deste tema.
     *
     * CHAMA o do Boost antes de acrescentar. Nao e detalhe: o core poe no head
     * coisas de que a pagina depende, e trocar esta chamada por um return
     * proprio quebraria em silencio.
     *
     * A chamada e explicita (\theme_boost\...::standard_head_html()) em vez de
     * parent::, e assim ficou de proposito: quando este tema herdava do Moove
     * era preciso PULAR um degrau, porque o theme_moove/fontsite ja nasce com
     * 'Roboto' gravado na instalacao e o site baixaria duas fontes do Google
     * aplicando a errada. Hoje o Boost e o pai direto e as duas formas seriam
     * equivalentes - a explicita continua por dizer de qual degrau se trata.
     *
     * @return string
     */
    public function standard_head_html() {
        $output = \theme_boost\output\core_renderer::standard_head_html();

        $theme = settings::theme_config();

        if (!empty($theme->settings->googleanalytics)) {
            $gacode = trim($theme->settings->googleanalytics);
            $output .= "<script async src='https://www.googletagmanager.com/gtag/js?id=" . s($gacode) . "'></script>
                        <script>
                            window.dataLayer = window.dataLayer || [];
                            function gtag() {
                                dataLayer.push(arguments);
                            }
                            gtag('js', new Date());
                            gtag('config', '" . s($gacode) . "');
                        </script>";
        }

        $sitefont = $theme->settings->fontsite ?? 'Inter';

        if ($sitefont !== 'Moodle') {
            // O preconnect economiza um RTT no handshake com o Google Fonts, e
            // essa fonte esta no caminho critico do primeiro render.
            $output .= '<link rel="preconnect" href="https://fonts.googleapis.com">
                        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                        <link href="https://fonts.googleapis.com/css2?family=' . urlencode($sitefont) .
                ':ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">';
        }

        return $output;
    }

    /**
     * Atributos da tag <body>.
     *
     * Refeito, e nao estendido, porque o metodo monta a string inteira no
     * return - nao ha como injetar classe nem atributo sem reescrever a string.
     *
     * E porque o modo de cor aqui NAO sai de preferencia de usuario sozinha.
     * Visitante anonimo nao tem preferencia, e a landing e a tela de login
     * sairiam claras para todo o publico de captacao - justamente quem o design
     * escuro foi feito para atender. Quem decide e
     * \theme_ldg\util\settings::color_mode(), que cai no padrao do site quando
     * nao ha escolha gravada.
     *
     * @param string|array $additionalclasses Classes extras para o body.
     * @return string
     */
    public function body_attributes($additionalclasses = []) {
        if (!is_array($additionalclasses)) {
            $additionalclasses = explode(' ', $additionalclasses);
        }

        // A barra de acessibilidade e OPT-IN.
        //
        // Ela ja foi sempre visivel aqui, e o resultado era uma faixa fixa
        // acima da navbar em toda pagina, para todo mundo. Barra permanente
        // ocupa altura util e some com a hierarquia do topo; quem precisa do
        // recurso liga uma vez e ele fica.
        //
        // Visitante anonimo nao tem preferencia, e portanto nao ve a barra -
        // esta certo: nao ha onde guardar a escolha dele.
        if (!empty(get_user_preferences('theme_ldg-accessibilitybar', 0))) {
            $additionalclasses[] = 'hasaccessibilitybar';
        }

        // O valor vem do PARAM_ALPHANUMEXT declarado em theme_ldg_user_prefe-
        // rences(), entao ja chega limitado a letra, numero, hifen e sublinhado
        // - nao da para injetar atributo pelo nome da classe.
        foreach (['accessibilitystyles_fontsizeclass', 'accessibilitystyles_sitecolorclass'] as $preference) {
            $class = get_user_preferences($preference, '');

            if ($class) {
                $additionalclasses[] = $class;
            }
        }

        // Pagina aberta DENTRO de um quadro perde o cabecalho, o menu e o
        // rodape - o formato de curso LDG embute a atividade, e ter o site
        // inteiro de novo dentro dele e sofrivel.
        //
        // A deteccao e pelo Sec-Fetch-Dest, que o navegador manda em TODA
        // navegacao cujo destino e um quadro, inclusive nos cliques dados
        // dentro dele. Um parametro na URL resolveria a primeira carga e se
        // perderia no primeiro link - o cabecalho voltaria no meio do quiz.
        //
        // O parametro fica como reserva para navegador sem Fetch Metadata. Ele
        // e so uma dica de aparencia: nao libera nada, nao esconde nada que a
        // permissao ja nao esconda, entao vir do cliente nao e problema.
        $destino = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';

        if ($destino === 'iframe' || optional_param('ldgembed', 0, PARAM_BOOL)) {
            $additionalclasses[] = 'ldg-embedded';
        }

        $settings = new settings();
        $colormode = $settings->color_mode();

        // Uma classe so, e o data-bs-theme abaixo.
        //
        // Havia tambem uma 'moove-darkmode' aqui, porque o darkmode.js do Moove
        // decidia o estado do botao olhando para ela. O colormode.js deste tema
        // le o data-bs-theme, que e o que o Bootstrap 5 de fato consulta -
        // entao a classe extra virou codigo morto.
        $additionalclasses[] = 'ldg-' . $colormode;

        return " id='{$this->body_id()}' class='{$this->body_css_classes($additionalclasses)}'" .
            " data-bs-theme='{$colormode}' ";
    }

    /**
     * URL da logo principal.
     *
     * Cai na logo embarcada em pix/ quando nao ha upload. Sem esse fallback o
     * tema nasce sem marca nenhuma: o Moove desce para a logo do site, que
     * tambem esta vazia, e o navbar renderiza um <img> com src vazio - um
     * icone de imagem quebrada ao lado do nome do site.
     *
     * @return string
     */
    public function get_theme_logo_url() {
        $theme = settings::theme_config();

        $logo = $theme->setting_file_url('logo', 'logo');

        if (!empty($logo)) {
            return $logo;
        }

        return (new moodle_url('/theme/ldg/pix/logo.svg'))->out(false);
    }

    /**
     * URL da logo do modo escuro.
     *
     * O navbar e escuro nos DOIS modos, entao a logo embarcada e a mesma: um
     * wordmark branco. Sao dois arquivos, e nao um, para que trocar a logo do
     * modo escuro no futuro nao exija tocar em codigo.
     *
     * @return string
     */
    public function get_theme_logo_dark_url() {
        $theme = settings::theme_config();

        $logo = $theme->setting_file_url('logodark', 'logodark');

        if (!empty($logo)) {
            return $logo;
        }

        return (new moodle_url('/theme/ldg/pix/logo_dark.svg'))->out(false);
    }

    /**
     * URL do favicon.
     *
     * @return moodle_url
     */
    public function favicon() {
        global $CFG;

        $theme = settings::theme_config();

        $favicon = $theme->setting_file_url('favicon', 'favicon');

        if (!empty($favicon)) {
            // O setting_file_url devolve URL absoluta; o core espera relativa
            // ao wwwroot para nao quebrar quando o site responde por mais de um
            // dominio - que e exatamente o caso aqui, com dominio por vendedor.
            $urlreplace = preg_replace('|^https?://|i', '//', $CFG->wwwroot);
            $favicon = str_replace($urlreplace, '', $favicon);

            return new moodle_url($favicon);
        }

        return \theme_boost\output\core_renderer::favicon();
    }

    /**
     * Botao de alternar entre claro e escuro.
     *
     * O Boost nao tem botao de modo de cor, entao nao ha o que estender - este
     * metodo existe por conta propria, comandado por
     * theme_ldg/enablecolormodetoggle.
     *
     * So para usuario autenticado: a escolha e gravada como preferencia de
     * usuario, e visitante anonimo nao tem onde guardar. Para ele vale o padrao
     * do site. Mostrar o botao para quem nao pode guardar a escolha seria um
     * controle que se desfaz na proxima pagina.
     *
     * @return string
     */
    public function render_darkmode_controls() {
        if (!isloggedin() || isguestuser()) {
            return '';
        }

        $settings = new settings();

        if (!$settings->color_mode_toggle_enabled()) {
            return '';
        }

        // O botao e de um icone so, e o icone diz para onde o clique leva. Sem
        // saber o modo atual ele sairia sempre com o mesmo desenho, e o
        // aria-pressed sairia sempre "false" - mentira para quem usa leitor de
        // tela numa pagina que ja esta escura.
        return $this->render_from_template('theme_ldg/colormode', [
            'isdark' => $settings->color_mode() === 'dark',
        ]);
    }

    /**
     * Ha logo para mostrar na navbar?
     *
     * O Boost nao tem este metodo - ele vinha do tema anterior por heranca, e
     * sumiu junto com ela. Sem ele o navbar.mustache cai no ramo de "nao ha
     * logo" e imprime o NOME DO SITE em texto. O sintoma pareceu "a logo
     * sumiu", quando na verdade era o metodo que nao existia mais.
     *
     * Anda em conjunto com should_display_theme_logo(), get_logo() e
     * get_logo_dark(): remover um so ja derruba o ramo do template.
     *
     * @return bool
     */
    public function should_display_logo() {
        return $this->should_display_theme_logo() || parent::should_display_navbar_logo();
    }

    /**
     * O tema tem logo propria configurada?
     *
     * @return bool
     */
    public function should_display_theme_logo() {
        return !empty($this->get_theme_logo_url());
    }

    /**
     * A logo para fundo claro.
     *
     * A do tema vence a do site: quem subiu logo aqui quer a dela.
     *
     * @return string|bool
     */
    public function get_logo() {
        $logo = $this->get_theme_logo_url();

        if ($logo) {
            return $logo;
        }

        $logo = $this->get_logo_url();

        return $logo ? $logo->out(false) : false;
    }

    /**
     * A logo para fundo escuro.
     *
     * Cai na clara quando nao ha variante escura - melhor uma logo com contraste
     * ruim do que nenhuma.
     *
     * @return string|bool
     */
    public function get_logo_dark() {
        $logo = $this->get_theme_logo_dark_url();

        return $logo ?: $this->get_logo();
    }
}
