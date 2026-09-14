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
 * Tokenizacao do cartao, no navegador.
 *
 * O numero do cartao vai daqui direto para o Pagar.me, com a chave publica, e
 * o Moodle so recebe o token. Nenhum dado de cartao toca este servidor - e o
 * que mantem a instalacao fora do escopo pesado de PCI.
 *
 * ATENCAO: nao ha transpilador neste projeto. Escreva AMD de verdade.
 *
 * @module     paygw_pagarme/cardform
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['paygw_pagarme/repository'], function(Repository) {

    var ENDPOINT = 'https://api.pagar.me/core/v5/tokens?appId=';

    /**
     * Separa "12/30" em mes e ano.
     *
     * @param {String} value
     * @return {Object}
     */
    var splitExpiry = function(value) {
        var parts = String(value).split('/');

        return {
            month: parseInt(parts[0], 10) || 0,
            year: parseInt(parts[1], 10) || 0
        };
    };

    return {
        /**
         * Liga o formulario.
         *
         * @param {String} reference
         * @param {String} publicKey
         */
        init: function(reference, publicKey) {
            var button = document.getElementById('paygw-pagarme-submit');
            var status = document.getElementById('paygw-pagarme-status');

            if (!button) {
                return;
            }

            var show = function(message) {
                if (status) {
                    status.textContent = message;
                }
            };

            button.addEventListener('click', function() {
                button.disabled = true;
                show('');

                var expiry = splitExpiry(document.getElementById('paygw-pagarme-expiry').value);
                var payload = {
                    type: 'card',
                    card: {
                        number: document.getElementById('paygw-pagarme-number').value.replace(/\s/g, ''),
                        holder_name: document.getElementById('paygw-pagarme-holder').value,
                        exp_month: expiry.month,
                        exp_year: expiry.year,
                        cvv: document.getElementById('paygw-pagarme-cvv').value
                    }
                };

                window.fetch(ENDPOINT + encodeURIComponent(publicKey), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                }).then(function(response) {
                    return response.json();
                }).then(function(token) {
                    if (!token.id) {
                        throw new Error(token.message || 'token');
                    }

                    return Repository.submitCard(reference, token.id, {
                        zipcode: document.getElementById('paygw-pagarme-zipcode').value,
                        line1: document.getElementById('paygw-pagarme-line1').value,
                        city: document.getElementById('paygw-pagarme-city').value,
                        state: document.getElementById('paygw-pagarme-state').value
                    });
                }).then(function(result) {
                    if (!result.success) {
                        throw new Error(result.message);
                    }

                    window.location.href = result.redirecturl;
                    return result;
                }).catch(function(error) {
                    button.disabled = false;
                    show(error.message);
                });
            });
        }
    };
});
