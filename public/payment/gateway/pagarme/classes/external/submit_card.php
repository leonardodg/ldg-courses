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
 * Recebe o token do cartao e cria a cobranca.
 *
 * Chega TOKEN, nunca numero de cartao. A tokenizacao acontece no navegador,
 * contra a chave publica, e o dado sensivel nao passa por este servidor - o
 * que mantem a instalacao fora do escopo mais pesado de PCI.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_card extends external_api {
    /**
     * Parametros aceitos.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_ALPHANUMEXT, 'Nossa referencia da cobranca'),
            'cardtoken' => new external_value(PARAM_ALPHANUMEXT, 'Token devolvido pelo Pagar.me'),
            // O endereco de cobranca nao vai no token, e a cobranca nao nasce
            // sem ele. Vem daqui porque o perfil do Moodle nao tem CEP.
            'zipcode' => new external_value(PARAM_ALPHANUMEXT, 'CEP, so digitos'),
            'line1' => new external_value(PARAM_TEXT, 'Logradouro, numero e bairro'),
            'city' => new external_value(PARAM_TEXT, 'Cidade'),
            'state' => new external_value(PARAM_ALPHA, 'UF'),
        ]);
    }

    /**
     * Executa.
     *
     * @param string $reference
     * @param string $cardtoken
     * @param string $zipcode
     * @param string $line1
     * @param string $city
     * @param string $state
     * @return array
     */
    public static function execute(
        string $reference,
        string $cardtoken,
        string $zipcode,
        string $line1,
        string $city,
        string $state
    ): array {
        global $DB, $USER;

        [
            'reference' => $reference,
            'cardtoken' => $cardtoken,
            'zipcode' => $zipcode,
            'line1' => $line1,
            'city' => $city,
            'state' => $state,
        ] = self::validate_parameters(self::execute_parameters(), [
            'reference' => $reference,
            'cardtoken' => $cardtoken,
            'zipcode' => $zipcode,
            'line1' => $line1,
            'city' => $city,
            'state' => $state,
        ]);

        self::validate_context(\context_system::instance());

        $record = $DB->get_record(payment_processor::TABLE, ['externalreference' => $reference]);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            throw new \moodle_exception('invalidaccess', 'error');
        }

        // Uma linha que ja tem cobranca nao pode ganhar outra: dois cliques no
        // botao cobrariam duas vezes.
        if (!empty($record->chargeid)) {
            return [
                'success' => true,
                'redirecturl' => (new \moodle_url(
                    '/payment/gateway/pagarme/return.php',
                    ['ref' => $reference]
                ))->out(false),
                'message' => '',
            ];
        }

        $billing = [
            'line_1' => $line1,
            'zip_code' => preg_replace('/\D/', '', $zipcode),
            'city' => $city,
            'state' => strtoupper($state),
            'country' => 'BR',
        ];

        try {
            payment_processor::create_charge_for($record, $cardtoken, $billing);
        } catch (\Throwable $e) {
            debugging('paygw_pagarme: ' . $e->getMessage(), DEBUG_DEVELOPER);

            return [
                'success' => false,
                'redirecturl' => '',
                'message' => get_string('errorcreatingcharge', 'paygw_pagarme'),
            ];
        }

        return [
            'success' => true,
            'redirecturl' => (new \moodle_url(
                '/payment/gateway/pagarme/return.php',
                ['ref' => $reference]
            ))->out(false),
            'message' => '',
        ];
    }

    /**
     * Formato da resposta.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Se deu certo'),
            'redirecturl' => new external_value(PARAM_RAW, 'Para onde mandar o aluno'),
            'message' => new external_value(PARAM_TEXT, 'Mensagem de erro, quando houver'),
        ]);
    }
}
