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
 * Alternador de modo claro/escuro.
 *
 * Derivado do darkmode do theme_moove, com tres diferencas:
 *
 * 1. NAO usa jQuery.
 * 2. NAO tem web service proprio. O Moove criou theme_moove_toggledarkmode; a
 *    preferencia aqui vai pelo core_user/repository, o mesmo caminho do navmenu
 *    e da barra de acessibilidade deste tema.
 * 3. Le o estado de data-bs-theme, e nao de uma classe propria. O atributo e o
 *    que o Bootstrap 5 realmente consulta, e o servidor ja o renderiza a partir
 *    da preferencia - assim nao existem duas fontes de verdade para o mesmo
 *    estado.
 *
 * @module     theme_ldg/colormode
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_user/repository'], function(UserRepository) {

    var TRIGGER = '#toggle-darkmode';

    // A mesma chave que \theme_ldg\util\settings::color_mode() le no servidor.
    // Mudar uma sem a outra faz o modo voltar ao padrao no proximo carregamento.
    var PREFERENCE = 'dark-mode-on';

    /**
     * O modo atual, lido do atributo que o Bootstrap consulta.
     *
     * @returns {boolean} Verdadeiro quando esta escuro.
     */
    var isDark = function() {
        return document.body.getAttribute('data-bs-theme') === 'dark';
    };

    /**
     * Aplica um modo e guarda a escolha.
     *
     * @param {boolean} dark
     * @returns {void}
     */
    var apply = function(dark) {
        var trigger = document.querySelector(TRIGGER);

        document.body.setAttribute('data-bs-theme', dark ? 'dark' : 'light');

        if (trigger) {
            trigger.setAttribute('aria-pressed', dark ? 'true' : 'false');
        }

        UserRepository.setUserPreference(PREFERENCE, dark ? 1 : 0);
    };

    return {
        init: function() {
            var trigger = document.querySelector(TRIGGER);

            if (!trigger) {
                return;
            }

            // O estado inicial vem do servidor; aqui so espelhamos no controle,
            // senao o botao anuncia "nao pressionado" numa pagina escura.
            trigger.setAttribute('aria-pressed', isDark() ? 'true' : 'false');

            trigger.addEventListener('click', function() {
                apply(!isDark());
            });
        }
    };
});
