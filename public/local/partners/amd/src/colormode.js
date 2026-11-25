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
 * Alternador de modo claro/escuro da landing de parceiros.
 *
 * Existe porque o theme_boost do 5.2 NAO tem alternador nenhum, e o publico
 * desta pagina e o visitante anonimo - que tambem nao tem preferencia de
 * usuario para guardar. Sem isto, "escuro por padrao" viraria "escuro e ponto".
 *
 * TRES DECISOES QUE VALE CONHECER:
 *
 * 1. Escreve data-bs-theme no WRAPPER da pagina, e nao na raiz. O Bootstrap 5.3
 *    aceita o atributo em qualquer elemento, e o Boost ja compila os blocos do
 *    modo escuro - entao a subarvore inteira rebaseia sem uma linha de CSS de
 *    tema.
 *
 * 2. Persiste em DOIS lugares, e cada um pelo seu motivo. Quem esta autenticado
 *    grava a preferencia 'dark-mode-on', a MESMA chave que o theme_ldg e o
 *    theme_moove usam: assim a escolha feita aqui vale no resto do site. Quem
 *    nao esta grava no localStorage, porque setUserPreference exige sessao.
 *
 * 3. So espelha no <body> quando o tema JA e dono do atributo. Sob Boost e
 *    Moove, que nao tem desenho escuro para navbar e rodape, meio-escurecer a
 *    pagina deles seria estragar o tema em vez de funcionar nele.
 *
 * NAO ha transpilador neste projeto: este arquivo precisa ser AMD de verdade.
 *
 * @module     local_partners/colormode
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_user/repository'], function(UserRepository) {

    var STORAGEKEY = 'local_partners-colormode';
    var PREFERENCE = 'dark-mode-on';
    var DARK = 'dark';
    var LIGHT = 'light';

    /**
     * O wrapper da pagina, que e quem carrega o modo.
     *
     * @returns {Element|null}
     */
    var wrapper = function() {
        return document.querySelector('.ldgp');
    };

    /**
     * O modo atual, lido do atributo que o Bootstrap consulta.
     *
     * @returns {string}
     */
    var current = function() {
        var el = wrapper();

        return el && el.getAttribute('data-bs-theme') === LIGHT ? LIGHT : DARK;
    };

    /**
     * Aplica um modo e guarda a escolha.
     *
     * @param {string} mode
     * @param {boolean} authenticated
     * @returns {void}
     */
    var apply = function(mode, authenticated) {
        var el = wrapper();

        if (!el) {
            return;
        }

        el.setAttribute('data-bs-theme', mode);

        // So acompanha quem ja fala essa lingua. Ver a decisao 3 no cabecalho.
        if (document.body.hasAttribute('data-bs-theme')) {
            document.body.setAttribute('data-bs-theme', mode);
        }

        var button = document.querySelector('[data-ldgp="colormode"]');
        if (button) {
            button.setAttribute('aria-pressed', mode === DARK ? 'true' : 'false');
        }

        if (authenticated) {
            UserRepository.setUserPreference(PREFERENCE, mode === DARK ? 1 : 0);
            return;
        }

        try {
            window.localStorage.setItem(STORAGEKEY, mode);
        } catch (e) {
            // Navegacao privada do Safari lanca na ESCRITA. A pagina continua
            // funcionando, so nao lembra da escolha no proximo carregamento.
        }
    };

    return {
        /**
         * Liga o alternador.
         *
         * @param {boolean} authenticated Quem esta logado guarda a preferencia no
         *                              perfil; quem nao esta, no navegador.
         * @returns {void}
         */
        init: function(authenticated) {
            var button = document.querySelector('[data-ldgp="colormode"]');

            if (!button) {
                return;
            }

            button.setAttribute('aria-pressed', current() === DARK ? 'true' : 'false');

            button.addEventListener('click', function() {
                apply(current() === DARK ? LIGHT : DARK, authenticated);
            });
        }
    };
});
