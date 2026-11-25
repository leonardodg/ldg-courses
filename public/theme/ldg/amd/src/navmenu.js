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
 * Recolhe e expande o menu lateral.
 *
 * AMD CLASSICO de proposito. Este projeto nao tem transpilador: quando nao ha
 * amd/build/*.min.js, o requirejs.php serve amd/src direto, e um modulo escrito
 * com import/export quebraria com "No define call". O arquivo em build/ e copia
 * fiel deste, pelo mesmo motivo.
 *
 * @module     theme_ldg/navmenu
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_user/repository'], function(UserRepository) {

    var SELECTORS = {
        MENU: '#ldg-navmenu',
        TOGGLE: '[data-action="ldg-navmenu-toggle"]'
    };

    var CLASSES = {
        COLLAPSED: 'ldg-navmenu-collapsed',
        BODY_COLLAPSED: 'ldg-navmenu-is-collapsed'
    };

    var PREFERENCE = 'theme_ldg-navmenu-collapsed';

    /**
     * Aplica o estado ao DOM.
     *
     * A classe vai no menu E no body: o menu se estreita, mas quem desloca o
     * conteudo da pagina e o body - o #page calcula a margem esquerda a partir
     * dela.
     *
     * @param {Element} menu O elemento do menu.
     * @param {Boolean} collapsed Estado desejado.
     */
    var apply = function(menu, collapsed) {
        menu.classList.toggle(CLASSES.COLLAPSED, collapsed);
        document.body.classList.toggle(CLASSES.BODY_COLLAPSED, collapsed);

        var toggle = menu.querySelector(SELECTORS.TOGGLE);
        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    };

    /**
     * Liga o botao.
     *
     * @param {Element} menu O elemento do menu.
     */
    var registerEventListeners = function(menu) {
        var toggle = menu.querySelector(SELECTORS.TOGGLE);
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function() {
            var collapsed = !menu.classList.contains(CLASSES.COLLAPSED);

            // O DOM muda ANTES da chamada: gravar a preferencia e um efeito
            // colateral, e o botao nao pode esperar a rede para responder.
            apply(menu, collapsed);

            UserRepository.setUserPreference(PREFERENCE, collapsed ? 1 : 0);
        });
    };

    return {
        init: function() {
            var menu = document.querySelector(SELECTORS.MENU);
            if (!menu) {
                return;
            }

            // Sincroniza o body com o que o PHP ja decidiu, para o caso de a
            // pagina ter sido servida do cache com a classe fora de sincronia.
            apply(menu, menu.classList.contains(CLASSES.COLLAPSED));

            registerEventListeners(menu);
        }
    };
});
