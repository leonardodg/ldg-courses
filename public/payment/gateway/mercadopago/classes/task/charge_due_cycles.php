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

namespace paygw_mercadopago\task;

use core\task\scheduled_task;
use paygw_mercadopago\payment_processor;

/**
 * Emite os ciclos vencidos das assinaturas, e avisa o aluno.
 *
 * NENHUM CICLO E COBRADO SOZINHO AQUI - nem cartao, nem Pix, nem boleto.
 * Medido em 16/09/2026: cobrar o cartao guardado sem o CVV devolve "400
 * security_code_id can't be null", e isso nao muda por historico de
 * pagamento - a conta nao tem ESC habilitado (o recurso que dispensaria o
 * CVV), e ESC nao liga sozinho. Sem ele, nao ha cobranca de cartao
 * verdadeiramente automatica nesta conta - fingir que ha trocaria uma falha
 * visivel (o aluno confirma) por uma silenciosa (o cron falha e ninguem
 * sabe). payment_processor::charge_cycle() continua no codigo, pronta para
 * o dia em que o suporte do Mercado Pago habilitar ESC nesta conta, mas
 * esta tarefa nao a chama mais.
 *
 * A diferenca operacional para o Asaas e a mesma de sempre: la o gateway tem
 * um objeto de assinatura que cobra sozinho; aqui nao existe esse objeto -
 * o preapproval nao carrega comissao, medido em 15/09/2026 -, entao cada
 * ciclo, de QUALQUER meio de pagamento, precisa da confirmacao do aluno.
 *
 * A consequencia operacional merece estar dita: CRON PARADO E ASSINATURA QUE
 * NAO AVISA. No Asaas o dinheiro continuaria entrando; aqui nao. E o tipo de
 * falha de que ninguem reclama, porque o sintoma e o vendedor recebendo menos,
 * e nao o aluno perdendo acesso.
 *
 * A tarefa e fina de proposito: toda a decisao vive em payment_processor::
 * is_due()/is_due_for_invoice(), puras e recebendo o "agora" por parametro.
 * Uma regra que so fosse exercitavel esperando trinta dias nao seria
 * exercitada nunca.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class charge_due_cycles extends scheduled_task {
    /**
     * Nome exibido na administracao.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskchargeduecycles', 'paygw_mercadopago');
    }

    /**
     * Executa.
     *
     * @return void
     */
    public function execute() {
        $agora = time();
        $cobrados = $falhas = 0;

        foreach (payment_processor::latest_cycles() as $linha) {
            // O intervalo e o teto sao regra do MARKETPLACE, e nao do gateway:
            // ele nao tem como saber o que e uma oferta recorrente. Sem o
            // marketplace instalado, recurrence_for() devolve null e nada aqui
            // cobra - que e o comportamento certo, porque nao havia assinatura
            // para comecar.
            $recorrencia = class_exists('\local_marketplace\api')
                ? \local_marketplace\api::recurrence_for($linha->component, (int) $linha->itemid, (string) $linha->paymentarea)
                : null;

            if (!$recorrencia) {
                continue;
            }

            // Cartao NAO cobra sozinho nesta conta (ver o docblock da classe) -
            // cria o ciclo e pede o CVV de novo, em vez de tentar uma cobranca
            // que sabidamente falha. Ver payment_processor::issue_card_cycle().
            if (payment_processor::is_due($linha, (int) $recorrencia->days, (int) $recorrencia->maxcycles, $agora)) {
                try {
                    payment_processor::issue_card_cycle($linha);
                    $cobrados++;
                    mtrace("Assinatura {$linha->subscriptionid}: ciclo "
                        . ((int) $linha->cycles + 1) . " emitido, aguardando confirmacao do CVV.");
                } catch (\Throwable $e) {
                    // Uma falha nao pode interromper as outras assinaturas. Um
                    // problema numa nao pode impedir o aviso das demais - e a
                    // linha do ciclo ja nasceu, entao a falha fica visivel no
                    // relatorio em vez de sumir.
                    $falhas++;
                    mtrace("Assinatura {$linha->subscriptionid}: FALHA ao emitir o ciclo - " . $e->getMessage());
                }
                continue;
            }

            // Pix e boleto nunca cobraram sozinhos: emite uma fatura NOVA e
            // avisa o aluno. Ver payment_processor::issue_invoice_cycle().
            if (payment_processor::is_due_for_invoice($linha, (int) $recorrencia->days, (int) $recorrencia->maxcycles, $agora)) {
                try {
                    payment_processor::issue_invoice_cycle($linha);
                    $cobrados++;
                    mtrace("Assinatura {$linha->subscriptionid}: fatura do ciclo "
                        . ((int) $linha->cycles + 1) . " emitida.");
                } catch (\Throwable $e) {
                    $falhas++;
                    mtrace("Assinatura {$linha->subscriptionid}: FALHA ao emitir fatura - " . $e->getMessage());
                }
            }
        }

        mtrace("Ciclos emitidos: $cobrados. Falhas: $falhas.");

        if ($falhas > 0) {
            mtrace('ATENCAO: assinaturas com falha precisam de atencao manual.');
        }
    }
}
