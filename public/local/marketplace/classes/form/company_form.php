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
use local_marketplace\company;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Cadastro de empresa, usado pelo administrador.
 *
 * A parceria e fechada fora do sistema; esta tela apenas provisiona o que foi
 * combinado. Nao ha auto-atendimento porque criar empresa cria uma CATEGORIA,
 * que e objeto global - aparece na arvore que todos os usuarios veem.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class company_form extends \moodleform {
    /**
     * Campos.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        // Na edicao, atalho e dono saem do formulario. Os dois tem donos de
        // problema proprios: o atalho ja pode estar em link divulgado, e a
        // troca de dono se resolve na tela de vendedores, onde da para promover
        // outro antes de rebaixar o atual.
        $editing = !empty($this->_customdata['companyid']);

        $mform->addElement('hidden', 'id', $this->_customdata['companyid'] ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('companyname', 'local_marketplace'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        if ($editing) {
            $mform->addElement(
                'static',
                'shortnamestatic',
                get_string('companyshortname', 'local_marketplace'),
                s($this->_customdata['shortname'] ?? '')
            );
            $mform->addElement('hidden', 'shortname', $this->_customdata['shortname'] ?? '');
            $mform->setType('shortname', PARAM_ALPHANUMEXT);
        } else {
            $mform->addElement('text', 'shortname', get_string('companyshortname', 'local_marketplace'), ['size' => 30]);
            $mform->setType('shortname', PARAM_ALPHANUMEXT);
            $mform->addRule('shortname', null, 'required', null, 'client');
            $mform->addHelpButton('shortname', 'companyshortname', 'local_marketplace');

            // O dono e quem administra a empresa e vincula a conta Mercado Pago.
            // Autocomplete por ajax em vez de uma lista de usuarios: numa
            // plataforma aberta a lista cresce sem limite.
            $mform->addElement('autocomplete', 'ownerid', get_string('companyowner', 'local_marketplace'), [], [
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
            $mform->addRule('ownerid', null, 'required', null, 'client');
            $mform->addHelpButton('ownerid', 'companyowner', 'local_marketplace');
        }

        $mform->addElement('text', 'cnpj', get_string('companycnpj', 'local_marketplace'), ['size' => 20]);
        $mform->setType('cnpj', PARAM_ALPHANUM);
        $mform->addHelpButton('cnpj', 'companycnpj', 'local_marketplace');

        // O plano vem antes da comissao de proposito: e a fonte mais comum, e
        // deixar a comissao vazia significa "usar a do plano". So planos ativos
        // aparecem - arquivar existe para tirar um plano de circulacao.
        $plans = ['' => get_string('noplan', 'local_marketplace')];
        foreach (
            \local_marketplace\plan::get_records(
                ['status' => \local_marketplace\plan::STATUS_ACTIVE],
                'sortorder, name'
            ) as $plan
        ) {
            $plans[(int) $plan->get('id')] = format_string($plan->get('name'));
        }
        $mform->addElement('select', 'planid', get_string('plan', 'local_marketplace'), $plans);
        $mform->setType('planid', PARAM_INT);

        // Vazio NAO e zero. Vazio herda o plano e, sem plano, o padrao do site;
        // zero e isencao negociada. Um campo numerico obrigatorio nao teria
        // como dizer isso.
        $mform->addElement(
            'text',
            'commissionpct',
            get_string('companycommission', 'local_marketplace'),
            ['size' => 8]
        );
        $mform->setType('commissionpct', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('commissionpct', 'companycommission', 'local_marketplace');

        // A base viaja junto com a taxa: quem define uma define a outra. O
        // vazio herda a base do site, e e diferente de escolher bruto - por
        // isso a opcao existe explicitamente.
        $mform->addElement('select', 'commissionbase', get_string('companycommissionbase', 'local_marketplace'), [
            '' => get_string('commissionbaseinherit', 'local_marketplace'),
            commission::BASE_GROSS => get_string('commissionbasegross', 'local_marketplace'),
            commission::BASE_NET => get_string('commissionbasenet', 'local_marketplace'),
        ]);
        $mform->setType('commissionbase', PARAM_ALPHA);
        $mform->addHelpButton('commissionbase', 'companycommissionbase', 'local_marketplace');

        // Tema por categoria. Depende de $CFG->allowcategorythemes, que o
        // install.php garante - sem ele o campo e gravado e nao surte efeito.
        $themes = ['' => get_string('defaultthemename', 'local_marketplace')];
        foreach (\core_component::get_plugin_list('theme') as $name => $unused) {
            $themes[$name] = get_string('pluginname', 'theme_' . $name);
        }
        $mform->addElement('select', 'themename', get_string('companytheme', 'local_marketplace'), $themes);
        $mform->setType('themename', PARAM_PLUGIN);

        // Fase 3. O campo existe para o cadastro nao precisar ser refeito
        // depois, mas o dominio so passa a funcionar quando o mapa
        // Host->wwwroot do config.php entrar.
        $mform->addElement('text', 'hostname', get_string('companyhostname', 'local_marketplace'), ['size' => 40]);
        $mform->setType('hostname', PARAM_HOST);
        $mform->addHelpButton('hostname', 'companyhostname', 'local_marketplace');

        // Vitrine.
        $mform->addElement('header', 'pageheader', get_string('pagesection', 'local_marketplace'));

        $mform->addElement('text', 'pagetitle', get_string('pagetitle', 'local_marketplace'), ['size' => 50]);
        $mform->setType('pagetitle', PARAM_TEXT);
        $mform->addHelpButton('pagetitle', 'pagetitle', 'local_marketplace');

        $mform->addElement('editor', 'pageintro', get_string('pageintro', 'local_marketplace'));
        $mform->setType('pageintro', PARAM_RAW);
        $mform->addHelpButton('pageintro', 'pageintro', 'local_marketplace');

        $mform->addElement(
            'filemanager',
            'pagelogo',
            get_string('pagelogo', 'local_marketplace'),
            null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']]
        );
        $mform->addHelpButton('pagelogo', 'pagelogo', 'local_marketplace');

        $mform->addElement('text', 'pageaccent', get_string('pageaccent', 'local_marketplace'), ['size' => 10]);
        $mform->setType('pageaccent', PARAM_TEXT);
        $mform->addHelpButton('pageaccent', 'pageaccent', 'local_marketplace');

        // Arquivo, e nao textarea. CSS colado num campo de texto acabaria
        // renderizado inline na pagina, e ai o filtro teria que decidir o que e
        // seguro. Servido como .css estatico, o navegador trata como folha de
        // estilo e nunca como script.
        $mform->addElement(
            'filemanager',
            'pagecss',
            get_string('pagecss', 'local_marketplace'),
            null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.css']]
        );
        $mform->addHelpButton('pagecss', 'pagecss', 'local_marketplace');

        $this->add_action_buttons(true, get_string(
            $editing ? 'savechanges' : 'createcompany',
            $editing ? 'core' : 'local_marketplace'
        ));
    }

    /**
     * Validacao.
     *
     * Repete aqui o que o persistent ja checa porque a mensagem do persistent
     * sairia como excecao, e nao ao lado do campo errado.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Na edicao, a propria empresa nao conta como conflito - senao salvar
        // sem mudar nada acusaria "atalho ja em uso".
        $self = (int) ($data['id'] ?? 0);

        if (!empty($data['shortname'])) {
            $other = company::get_record(['shortname' => $data['shortname']]);
            if ($other && (int) $other->get('id') !== $self) {
                $errors['shortname'] = get_string('errorshortnametaken', 'local_marketplace');
            }
        }

        if (!empty($data['hostname'])) {
            $other = company::get_record(['hostname' => $data['hostname']]);
            if ($other && (int) $other->get('id') !== $self) {
                $errors['hostname'] = get_string('errorhostnametaken', 'local_marketplace');
            }
        }

        // String vazia herda o padrao do site e e valida. "0" nao e vazio: e
        // isencao, e precisa passar - is_numeric cuida disso, empty() nao.
        $pct = $data['commissionpct'] ?? '';
        if ($pct !== '' && $pct !== null) {
            if (!is_numeric($pct)) {
                $errors['commissionpct'] = get_string('errorcommissionrange', 'local_marketplace');
            } else if ((float) $pct < 0 || (float) $pct > 100) {
                $errors['commissionpct'] = get_string('errorcommissionrange', 'local_marketplace');
            }
        }

        return $errors;
    }
}
