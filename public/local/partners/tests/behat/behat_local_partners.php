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
 * Passos de teste da captacao de parceiros.
 *
 * @package    local_partners
 * @category   test
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * O que so o navegador prova sobre estas paginas.
 *
 * A promessa central desta reforma - "renderiza igual sob theme_boost,
 * theme_moove e theme_ldg, e cabe num celular" - nao aparece em teste nenhum de
 * servidor. O HTML sai identico em qualquer largura e em qualquer tema; o que
 * muda e o que o navegador calcula a partir do CSS.
 *
 * Sete defeitos desta reforma so apareceram medindo: o miolo preso em 720px, a
 * moldura do tema em volta, o texto do botao saindo azul, o botao com 110px de
 * altura, oito campos numa coluna so, a coluna com 644px num viewport de 390, e
 * o docblock do template virando paragrafo na tela. Nenhum quebrou um teste.
 * Estes passos sao a rede para a proxima vez.
 *
 * @package    local_partners
 * @category   test
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_partners extends behat_base {
    /**
     * Os elementos indicados ficam em uma coluna so.
     *
     * A afirmacao e sobre POSICAO, e nao sobre classe de CSS: todos comecam na
     * mesma coordenada horizontal e descem na vertical. Conferir se a media
     * query "existe" nao prova que ela venceu.
     *
     * @Then /^the "(?P<selector_string>(?:[^"]|\\")*)" elements should be stacked in one column$/
     *
     * @param string $selector Seletor CSS.
     * @return void
     */
    public function the_elements_should_be_stacked_in_one_column(string $selector): void {
        $boxes = $this->measure_boxes($selector);
        $lefts = array_unique(array_column($boxes, 'left'));

        if (count($lefts) !== 1) {
            throw new ExpectationException(
                sprintf(
                    'Esperava "%s" em uma coluna, e os elementos comecam em %d posicoes horizontais: %s.',
                    $selector,
                    count($lefts),
                    implode(', ', $lefts)
                ),
                $this->getSession()
            );
        }
    }

    /**
     * Os elementos indicados ficam lado a lado, no numero de colunas pedido.
     *
     * @Then /^the "(?P<selector_string>(?:[^"]|\\")*)" elements should sit in (?P<count_number>\d+) columns$/
     *
     * @param string $selector Seletor CSS.
     * @param int $expected Numero de colunas.
     * @return void
     */
    public function the_elements_should_sit_in_columns(string $selector, int $expected): void {
        $boxes = $this->measure_boxes($selector);
        $columns = count(array_unique(array_column($boxes, 'left')));

        if ($columns !== (int) $expected) {
            throw new ExpectationException(
                sprintf('Esperava %d colunas em "%s", e encontrei %d.', $expected, $selector, $columns),
                $this->getSession()
            );
        }
    }

    /**
     * A pagina nao rola na horizontal.
     *
     * E o defeito mais comum de porte de layout para celular, e o que nenhum
     * outro teste pega: a pagina "funciona", so que o visitante precisa
     * arrastar para o lado para ler.
     *
     * A comparacao e com a largura de CADA elemento, e nao com o scrollWidth do
     * documento: um overflow:hidden em qualquer ancestral esconde o estouro do
     * scrollWidth e deixa o defeito passar.
     *
     * @Then the page should not scroll sideways
     * @return void
     */
    public function the_page_should_not_scroll_sideways(): void {
        $overflow = $this->evaluate_script(
            '(function() {'
            . 'var width = document.documentElement.clientWidth;'
            . 'var worst = [];'
            . 'var all = document.querySelectorAll(".ldgp, .ldgp *");'
            // Container que rola na horizontal DE PROPOSITO - a lista de
            // ancoras da barra no celular - tem filhos que passam da tela por
            // desenho. Quem precisa caber e o container; medir os filhos dele
            // acusaria falha onde ha uma decisao.
            . 'var isDeliberate = function(el) {'
            . '  for (var p = el.parentElement; p && p !== document.body; p = p.parentElement) {'
            . '    var ox = window.getComputedStyle(p).overflowX;'
            . '    if (ox === "auto" || ox === "scroll") { return true; }'
            . '  }'
            . '  return false;'
            . '};'
            . 'for (var i = 0; i < all.length; i++) {'
            . '  var r = all[i].getBoundingClientRect();'
            . '  if (r.width > 0 && Math.round(r.right) > width + 1 && !isDeliberate(all[i])) {'
            . '    worst.push(all[i].className + " ate " + Math.round(r.right));'
            . '  }'
            . '}'
            . 'return {width: width, worst: worst.slice(0, 5)};'
            . '})()'
        );

        if (!empty($overflow['worst'])) {
            throw new ExpectationException(
                sprintf(
                    'A pagina tem %dpx de largura e algo passa disso: %s.',
                    $overflow['width'],
                    implode(' | ', $overflow['worst'])
                ),
                $this->getSession()
            );
        }
    }

    /**
     * A barra de secoes gruda logo abaixo do cabecalho ao rolar.
     *
     * E o unico jeito de pegar "o sticky parou de funcionar porque um ancestral
     * ganhou overflow: hidden" - falha que nao produz erro, nao produz log, e
     * so aparece para quem rola a pagina.
     *
     * @Then the section bar should stick below the header
     * @return void
     */
    public function the_section_bar_should_stick_below_the_header(): void {
        $measured = $this->evaluate_script(
            '(function() {'
            . 'var bar = document.querySelector(".ldgp-bar");'
            . 'if (!bar) { return null; }'
            . 'var header = document.querySelector(".navbar.fixed-top");'
            . 'var headerheight = header ? header.getBoundingClientRect().height : 0;'
            . 'return {top: bar.getBoundingClientRect().top, header: headerheight};'
            . '})()'
        );

        if ($measured === null) {
            throw new ExpectationException('Nao ha barra de secoes nesta pagina.', $this->getSession());
        }

        // Dois pixels de folga: o navegador arredonda a posicao quando a pagina
        // esta a meio caminho de um pixel logico.
        if (abs($measured['top'] - $measured['header']) > 2) {
            throw new ExpectationException(
                sprintf(
                    'A barra deveria parar em %dpx, logo abaixo do cabecalho, e parou em %dpx.',
                    round($measured['header']),
                    round($measured['top'])
                ),
                $this->getSession()
            );
        }
    }

    /**
     * Todo alvo de toque tem pelo menos a altura pedida.
     *
     * @Then /^every "(?P<selector_string>(?:[^"]|\\")*)" touch target should be at least (?P<pixels_number>\d+) pixels tall$/
     *
     * @param string $selector Seletor CSS.
     * @param int $minimum Altura minima em pixels.
     * @return void
     */
    public function every_touch_target_should_be_at_least_pixels_tall(string $selector, int $minimum): void {
        $heights = array_column($this->measure_boxes($selector), 'height');
        $smallest = min($heights);

        if ($smallest < (int) $minimum) {
            throw new ExpectationException(
                sprintf('O menor alvo "%s" tem %dpx de altura, e o minimo e %dpx.', $selector, $smallest, $minimum),
                $this->getSession()
            );
        }
    }

    /**
     * O campo-armadilha esta fora da tela.
     *
     * A regra que o esconde ja morou no tema, e por isso o campo aparecia para
     * todo visitante sob theme_boost e theme_moove. Este passo roda nos tres
     * temas justamente por causa disso.
     *
     * @Then the honeypot field should be off screen
     * @return void
     */
    public function the_honeypot_field_should_be_off_screen(): void {
        $left = $this->evaluate_script(
            '(function() {'
            . 'var el = document.querySelector("#fitem_id_fax") || document.querySelector(".local-partners-honeypot");'
            . 'if (!el) { return null; }'
            . 'return el.getBoundingClientRect().left;'
            . '})()'
        );

        if ($left === null) {
            throw new ExpectationException('Nao encontrei o campo-armadilha nesta pagina.', $this->getSession());
        }

        if ($left > -1000) {
            throw new ExpectationException(
                sprintf(
                    'O campo-armadilha esta em %dpx da esquerda, ou seja, visivel. '
                    . 'A regra que o esconde nao chegou a este tema.',
                    round($left)
                ),
                $this->getSession()
            );
        }
    }

    /**
     * A pagina esta no modo de cor indicado.
     *
     * @Then /^the partner page should be in "(?P<mode_string>dark|light)" mode$/
     *
     * @param string $expected 'dark' ou 'light'.
     * @return void
     */
    public function the_partner_page_should_be_in_mode(string $expected): void {
        $mode = $this->evaluate_script(
            '(function() {'
            . 'var el = document.querySelector(".ldgp");'
            . 'return el ? el.getAttribute("data-bs-theme") : null;'
            . '})()'
        );

        if ($mode !== $expected) {
            throw new ExpectationException(
                sprintf('Esperava a pagina em modo "%s", e ela esta em "%s".', $expected, (string) $mode),
                $this->getSession()
            );
        }
    }

    /**
     * O navegador carregou os componentes JS do Bootstrap?
     *
     * NAO e checagem de tag no HTML: pergunta ao RequireJS se o modulo chegou a
     * ser definido, ou seja, se ele baixou e EXECUTOU nesta pagina.
     *
     * Existe porque a landing servida na raiz ficou sem ele. O
     * theme_boost/loader e carregado por cada TEMPLATE de layout do Boost, e o
     * theme_ldg/landing - escrito do zero para a raiz nao duplicar o cromo -
     * nao trouxe o bloco. O data-bs-toggle do seletor de idioma e so um
     * atributo: sem este modulo ninguem liga comportamento a ele, e o menu nao
     * abre. A MESMA pagina em /local/partners/index.php funcionava, porque
     * aquela usa o layout 'embedded', que traz o bloco.
     *
     * O clique no proprio seletor seria a prova mais direta, e nao esta ao
     * alcance: o menu so e renderizado com mais de um idioma instalado, e o
     * site do behat tem so o ingles.
     *
     * @Then the page should have the Bootstrap components loaded
     * @return void
     */
    public function the_page_should_have_the_bootstrap_components_loaded(): void {
        $loaded = $this->evaluate_script(
            '(function() {'
            . 'if (typeof require !== "function" || typeof require.defined !== "function") {'
            . 'return "sem-requirejs";'
            . '}'
            . 'return require.defined("theme_boost/loader") ? "sim" : "nao";'
            . '})()'
        );

        if ($loaded !== 'sim') {
            throw new ExpectationException(
                sprintf(
                    'O theme_boost/loader nao esta carregado nesta pagina (%s), '
                    . 'entao nenhum componente do Bootstrap responde - dropdown de idioma inclusive.',
                    (string) $loaded
                ),
                $this->getSession()
            );
        }
    }

    /**
     * Quantos elementos casam com o seletor.
     *
     * Existe por causa da DUPLICIDADE de cromo: a home servia a landing e o
     * layout do tema montava a propria navbar e o proprio rodape por cima dela,
     * entao o visitante via duas barras e dois rodapes, um dentro do outro. O
     * HTML estava correto nos dois; o defeito era haver dois.
     *
     * @Then /^I should see exactly (?P<count_number>\d+) "(?P<selector_string>(?:[^"]|\\")*)" elements$/
     * @param int $expected
     * @param string $selector
     * @return void
     */
    public function i_should_see_exactly_elements(int $expected, string $selector): void {
        $total = $this->evaluate_script(
            'document.querySelectorAll(' . json_encode($selector) . ').length'
        );

        if ((int) $total !== $expected) {
            throw new ExpectationException(
                sprintf('Esperava %d elementos "%s", e a pagina tem %d.', $expected, $selector, $total),
                $this->getSession()
            );
        }
    }

    /**
     * O elemento ocupa a largura inteira da janela.
     *
     * A landing e de sangria total. Dentro do container de leitura do Boost ela
     * saia com faixas do fundo do TEMA dos dois lados - numa pagina escura sob
     * um tema claro, aquilo parece defeito de carregamento.
     *
     * A tolerancia de 2px cobre o arredondamento da barra de rolagem.
     *
     * @Then /^the "(?P<selector_string>(?:[^"]|\\")*)" element should span the full viewport width$/
     * @param string $selector
     * @return void
     */
    public function the_element_should_span_the_full_viewport_width(string $selector): void {
        $box = $this->measure_boxes($selector)[0];

        // A medida e contra o BODY, e nao contra o documentElement: o Chrome do
        // Selenium desenha barra de rolagem classica, de 15px, e ela entra na
        // largura do documentElement mas nao na do body. Comparar com o
        // documentElement acusaria 15px de falta numa pagina que ocupa tudo.
        $width = (int) $this->evaluate_script('document.body.clientWidth');

        if (abs($box['width'] - $width) > 2) {
            throw new ExpectationException(
                sprintf(
                    'O "%s" tem %dpx numa janela de %dpx - deveria ocupar a largura toda.',
                    $selector,
                    $box['width'],
                    $width
                ),
                $this->getSession()
            );
        }
    }

    /**
     * O elemento nao passa de uma altura.
     *
     * Serve para a barra do celular: ela pode quebrar em linhas, mas nao pode
     * virar um bloco que come um terco da primeira dobra.
     *
     * @Then /^the "(?P<selector_string>(?:[^"]|\\")*)" element should be at most (?P<pixels_number>\d+) pixels tall$/
     * @param string $selector
     * @param int $maximum
     * @return void
     */
    public function the_element_should_be_at_most_pixels_tall(string $selector, int $maximum): void {
        $box = $this->measure_boxes($selector)[0];

        if ($box['height'] > $maximum) {
            throw new ExpectationException(
                sprintf('O "%s" tem %dpx de altura, e o limite e %d.', $selector, $box['height'], $maximum),
                $this->getSession()
            );
        }
    }

    /**
     * O fundo do elemento e escuro, mesmo com a pagina em claro.
     *
     * O rodape e a barra de controles do cadastro sao superficies de MOLDURA e
     * nao acompanham o modo de cor. Medir a luminancia e o unico jeito de
     * provar isso sem fixar um hexadecimal no teste, que quebraria a cada ajuste
     * de paleta.
     *
     * @Then /^the "(?P<selector_string>(?:[^"]|\\")*)" element should have a dark background$/
     * @param string $selector
     * @return void
     */
    public function the_element_should_have_a_dark_background(string $selector): void {
        $luminance = $this->evaluate_script(
            '(function() {'
            . 'var el = document.querySelector(' . json_encode($selector) . ');'
            . 'if (!el) { return null; }'
            . 'var color = window.getComputedStyle(el).backgroundColor;'
            . 'var n = color.match(/[\\d.]+/g);'
            . 'if (!n || n.length < 3) { return null; }'
            // As tres primeiras casas de rgb() e de color(srgb ...). O srgb vem
            // em 0..1, e o rgb em 0..255 - a normalizacao cobre os dois.
            . 'var v = n.slice(0, 3).map(function(x) { x = parseFloat(x); return x <= 1 ? x * 255 : x; });'
            . 'return (0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]) / 255;'
            . '})()'
        );

        if ($luminance === null || $luminance > 0.25) {
            throw new ExpectationException(
                sprintf(
                    'O fundo de "%s" tem luminancia %s - nao e uma superficie escura.',
                    $selector,
                    var_export($luminance, true)
                ),
                $this->getSession()
            );
        }
    }

    /**
     * Mede a caixa de cada elemento que casa com o seletor.
     *
     * @param string $selector
     * @return array
     */
    protected function measure_boxes(string $selector): array {
        // ENVOLVIDO NUMA FUNCAO de proposito: o evaluate_script avalia uma
        // EXPRESSAO, e nao um bloco. Declarar const ali devolve
        // "Unexpected token 'const'" vindo do Chrome, longe da causa.
        $boxes = $this->evaluate_script(
            '(function() {'
            . 'var all = document.querySelectorAll(' . json_encode($selector) . ');'
            . 'var output = [];'
            . 'for (var i = 0; i < all.length; i++) {'
            . '  var r = all[i].getBoundingClientRect();'
            . '  output.push({left: Math.round(r.left), top: Math.round(r.top),'
            . '              width: Math.round(r.width), height: Math.round(r.height)});'
            . '}'
            . 'return output;'
            . '})()'
        );

        if (empty($boxes)) {
            throw new ExpectationException(
                sprintf('Nao ha nenhum elemento "%s" nesta pagina.', $selector),
                $this->getSession()
            );
        }

        return $boxes;
    }
}
