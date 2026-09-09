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
 * Cria a cobranca e devolve para onde mandar o aluno.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_charge extends external_api {
    /**
     * Parametros aceitos.
     *
     * O preco NAO e parametro, e nao pode ser: ele vem de get_payable() no
     * servidor. Aceitar valor vindo do navegador seria deixar o aluno
     * escolher quanto pagar.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Componente que vende'),
            'paymentarea' => new external_value(PARAM_AREA, 'Area de pagamento'),
            'itemid' => new external_value(PARAM_INT, 'Item comprado'),
        ]);
    }

    /**
     * Executa.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid): array {
        global $USER;

        [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);

        self::validate_context(\context_system::instance());

        try {
            $url = payment_processor::start_payment($component, $paymentarea, $itemid, (int) $USER->id);
        } catch (\Throwable $e) {
            // A mensagem crua da API nao chega a tela do aluno: ela pode
            // carregar dado da conta do vendedor.
            debugging('paygw_pagarme: ' . $e->getMessage(), DEBUG_DEVELOPER);

            return [
                'success' => false,
                'redirecturl' => '',
                'message' => get_string('errorcreatingcharge', 'paygw_pagarme'),
            ];
        }

        return ['success' => true, 'redirecturl' => $url, 'message' => ''];
    }

    /**
     * Formato da resposta.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Se deu certo'),
            'redirecturl' => new external_value(PARAM_URL, 'Para onde mandar o aluno'),
            'message' => new external_value(PARAM_TEXT, 'Mensagem de erro, quando houver'),
        ]);
    }
}
