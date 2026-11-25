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

namespace paygw_asaas\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use paygw_asaas\payment_processor;

/**
 * Cria a cobranca e devolve para onde mandar o aluno.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_charge extends external_api {
    /**
     * Parametros.
     *
     * O PRECO nao e parametro, e nunca pode ser: ele sai do get_payable() a
     * partir do itemid, no servidor. Aceitar valor do navegador seria deixar o
     * aluno escolher quanto pagar.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Componente dono do item'),
            'paymentarea' => new external_value(PARAM_AREA, 'Area de pagamento'),
            'itemid' => new external_value(PARAM_INT, 'Id do item'),
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
            // O detalhe vai para o log do desenvolvedor, nao para a tela: a
            // mensagem crua da API pode carregar dado da conta do vendedor, e
            // quem esta olhando e o aluno.
            debugging('paygw_asaas create_charge: ' . $e->getMessage(), DEBUG_DEVELOPER);

            return [
                'success' => false,
                'redirecturl' => '',
                'message' => get_string('errorcreatingcharge', 'paygw_asaas'),
            ];
        }

        return ['success' => true, 'redirecturl' => $url, 'message' => ''];
    }

    /**
     * Retorno.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Se a cobranca foi criada'),
            'redirecturl' => new external_value(PARAM_URL, 'Fatura hospedada do Asaas'),
            'message' => new external_value(PARAM_TEXT, 'Mensagem de erro, se houver'),
        ]);
    }
}
