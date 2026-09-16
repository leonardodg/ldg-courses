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
 * Cobra os ciclos vencidos das assinaturas.
 *
 * ESTA TAREFA E O MOTOR DA ASSINATURA NO MERCADO PAGO, e e a diferenca de fundo
 * para o Asaas: la o gateway tem um objeto de assinatura que cobra sozinho, e a
 * tarefa de la so concilia. Aqui nao existe esse objeto - o preapproval nao
 * carrega comissao, medido em 15/09/2026 -, entao quem dispara cada cobranca
 * somos nos.
 *
 * A consequencia operacional merece estar dita: CRON PARADO E ASSINATURA QUE
 * NAO COBRA. No Asaas o dinheiro continuaria entrando; aqui nao. E o tipo de
 * falha de que ninguem reclama, porque o sintoma e o vendedor recebendo menos,
 * e nao o aluno perdendo acesso.
 *
 * A tarefa e fina de proposito: toda a decisao vive em payment_processor::
 * is_due(), que e pura e recebe o "agora" por parametro. Uma regra que so fosse
 * exercitavel esperando trinta dias nao seria exercitada nunca.
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
                ? \local_marketplace\api::recurrence_for($linha->component, (int) $linha->itemid)
                : null;

            if (!$recorrencia) {
                continue;
            }

            if (!payment_processor::is_due($linha, (int) $recorrencia->days, (int) $recorrencia->maxcycles, $agora)) {
                continue;
            }

            try {
                payment_processor::charge_cycle($linha);
                $cobrados++;
                mtrace("Assinatura {$linha->subscriptionid}: ciclo " . ((int) $linha->cycles + 1) . " cobrado.");
            } catch (\Throwable $e) {
                // Uma falha nao pode interromper as outras assinaturas. Cartao
                // vencido de um aluno nao pode impedir a cobranca dos demais -
                // e a linha do ciclo ja nasceu, entao a falha fica visivel no
                // relatorio em vez de sumir.
                $falhas++;
                mtrace("Assinatura {$linha->subscriptionid}: FALHA ao cobrar - " . $e->getMessage());
            }
        }

        mtrace("Ciclos cobrados: $cobrados. Falhas: $falhas.");

        if ($falhas > 0) {
            // O aluno com cartao recusado cai no caminho manual: recebe os
            // avisos de vencimento do marketplace e informa outro cartao pelo
            // subscribe.php, que e o que o pending_invoice() aponta.
            mtrace('ATENCAO: assinaturas com falha precisam de cartao novo pelo aluno.');
        }
    }
}
