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
 * Barra de acessibilidade: tamanho de fonte e contraste.
 *
 * Derivado do accessibilitybar do theme_moove, com duas diferencas que valem:
 *
 * 1. NAO usa jQuery. O original dependia dele so para achar elemento e mexer em
 *    classe, e o DOM nativo faz as duas coisas.
 * 2. NAO tem web service proprio. O Moove criou theme_moove_fontsize e
 *    theme_moove_sitecolor para calcular a classe no servidor; aqui a conta e
 *    feita no navegador e a preferencia vai pelo core_user/repository, que e o
 *    mesmo caminho que o navmenu deste tema ja usa. Dois web services a menos
 *    para declarar, versionar e proteger.
 *
 * A aparencia continua sendo decidida por CSS: este modulo so troca classe no
 * body e grava a escolha.
 *
 * @module     theme_ldg/accessibilitybar
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_user/repository'], function(UserRepository) {

    // Os limites existem porque o SCSS so define classes ate estes degraus.
    // Passar deles geraria uma classe sem regra nenhuma, e o botao pararia de
    // responder sem erro visivel.
    var MAX_STEP = 6;

    var PREF_FONTSIZE = 'accessibilitystyles_fontsizeclass';
    var PREF_SITECOLOR = 'accessibilitystyles_sitecolorclass';

    var FONTSIZE_PATTERN = /^fontsize-(inc|dec)-([1-6])$/;

    /**
     * Le o degrau atual de tamanho de fonte a partir da classe no body.
     *
     * O estado vive no DOM, e nao numa variavel do modulo: o servidor ja
     * renderiza a classe, entao ler dali dispensa sincronizar dois lugares.
     *
     * @returns {number} Negativo diminui, positivo aumenta, zero e o padrao.
     */
    var currentStep = function() {
        var found = 0;

        document.body.classList.forEach(function(name) {
            var match = FONTSIZE_PATTERN.exec(name);

            if (match) {
                found = (match[1] === 'inc' ? 1 : -1) * parseInt(match[2], 10);
            }
        });

        return found;
    };

    /**
     * Remove as classes que casam com um padrao.
     *
     * @param {RegExp} pattern
     * @returns {void}
     */
    var clearMatching = function(pattern) {
        Array.prototype.slice.call(document.body.classList).forEach(function(name) {
            if (pattern.test(name)) {
                document.body.classList.remove(name);
            }
        });
    };

    /**
     * Aplica um degrau de tamanho de fonte e guarda a escolha.
     *
     * @param {number} step
     * @returns {void}
     */
    var applyFontSize = function(step) {
        var bounded = Math.max(-MAX_STEP, Math.min(MAX_STEP, step));
        var name = '';

        clearMatching(FONTSIZE_PATTERN);

        if (bounded !== 0) {
            name = 'fontsize-' + (bounded > 0 ? 'inc' : 'dec') + '-' + Math.abs(bounded);
            document.body.classList.add(name);
        }

        updateButtons(bounded);
        UserRepository.setUserPreference(PREF_FONTSIZE, name);
    };

    /**
     * Desabilita o botao que ja chegou no limite.
     *
     * Sem isto o usuario continua clicando e nada acontece - o pior tipo de
     * resposta, porque parece defeito.
     *
     * @param {number} step
     * @returns {void}
     */
    var updateButtons = function(step) {
        var dec = document.getElementById('fontsize_dec');
        var inc = document.getElementById('fontsize_inc');

        if (dec) {
            dec.classList.toggle('disabled', step <= -MAX_STEP);
        }

        if (inc) {
            inc.classList.toggle('disabled', step >= MAX_STEP);
        }
    };

    /**
     * Aplica um contraste e guarda a escolha.
     *
     * @param {string} action Uma das sitecolor-color-N, ou 'reset'.
     * @returns {void}
     */
    var applySiteColor = function(action) {
        var name = action === 'reset' ? '' : action;

        clearMatching(/^sitecolor-color-/);

        if (name) {
            document.body.classList.add(name);
        }

        UserRepository.setUserPreference(PREF_SITECOLOR, name);
    };

    /**
     * Liga os cliques.
     *
     * @param {string} selector
     * @param {Function} handler
     * @returns {void}
     */
    var bind = function(selector, handler) {
        document.querySelectorAll(selector).forEach(function(element) {
            element.addEventListener('click', function(event) {
                event.preventDefault();
                handler(element.getAttribute('data-action'));
            });
        });
    };

    return {
        init: function() {
            bind('#accessibilitybar .fontsize [data-action]', function(action) {
                if (action === 'reset') {
                    applyFontSize(0);
                    return;
                }

                applyFontSize(currentStep() + (action === 'increase' ? 1 : -1));
            });

            bind('#accessibilitybar .sitecolor [data-action]', applySiteColor);

            updateButtons(currentStep());
        }
    };
});
