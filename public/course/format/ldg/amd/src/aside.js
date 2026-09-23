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
 * Esconder e mostrar as laterais do portal.
 *
 * O aluno pode querer a tela so com a aula. As duas laterais - a navegacao e o
 * indice - somem de forma independente, e a escolha SOBREVIVE a troca de aula,
 * que e o ponto: cada clique numa aula e uma carga nova, e sem gravar a
 * preferencia a lateral reabriria o tempo todo.
 *
 * O que garante que ninguem fica sem saida e a barra de atalho entre aulas, que
 * fica grudada no topo do miolo e nao depende de lateral nenhuma.
 *
 * @module     format_ldg/aside
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setUserPreference} from 'core_user/repository';

/** @var {string} A preferencia guarda os nomes das laterais escondidas. */
const PREFERENCE = 'format_ldg_aside_hidden';

/** @var {string} Classe posta no portal para cada lateral escondida. */
const CLASSNAME = 'ldg-portal--hide-';

/**
 * As laterais escondidas agora, lidas do proprio DOM.
 *
 * O DOM e a fonte da verdade, e nao uma variavel do modulo: o estado inicial vem
 * do SERVIDOR, ja aplicado no HTML, para a lateral nao aparecer e sumir depois
 * que o JavaScript roda.
 *
 * @param {HTMLElement} portal
 * @returns {string[]}
 */
const hidden = (portal) => {
    return ['nav', 'index'].filter((which) => portal.classList.contains(CLASSNAME + which));
};

/**
 * Liga o botao de uma lateral.
 *
 * @param {HTMLElement} portal
 * @param {HTMLElement} button
 */
const bind = (portal, button) => {
    const which = button.dataset.ldgAside;

    if (!which) {
        return;
    }

    button.addEventListener('click', () => {
        const isHidden = portal.classList.toggle(CLASSNAME + which);

        // aria-expanded e o que diz ao leitor de tela o que aconteceu. Sem ele o
        // botao alterna em silencio, e quem nao ve a tela nao sabe do que se
        // trata.
        button.setAttribute('aria-expanded', isHidden ? 'false' : 'true');

        // Falha de gravacao NAO desfaz o que o aluno acabou de fazer: ele pediu
        // para esconder, e a tela obedece. O que se perde e a memoria disso na
        // proxima carga, e isso e melhor do que a lateral voltar sozinha no meio
        // do clique.
        setUserPreference(PREFERENCE, hidden(portal).join('-')).catch(() => {
            window.console.warn('format_ldg: nao consegui gravar a preferencia da lateral');
        });
    });
};

/**
 * Inicializa.
 *
 * @param {string} selector Seletor do portal.
 */
export const init = (selector) => {
    const portal = document.querySelector(selector);

    if (!portal) {
        return;
    }

    // Os botoes so existem porque este modulo carregou. Sem JavaScript nao ha
    // botao, e a lateral fica visivel - que e o estado util.
    portal.classList.add('ldg-portal--can-hide');

    portal.querySelectorAll('[data-ldg-aside]').forEach((button) => bind(portal, button));
};
