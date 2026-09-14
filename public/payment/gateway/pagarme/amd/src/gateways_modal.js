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
 * Ponto de entrada que o core_payment chama quando o aluno escolhe o Pagar.me.
 *
 * ATENCAO: nao ha transpilador neste projeto. Escreva AMD de verdade.
 *
 * @module     paygw_pagarme/gateways_modal
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['paygw_pagarme/repository'], function(Repository) {

    return {
        /**
         * Cria a cobranca e leva o aluno para onde ele paga.
         *
         * @param {String} component
         * @param {String} paymentArea
         * @param {Number} itemId
         * @return {Promise}
         */
        process: function(component, paymentArea, itemId) {
            return Repository.createCharge(component, paymentArea, itemId)
                .then(function(result) {
                    if (!result.success) {
                        throw new Error(result.message);
                    }

                    window.location.href = result.redirecturl;

                    // De proposito, uma promessa que nunca resolve: resolver
                    // faria o modal piscar "pagamento concluido" antes de a
                    // pagina de pagamento sequer abrir.
                    return new Promise(function() {
                        return;
                    });
                });
        }
    };
});
