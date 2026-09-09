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

namespace paygw_pagarme\form;

use paygw_pagarme\pagarme_client;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Onde o vendedor cola a chave e o recebedor da plataforma.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_form extends \moodleform {
    /**
     * Campos.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'accountid');
        $mform->setType('accountid', PARAM_INT);

        $mform->addElement('hidden', 'environment');
        $mform->setType('environment', PARAM_ALPHA);

        $mform->addElement(
            'passwordunmask',
            'apikey',
            get_string('apikey', 'paygw_pagarme'),
            ['size' => 60]
        );
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        $mform->addRule('apikey', null, 'required', null, 'client');
        $mform->addHelpButton('apikey', 'apikey', 'paygw_pagarme');

        $mform->addElement(
            'text',
            'platformrecipient',
            get_string('platformrecipient', 'paygw_pagarme'),
            ['size' => 40]
        );
        $mform->setType('platformrecipient', PARAM_ALPHANUMEXT);
        $mform->addRule('platformrecipient', null, 'required', null, 'client');
        $mform->addHelpButton('platformrecipient', 'platformrecipient', 'paygw_pagarme');

        $this->add_action_buttons(true, get_string('link', 'paygw_pagarme'));
    }

    /**
     * Recusa chave do ambiente errado antes de gastar uma chamada na API.
     *
     * O prefixo e a unica coisa que separa homologacao de producao no
     * Pagar.me - nao ha host diferente para tropecar antes.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $environment = (string) ($data['environment'] ?? '');
        $key = (string) ($data['apikey'] ?? '');

        if ($key !== '' && pagarme_client::environment_of_key($key) !== $environment) {
            $errors['apikey'] = get_string('errorkeyenvironment', 'paygw_pagarme');
        }

        return $errors;
    }
}
