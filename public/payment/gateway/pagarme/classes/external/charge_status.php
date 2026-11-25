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

namespace paygw_pagarme\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use paygw_pagarme\payment_processor;

/**
 * Situacao da cobranca, para a pagina do Pix perguntar.
 *
 * A pagina pergunta porque o Pix e assincrono e o webhook pode demorar - ou
 * se perder. Perguntar aqui nao substitui o webhook: quem entrega o curso e
 * sempre o process_notification, e este servico so decide quando a tela pode
 * parar de esperar.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class charge_status extends external_api {
    /**
     * Parametros aceitos.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_ALPHANUMEXT, 'Nossa referencia da cobranca'),
        ]);
    }

    /**
     * Executa.
     *
     * @param string $reference
     * @return array
     */
    public static function execute(string $reference): array {
        global $DB, $USER;

        ['reference' => $reference] = self::validate_parameters(
            self::execute_parameters(),
            ['reference' => $reference]
        );

        self::validate_context(\context_system::instance());

        $record = $DB->get_record(payment_processor::TABLE, ['externalreference' => $reference]);

        // A referencia e adivinhavel o bastante para nao valer sozinha como
        // autorizacao: quem pergunta tem que ser o dono da cobranca.
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            return ['paid' => false, 'status' => '', 'redirecturl' => ''];
        }

        // Consulta a API, e nao so a nossa linha: o webhook pode nao ter
        // chegado, e e isso que a tela esta esperando descobrir.
        try {
            payment_processor::process_notification((string) $record->chargeid);
            $record = $DB->get_record(payment_processor::TABLE, ['id' => $record->id]);
        } catch (\Throwable $e) {
            debugging('paygw_pagarme: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $paid = payment_processor::is_paid((string) $record->status) && !empty($record->paymentid);
        $url = '';
        if ($paid) {
            $url = \core_payment\helper::get_success_url(
                $record->component,
                $record->paymentarea,
                (int) $record->itemid
            )->out(false);
        }

        return ['paid' => $paid, 'status' => (string) $record->status, 'redirecturl' => $url];
    }

    /**
     * Formato da resposta.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'paid' => new external_value(PARAM_BOOL, 'Se o pagamento ja entrou'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Situacao crua no Pagar.me'),
            'redirecturl' => new external_value(PARAM_RAW, 'Para onde ir quando pago'),
        ]);
    }
}
