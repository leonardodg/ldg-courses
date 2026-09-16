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
 * Monta os campos do cartao da assinatura e produz os tokens.
 *
 * NAO HA TRANSPILADOR nesta base: este arquivo precisa ser AMD de verdade, e o
 * amd/build/ precisa existir. Com cachejs ligado o Moodle serve o build, entao
 * modulo sem build simplesmente nao roda - botao que nao faz nada, sem erro no
 * console e sem pista. Rode `npx grunt amd` no HOST, porque o node do container
 * e v20 e o Moodle 5.2 exige v22.
 *
 * O NAVEGADOR PRODUZ UM TOKEN SO. Ele e de uso unico, e quem o consome e o
 * servidor, ao GUARDAR o cartao; o token que cobra nasce depois, do card_id.
 * Pedir dois aqui seria impossivel no modo brick, porque o Card Payment Brick
 * devolve um por submissao e nao entrega os dados do cartao.
 *
 * O numero do cartao nunca sai daqui para o nosso servidor: o que vai no POST
 * sao o token e a bandeira. O modo nativo, em que o numero vai mesmo,
 * NAO passa por este modulo - la o formulario e HTML puro e quem tokeniza e o
 * PHP.
 *
 * @module     paygw_mercadopago/card_form
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification', 'core/str'], function(Notification, Str) {

    /**
     * Endereco do SDK do Mercado Pago.
     *
     * Carregado de la, e nao empacotado aqui, porque e requisito do PCI DSS
     * v4.0 que o script de tokenizacao seja o do provedor: uma copia nossa
     * viraria mais um script sob a nossa responsabilidade de integridade.
     *
     * @type {string}
     */
    var SDK = 'https://sdk.mercadopago.com/js/v2';

    /**
     * Carrega o SDK uma vez so, e devolve sempre a mesma promessa.
     *
     * @return {Promise}
     */
    var loadSdk = function() {
        if (window.paygwMercadopagoSdk) {
            return window.paygwMercadopagoSdk;
        }

        window.paygwMercadopagoSdk = new Promise(function(resolve, reject) {
            if (window.MercadoPago) {
                resolve(window.MercadoPago);
                return;
            }

            var script = document.createElement('script');
            script.src = SDK;
            script.onload = function() {
                resolve(window.MercadoPago);
            };
            script.onerror = function() {
                reject(new Error('Mercado Pago SDK could not be loaded'));
            };
            document.head.appendChild(script);
        });

        return window.paygwMercadopagoSdk;
    };

    /**
     * Guarda o token no campo escondido e envia o formulario.
     *
     * @param {HTMLFormElement} form
     * @param {string} token
     * @param {string} method Bandeira, como o Mercado Pago a nomeia
     */
    var submitWith = function(form, token, method) {
        form.querySelector('[name="cardtoken"]').value = token;
        form.querySelector('[name="paymentmethod"]').value = method || '';
        form.submit();
    };

    /**
     * Desliga o botao enquanto a tokenizacao acontece.
     *
     * Sem isto, o duplo clique gera dois tokens e duas cobrancas - e a segunda
     * so apareceria no extrato do aluno.
     *
     * @param {HTMLFormElement} form
     * @param {boolean} busy
     */
    var setBusy = function(form, busy) {
        var button = form.querySelector('[data-action="mp-pay"]');
        if (button) {
            button.disabled = busy;
        }
    };

    /**
     * Mostra a falha sem deixar o aluno preso numa tela em branco.
     *
     * No modo brick nao ha botao nosso: se o Brick nao montar, a area fica
     * VAZIA e nao ha nada em que clicar. Por isso a mensagem tambem e escrita
     * no lugar onde o Brick deveria estar, e nao so como notificacao - uma
     * notificacao no topo de uma pagina em branco nao explica o que fazer.
     *
     * @param {HTMLFormElement} form
     * @param {Error} error
     */
    var fail = function(form, error) {
        setBusy(form, false);

        // O ERRO CRU VAI PARA A TELA, e nao so para o console.
        //
        // A mensagem generica sozinha transforma qualquer falha do Brick em
        // "tente de novo", e tentar de novo nao muda nada quando a causa e de
        // configuracao. Quem esta provando o fluxo precisa da causa, e quem
        // nao tem devtools aberto tambem.
        var detalhe = '';
        if (error) {
            detalhe = error.message || error.cause || error.type || '';
            if (!detalhe && typeof error === 'object') {
                try {
                    detalhe = JSON.stringify(error);
                } catch (e) {
                    detalhe = String(error);
                }
            }
        }

        var area = document.querySelector('[data-region="mp-card-fields"]');
        if (area) {
            // Limpa o esqueleto do Brick: deixa-lo na tela faz parecer que
            // ainda esta carregando, e nao que desistiu.
            area.innerHTML = '';
            area.classList.add('alert', 'alert-danger');
        }

        Str.get_string('errorcardtokenmissing', 'paygw_mercadopago')
            .then(function(message) {
                var texto = detalhe ? message + ' [' + detalhe + ']' : message;
                Notification.addNotification({message: texto, type: 'error'});
                if (area) {
                    area.textContent = texto;
                }
                return texto;
            })
            .catch(Notification.exception);

        window.console.error('paygw_mercadopago card_form:', error);
    };

    /**
     * Modo BRICK: o Card Payment Brick monta e valida tudo.
     *
     * O Brick devolve um token por submissao e nao entrega os dados do cartao.
     * E o suficiente: o servidor guarda o cartao com ele e gera o token da
     * cobranca a partir do card_id.
     *
     * @param {Object} mp Instancia do MercadoPago
     * @param {HTMLFormElement} form
     * @param {Object} config
     */
    var mountBrick = function(mp, form, config) {
        var bricks = mp.bricks();

        // O formulario fica escondido e VAZIO no modo brick: quem tem campos e
        // botao e o proprio Brick, que vive fora dele. Aninhar os dois seria
        // HTML invalido, e o navegador descartaria o de dentro - foi assim que
        // a primeira prova submeteu sem token.

        bricks.create('cardPayment', 'mp-card-brick', {
            initialization: {
                amount: config.amount
            },
            customization: {
                // Uma parcela, e nao ha configuracao para mudar. Parcelar uma
                // cobranca que se repete todo mes empilha parcela sobre
                // parcela, e o aluno passa a dever mais do que assinou - a
                // mesma regra que o build_cycle_payment_body() aplica no PHP.
                paymentMethods: {
                    maxInstallments: 1
                }
            },
            callbacks: {
                // OBRIGATORIO, e a falta dele nao parece falta de callback: o
                // Brick fica no esqueleto de carregamento para sempre e
                // devolve "Callbacks onReady and/or onError are required" so
                // no console. Custou uma rodada de prova real em 16/09/2026.
                onReady: function() {
                    var area = document.querySelector('[data-region="mp-card-fields"]');
                    if (area) {
                        area.classList.remove('alert', 'alert-danger');
                    }
                },
                onError: function(error) {
                    fail(form, error);
                },
                onSubmit: function(formData) {
                    setBusy(form, true);
                    submitWith(form, formData.token, formData.payment_method_id);

                    return Promise.resolve();
                }
            }
        }).catch(function(error) {
            fail(form, error);
        });
    };

    /**
     * Modo DIRETO: os campos sao iframes do Mercado Pago, o layout e nosso.
     *
     * Aqui a tokenizacao e NOSSA chamada, o que da controle sobre o momento e
     * sobre a mensagem de erro - mas o resultado e o mesmo do brick: um token.
     *
     * @param {Object} mp Instancia do MercadoPago
     * @param {HTMLFormElement} form
     */
    var mountFields = function(mp, form) {
        var cardNumber;

        // O IFRAME NAO HERDA O TEMA. Os campos sensiveis sao servidos pelo
        // Mercado Pago, entao cor e tamanho precisam ser ENVIADOS - sem isso o
        // texto sai preto sobre fundo escuro e o aluno nao ve o que digita.
        //
        // A cor sai do computado da propria pagina, e nao de um valor fixo:
        // assim acompanha o alternador claro/escuro do tema sem saber que ele
        // existe.
        var estilo = {
            color: window.getComputedStyle(form).color || '#212529',
            fontSize: '16px',
            placeholderColor: '#9aa0a6'
        };

        try {
            cardNumber = mp.fields.create('cardNumber', {
                placeholder: '0000 0000 0000 0000',
                style: estilo
            }).mount('mp-field-number');
            mp.fields.create('expirationDate', {
                placeholder: 'MM/AA',
                style: estilo
            }).mount('mp-field-expiration');
            mp.fields.create('securityCode', {
                placeholder: 'CVV',
                style: estilo
            }).mount('mp-field-security');
        } catch (error) {
            fail(form, error);
            return;
        }

        // Mascara do CPF. Formata enquanto digita e nao atrapalha apagar: o
        // valor e reconstruido do zero a cada tecla, a partir so dos digitos.
        var doc = form.querySelector('#mp-holderdoc');
        if (doc) {
            doc.addEventListener('input', function() {
                var d = doc.value.replace(/\D/g, '').slice(0, 11);
                var saida = d;
                if (d.length > 9) {
                    saida = d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
                } else if (d.length > 6) {
                    saida = d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
                } else if (d.length > 3) {
                    saida = d.slice(0, 3) + '.' + d.slice(3);
                }
                doc.value = saida;
            });
        }

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            var holder = form.querySelector('#mp-holdername');
            var nome = holder ? holder.value.trim() : '';
            var documento = doc ? doc.value.replace(/\D/g, '') : '';

            // Validado AQUI porque o erro do Mercado Pago para nome vazio nao
            // diz que e o nome: ele volta como falha generica de tokenizacao,
            // e o aluno fica tentando trocar de cartao.
            if (nome === '' || documento === '') {
                fail(form, new Error('Informe o nome impresso no cartao e o CPF do titular'));
                return;
            }

            setBusy(form, true);

            // A BANDEIRA E DESCOBERTA AQUI, pelo BIN, e viaja junto do token.
            //
            // Medido em 16/09/2026: o token dos Secure Fields NAO carrega
            // payment_method_id, e o POST /customers/{id}/cards o EXIGE -
            // devolve "400 invalid parameter in payment method". E assimetrico
            // com o /v1/payments, que infere a bandeira do proprio token, e foi
            // essa assimetria que custou quatro rodadas de prova real.
            cardNumber.getBin()
                .then(function(bin) {
                    return mp.getPaymentMethods({bin: bin});
                })
                .then(function(resposta) {
                    var metodos = (resposta && resposta.results) || [];
                    if (!metodos.length) {
                        throw new Error('Cartao nao reconhecido pelo Mercado Pago');
                    }

                    return mp.createCardToken({
                        cardholderName: nome,
                        identificationType: 'CPF',
                        identificationNumber: documento
                    }).then(function(token) {
                        if (!token || !token.id) {
                            throw new Error('O Mercado Pago nao devolveu token para este cartao');
                        }
                        submitWith(form, token.id, metodos[0].id);
                        return token;
                    });
                })
                .catch(function(error) {
                    fail(form, error);
                });
        });
    };

    return {
        /**
         * Monta o formulario conforme o modo de captura.
         *
         * @param {Object} config publickey, mode e amount
         */
        init: function(config) {
            var form = document.querySelector('[data-region="mp-card-form"]');
            if (!form) {
                return;
            }

            loadSdk()
                .then(function(MercadoPago) {
                    var mp = new MercadoPago(config.publickey, {locale: 'pt-BR'});

                    if (config.mode === 'brick') {
                        mountBrick(mp, form, config);
                    } else {
                        mountFields(mp, form);
                    }

                    return mp;
                })
                .catch(function(error) {
                    fail(form, error);
                });
        }
    };
});
