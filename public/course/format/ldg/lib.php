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
 * Formato de curso LDG - portal do aluno.
 *
 * A secao e o MODULO do curso, e cada atividade dentro dela e uma AULA. A tela
 * mostra uma aula por vez, com a lista completa ao lado - e nao todas as
 * atividades empilhadas, como o Topics faz.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Formato de curso LDG.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_ldg extends core_courseformat\base {
    /**
     * O formato usa secoes.
     *
     * Elas sao os modulos do curso, e a lista de aulas agrupa por elas.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * O formato NAO usa o indice lateral do curso.
     *
     * A lista de aulas ja cumpre esse papel, e na mesma tela. Duas navegacoes
     * concorrentes disputariam o mesmo espaco e a mesma atencao.
     *
     * Vale saber por que nao e so estetica: o course index e renderizado NO
     * CLIENTE, a partir do state reativo, com o template fixo
     * core_courseformat/local/courseindex/courseindex. Trocar o CONTEUDO dele
     * exigiria um modulo AMD proprio - custo que nao se paga quando a lista ao
     * lado ja mostra o mesmo.
     *
     * @return bool
     */
    public function uses_course_index() {
        return false;
    }

    /**
     * Sem indentacao de atividade.
     *
     * A lista de aulas e plana dentro do modulo: aula recuada sugere hierarquia
     * que nao existe no percurso.
     *
     * @return bool
     */
    public function uses_indentation(): bool {
        return false;
    }

    /**
     * O formato suporta o editor reativo.
     *
     * @return bool
     */
    public function supports_components() {
        return true;
    }

    /**
     * Nome da secao, com o padrao "Modulo N" quando ninguem nomeou.
     *
     * @param stdClass|section_info $section
     * @return string
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);

        if (!empty($section->name)) {
            return format_string($section->name, true, ['context' => $this->get_context()]);
        }

        return $this->get_default_section_name($section);
    }

    /**
     * Nome padrao da secao.
     *
     * A secao 0 e a abertura do curso, e nao um modulo - ela costuma guardar
     * avisos e a apresentacao, entao numera-la como "Modulo 0" seria mentira.
     *
     * NORMALIZA ANTES DE LER. Este metodo e publico e o core o chama DIRETO,
     * sem passar pelo get_section_name() - o course/editsection.php entrega o
     * registro cru de course_sections, que tem "section" e NAO tem
     * "sectionnum". Sem esta linha saia "Undefined property" na tela de editar
     * secao; e o aviso, sendo saida, ainda derrubava o redirect() seguinte, que
     * virava um segundo erro sobre sessao mutada depois de fechada.
     *
     * A assinatura do core tambem aceita o numero puro, e ai a leitura direta
     * dava "Attempt to read property on int".
     *
     * @param int|stdClass|section_info $section
     * @return string
     */
    public function get_default_section_name($section) {
        $section = $this->get_section($section);

        if ($section->sectionnum == 0) {
            return get_string('section0name', 'format_ldg');
        }

        return get_string('sectionname', 'format_ldg') . ' ' . $section->sectionnum;
    }

    /**
     * URL da pagina do curso.
     *
     * Diferente do Topics, a secao NAO leva a uma pagina propria: tudo acontece
     * na mesma tela, e o que muda e a aula em foco. Por isso o parametro e o
     * cmid da aula, e nao o numero da secao.
     *
     * @param int|stdClass|section_info $section
     * @param array $options
     * @return moodle_url
     */
    public function get_view_url($section, $options = []) {
        $url = new moodle_url('/course/view.php', ['id' => $this->courseid]);

        if (!empty($options['lesson'])) {
            $url->param('lesson', (int) $options['lesson']);
        }

        // O destino do portal viaja na URL, como a aula. Assim o botao voltar, o
        // favorito e o Ctrl+clique funcionam sem uma linha de JavaScript.
        if (!empty($options['ldgview'])) {
            $url->param('ldgview', (string) $options['ldgview']);
        }

        return $url;
    }

    /**
     * A aula em foco, ou null quando o curso nao tem nenhuma disponivel.
     *
     * Vem do parametro lesson da URL. O valor NAO e usado como veio: um cmid de
     * outro curso, de atividade apagada ou de aula que a pessoa nao pode ver
     * viraria uma tela quebrada, ou pior, o conteudo de um curso que ela nao
     * comprou dentro do iframe. Por isso passa por modinfo e por uservisible
     * antes de valer.
     *
     * Sem parametro valido, cai na primeira aula disponivel - abrir o curso e
     * nao ver nada seria um beco sem saida.
     *
     * @return cm_info|null
     */
    public function get_selected_cm(?\format_ldg\catalog $catalog = null): ?cm_info {
        // Aceita um catalogo ja construido para nao repetir a varredura de
        // classificacao do curso: o construtor do catalogo ja percorre secao
        // por secao, cm por cm, e classifica cada um - refazer isso aqui era a
        // MESMA varredura pela segunda vez a cada renderizacao de pagina
        // (content.php faz uma terceira, parcial, em lessonlist).
        $catalog = $catalog ?? new \format_ldg\catalog($this);
        $requested = optional_param('lesson', 0, PARAM_INT);

        $first = null;
        $requestedcm = null;

        foreach ($catalog->get(\format_ldg\catalog::AULA) as $cm) {
            if ($requested && $cm->id == $requested) {
                // Existe no curso, bloqueada ou nao - devolve ELA, e nao
                // teleporta para a primeira disponivel do curso inteiro.
                // lessonviewer::export_for_template() mostra o cadeado quando
                // nao uservisible; pular em silencio escondia o bloqueio
                // atras de uma aula sem relacao nenhuma com a que o aluno pediu.
                $requestedcm = $cm;
            }

            if ($first === null && $cm->uservisible) {
                $first = $cm;
            }
        }

        // Sem pedido especifico (entrada fresca no curso), ou pedido que nao
        // corresponde a nenhuma aula deste curso: cai na primeira disponivel.
        return $requestedcm ?? $first;
    }

    /**
     * Opcoes do formato, por curso.
     *
     * @param bool $foreditform
     * @return array
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'hiddensections' => [
                    'default' => 1,
                    'type' => PARAM_INT,
                ],
                'coursedisplay' => [
                    'default' => COURSE_DISPLAY_SINGLEPAGE,
                    'type' => PARAM_INT,
                ],
            ];
        }

        if ($foreditform && !isset($courseformatoptions['hiddensections']['label'])) {
            // O padrao do 5.2 e o choicedropdown, e nao um select com rotulos
            // soltos: a explicacao de cada opcao vive DENTRO da escolha. O
            // caminho antigo usava hiddensections_help, que esta na lista de
            // strings depreciadas do core - e string depreciada nao quebra a
            // tela, so enche o log de debugging. Foi o behat que pegou.
            $escolhas = new \core\output\choicelist();
            $escolhas->set_allow_empty(false);
            $escolhas->add_option(
                1,
                new lang_string('hiddensectionsinvisible'),
                ['description' => new lang_string('hiddensectionsinvisible_description')]
            );
            $escolhas->add_option(
                0,
                new lang_string('hiddensectionscollapsed'),
                ['description' => new lang_string('hiddensectionscollapsed_description')]
            );

            $courseformatoptions = array_merge_recursive($courseformatoptions, [
                'hiddensections' => [
                    'label' => new lang_string('hiddensections'),
                    'element_type' => 'choicedropdown',
                    'element_attributes' => [$escolhas],
                ],
                // O aluno nunca escolhe entre pagina unica e por secao: a tela
                // do portal e uma so, por desenho. O campo fica escondido.
                //
                // O LABEL E OBRIGATORIO MESMO ASSIM. O
                // create_edit_form_elements() do core le ['label'] de TODA
                // opcao, sem checar antes e sem olhar o element_type - sem ele,
                // abrir as configuracoes do curso morre com "Undefined array
                // key label", e o erro aponta para o core, nao para este
                // arquivo.
                'coursedisplay' => [
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'hidden',
                ],
            ]);
        }

        return $courseformatoptions;
    }

    /**
     * Limpeza quando o CURSO e apagado.
     *
     * Vale reparar no que este gancho NAO e: ele nao roda quando o curso troca
     * de formato. Isso e de proposito - trocar para Topics e voltar tem que
     * preservar as duracoes, senao experimentar formato custa retrabalho. A
     * linha orfa nesse meio-tempo nao incomoda ninguem, porque a tabela nao tem
     * chave estrangeira para course_modules.
     *
     * @return void
     */
    public function delete_format_data() {
        parent::delete_format_data();

        \format_ldg\lesson::delete_for_course($this->courseid);
    }

    /**
     * A secao 0 fica sempre visivel.
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function is_section_visible($section): bool {
        $section = $this->get_section($section);

        if ($section->sectionnum == 0) {
            return true;
        }

        return parent::is_section_visible($section);
    }
}

/**
 * Preferencias de usuario que este formato pode gravar por AJAX.
 *
 * Sem esta funcao o core_user_update_user_preferences RECUSA a gravacao vinda do
 * navegador: a lateral fecha na tela e reabre no proximo carregamento, sem erro
 * visivel - so um 400 no console. E o mesmo mecanismo que o theme_ldg usa para
 * as preferencias dele.
 *
 * @return array
 */
function format_ldg_user_preferences(): array {
    return [
        // Guarda QUAIS laterais estao escondidas, e nao um booleano: sao duas
        // colunas independentes - a navegacao e o indice - e o aluno pode
        // querer esconder so uma.
        'format_ldg_aside_hidden' => [
            'type' => PARAM_ALPHAEXT,
            'null' => NULL_NOT_ALLOWED,
            'default' => '',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
