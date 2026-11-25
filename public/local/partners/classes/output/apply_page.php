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

namespace local_partners\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_partners\form\application_form;
use moodle_url;

/**
 * A pagina de cadastro de parceiro.
 *
 * O formulario continua sendo renderizado pelo MOODLE. Esta classe so captura o
 * HTML dele e o entrega ao template, que monta as duas colunas em volta - o
 * mesmo caminho que o login/signup_form.php do core usa.
 *
 * A razao e simples: sesskey, redisplay de erro do servidor, o widget do
 * reCAPTCHA, o honeypot e a tipagem do get_data() vem de graca enquanto for o
 * moodleform desenhando a si mesmo. Trocar isso por HTML a mao custaria
 * reescrever as tres camadas de anti-spam para ganhar aparencia.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_page implements renderable, templatable {
    /** @var application_form O formulario ja construido pela pagina. */
    protected application_form $form;

    /**
     * Recebe o formulario ja construido pela pagina.
     *
     * @param application_form $form
     */
    public function __construct(application_form $form) {
        $this->form = $form;
    }

    /**
     * Contexto para o template.
     *
     * @param renderer_base $output O renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'formhtml' => $this->form->render(),
            'landingurl' => (new moodle_url('/local/partners/index.php'))->out(false),
            'colormode' => landing_page::color_mode(),
            'isloggedin' => isloggedin() && !isguestuser(),
            'loginurl' => (new moodle_url('/login/index.php'))->out(false),
            'logouturl' => landing_page::logout_url(),
            // Variante SIMPLES: no cadastro o rodape e uma linha so, como no
            // mockup - a atencao ali pertence ao formulario.
            'footer' => landing_page::footer(true),
            'brand' => landing_page::brand($output),
            'languages' => landing_page::languages(),
            'currentlanguage' => landing_page::current_language_label(),
            'haslanguages' => count(landing_page::languages()) > 1,
            'trust' => $this->trust(),
        ];
    }

    /**
     * Os cartoes de confianca da coluna da esquerda.
     *
     * Sao os MESMOS argumentos dos pilares da landing, e nao afirmacoes novas:
     * quem chega aqui pelo botao acabou de le-los, e repetir outra coisa seria
     * comecar uma segunda promessa no meio do cadastro.
     *
     * @return array
     */
    protected function trust(): array {
        $out = [];

        foreach ([1, 2, 3] as $i) {
            $out[] = [
                'title' => get_string('value' . $i . 'title', 'local_partners'),
                'text' => get_string('value' . $i . 'text', 'local_partners'),
                'iscoin' => $i === 1,
                'isserver' => $i === 2,
                'isshield' => $i === 3,
            ];
        }

        return $out;
    }
}
