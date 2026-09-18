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
 * Avisa quem paga por Pix ou boleto de que o proximo ciclo esta chegando.
 *
 * SO EXISTE PARA PIX E BOLETO. O cartao cobra sozinho, e um aviso "sua
 * cobranca automatica esta chegando" nao muda a acao de ninguem - nao ha
 * acao nenhuma para tomar. Aqui, sim: sem o aviso o aluno so descobre que
 * precisa pagar quando charge_due_cycles ja gerou a fatura, e no boleto isso
 * pode ser tarde demais para o prazo de compensacao bancaria.
 *
 * Roda separada de charge_due_cycles de proposito: uma gera a fatura, a
 * outra so avisa que ela esta chegando. Juntar as duas faria uma alteracao
 * na janela de aviso arriscar mexer em quem realmente cobra.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remind_upcoming_cycles extends scheduled_task {
    /**
     * Nome exibido na administracao.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskremindupcomingcycles', 'paygw_mercadopago');
    }

    /**
     * Executa.
     *
     * @return void
     */
    public function execute() {
        $agora = time();
        $reminderdays = (int) get_config('paygw_mercadopago', 'reminderdays');
        $avisados = 0;

        foreach (payment_processor::latest_cycles() as $linha) {
            $recorrencia = class_exists('\local_marketplace\api')
                ? \local_marketplace\api::recurrence_for($linha->component, (int) $linha->itemid, (string) $linha->paymentarea)
                : null;

            if (!$recorrencia) {
                continue;
            }

            $precisalembrar = payment_processor::needs_reminder(
                $linha,
                (int) $recorrencia->days,
                $reminderdays,
                (int) $recorrencia->maxcycles,
                $agora
            );

            if (!$precisalembrar) {
                continue;
            }

            payment_processor::send_reminder($linha);
            $avisados++;
            mtrace("Assinatura {$linha->subscriptionid}: lembrete de vencimento mandado.");
        }

        mtrace("Lembretes mandados: $avisados.");
    }
}
