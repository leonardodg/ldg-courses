<?php
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

namespace paygw_pagarme;

/**
 * Respostas de exemplo da documentacao do Pagar.me, copiadas sem edicao.
 *
 * Por que existem: a conta de homologacao nao processa cobranca, entao nao ha
 * resposta REAL de cobranca paga para testar contra. Estas sao o mais proximo
 * disso - e sao a mesma coisa que o codigo assume ao ser escrito, o que faz
 * delas o teste da SUPOSICAO, nao da realidade.
 *
 * Quando a conta for liberada, troque cada uma pela resposta medida e rode de
 * novo. Se algum teste mudar de resultado, a documentacao mentia - e o
 * roteiro em docs/data-validation/pagarme-sandbox.md ganha mais uma linha.
 *
 * Fontes:
 *   https://docs.pagar.me/reference/pix-2
 *   https://docs.pagar.me/reference/criar-pedido-2
 *   https://docs.pagar.me/reference/criar-assinatura-avulsa
 *   https://docs.pagar.me/reference/split-1
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class documented_responses {
    /**
     * Pedido com Pix aguardando pagamento.
     *
     * Repare em dois detalhes que o codigo precisa aguentar: o
     * payment_method vem com inicial MAIUSCULA ("Pix"), e o status da
     * cobranca ("pending") nao e o mesmo da transacao ("waiting_payment").
     *
     * @return array
     */
    public static function pix_pendente(): array {
        return [
            'id' => 'or_56GXnk6T0eU88qMm',
            'code' => 'YV3RCRIN24',
            'amount' => 3090,
            'currency' => 'BRL',
            'closed' => true,
            'status' => 'pending',
            'charges' => [[
                'id' => 'ch_K2rJ5nlHwTE4qRDP',
                'code' => 'YV3RCRIN24',
                'gateway_id' => '3b4bb2d9-19b3-4638-a974-0bb914fff472',
                'amount' => 3090,
                'status' => 'pending',
                'currency' => 'BRL',
                'payment_method' => 'Pix',
                'last_transaction' => [
                    'id' => 'tran_bZ0N3DjjUzTW68eq',
                    'transaction_type' => 'Pix',
                    'amount' => 3090,
                    'status' => 'waiting_payment',
                    'success' => true,
                    'qr_code' => '00020101021226480019BR.COM.STONE.QRCODE0108A37F87120209123456789',
                    'qr_code_url' =>
                        'https://api.pagar.me/core/v1/transactions/tran_bZ0N3DjjUzTW68eq/qrcode.png',
                    'expires_at' => '2020-09-20T00:00:00Z',
                    'gateway_response' => [],
                ],
            ]],
        ];
    }

    /**
     * Cobranca de cartao aprovada, com split de duas regras.
     *
     * O split e o do exemplo de criar-pedido-2, com os dois recebedores em
     * 50%. Os valores em splits[] estao em CENTAVOS, que e o que
     * commission_from() soma.
     *
     * @return array
     */
    public static function cobranca_paga_com_split(): array {
        return [
            'id' => 'ch_y9bdaX9JHns07L1Z',
            'code' => '4G63F90FOR',
            'amount' => 10000,
            'status' => 'paid',
            'currency' => 'BRL',
            'payment_method' => 'credit_card',
            'splits' => [
                [
                    'amount' => 7500,
                    'recipient_id' => 'rp_5yGwpMGckBHVYmb6',
                    'type' => 'flat',
                    'options' => [
                        'charge_processing_fee' => true,
                        'charge_remainder_fee' => true,
                        'liable' => true,
                    ],
                ],
                [
                    'amount' => 2500,
                    'recipient_id' => 'rp_yLnAyVpHbQIqZxwO',
                    'type' => 'flat',
                    'options' => [
                        'charge_processing_fee' => false,
                        'charge_remainder_fee' => false,
                        'liable' => false,
                    ],
                ],
            ],
            'last_transaction' => [
                'id' => 'tran_Kably82TKxiMMY0d',
                'transaction_type' => 'credit_card',
                'amount' => 10000,
                'status' => 'captured',
                'success' => true,
                'acquirer_message' => 'Stone|Aprovado',
                'acquirer_return_code' => '0000',
                'gateway_response' => ['code' => '201', 'errors' => []],
            ],
        ];
    }

    /**
     * Assinatura recem-criada.
     *
     * @return array
     */
    public static function assinatura(): array {
        return [
            'id' => 'sub_bpYjMr9f8sA6QJNg',
            'code' => 'XPLMBV9U10',
            'start_at' => '2018-04-04T00:00:00Z',
            'interval' => 'month',
            'interval_count' => 1,
            'billing_type' => 'postpaid',
            'current_cycle' => [
                'id' => 'cycle_j6WnJ7ei1hW68bXo',
                'start_at' => '2018-04-04T00:00:00Z',
                'end_at' => '2018-05-03T23:59:59Z',
                'billing_at' => '2018-05-04T00:00:00Z',
            ],
            'next_billing_at' => '2018-05-04T00:00:00Z',
            'payment_method' => 'boleto',
            'currency' => 'BRL',
            'installments' => 1,
            'status' => 'active',
        ];
    }

    /**
     * A cobranca que falhou no adquirente, medida em 09/09/2026.
     *
     * Esta NAO vem da documentacao: e resposta real da conta de homologacao,
     * e e o unico exemplo de fracasso que temos de verdade. O POST devolveu
     * HTTP 200 com este corpo.
     *
     * @return array
     */
    public static function cobranca_que_falhou(): array {
        return [
            'id' => 'ch_N6XEkm8ivuxnRPY8',
            'amount' => 10000,
            'status' => 'failed',
            'currency' => 'BRL',
            'payment_method' => 'pix',
            'last_transaction' => [
                'id' => 'tran_4ZVwXGBSBpc3JbQz',
                'transaction_type' => 'pix',
                'amount' => 10000,
                'status' => 'failed',
                'success' => false,
                'gateway_response' => [
                    'code' => '500',
                    'errors' => [
                        ['message' => 'internal_error |  | Erro desconhecido no proxy'],
                    ],
                ],
            ],
        ];
    }

    /**
     * A order com split para recebedor inexistente, medida em 09/09/2026.
     *
     * Tambem real. O POST devolveu HTTP 200 e corpo completo; so o GET
     * denunciou, com splits nulo e o 404 enterrado no gateway_response.
     *
     * @return array
     */
    public static function split_para_recebedor_inexistente(): array {
        return [
            'id' => 'or_LlDa5NLH9eSdxPep',
            'code' => 'GPRYC60TV1',
            'amount' => 10000,
            'currency' => 'BRL',
            'status' => 'failed',
            'splits' => null,
            'charges' => [[
                'id' => 'ch_falhou',
                'amount' => 10000,
                'status' => 'failed',
                'payment_method' => 'pix',
                'splits' => null,
                'last_transaction' => [
                    'status' => 'failed',
                    'success' => false,
                    'gateway_response' => [
                        'code' => '404',
                        'errors' => [['message' => 'Recipient not found']],
                    ],
                ],
            ]],
        ];
    }
}
