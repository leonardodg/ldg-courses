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

use paygw_mercadopago\payment_processor;

/**
 * Confere no Mercado Pago as transacoes que continuam pendentes aqui.
 *
 * O webhook e a fonte da verdade, mas ele e uma entrega pela rede: pode cair
 * durante um deploy, pode esbarrar num certificado expirado, pode chegar
 * enquanto o banco esta em manutencao. Sem esta varredura, o aluno que pagou
 * ficaria sem o curso e sem ninguem saber - e a unica pista seria uma
 * reclamacao dele dias depois.
 *
 * A CONSULTA E POR REFERENCIA, e nao por id do pagamento. E a diferenca para o
 * paygw_asaas, e ela e estrutural: la a cobranca nasce com id no momento da
 * criacao; aqui nasce a PREFERENCIA, e o pagamento so passa a existir quando o
 * aluno paga. Uma linha pendente nao tem mppaymentid nenhum para consultar - so
 * tem a external_reference que nos mesmos geramos, e e por ela que se procura.
 *
 * Nao substitui o webhook: complementa. Por isso roda de hora em hora e olha
 * uma janela curta, em vez de varrer o historico inteiro.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile extends \core\task\scheduled_task {
    /** @var int Idade minima da transacao para valer a pena conferir. */
    public const MIN_AGE = 5 * MINSECS;

    /** @var int Depois disto a preferencia ja expirou e nao vale mais uma ida a rede. */
    public const MAX_AGE = 30 * DAYSECS;

    /** @var int Teto por execucao, para uma fila grande nao estourar o cron. */
    public const BATCH = 200;

    /**
     * Nome na tela de tarefas.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskreconcile', 'paygw_mercadopago');
    }

    /**
     * Executa.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();

        // Qualquer status NAO TERMINAL e sem pagamento entregue - nao so
        // 'pending'. Um pagamento em analise antifraude fixa 'in_process', e
        // cartao com 3DS pendente fixa 'authorized'; se o webhook que
        // avancaria dali se perder, a linha ficava presa para sempre porque
        // esta varredura so olhava o status literal 'pending'. 'rejected' e
        // 'cancelled' ficam de fora de proposito: o Mercado Pago nao os
        // reverte, e conferir de novo so gastaria chamada a toa.
        //
        // A linha nasce antes da chamada a API de proposito, para que uma
        // falha ali deixe rastro reconciliavel em vez de sumir.
        $select = "status NOT IN (:rejected, :cancelled) AND paymentid IS NULL
                    AND timecreated < :young AND timecreated > :old";
        $params = [
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            // Transacoes recem-criadas ainda estao com o aluno na tela de
            // pagamento. Conferir agora so gastaria chamada a toa.
            'young' => $now - self::MIN_AGE,
            'old' => $now - self::MAX_AGE,
        ];

        $records = $DB->get_records_select(
            payment_processor::TABLE,
            $select,
            $params,
            'timecreated ASC',
            'id, externalreference, accountid',
            0,
            self::BATCH
        );

        $conferidas = 0;
        $entregues = 0;

        foreach ($records as $record) {
            if (empty($record->externalreference)) {
                continue;
            }

            $conferidas++;

            try {
                if (payment_processor::reconcile_transaction($record)) {
                    $entregues++;
                }
            } catch (\Throwable $e) {
                // Uma conta com token revogado nao pode parar a fila das
                // outras empresas.
                mtrace('paygw_mercadopago: falha ao conferir ' . $record->externalreference . ' - ' . $e->getMessage());
            }
        }

        mtrace(sprintf('paygw_mercadopago: %d transacoes conferidas, %d entregues agora.', $conferidas, $entregues));
    }
}
