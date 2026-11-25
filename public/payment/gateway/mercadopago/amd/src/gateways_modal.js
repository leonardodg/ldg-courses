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
 * Fluxo de pagamento do Mercado Pago.
 *
 * O Checkout Pro e por REDIRECIONAMENTO: nao ha formulario a exibir no modal.
 * Este modulo so pede a preferencia ao servidor e manda o navegador para o
 * Mercado Pago. A confirmacao NAO acontece na volta - ela chega pelo webhook,
 * porque o aluno pode fechar a aba, e com Pix a aprovacao vem depois.
 *
 * Escrito em AMD, e nao no ES6 que o Moodle usa por convencao. Ver a nota em
 * repository.js: sem transpilador, este e o arquivo que o servidor entrega.
 *
 * @module     paygw_mercadopago/gateways_modal
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['paygw_mercadopago/repository', 'core/str'], function(Repository, Str) {

    return {
        /**
         * Ponto de entrada chamado pelo core_payment.
         *
         * @param {String} component
         * @param {String} paymentArea
         * @param {Number} itemId
         * @param {String} description
         * @returns {Promise<String>}
         */
        process: function(component, paymentArea, itemId, description) {
            return Repository.createPreference(component, paymentArea, itemId)
                .then(function(result) {
                    if (!result.success || !result.redirecturl) {
                        throw new Error(result.message);
                    }
                    window.location.href = result.redirecturl;

                    // A promise nao resolve de proposito: o navegador esta
                    // saindo da pagina. Resolver aqui faria o modal exibir
                    // "pagamento concluido" por um instante antes do
                    // redirecionamento, o que seria mentira - nada foi pago.
                    return new Promise(function() {
                        return description;
                    });
                })
                .catch(function(error) {
                    if (error && error.message) {
                        throw new Error(error.message);
                    }
                    return Str.get_string('errorcreatingpreference', 'paygw_mercadopago')
                        .then(function(message) {
                            throw new Error(message);
                        });
                });
        }
    };
});
