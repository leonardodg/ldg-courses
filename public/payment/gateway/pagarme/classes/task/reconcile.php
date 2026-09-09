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

namespace paygw_pagarme\task;

use paygw_pagarme\payment_processor;

/**
 * Varre cobrancas pendentes que o webhook nao alcancou.
 *
 * O webhook e a via normal; esta tarefa e a rede de baixo. Sem ela, um aluno
 * que pagou durante uma queda do site ficaria sem acesso ate alguem reparar.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile extends \core\task\scheduled_task {
    /** @var int Mais novo que isto, o aluno ainda esta na tela de pagamento. */
    const MIN_AGE = 5 * MINSECS;

    /** @var int Mais velho que isto, a cobranca ja expirou. */
    const MAX_AGE = 30 * DAYSECS;

    /** @var int Teto por rodada. */
    const BATCH = 200;

    /**
     * Nome na tela de tarefas.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskreconcile', 'paygw_pagarme');
    }

    /**
     * Executa.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();

        // O status processing entra junto com pending por medicao: uma
        // cobranca de cartao ficou nesse estado e nunca saiu de la sozinha.
        [$insql, $inparams] = $DB->get_in_or_equal(
            ['pending', 'processing'],
            SQL_PARAMS_NAMED,
            'st'
        );

        $records = $DB->get_records_select(
            payment_processor::TABLE,
            "status $insql AND paymentid IS NULL
             AND chargeid <> '' AND chargeid IS NOT NULL
             AND timecreated < :young AND timecreated > :old",
            $inparams + ['young' => $now - self::MIN_AGE, 'old' => $now - self::MAX_AGE],
            'timecreated ASC',
            'id, chargeid, subscriptionid',
            0,
            self::BATCH
        );

        $checked = 0;
        $delivered = 0;

        foreach ($records as $record) {
            $checked++;
            try {
                $done = payment_processor::process_notification(
                    (string) $record->chargeid,
                    (string) ($record->subscriptionid ?? '')
                );
                if ($done) {
                    $delivered++;
                }
            } catch (\Throwable $e) {
                // Uma credencial revogada nao pode travar a fila inteira.
                mtrace('paygw_pagarme: cobranca ' . $record->chargeid . ' falhou - ' . $e->getMessage());
            }
        }

        mtrace(sprintf(
            'paygw_pagarme: %d cobrancas conferidas, %d entregues agora.',
            $checked,
            $delivered
        ));
    }
}
