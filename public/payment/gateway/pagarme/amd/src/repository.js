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
 * Chamadas ao servidor.
 *
 * ATENCAO: nao ha transpilador neste projeto. O requirejs.php serve amd/src
 * quando nao existe .map, entao este arquivo precisa ser AMD de verdade - ES6
 * aqui vira "No define call" no navegador. O amd/build e copia deste, com o
 * nome do modulo dentro do define().
 *
 * @module     paygw_pagarme/repository
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    return {
        /**
         * Cria a cobranca. O preco nunca viaja daqui.
         *
         * @param {String} component
         * @param {String} paymentArea
         * @param {Number} itemId
         * @return {Promise}
         */
        createCharge: function(component, paymentArea, itemId) {
            return Ajax.call([{
                methodname: 'paygw_pagarme_create_charge',
                args: {component: component, paymentarea: paymentArea, itemid: itemId}
            }])[0];
        },

        /**
         * Pergunta se a cobranca ja foi paga.
         *
         * @param {String} reference
         * @return {Promise}
         */
        chargeStatus: function(reference) {
            return Ajax.call([{
                methodname: 'paygw_pagarme_charge_status',
                args: {reference: reference}
            }])[0];
        },

        /**
         * Manda o token do cartao. O numero do cartao nunca passa por aqui.
         *
         * @param {String} reference
         * @param {String} cardToken
         * @return {Promise}
         */
        submitCard: function(reference, cardToken) {
            return Ajax.call([{
                methodname: 'paygw_pagarme_submit_card',
                args: {reference: reference, cardtoken: cardToken}
            }])[0];
        }
    };
});
