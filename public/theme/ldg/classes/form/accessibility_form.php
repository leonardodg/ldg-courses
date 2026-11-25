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

namespace theme_ldg\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Preferencias de acessibilidade.
 *
 * @package    theme_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accessibility_form extends moodleform {
    /**
     * Monta o formulario.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'advcheckbox',
            'accessibilitybar',
            get_string('accessibilitybar', 'theme_ldg'),
            get_string('accessibilitybardesc', 'theme_ldg')
        );
        $mform->addHelpButton('accessibilitybar', 'accessibilitybar', 'theme_ldg');
        $mform->setType('accessibilitybar', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
