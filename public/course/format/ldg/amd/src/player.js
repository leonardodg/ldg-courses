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
 * Ajusta a altura do quadro da aula.
 *
 * O quadro carrega uma pagina do PROPRIO site, entao ele e mesma origem e da
 * para ler a altura do documento direto. Isso evita o vaivem de postMessage que
 * este tipo de embutido costuma ter - e evita tambem ter que instalar um
 * ouvinte em toda pagina do Moodle so para responder "que altura voce tem".
 *
 * O ResizeObserver cobre o que o evento load nao cobre: quiz que abre uma
 * pergunta, imagem que termina de carregar, secao que expande. Sem ele a altura
 * fica travada no que era no primeiro instante, e o conteudo ganha uma barra de
 * rolagem interna - a rolagem dentro da rolagem que ninguem consegue usar.
 *
 * ESTE ARQUIVO NAO PASSA POR TRANSPILADOR. O requirejs.php serve o amd/src
 * quando nao existe .map, entao o codigo aqui precisa ser AMD de verdade.
 *
 * @module     format_ldg/player
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log'], function(Log) {

    /**
     * Altura minima, para o quadro nao piscar em zero antes da primeira medida.
     *
     * @type {number}
     */
    var MIN_HEIGHT = 420;

    /**
     * Folga somada a altura medida.
     *
     * Sem ela, um documento com margem no ultimo elemento ganha um pixel de
     * rolagem e a barra interna aparece do nada.
     *
     * @type {number}
     */
    var PADDING = 24;

    /**
     * Mede o documento de dentro do quadro e aplica a altura.
     *
     * CUIDADO AO MEXER AQUI: este metodo ja teve um laco de realimentacao que so
     * aparece em navegador, e nao em nenhum teste.
     *
     * scrollHeight NUNCA e menor que a altura do proprio quadro - ele inclui a
     * area visivel. Entao, medir e somar uma folga faz o quadro crescer a folga
     * por ciclo do ResizeObserver: 420px viraram 8419px, e depois 12084px, sem
     * parar. A folga alimentava a proxima medida.
     *
     * A saida e ENCOLHER O QUADRO ANTES DE MEDIR. Com ele em zero, o documento
     * de dentro volta ao tamanho que o conteudo pede, e a medida deixa de
     * depender do valor anterior - sem dependencia, nao ha realimentacao.
     *
     * Medir "o filho mais alto do body" NAO resolve, e isso ja foi tentado: o
     * #page-wrapper e o #page tambem acompanham a altura do quadro, entao a
     * medida continua sendo a do proprio quadro. O sintoma vira o oposto - em
     * vez de crescer sem parar, o quadro trava na altura minima e corta
     * conteudo.
     *
     * @param {HTMLIFrameElement} frame
     * @return {void}
     */
    var resize = function(frame) {
        var doc;

        try {
            doc = frame.contentDocument;
        } catch (e) {
            // Mesma origem e o esperado, mas um redirecionamento para fora - um
            // provedor de video, por exemplo - torna o documento inacessivel.
            // Nesse caso a altura minima e o melhor que da para fazer.
            Log.debug('format_ldg/player: quadro de outra origem, mantendo a altura minima.');
            return;
        }

        if (!doc || !doc.body) {
            return;
        }

        var current = parseInt(frame.style.height, 10) || 0;

        // ENCOLHE ANTES DE MEDIR. Enquanto o quadro tem altura, o documento de
        // dentro se estica ate ela - #page-wrapper e #page acompanham - e
        // qualquer medida devolve a altura do proprio quadro, nao a do conteudo.
        //
        // Medir com o quadro em zero desfaz isso: o documento volta ao tamanho
        // que o conteudo pede. Nao ha piscada, porque o navegador nao pinta
        // entre as duas atribuicoes - so recalcula o layout.
        frame.style.height = '0px';

        var content = Math.max(
            doc.documentElement ? doc.documentElement.scrollHeight : 0,
            doc.body.scrollHeight
        );

        var newheight = Math.max(content + PADDING, MIN_HEIGHT);

        frame.style.height = newheight + 'px';

        // Se nada mudou, nao ha por que o observador reagir de novo.
        if (newheight === current) {
            return;
        }

        Log.debug('format_ldg/player: quadro ajustado para ' + newheight + 'px.');
    };

    /**
     * Passa a acompanhar o tamanho do conteudo do quadro.
     *
     * @param {HTMLIFrameElement} frame
     * @return {void}
     */
    var observe = function(frame) {
        resize(frame);

        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        var doc = null;

        try {
            doc = frame.contentDocument;
        } catch (e) {
            return;
        }

        if (!doc || !doc.body) {
            return;
        }

        var observer = new ResizeObserver(function() {
            resize(frame);
        });

        observer.observe(doc.body);
    };

    return {
        /**
         * Liga o ajuste de altura para a aula em foco.
         *
         * @param {string|number} cmid
         * @return {void}
         */
        init: function(cmid) {
            var frame = document.getElementById('ldg-lesson-frame-' + cmid);

            if (!frame) {
                return;
            }

            frame.style.height = MIN_HEIGHT + 'px';

            // O load dispara a cada navegacao DENTRO do quadro, e nao so na
            // primeira: e por isso que ele fica no lugar de uma medida unica.
            frame.addEventListener('load', function() {
                observe(frame);
            });

            // Se o quadro ja carregou antes deste script rodar, o load nao vem
            // mais - e sem esta linha a altura ficaria na minima para sempre.
            if (frame.contentDocument && frame.contentDocument.readyState === 'complete') {
                observe(frame);
            }
        }
    };
});
