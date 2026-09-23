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
 * Barra de secoes da landing de parceiros.
 *
 * Faz duas coisas, e as duas por medicao e nao por valor fixo:
 *
 * 1. Publica a altura do cabecalho FIXO do tema em --ldgp-sticky-top. A altura
 *    muda por tema, e no theme_ldg muda tambem entre celular e desktop.
 *    Hardcodar 64px quebra no Moove e quebra no celular do ldg.
 *
 * 2. Marca a secao em vista com aria-current. Por IntersectionObserver, e nao
 *    por evento de rolagem: o observer nao roda a cada pixel.
 *
 * O sticky em si e CSS. Este modulo so alimenta o numero de que o CSS precisa -
 * se ele nao carregar, a barra continua grudando, so que no topo do viewport.
 *
 * NAO ha transpilador neste projeto: este arquivo precisa ser AMD de verdade.
 *
 * @module     local_partners/sectionbar
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Mede o cabecalho fixo e publica a altura para o CSS.
     *
     * @returns {void}
     */
    var measureHeader = function() {
        var bar = document.querySelector('.ldgp-bar');

        if (!bar) {
            return;
        }

        var header = document.querySelector('.navbar.fixed-top');
        var height = header ? Math.round(header.getBoundingClientRect().height) : 0;

        bar.style.setProperty('--ldgp-sticky-top', height + 'px');

        // O alvo da ancora tambem desconta, e ele nao esta dentro da barra.
        var page = document.querySelector('.ldgp');
        if (page) {
            page.style.setProperty('--ldgp-sticky-top', height + 'px');
        }
    };

    /**
     * Liga o marcador de secao em vista.
     *
     * @returns {void}
     */
    var observeSections = function() {
        var links = Array.prototype.slice.call(document.querySelectorAll('.ldgp-bar-link[href^="#"]'));

        if (!links.length || !window.IntersectionObserver) {
            return;
        }

        var byId = {};
        var sections = [];

        links.forEach(function(link) {
            var id = link.getAttribute('href').slice(1);
            var section = document.getElementById(id);

            if (section) {
                byId[id] = link;
                sections.push(section);
            }
        });

        var mark = function(id) {
            links.forEach(function(link) {
                link.removeAttribute('aria-current');
            });

            if (byId[id]) {
                // "true", e nao "page": sao ancoras dentro da mesma pagina, e
                // "page" diria ao leitor de tela que aquele link e a pagina
                // atual do site.
                byId[id].setAttribute('aria-current', 'true');
            }
        };

        var observer = new window.IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    mark(entry.target.id);
                }
            });
        }, {
            // A faixa de deteccao fica na parte de cima da tela, logo abaixo da
            // barra: sem isto, duas secoes visiveis ao mesmo tempo brigam pela
            // marcacao e ela pisca durante a rolagem.
            rootMargin: '-20% 0px -70% 0px',
            threshold: 0
        });

        sections.forEach(function(section) {
            observer.observe(section);
        });
    };

    /**
     * Debounce simples, para o redimensionamento.
     *
     * @param {Function} fn
     * @param {number} wait
     * @returns {Function}
     */
    var debounce = function(fn, wait) {
        var timer = null;

        return function() {
            window.clearTimeout(timer);
            timer = window.setTimeout(fn, wait);
        };
    };

    return {
        /**
         * @returns {void}
         */
        init: function() {
            measureHeader();
            observeSections();

            window.addEventListener('resize', debounce(measureHeader, 150));
        }
    };
});
