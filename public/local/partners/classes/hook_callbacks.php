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

namespace local_partners;

use core\hook\output\before_http_headers;
use core\hook\output\before_standard_head_html_generation;
use moodle_url;

/**
 * Callbacks de hook do local_partners.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Injeta as meta tags de compartilhamento da landing.
     *
     * ESTE CALLBACK RODA EM TODA PAGINA DO SITE. Por isso as saidas antecipadas
     * vem primeiro e sao baratas, e a checagem de URL vem antes de qualquer
     * consulta: sem elas, compartilhar um curso no WhatsApp mostraria o pitch de
     * parceria em vez do curso.
     *
     * @param before_standard_head_html_generation $hook
     * @return void
     */
    public static function before_standard_head_html_generation(
        before_standard_head_html_generation $hook
    ): void {
        global $CFG, $PAGE;

        // Usuario autenticado nunca ve a landing.
        if (isloggedin() && !isguestuser()) {
            return;
        }

        // Dominio de vendedor tem a home dele, e nao a nossa.
        if (!empty($CFG->marketplacecompany)) {
            return;
        }

        if (!$PAGE->has_set_url()) {
            return;
        }

        // So as paginas publicas de captacao recebem as tags. Poluir o resto do
        // site com og:type=website da landing faria toda pagina compartilhada
        // parecer a mesma coisa.
        $superficie = self::surface_for($PAGE->url->get_path());

        if ($superficie !== null) {
            $hook->add_html(landing::head_html($superficie));

            return;
        }

        // O catalogo do LMS leva SO a description, e nada mais.
        //
        // Nao leva canonical: a pagina aceita categoryid e paginacao, e
        // canonical montada sem olhar para os parametros consolidaria
        // categorias distintas numa URL so. Nao leva og:*: o catalogo nao e
        // uma pagina de campanha, e o og:type=website da landing faria todo
        // link compartilhado parecer a mesma coisa.
        if (self::is_course_index($PAGE->url->get_path()) && landing::replaces_frontpage()) {
            $hook->add_html(
                '<meta name="description" content="'
                . s(get_string('catalogmetadescription', 'local_partners')) . '">' . "\n"
            );
        }
    }

    /**
     * O caminho e a listagem de cursos do core?
     *
     * @param string $path
     * @return bool
     */
    protected static function is_course_index(string $path): bool {
        // SO a raiz do catalogo. As paginas de categoria compartilham o mesmo
        // caminho, e uma description generica repetida em todas elas e pior que
        // nenhuma: o buscador reporta description duplicada, e o trecho que ele
        // escreveria a partir do conteudo da categoria seria mais util.
        if (optional_param('categoryid', 0, PARAM_INT) !== 0) {
            return false;
        }

        $path = rtrim($path, '/') ?: '';
        $catalog = rtrim((new moodle_url('/course/index.php'))->get_path(), '/');

        return $path === $catalog || $path === dirname($catalog);
    }

    /**
     * Qual superficie publica mora neste caminho, se alguma.
     *
     * @param string $path
     * @return string|null
     */
    protected static function surface_for(string $path): ?string {
        $path = rtrim($path, '/') ?: '';
        $root = rtrim((new moodle_url('/'))->get_path(), '/');
        $index = (new moodle_url('/local/partners/index.php'))->get_path();
        $apply = (new moodle_url('/local/partners/apply.php'))->get_path();

        if ($path === rtrim($apply, '/')) {
            return seo::SURFACE_APPLY;
        }

        if ($path === rtrim($index, '/')) {
            return seo::SURFACE_LANDING;
        }

        // A raiz so e a landing quando o administrador escolheu isso. A consulta
        // de configuracao fica por ultimo, depois de todas as comparacoes de
        // string, que sao baratas.
        if (($path === $root || $path === $root . '/index.php') && landing::replaces_frontpage()) {
            return seo::SURFACE_LANDING;
        }

        return null;
    }

    /**
     * Poe o titulo da landing na home do site.
     *
     * ESTE CALLBACK NAO PODE VIRAR UM before_standard_head_html_generation.
     * O template do tema resolve `page_title` ANTES de `standard_head_html`
     * (theme/boost/templates/head.mustache), entao um set_title() feito de
     * dentro do hook de <head> nao tem efeito nenhum - e sem erro, sem aviso,
     * so o titulo default do Moodle na pagina. O before_http_headers dispara no
     * inicio do core_renderer::header(), antes de qualquer template.
     *
     * So a HOME precisa disto: as paginas proprias do plugin chamam
     * set_title() elas mesmas, porque tem um index.php onde chamar.
     *
     * @param before_http_headers $hook
     * @return void
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $CFG, $PAGE;

        if (isloggedin() && !isguestuser()) {
            return;
        }

        if (!empty($CFG->marketplacecompany)) {
            return;
        }

        if (!$PAGE->has_set_url() || !landing::replaces_frontpage()) {
            return;
        }

        $path = rtrim($PAGE->url->get_path(), '/');
        $raiz = rtrim((new moodle_url('/'))->get_path(), '/');

        if ($path !== $raiz && $path !== $raiz . '/index.php') {
            return;
        }

        // O false e obrigatorio: a marca ja esta na string de idioma, e deixar o
        // core anexar o nome do site poria uma segunda marca no mesmo titulo.
        $PAGE->set_title(seo::page_title(), false);
    }
}
