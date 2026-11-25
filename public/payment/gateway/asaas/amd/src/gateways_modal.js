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
 * Ponto de entrada que o core_payment chama ao escolher este gateway.
 *
 * ATENCAO: nao ha transpilador neste projeto. Este arquivo precisa ser AMD de
 * verdade - ES6 aqui vira "No define call" no navegador.
 *
 * @module     paygw_asaas/gateways_modal
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['paygw_asaas/repository', 'core/notification'], function(Repository, Notification) {

    return {
        /**
         * Cria a cobranca e leva o aluno para a fatura do Asaas.
         *
         * @param {String} component
         * @param {String} paymentArea
         * @param {Number} itemId
         * @param {String} description
         * @returns {Promise}
         */
        process: function(component, paymentArea, itemId, description) {
            return Repository.createCharge(component, paymentArea, itemId)
                .then(function(result) {
                    if (!result.success) {
                        throw new Error(result.message);
                    }

                    window.location.href = result.redirecturl;

                    // Promessa que nunca resolve, de proposito: o navegador ja
                    // esta saindo da pagina. Resolver faria o modal piscar
                    // "pagamento concluido" antes de a cobranca sequer ser
                    // aberta - e o aluno pensaria que ja pagou.
                    return new Promise(function() {
                        return;
                    });
                })
                .catch(function(error) {
                    Notification.alert('', error.message || error);
                    throw error;
                });
        }
    };
});
