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

namespace paygw_asaas\task;

use paygw_asaas\payment_processor;

/**
 * Confere no Asaas as cobrancas que continuam pendentes aqui.
 *
 * O webhook e a fonte da verdade, mas ele e uma entrega pela rede: pode cair
 * durante um deploy, pode esbarrar num certificado expirado, pode chegar
 * enquanto o banco esta em manutencao. Sem esta varredura, o aluno que pagou
 * ficaria sem o curso e sem ninguem saber - e a unica pista seria uma
 * reclamacao dele dias depois.
 *
 * Nao substitui o webhook: complementa. Por isso roda de hora em hora e olha
 * uma janela curta, em vez de varrer o historico inteiro.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile extends \core\task\scheduled_task {
    /** @var int Idade minima da cobranca para valer a pena conferir. */
    public const MIN_AGE = 5 * MINSECS;

    /** @var int Depois disto a cobranca ja venceu e nao vale mais uma ida a rede. */
    public const MAX_AGE = 30 * DAYSECS;

    /** @var int Teto por execucao, para uma fila grande nao estourar o cron. */
    public const BATCH = 200;

    /**
     * Nome na tela de tarefas.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskreconcile', 'paygw_asaas');
    }

    /**
     * Executa.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();
        $select = "status = :status AND paymentid IS NULL AND timecreated < :young AND timecreated > :old";
        $params = [
            'status' => 'PENDING',
            // Cobrancas recem-criadas ainda estao com o aluno na tela de
            // pagamento. Conferir agora so gastaria chamada a toa.
            'young' => $now - self::MIN_AGE,
            'old' => $now - self::MAX_AGE,
        ];

        $records = $DB->get_records_select(
            payment_processor::TABLE,
            $select,
            $params,
            'timecreated ASC',
            'id, asaaspaymentid',
            0,
            self::BATCH
        );

        $checked = 0;
        $delivered = 0;

        foreach ($records as $record) {
            if (empty($record->asaaspaymentid)) {
                // A cobranca nao chegou a ser criada no Asaas - a chamada
                // falhou depois de gravarmos a linha. Nao ha o que consultar.
                continue;
            }

            $checked++;

            try {
                if (payment_processor::process_notification($record->asaaspaymentid)) {
                    $delivered++;
                }
            } catch (\Throwable $e) {
                // Uma conta com credencial revogada nao pode parar a fila das
                // outras empresas.
                mtrace('paygw_asaas: falha ao conferir ' . $record->asaaspaymentid . ' - ' . $e->getMessage());
            }
        }

        mtrace(sprintf('paygw_asaas: %d cobrancas conferidas, %d entregues agora.', $checked, $delivered));
    }
}
