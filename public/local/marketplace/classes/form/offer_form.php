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

use local_marketplace\company;
use local_marketplace\offer;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Cadastro de oferta.
 *
 * A assinatura tem TRES parametros independentes, e nao um. Ate aqui havia so
 * accessdays, que significava "duracao do acesso" num modo e "intervalo de
 * cobranca" noutro - dois conceitos no mesmo campo, o que impedia dar carencia
 * e impedia limitar quantas vezes a assinatura cobra.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class offer_form extends \moodleform {
    /**
     * Campos.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $company = $this->_customdata['company'];

        $mform->addElement('hidden', 'id', $this->_customdata['offerid'] ?? 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'company', $company->get('shortname'));
        $mform->setType('company', PARAM_ALPHANUMEXT);

        // Basico.
        $mform->addElement('text', 'name', get_string('offername', 'local_marketplace'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'description', get_string('description'));
        $mform->setType('description', PARAM_RAW);

        $mform->addElement('select', 'offertype', get_string('offertype', 'local_marketplace'), [
            offer::TYPE_SINGLE => get_string('typesingle', 'local_marketplace'),
            offer::TYPE_BUNDLE => get_string('typebundle', 'local_marketplace'),
            offer::TYPE_CATALOG => get_string('typecatalog', 'local_marketplace'),
        ]);
        $mform->addHelpButton('offertype', 'offertype', 'local_marketplace');

        // Catalogo segue a categoria da empresa: cursos novos entram sozinhos,
        // entao escolher cursos ali seria uma lista que o sistema ignora.
        $courses = [];
        if ($company->get('categoryid')) {
            global $DB;
            $records = $DB->get_records(
                'course',
                ['category' => (int) $company->get('categoryid')],
                'fullname',
                'id, fullname'
            );
            foreach ($records as $c) {
                $courses[$c->id] = format_string($c->fullname);
            }
        }
        $select = $mform->addElement('select', 'courses', get_string('courses'), $courses);
        $select->setMultiple(true);
        $mform->hideIf('courses', 'offertype', 'eq', offer::TYPE_CATALOG);
        $mform->addHelpButton('courses', 'offercourses', 'local_marketplace');

        // Preco.
        $mform->addElement('text', 'price', get_string('cost'), ['size' => 12]);
        $mform->setType('price', PARAM_FLOAT);
        $mform->addHelpButton('price', 'offerprice', 'local_marketplace');

        // O pais decide a conta que recebe, a moeda e quais gateways aparecem
        // no checkout. Vender em outro pais e outra OFERTA, nao outro preco na
        // mesma: o core resolve valor, moeda e conta pelo itemid, sem saber
        // quem esta comprando.
        //
        // A lista sao os paises em que a empresa TEM conta. Oferecer um pais
        // sem conta produziria oferta que ninguem consegue pagar - o checkout
        // recusaria com o aluno ja decidido a comprar.
        $countries = [];
        foreach ($company->get_countries() as $code) {
            $countries[$code] = \local_marketplace\country::describe($code);
        }

        if ($countries) {
            $mform->addElement('select', 'country', get_string('offercountry', 'local_marketplace'), $countries);
            $mform->addHelpButton('country', 'offercountry', 'local_marketplace');
        } else {
            // Empresa ainda sem conta nenhuma. A oferta so pode ser gratuita, e
            // o pais fica no padrao ate alguem vincular uma conta.
            $mform->addElement(
                'static',
                'countrystatic',
                get_string('offercountry', 'local_marketplace'),
                get_string('nopaymentaccount', 'local_marketplace')
            );
            $mform->addElement('hidden', 'country', \local_marketplace\api::default_country());
        }
        $mform->setType('country', PARAM_ALPHA);

        // Assinatura.
        $mform->addElement('header', 'accessheader', get_string('offeraccess', 'local_marketplace'));
        $mform->setExpanded('accessheader');

        $mform->addElement('select', 'accessmode', get_string('offeraccessmode', 'local_marketplace'), [
            offer::ACCESS_LIFETIME => get_string('modelifetime', 'local_marketplace'),
            offer::ACCESS_DAYS => get_string('modedays', 'local_marketplace'),
            offer::ACCESS_RECURRING => get_string('moderecurring', 'local_marketplace'),
        ]);
        $mform->addHelpButton('accessmode', 'offeraccessmode', 'local_marketplace');

        $mform->addElement('text', 'accessdays', get_string('offeraccessdays', 'local_marketplace'), ['size' => 8]);
        $mform->setType('accessdays', PARAM_INT);
        $mform->setDefault('accessdays', 30);
        $mform->hideIf('accessdays', 'accessmode', 'eq', offer::ACCESS_LIFETIME);
        $mform->addHelpButton('accessdays', 'offeraccessdays', 'local_marketplace');

        $mform->addElement('text', 'billingdays', get_string('offerbillingdays', 'local_marketplace'), ['size' => 8]);
        $mform->setType('billingdays', PARAM_INT);
        $mform->setDefault('billingdays', 30);
        $mform->hideIf('billingdays', 'accessmode', 'neq', offer::ACCESS_RECURRING);
        $mform->addHelpButton('billingdays', 'offerbillingdays', 'local_marketplace');

        $mform->addElement('text', 'maxcycles', get_string('offermaxcycles', 'local_marketplace'), ['size' => 8]);
        $mform->setType('maxcycles', PARAM_INT);
        $mform->setDefault('maxcycles', 0);
        $mform->hideIf('maxcycles', 'accessmode', 'neq', offer::ACCESS_RECURRING);
        $mform->addHelpButton('maxcycles', 'offermaxcycles', 'local_marketplace');

        $mform->addElement(
            'static',
            'recurringwarning',
            '',
            get_string('offerrecurringwarning', 'local_marketplace')
        );
        $mform->hideIf('recurringwarning', 'accessmode', 'neq', offer::ACCESS_RECURRING);

        // Publicacao.
        $mform->addElement('header', 'pubheader', get_string('offerpublication', 'local_marketplace'));
        $mform->setExpanded('pubheader');

        $mform->addElement('select', 'status', get_string('companystatus', 'local_marketplace'), [
            offer::STATUS_DRAFT => get_string('statusdraft', 'local_marketplace'),
            offer::STATUS_PUBLISHED => get_string('statuspublished', 'local_marketplace'),
            offer::STATUS_ARCHIVED => get_string('statusarchived', 'local_marketplace'),
        ]);
        $mform->addHelpButton('status', 'offerstatus', 'local_marketplace');

        $mform->addElement('text', 'sortorder', get_string('offersortorder', 'local_marketplace'), ['size' => 8]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

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

        $mode = $data['accessmode'] ?? offer::ACCESS_LIFETIME;
        $paid = (float) ($data['price'] ?? 0) > 0;

        if ($mode !== offer::ACCESS_LIFETIME && (int) ($data['accessdays'] ?? 0) < 1) {
            $errors['accessdays'] = get_string('erroraccessdays', 'local_marketplace');
        }

        if ($mode === offer::ACCESS_RECURRING) {
            if ((int) ($data['billingdays'] ?? 0) < 1) {
                $errors['billingdays'] = get_string('errorbillingdays', 'local_marketplace');
            }
            if ((int) ($data['maxcycles'] ?? 0) < 0) {
                $errors['maxcycles'] = get_string('errormaxcycles', 'local_marketplace');
            }
            // Assinatura gratuita nao tem o que cobrar, entao o intervalo e o
            // limite de ciclos nunca seriam exercidos - e o aluno ficaria com
            // acesso que expira sem forma de renovar.
            if (!$paid) {
                $errors['price'] = get_string('errorrecurringfree', 'local_marketplace');
            }
        }

        // Oferta que nao e catalogo precisa dizer quais cursos libera, senao
        // vende acesso a nada.
        if (($data['offertype'] ?? '') !== offer::TYPE_CATALOG && empty($data['courses'])) {
            $errors['courses'] = get_string('errornocourses', 'local_marketplace');
        }

        if (($data['offertype'] ?? '') === offer::TYPE_SINGLE && count($data['courses'] ?? []) > 1) {
            $errors['courses'] = get_string('errorsinglemanycourses', 'local_marketplace');
        }

        return $errors;
    }
}
