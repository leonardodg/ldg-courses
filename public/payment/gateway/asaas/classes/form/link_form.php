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

namespace paygw_asaas\form;

use paygw_asaas\asaas_client;
use paygw_asaas\gateway;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Onde o vendedor cola a chave da propria conta Asaas.
 *
 * Fica fora do formulario de gateway do core de proposito: aquele formulario
 * serializa em config tudo que ele devolve, entao a chave ficaria em claro no
 * JSON e voltaria para a tela a cada edicao. Aqui a chave e conferida contra a
 * API, cifrada e gravada - e nunca mais renderizada.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_form extends \moodleform {
    /**
     * Campos.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $environment = $this->_customdata['environment'];

        $mform->addElement('hidden', 'accountid');
        $mform->setType('accountid', PARAM_INT);

        $mform->addElement('hidden', 'environment');
        $mform->setType('environment', PARAM_ALPHA);

        $mform->addElement('static', 'envnotice', get_string('environment', 'paygw_asaas'), \html_writer::tag(
            'strong',
            gateway::environment_label($environment)
        ));

        $mform->addElement('passwordunmask', 'apikey', get_string('apikey', 'paygw_asaas'), ['size' => 60]);
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        $mform->addRule('apikey', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('apikey', 'apikey', 'paygw_asaas');

        $this->add_action_buttons(true, get_string('link', 'paygw_asaas'));
    }

    /**
     * Recusa a chave do ambiente errado antes de gastar uma ida a rede.
     *
     * Colar a chave de homologacao no bloco de producao e o erro mais provavel
     * desta tela, e o prefixo aact_hmlg_ deixa isso obvio sem perguntar a
     * ninguem. Sem esta checagem o retorno seria um 401 generico, que nao diz
     * qual foi o engano.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $key = trim((string) ($data['apikey'] ?? ''));
        if ($key === '') {
            return $errors;
        }

        // Sanidade minima ANTES de gastar uma ida-e-volta com a API real: uma
        // chave truncada por um copiar-colar acidental so falharia depois,
        // com a mensagem crua de rejeicao do Asaas em vez de um aviso
        // imediato. Chaves reais do Asaas tem bem mais que 40 caracteres.
        if (strlen($key) < 40 || !preg_match('/^\$?[A-Za-z0-9_.\-]+$/', $key)) {
            $errors['apikey'] = get_string('errorkeyformat', 'paygw_asaas');
            return $errors;
        }

        $declared = asaas_client::environment_of_key($key);
        if ($declared !== $data['environment']) {
            $errors['apikey'] = get_string('errorkeyenvironment', 'paygw_asaas', (object) [
                'chosen' => gateway::environment_label($data['environment']),
                'key' => gateway::environment_label($declared),
            ]);
        }

        return $errors;
    }
}
