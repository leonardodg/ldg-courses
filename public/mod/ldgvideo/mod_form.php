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

/**
 * Formulario da atividade de video.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * O formulario da atividade.
 *
 * @package    mod_ldgvideo
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ldgvideo_mod_form extends moodleform_mod {
    /**
     * Monta o formulario.
     *
     * @return void
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $config = get_config('ldgvideo');

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '48']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 1333), 'maxlength', 1333, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'videoheader', get_string('videoheader', 'ldgvideo'));
        $mform->setExpanded('videoheader');

        // TEXTAREA, e nao input de uma linha. O que o professor cola e o trecho
        // <iframe> inteiro, que tem centenas de caracteres: num campo de uma
        // linha ele nao consegue nem conferir o que colou.
        //
        // PARAM_RAW de proposito. Limpar aqui destruiria o trecho antes de a
        // url::normalize() poder extrair o src dele; quem sanitiza e ela, e o
        // que sobra e so o endereco.
        $mform->addElement('textarea', 'videourl', get_string('videourl', 'ldgvideo'), [
            'rows' => 3,
            'cols' => 60,
            'spellcheck' => 'false',
        ]);
        $mform->setType('videourl', PARAM_RAW);
        $mform->addRule('videourl', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('videourl', 'videourl', 'ldgvideo');

        $ratios = [];
        foreach (\mod_ldgvideo\url::ratios() as $value => $key) {
            $ratios[$value] = get_string($key, 'ldgvideo');
        }

        $mform->addElement('select', 'aspectratio', get_string('aspectratio', 'ldgvideo'), $ratios);
        $mform->setDefault('aspectratio', $config->aspectratio ?? \mod_ldgvideo\url::RATIO_LANDSCAPE);
        $mform->addHelpButton('aspectratio', 'aspectratio', 'ldgvideo');

        $mform->addElement('header', 'appearancehdr', get_string('appearance'));

        $mform->addElement('advcheckbox', 'printintro', get_string('printintro', 'ldgvideo'));
        $mform->setDefault('printintro', $config->printintro ?? 1);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Desfaz o displayoptions serializado para o formulario.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($defaultvalues['displayoptions'])) {
            $options = (array) unserialize_array($defaultvalues['displayoptions']);
            $defaultvalues['printintro'] = $options['printintro'] ?? 1;
        }
    }

    /**
     * Os dois portoes do campo de video.
     *
     * A ORDEM IMPORTA. O primeiro portao recusa o que nao e video, e e ele que
     * garante que o HTML colado nunca chega ao banco. O segundo e regra de
     * negocio, e nao paranoia: o professor pode subir um .mp4 num rotulo do
     * curso, copiar o link do pluginfile.php e colar aqui - video servido pela
     * NOSSA banda, no plano que existe para custar zero.
     *
     * O portao do papel de vendedor - que nem deixa o arquivo existir - e a
     * outra metade disto, e vive no local_marketplace. Este aqui protege contra
     * o engano; aquele protege contra a intencao.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $problem = \mod_ldgvideo\url::problem((string) ($data['videourl'] ?? ''));

        if ($problem !== null) {
            $errors['videourl'] = get_string($problem, 'ldgvideo');
        }

        return $errors;
    }

    /**
     * Guarda o endereco limpo, e nao o que foi colado.
     *
     * E AQUI QUE O HTML MORRE. Depois desta linha, o que segue para o banco e
     * uma URL - o trecho <iframe> com onload=... nao existe mais.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();

        if (!$data) {
            return $data;
        }

        $video = \mod_ldgvideo\url::normalize((string) ($data->videourl ?? ''));

        if ($video === null) {
            return $data;
        }

        $data->videourl = $video['url']->out(false);

        $default = get_config('ldgvideo', 'aspectratio') ?: \mod_ldgvideo\url::RATIO_LANDSCAPE;

        $data->aspectratio = \mod_ldgvideo\url::choose_ratio(
            $video['ratio'],
            (string) ($data->aspectratio ?? $default),
            $default
        );

        return $data;
    }
}
