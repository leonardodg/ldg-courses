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
 * A aula em foco.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\output\courseformat\content;

use cm_info;
use core\output\named_templatable;
use core\output\renderer_base;
use core_availability\info;
use core_courseformat\base as course_format;
use renderable;
use stdClass;

/**
 * A aula em foco, embutida na propria pagina do curso.
 *
 * O conteudo NAO e reimplementado aqui: o iframe aponta para a view.php do
 * modulo, e quem desenha continua sendo o proprio modulo. Isso nao e preguica -
 * e o que faz a visita ser real. O Moodle registra o log, marca "visualizado",
 * aplica a restricao de acesso e respeita a nota, tudo sem o formato saber uma
 * linha sobre quiz, page ou url.
 *
 * O preco esta anotado no README: o Moodle NAO tem um modo embutido generico.
 * So o mod_page aceita inpopup, e mesmo assim num caso so. Entao a pagina
 * dentro do quadro vem com o cabecalho e o rodape do site, e quem tira isso e o
 * tema, pelo Sec-Fetch-Dest. Com outro tema, o quadro mostra o site inteiro
 * dentro dele - feio, mas funcional.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lessonviewer implements named_templatable, renderable {
    /** @var string Parametro que pede ao tema o modo embutido. */
    public const EMBED_PARAM = 'ldgembed';

    /** @var course_format */
    protected course_format $format;

    /** @var cm_info|null */
    protected ?cm_info $cm;

    /**
     * Construtor.
     *
     * @param course_format $format
     * @param cm_info|null $cm
     */
    public function __construct(course_format $format, ?cm_info $cm = null) {
        $this->format = $format;
        $this->cm = $cm;
    }

    /**
     * Template.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_ldg/local/content/lessonviewer';
    }

    /**
     * Dados do template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        if ($this->cm === null) {
            return (object) ['haslesson' => false];
        }

        $cm = $this->cm;

        $data = (object) [
            'haslesson' => true,
            'cmid' => $cm->id,
            'name' => $cm->get_formatted_name(),
            'modname' => $cm->modname,
            'modfullname' => $cm->modfullname,
            'description' => $cm->get_formatted_content(),
            'hasdescription' => trim(strip_tags((string) $cm->content)) !== '',
            'locked' => false,
            'lockinfo' => '',
        ];

        // Bloqueado por restricao de acesso - o destino Forum/Certificado
        // embute o primeiro item do balde sem filtrar por uservisible, ao
        // contrario da lista de aulas (lessonlist::lock_info()), que ja trata
        // isto. Sem esta checagem aqui tambem, o aluno via a pagina crua de
        // "acesso negado" que o proprio modulo embutido desenha dentro do
        // quadro, em vez do cadeado do portal.
        if (!$cm->uservisible) {
            $data->hasframe = false;
            $data->locked = true;
            if (!empty($cm->availableinfo)) {
                $data->lockinfo = info::format_info($cm->availableinfo, $this->format->get_course());
            }

            return $data;
        }

        // Atividade sem pagina propria - a etiqueta, por exemplo - nao tem o que
        // embutir. Mostra a descricao e pronto, em vez de um quadro vazio.
        if (empty($cm->url)) {
            $data->hasframe = false;

            return $data;
        }

        $url = new \moodle_url($cm->url);
        $url->param(self::EMBED_PARAM, 1);

        $data->hasframe = true;
        $data->frameurl = $url->out(false);
        $data->openurl = $cm->url->out(false);

        // A CONCLUSAO NAO E MONTADA AQUI, e ja foi.
        //
        // Quem carrega no quadro e a view.php da atividade, e ela ja desenha o
        // proprio estado de conclusao - os requisitos e o botao de marcar.
        // Exportar de novo mostrava a MESMA frase duas vezes na tela, uma
        // dentro do quadro e outra logo abaixo dele. Nenhum teste de servidor
        // pegaria isso: os dois blocos estao corretos, o problema e existirem
        // juntos.

        return $data;
    }
}
