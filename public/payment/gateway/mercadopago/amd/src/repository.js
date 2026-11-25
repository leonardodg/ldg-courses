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
 * Escrito em AMD, e nao no ES6 que o Moodle usa por convencao, porque este
 * projeto nao tem grunt nem babel: nada transpila src em build. Com ES6 aqui, o
 * arquivo compilava so na imaginacao - e o requirejs.php entrega ESTE arquivo
 * sempre que nao existe um .map ao lado do build, produzindo "No define call".
 *
 * @module     paygw_mercadopago/repository
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {

    return {
        /**
         * Pede ao servidor a criacao da preferencia.
         *
         * O valor NAO e enviado daqui: quem o resolve e o servidor, a partir do
         * item. Confiar num valor vindo do navegador deixaria o preco a escolha
         * de quem compra.
         *
         * @param {String} component
         * @param {String} paymentArea
         * @param {Number} itemId
         * @returns {Promise<{success: Boolean, redirecturl: String, message: String}>}
         */
        createPreference: function(component, paymentArea, itemId) {
            return Ajax.call([{
                methodname: 'paygw_mercadopago_create_preference',
                args: {
                    component: component,
                    paymentarea: paymentArea,
                    itemid: itemId
                }
            }])[0];
        }
    };
});
