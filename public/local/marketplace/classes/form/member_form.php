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

namespace local_marketplace\form;

use local_marketplace\member;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Vincula um usuario existente a uma empresa.
 *
 * Nao cria usuario: a pessoa precisa ja ter conta na plataforma. Criar conta
 * daqui produziria um usuario sem senha, que e exatamente o problema que o
 * seed_demo tem.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class member_form extends \moodleform {
    /**
     * Campos.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'addheader', get_string('addmember', 'local_marketplace'));

        $mform->addElement('autocomplete', 'userid', get_string('user'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'valuehtmlcallback' => function ($userid) {
                if (empty($userid)) {
                    return '';
                }
                $user = \core_user::get_user($userid);
                return $user ? fullname($user) . ' (' . s($user->email) . ')' : '';
            },
        ]);
        $mform->addRule('userid', null, 'required', null, 'client');
        $mform->addHelpButton('userid', 'addmember', 'local_marketplace');

        $mform->addElement('hidden', 'id', $this->_customdata['companyid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('submit', 'submitbutton', get_string('addmember', 'local_marketplace'));
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

        $companyid = (int) $this->_customdata['companyid'];
        if (!empty($data['userid']) && member::get_membership($companyid, (int) $data['userid'])) {
            $errors['userid'] = get_string('erroralreadymember', 'local_marketplace');
        }

        return $errors;
    }
}
