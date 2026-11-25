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

use local_marketplace\cnpj;
use local_marketplace\plan;
use local_partners\api;
use local_partners\application;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Candidatura de empresa parceira, preenchida por visitante anonimo.
 *
 * Formulario PUBLICO. Tudo aqui parte do principio de que quem envia pode ser
 * um robo, e as tres camadas de defesa estao nesta ordem de confiabilidade:
 *
 *   1. limite de taxa por IP   sempre ligado, nao depende de nada externo
 *   2. honeypot                sempre ligado, nao depende de nada externo
 *   3. reCAPTCHA               so com o interruptor ligado E as chaves do site
 *                              cadastradas - as duas condicoes
 *
 * A ordem importa: a camada mais forte nao pode ser a que alguem precisa
 * lembrar de configurar. O honeypot NAO tem interruptor de proposito: ele e
 * invisivel, nao atrapalha ninguem e nao custa nada, entao desliga-lo so
 * pioraria o site.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_form extends \moodleform {
    /**
     * @var array Teto de cada campo, igual ao tamanho da coluna no banco.
     *
     * Fica aqui e nao no install.xml porque quem precisa recusar antes do
     * INSERT e o formulario: o banco recusa tambem, mas com erro 500.
     */
    private const MAX_LENGTHS = [
        'companyname' => 255,
        'cnpj' => 18,
        'contactname' => 255,
        'contactemail' => 255,
        'contactphone' => 30,
        'website' => 255,
    ];

    /** @var int Teto da mensagem livre. */
    private const MAX_MESSAGE = 2000;

    /**
     * Campos.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'text',
            'companyname',
            get_string('companyname', 'local_partners'),
            ['size' => 50, 'maxlength' => 255, 'parentclass' => 'ldgp-half']
        );
        $mform->setType('companyname', PARAM_TEXT);
        $mform->addRule('companyname', null, 'required', null, 'client');

        $mform->addElement(
            'text',
            'cnpj',
            get_string('cnpj', 'local_partners'),
            ['size' => 20, 'maxlength' => 18, 'parentclass' => 'ldgp-half']
        );
        $mform->setType('cnpj', PARAM_TEXT);
        $mform->addHelpButton('cnpj', 'cnpj', 'local_partners');

        // Quem esta autenticado nao redigita o que o site ja sabe. Alem do
        // atrito, um e-mail diferente do da conta criaria uma candidatura que
        // parece de outra pessoa - e o dono da empresa sai daqui.
        if ($this->is_authenticated()) {
            global $USER;

            $mform->addElement(
                'static',
                'contactnamestatic',
                get_string('contactname', 'local_partners'),
                fullname($USER)
            );
            $mform->addElement('hidden', 'contactname', fullname($USER));
            $mform->setType('contactname', PARAM_TEXT);

            $mform->addElement(
                'static',
                'contactemailstatic',
                get_string('contactemail', 'local_partners'),
                s($USER->email)
            );
            $mform->addElement('hidden', 'contactemail', $USER->email);
            $mform->setType('contactemail', PARAM_RAW_TRIMMED);
        } else {
            $mform->addElement(
                'text',
                'contactname',
                get_string('contactname', 'local_partners'),
                ['size' => 50, 'maxlength' => 255, 'parentclass' => 'ldgp-half']
            );
            $mform->setType('contactname', PARAM_TEXT);
            $mform->addRule('contactname', null, 'required', null, 'client');

            $mform->addElement(
                'text',
                'contactemail',
                get_string('contactemail', 'local_partners'),
                ['size' => 50, 'maxlength' => 255, 'parentclass' => 'ldgp-half']
            );
            $mform->setType('contactemail', PARAM_RAW_TRIMMED);
            $mform->addRule('contactemail', null, 'required', null, 'client');
        }

        $mform->addElement(
            'text',
            'contactphone',
            get_string('contactphone', 'local_partners'),
            ['size' => 30, 'maxlength' => 30, 'parentclass' => 'ldgp-half']
        );
        $mform->setType('contactphone', PARAM_TEXT);

        $mform->addElement(
            'text',
            'website',
            get_string('website', 'local_partners'),
            ['size' => 50, 'maxlength' => 255, 'parentclass' => 'ldgp-half']
        );
        $mform->setType('website', PARAM_RAW_TRIMMED);

        // Os planos vem do banco, e nao de uma lista escrita aqui: e a mesma
        // fonte que a comparacao de planos da landing usa.
        //
        // Sao CARTOES de radio, e nao um select, porque o plano e uma escolha
        // com preco e comissao - informacao que nao cabe numa linha de select.
        // O element-radio do core imprime o rotulo CRU, entao o cartao inteiro
        // cabe ali como HTML montado aqui. A classe passada em atributos cai
        // no <label> que envolve o radio, e nao no proprio input - por isso o
        // nome 'wrap'. O input fica irmao do cartao, que e o que permite
        // pintar o selecionado com input:checked + .ldgp-plancard.
        $radios = [];

        foreach (plan::get_public_plans() as $plan) {
            $radios[] = $mform->createElement(
                'radio',
                'planid',
                '',
                $this->plan_card((int) $plan->get('id')),
                (int) $plan->get('id'),
                ['class' => 'ldgp-plancard__wrap']
            );
        }

        $radios[] = $mform->createElement(
            'radio',
            'planid',
            '',
            $this->plan_card(0),
            0,
            ['class' => 'ldgp-plancard__wrap']
        );

        // O ultimo argumento e $appendName, e ele PRECISA ser false. Com true o
        // campo vira plangroup[planid], api::submit() grava null em toda
        // candidatura, e nenhum teste que nao escolha plano perceberia.
        $mform->addGroup(
            $radios,
            'plangroup',
            get_string('planofinterest', 'local_partners'),
            '',
            false
        );
        $mform->setType('planid', PARAM_INT);
        $mform->setDefault('planid', 0);

        // A lista de paises e a do Moodle: ja vem traduzida nos tres idiomas, e
        // respeita a restricao do administrador em $CFG->allcountrycodes. Uma
        // lista escrita aqui divergiria dela na primeira mudanca.
        $countries = ['' => get_string('choosedots')] + get_string_manager()->get_list_of_countries();
        $mform->addElement(
            'select',
            'country',
            get_string('country', 'local_partners'),
            $countries,
            ['parentclass' => 'ldgp-half']
        );
        $mform->setType('country', PARAM_ALPHA);
        $mform->addRule('country', null, 'required', null, 'client');

        if (!empty($GLOBALS['CFG']->country)) {
            $mform->setDefault('country', $GLOBALS['CFG']->country);
        }

        $bands = ['' => get_string('learnersbandundecided', 'local_partners')];
        foreach (application::LEARNER_BANDS as $band) {
            $bands[$band] = application::band_label($band);
        }
        $mform->addElement(
            'select',
            'learnersband',
            get_string('learnersband', 'local_partners'),
            $bands,
            ['parentclass' => 'ldgp-half']
        );
        $mform->setType('learnersband', PARAM_ALPHANUM);

        $mform->addElement('textarea', 'message', get_string('applicationmessage', 'local_partners'), [
            'rows' => 4,
            'cols' => 50,
        ]);
        $mform->setType('message', PARAM_TEXT);

        // O advcheckbox posta valor tambem quando DESMARCADO, e o checkbox
        // comum nao posta nada. Com o comum, o servidor nao distingue "nao
        // aceitou" de "campo nao veio", e a checagem do aceite ficaria sujeita
        // a como o navegador montou o POST.
        $mform->addElement('advcheckbox', 'termsaccepted', '', $this->terms_label());
        $mform->setType('termsaccepted', PARAM_INT);

        // Honeypot. Escondido por CSS, nunca por type="hidden": um robo ignora
        // display:none e preenche tudo que parece campo, mas tambem preenche
        // hidden. O rotulo existe para leitor de tela avisar para nao preencher.
        $mform->addElement('text', 'fax', get_string('honeypotlabel', 'local_partners'), [
            'autocomplete' => 'off',
            'tabindex' => '-1',
            'class' => 'local-partners-honeypot',
        ]);
        $mform->setType('fax', PARAM_TEXT);

        // Captcha so para visitante anonimo: quem ja entrou no site passou pelo
        // login, e um captcha ali seria atrito sem defesa nenhuma.
        if (!$this->is_authenticated() && api::recaptcha_available()) {
            $mform->addElement('recaptcha', 'recaptcha_element', get_string('security_question', 'auth'));
            $mform->closeHeaderBefore('recaptcha_element');
        }

        $this->add_action_buttons(true, get_string('submitapplication', 'local_partners'));
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

        // O maxlength dos campos e atributo HTML, e some com um curl. A
        // checagem que vale e esta: sem ela, um envio maior que a coluna sobe
        // um dml_write_exception - erro 500 numa pagina publica, com stack
        // trace no log a cada tentativa.
        foreach (self::MAX_LENGTHS as $field => $max) {
            if (\core_text::strlen((string) ($data[$field] ?? '')) > $max) {
                $errors[$field] = get_string('errortoolong', 'local_partners', $max);
            }
        }

        // A mensagem e TEXT no banco, mas sem teto um robo posta megabytes.
        if (\core_text::strlen((string) ($data['message'] ?? '')) > self::MAX_MESSAGE) {
            $errors['message'] = get_string('errortoolong', 'local_partners', self::MAX_MESSAGE);
        }

        if (!validate_email($data['contactemail'] ?? '')) {
            $errors['contactemail'] = get_string('erroremailinvalid', 'local_partners');
        }

        // O aceite se confere AQUI, e nao so no navegador. O required do
        // moodleform vira atributo HTML, e atributo HTML some com um curl - uma
        // candidatura sem consentimento entraria na fila em silencio, e o
        // carimbo de tempo perderia o valor de prova que e a razao de ele
        // existir.
        if (empty($data['termsaccepted'])) {
            $errors['termsaccepted'] = get_string('errortermsrequired', 'local_partners');
        }

        if (
            !empty($data['country'])
            && !array_key_exists($data['country'], get_string_manager()->get_list_of_countries())
        ) {
            $errors['country'] = get_string('errorcountryinvalid', 'local_partners');
        }

        if (
            !empty($data['learnersband'])
            && !in_array($data['learnersband'], application::LEARNER_BANDS, true)
        ) {
            $errors['learnersband'] = get_string('errorlearnersbandinvalid', 'local_partners');
        }

        $digits = !empty($data['cnpj']) ? cnpj::normalise($data['cnpj']) : '';

        if ($digits !== '' && !cnpj::is_valid($digits)) {
            $errors['cnpj'] = get_string('errorcnpjinvalid', 'local_partners');
        }

        // Duplicidade em aberto. Nao e anti-spam: e evitar que a mesma empresa
        // ocupe tres linhas da fila porque quem enviou nao viu a confirmacao.
        if (empty($errors) && application::has_pending_for($data['contactemail'], $digits ?: null)) {
            $errors['contactemail'] = get_string('errorduplicatepending', 'local_partners');
        }

        // Limite de taxa por IP.
        if (empty($errors) && application::count_recent_from_ip(getremoteaddr('', 45)) >= api::max_per_hour()) {
            $errors['companyname'] = get_string('errortoomany', 'local_partners');
        }

        // Limite de taxa por e-mail: rotacionar IP nao protege quem nunca se
        // candidatou, se o alvo do abuso e sempre o mesmo endereco.
        if (
            empty($errors)
            && !empty($data['contactemail'])
            && application::count_recent_from_email($data['contactemail']) >= api::max_per_hour()
        ) {
            $errors['contactemail'] = get_string('errortoomany', 'local_partners');
        }

        // O elemento recaptcha do Moodle NAO se valida sozinho: ele desenha o
        // widget e para por ai. Quem verifica e o formulario, chamando verify()
        // - e o signup_form.php do core faz exatamente isto. Sem este bloco o
        // captcha e decoracao: aparece na tela e nao barra ninguem.
        if (!$this->is_authenticated() && api::recaptcha_available()) {
            $element = $this->_form->getElement('recaptcha_element');
            $response = $this->_form->_submitValues['g-recaptcha-response'] ?? '';

            if ($response === '') {
                $errors['recaptcha_element'] = get_string('missingrecaptchachallengefield');
            } else if (!$element->verify($response)) {
                $errors['recaptcha_element'] = get_string('incorrectpleasetryagain', 'auth');
            }
        }

        return $errors;
    }

    /**
     * O cartao de um plano, usado como rotulo do radio.
     *
     * Nome, mensalidade e comissao saem do REGISTRO. Escrever numero aqui faria
     * o cadastro divergir da landing no primeiro reajuste, e as duas telas sao
     * lidas com dez segundos de diferenca.
     *
     * @param int $planid Zero para o cartao "ainda nao decidi".
     * @return string
     */
    protected function plan_card(int $planid): string {
        if ($planid === 0) {
            return \html_writer::div(
                \html_writer::tag('strong', get_string('planundecided', 'local_partners')),
                'ldgp-plancard ldgp-plancard--undecided'
            );
        }

        $plan = plan::get_record(['id' => $planid]);

        if (!$plan) {
            return '';
        }

        $fee = (float) $plan->get('monthlyfee');
        $price = $fee <= 0
            ? get_string('planfree', 'local_partners')
            : \core_payment\helper::get_cost_as_string($fee, $plan->get('currency'));

        return \html_writer::div(
            \html_writer::tag('strong', format_string($plan->get('name')), ['class' => 'ldgp-plancard__name'])
            . \html_writer::span($price, 'ldgp-plancard__price')
            . \html_writer::span(
                get_string('plancommission', 'local_partners', format_float((float) $plan->get('commissionpct'), 2)),
                'ldgp-plancard__note'
            ),
            'ldgp-plancard'
        );
    }

    /**
     * O rotulo do aceite, com link para a politica do site quando houver uma.
     *
     * O endereco sai de $CFG->sitepolicy, que e onde o Moodle ja guarda esse
     * documento - o mockup usava href="#", e um link que nao leva a lugar
     * nenhum ao lado de "eu concordo" e pior que nao ter link.
     *
     * @return string
     */
    protected function terms_label(): string {
        global $CFG;

        $url = !empty($CFG->sitepolicy) ? $CFG->sitepolicy : ($CFG->sitepolicyguest ?? '');

        if (empty($url)) {
            return get_string('termsacceptplain', 'local_partners');
        }

        $link = \html_writer::link($url, get_string('termslinktext', 'local_partners'), [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ]);

        return get_string('termsaccept', 'local_partners', $link);
    }

    /**
     * O envio veio de alguem autenticado?
     *
     * @return bool
     */
    protected function is_authenticated(): bool {
        return isloggedin() && !isguestuser();
    }

    /**
     * O honeypot foi preenchido?
     *
     * Fica fora do validation() de proposito: devolver erro ensinaria o robo a
     * nao preencher o campo da proxima vez. Quem chama deve mostrar a mesma
     * tela de sucesso e nao gravar nada.
     *
     * @return bool
     */
    public function is_bot(): bool {
        $data = $this->get_data();

        return $data && !empty($data->fax);
    }
}
