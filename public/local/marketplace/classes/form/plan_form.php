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

use local_marketplace\commission;
use local_marketplace\country;
use local_marketplace\plan;
use local_marketplace\plan_tier;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Cadastro de plano comercial.
 *
 * Esta tela existe para que preco e comissao mudem por decisao comercial, e nao
 * por deploy. O seed da instalacao apenas semeia valores iniciais; a partir
 * dai a verdade e o que estiver aqui.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_form extends \moodleform {
    /** @var int Quantas faixas de resolucao o formulario oferece. */
    public const TIER_SLOTS = 4;

    /**
     * Campos.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $planid = (int) ($this->_customdata['planid'] ?? 0);
        $editing = $planid > 0;

        $mform->addElement('hidden', 'id', $planid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('planname', 'local_marketplace'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // O nome curto e chave de codigo e do seed. Depois de criado ele nao
        // muda: mudar quebraria a idempotencia do seed, que reinseriria o plano
        // como se fosse novo na proxima instalacao.
        if ($editing) {
            $mform->addElement(
                'static',
                'shortnamestatic',
                get_string('planshortname', 'local_marketplace'),
                s($this->_customdata['shortname'] ?? '')
            );
            $mform->addElement('hidden', 'shortname', $this->_customdata['shortname'] ?? '');
            $mform->setType('shortname', PARAM_ALPHANUMEXT);
        } else {
            $mform->addElement('text', 'shortname', get_string('planshortname', 'local_marketplace'), ['size' => 30]);
            $mform->setType('shortname', PARAM_ALPHANUMEXT);
            $mform->addRule('shortname', null, 'required', null, 'client');
            $mform->addHelpButton('shortname', 'planshortname', 'local_marketplace');
        }

        $mform->addElement('textarea', 'description', get_string('plandescription', 'local_marketplace'), [
            'rows' => 3,
            'cols' => 60,
        ]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('text', 'monthlyfee', get_string('planmonthlyfee', 'local_marketplace'), ['size' => 10]);
        $mform->setType('monthlyfee', PARAM_FLOAT);
        $mform->addHelpButton('monthlyfee', 'planmonthlyfee', 'local_marketplace');

        $mform->addElement('text', 'commissionpct', get_string('plancommissionpct', 'local_marketplace'), ['size' => 10]);
        $mform->setType('commissionpct', PARAM_FLOAT);
        $mform->addHelpButton('commissionpct', 'plancommissionpct', 'local_marketplace');

        // A base viaja junto com a taxa: quem define uma define a outra. O
        // vazio herda a base do site, e e diferente de escolher bruto - por
        // isso a opcao existe explicitamente.
        $mform->addElement('select', 'commissionbase', get_string('plancommissionbase', 'local_marketplace'), [
            '' => get_string('commissionbaseinherit', 'local_marketplace'),
            commission::BASE_GROSS => get_string('commissionbasegross', 'local_marketplace'),
            commission::BASE_NET => get_string('commissionbasenet', 'local_marketplace'),
        ]);
        $mform->setType('commissionbase', PARAM_ALPHA);
        $mform->addHelpButton('commissionbase', 'plancommissionbase', 'local_marketplace');

        $countries = [];
        foreach (country::codes() as $code) {
            $countries[$code] = country::describe($code);
        }
        $mform->addElement('select', 'country', get_string('plancountry', 'local_marketplace'), $countries);
        $mform->setDefault('country', country::DEFAULT_COUNTRY);

        $mform->addElement('select', 'hostingmodel', get_string('planhostingmodel', 'local_marketplace'), [
            plan::HOSTING_NATIVE => get_string('hostingnative', 'local_marketplace'),
            plan::HOSTING_BYOS => get_string('hostingbyos', 'local_marketplace'),
        ]);

        $mform->addElement('advcheckbox', 'ispublic', get_string('planispublic', 'local_marketplace'));
        $mform->setDefault('ispublic', 1);

        $mform->addElement('select', 'status', get_string('planstatus', 'local_marketplace'), [
            plan::STATUS_ACTIVE => get_string('planstatusactive', 'local_marketplace'),
            plan::STATUS_ARCHIVED => get_string('planstatusarchived', 'local_marketplace'),
        ]);

        $mform->addElement('text', 'sortorder', get_string('plansortorder', 'local_marketplace'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        // Faixas de resolucao. Slots fixos em vez de repeat dinamico: sao no
        // maximo quatro resolucoes possiveis, entao mais de quatro faixas nao
        // teria o que dizer.
        $mform->addElement('header', 'tiersheader', get_string('plantiers', 'local_marketplace'));
        $mform->addHelpButton('tiersheader', 'plantiers', 'local_marketplace');
        $mform->setExpanded('tiersheader');

        $resolutions = [];
        foreach (plan_tier::RESOLUTIONS as $resolution) {
            $resolutions[$resolution] = $resolution;
        }

        for ($i = 0; $i < self::TIER_SLOTS; $i++) {
            $group = [
                $mform->createElement('text', "tiermaxprice[$i]", '', ['size' => 10]),
                $mform->createElement('select', "tiermaxresolution[$i]", '', $resolutions),
            ];

            $mform->addGroup(
                $group,
                "tier$i",
                get_string('plantiermaxprice', 'local_marketplace') . ' / ' .
                    get_string('plantiermaxresolution', 'local_marketplace'),
                ' ',
                false
            );
            $mform->setType("tiermaxprice[$i]", PARAM_RAW_TRIMMED);
        }

        $this->add_action_buttons();
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

        if (isset($data['commissionpct']) && ($data['commissionpct'] < 0 || $data['commissionpct'] > 100)) {
            $errors['commissionpct'] = get_string('errorcommissionrange', 'local_marketplace');
        }

        if (isset($data['monthlyfee']) && $data['monthlyfee'] < 0) {
            $errors['monthlyfee'] = get_string('errorplanfeenegative', 'local_marketplace');
        }

        foreach (($data['tiermaxprice'] ?? []) as $i => $value) {
            $value = trim((string) $value);

            // Vazio e valido e significa "sem teto" - e a ultima faixa.
            if ($value === '') {
                continue;
            }

            if (!is_numeric($value) || (float) $value < 0) {
                $errors["tier$i"] = get_string('errorplantiernegative', 'local_marketplace');
            }
        }

        return $errors;
    }
}
