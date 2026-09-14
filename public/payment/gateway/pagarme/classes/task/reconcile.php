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

    /**
     * Ate quando vale corrigir a comissao de uma venda ja entregue.
     *
     * NAO e o limite da varredura de pendentes - esse sai de
     * payment_processor::sweep_window_for(), por forma de pagamento. Aqui a
     * janela e longa de proposito: a venda ja aconteceu, e uma comissao que
     * ficou zero merece semanas de tentativa, nao horas.
     *
     * @var int
     */
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

        // A janela de cada forma de pagamento entra na propria consulta, em
        // vez de carregar tudo e descartar em PHP. Ver
        // payment_processor::sweep_window_for(): o Pagar.me nunca diz que a
        // cobranca venceu, entao o limite tem que sair daqui.
        $janelas = [];
        $prazos = [];
        foreach (['pix', 'boleto', 'credit_card'] as $i => $metodo) {
            $janelas[] = "(paymentmethod = :m$i AND timecreated > :t$i)";
            $prazos["m$i"] = $metodo;
            $prazos["t$i"] = $now - payment_processor::sweep_window_for($metodo);
        }
        $dentrodajanela = '(' . implode(' OR ', $janelas) . ')';

        $records = $DB->get_records_select(
            payment_processor::TABLE,
            "status $insql AND paymentid IS NULL
             AND chargeid <> '' AND chargeid IS NOT NULL
             AND timecreated < :young AND $dentrodajanela",
            $inparams + $prazos + ['young' => $now - self::MIN_AGE],
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

        $corrigidas = $this->fix_missing_commission();

        mtrace(sprintf(
            'paygw_pagarme: %d cobrancas conferidas, %d entregues agora, %d comissoes corrigidas.',
            $checked,
            $delivered,
            $corrigidas
        ));
    }

    /**
     * Preenche a comissao das vendas que foram entregues antes do extrato.
     *
     * O payable nasce cerca de 16 segundos depois do pagamento, e o webhook
     * chega antes. A venda entra com feeamount zero de proposito - gravar o
     * valor esperado seria registrar dinheiro que ninguem viu - e e aqui que
     * ele e corrigido, lendo o extrato de verdade.
     *
     * Uma linha que continuar zerada depois disso nao e atraso: e split que
     * nao aconteceu, e ai o zero e a informacao certa.
     *
     * @return int Quantas linhas foram corrigidas.
     */
    protected function fix_missing_commission(): int {
        global $DB;

        $records = $DB->get_records_select(
            payment_processor::TABLE,
            "paymentid IS NOT NULL AND feeamount <= 0 AND feepercent > 0
             AND chargeid <> '' AND chargeid IS NOT NULL
             AND timemodified > :desde",
            ['desde' => time() - self::MAX_AGE],
            'timemodified ASC',
            '*',
            0,
            self::BATCH
        );

        $corrigidas = 0;

        foreach ($records as $record) {
            try {
                $apikey = \paygw_pagarme\credentials::api_key(
                    (int) $record->accountid,
                    $record->environment
                );
                if ($apikey === '') {
                    continue;
                }

                $recipient = \paygw_pagarme\credentials::platform_recipient(
                    (int) $record->accountid,
                    $record->environment
                );
                $comissao = (new \paygw_pagarme\pagarme_client($apikey))
                    ->commission_for_charge((string) $record->chargeid, $recipient);

                if ($comissao <= 0) {
                    continue;
                }

                $record->feeamount = $comissao;
                $record->timemodified = time();
                $DB->update_record(payment_processor::TABLE, $record);
                $corrigidas++;

                // A venda no marketplace tambem guarda o valor, e ela e a que
                // o relatorio le.
                if (class_exists('\local_marketplace\sale')) {
                    $venda = \local_marketplace\sale::get_record(['paymentid' => (int) $record->paymentid]);
                    if ($venda) {
                        $venda->set('feeamount', $comissao);
                        $venda->update();
                    }
                }
            } catch (\Throwable $e) {
                mtrace('paygw_pagarme: comissao de ' . $record->chargeid . ' - ' . $e->getMessage());
            }
        }

        return $corrigidas;
    }
}
