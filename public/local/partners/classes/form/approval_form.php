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

namespace local_partners\form;

use local_marketplace\company;
use local_marketplace\plan;
use local_partners\application;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Decisao sobre uma candidatura: aprovar ou recusar.
 *
 * Aprovar cria uma EMPRESA e uma CATEGORIA de curso - objeto global do site,
 * que aparece na arvore que todo usuario ve. Por isso a decisao e humana e por
 * isso os campos que definem esse objeto (o atalho e o dono) sao preenchidos
 * aqui, e nao no formulario publico. E exatamente o que a regra "sem
 * auto-atendimento para criar empresa" pede.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class approval_form extends \moodleform {
    /**
     * Campos.
     *
     * @return void
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'decision', get_string('decision', 'local_partners'), [
            application::STATUS_APPROVED => get_string('decisionapprove', 'local_partners'),
            application::STATUS_REJECTED => get_string('decisionreject', 'local_partners'),
        ]);

        // Campos que so valem na aprovacao.

        $mform->addElement('text', 'shortname', get_string('companyshortname', 'local_partners'), ['size' => 30]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('shortname', 'companyshortname', 'local_partners');
        $mform->setDefault('shortname', $this->_customdata['suggestedshortname'] ?? '');
        $mform->hideIf('shortname', 'decision', 'eq', application::STATUS_REJECTED);

        // O e-mail do candidato aparece como texto para o administrador achar a
        // conta dele no seletor - ou perceber que ela nao existe.
        $mform->addElement(
            'static',
            'contactemailstatic',
            get_string('contactemail', 'local_partners'),
            s($this->_customdata['contactemail'] ?? '')
        );
        $mform->hideIf('contactemailstatic', 'decision', 'eq', application::STATUS_REJECTED);

        $mform->addElement('autocomplete', 'ownerid', get_string('companyowner', 'local_partners'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'valuehtmlcallback' => function ($userid) use ($DB) {
                if (empty($userid)) {
                    return '';
                }
                $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);

                return $user ? fullname($user) . ' (' . s($user->email) . ')' : '';
            },
        ]);
        $mform->addHelpButton('ownerid', 'companyowner', 'local_partners');
        $mform->setDefault('ownerid', $this->_customdata['matcheduserid'] ?? null);
        $mform->hideIf('ownerid', 'decision', 'eq', application::STATUS_REJECTED);

        // Deixar em branco tem significado, e o formulario precisa dizer qual.
        // Sem esta linha o administrador so descobre o que aconteceu depois de
        // salvar - e criar usuario nao e coisa que se descobre depois.
        $mform->addElement(
            'static',
            'ownernotice',
            '',
            get_string(
                empty($this->_customdata['matcheduserid']) ? 'ownerwillbecreated' : 'ownermatched',
                'local_partners',
                s($this->_customdata['contactemail'] ?? '')
            )
        );
        $mform->hideIf('ownernotice', 'decision', 'eq', application::STATUS_REJECTED);

        $plans = ['' => get_string('planundecided', 'local_partners')];
        foreach (plan::get_records(['status' => plan::STATUS_ACTIVE], 'sortorder, name') as $plan) {
            $plans[(int) $plan->get('id')] = format_string($plan->get('name'));
        }
        $mform->addElement('select', 'planid', get_string('plan', 'local_partners'), $plans);
        $mform->setType('planid', PARAM_INT);
        $mform->setDefault('planid', $this->_customdata['planid'] ?? '');
        $mform->hideIf('planid', 'decision', 'eq', application::STATUS_REJECTED);

        // Comum as duas decisoes.

        $mform->addElement('textarea', 'reviewnote', get_string('reviewnote', 'local_partners'), [
            'rows' => 3,
            'cols' => 60,
        ]);
        $mform->setType('reviewnote', PARAM_TEXT);
        $mform->addHelpButton('reviewnote', 'reviewnote', 'local_partners');

        $this->add_action_buttons(true, get_string('savedecision', 'local_partners'));
    }

    /**
     * Validacao.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (($data['decision'] ?? '') !== application::STATUS_APPROVED) {
            return $errors;
        }

        $shortname = trim((string) ($data['shortname'] ?? ''));

        if ($shortname === '') {
            $errors['shortname'] = get_string('errorshortnamerequired', 'local_partners');
        } else if (company::get_record(['shortname' => $shortname])) {
            $errors['shortname'] = get_string('errorshortnametaken', 'local_partners');
        }

        return $errors;
    }
}
