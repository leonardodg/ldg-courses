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

namespace theme_ldg\output;

use core\output\renderer_base;
use core\output\renderable;
use core\output\templatable;
use moodle_url;

/**
 * Menu de navegacao lateral.
 *
 * Monta os grupos do aside a partir da configuracao do site, e nao de uma lista
 * escrita no tema. Sao tres fontes, nesta ordem:
 *
 *   1. Navegacao   enablemyhome, enabledashboard e enablemycourses, com o
 *                  defaulthomepage marcando qual deles e a home.
 *   2. Conta       o que o administrador escreveu em customusermenuitems, lido
 *                  por user_get_user_navigation_info() - a MESMA funcao que
 *                  alimenta o menu do usuario na navbar. Escrever um parser
 *                  proprio criaria duas verdades para a mesma configuracao.
 *   3. Rodape      Preferencias e Sair, separados do grupo Conta porque ficam
 *                  colados na base do menu.
 *
 * O course index e montado pelo core e entra aqui ja pronto, entre os grupos e
 * o rodape. Poderia ser irmao do menu dentro do drawer, mas ai o rodape com
 * avatar, preferencias e sair cairia no MEIO da coluna, antes das secoes do
 * curso - foi o que aconteceu na primeira tentativa.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navmenu implements renderable, templatable {
    /**
     * @var array Icone por identificador de string, para os itens que vem da
     *            configuracao. Quem nao estiver aqui cai no icone generico -
     *            e melhor um circulo neutro do que um icone que mente.
     */
    protected const ICONS = [
        'home' => 'fa-house',
        'mymoodle,admin' => 'fa-table-columns',
        'mycourses,admin' => 'fa-graduation-cap',
        'profile,moodle' => 'fa-user',
        'grades,grades' => 'fa-chart-simple',
        'calendar,calendar' => 'fa-calendar',
        'privatefiles,moodle' => 'fa-folder',
        'reports,reportbuilder' => 'fa-chart-line',
        'preferences,moodle' => 'fa-gear',
        'switchroleto,moodle' => 'fa-user-gear',
        'switchrolereturn,moodle' => 'fa-rotate-left',
        'logout,moodle' => 'fa-right-from-bracket',
        'accessibility,theme_ldg' => 'fa-universal-access',
    ];

    /**
     * @var array Itens que saem do grupo Conta e vao para o rodape do menu.
     */
    protected const FOOTER_ITEMS = ['preferences,moodle', 'logout,moodle'];

    /** @var \moodle_page A pagina em render. */
    protected $page;

    /** @var string HTML do course index, ou vazio fora de curso. */
    protected $courseindex;

    /**
     * Construtor.
     *
     * @param \moodle_page $page A pagina em render.
     * @param string $courseindex HTML do course index, ja montado pelo core.
     */
    public function __construct(\moodle_page $page, string $courseindex = '') {
        $this->page = $page;
        $this->courseindex = $courseindex;
    }

    /**
     * Contexto para o template.
     *
     * @param renderer_base $output O renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG;

        require_once($CFG->dirroot . '/user/lib.php');

        $groups = [];

        $navigation = $this->navigation_items();
        if ($navigation) {
            $groups[] = [
                'title' => get_string('navmenugroupnavigation', 'theme_ldg'),
                'items' => $navigation,
            ];
        }

        [$accountitems, $footeritems] = $this->account_items($output);

        if ($accountitems) {
            $groups[] = [
                'title' => get_string('navmenugroupaccount', 'theme_ldg'),
                'items' => $accountitems,
            ];
        }

        $collapsed = (bool) get_user_preferences('theme_ldg-navmenu-collapsed', 0);

        return [
            'collapsed' => $collapsed,
            'groups' => $groups,
            'hasgroups' => !empty($groups),
            'footeritems' => $footeritems,
            'hasfooteritems' => !empty($footeritems),
            'courseindex' => $this->courseindex,
            'userfullname' => fullname($USER),
            'useravatar' => $output->user_picture($USER, ['size' => 32, 'link' => false]),
        ];
    }

    /**
     * Grupo de navegacao, respeitando os tres interruptores do site.
     *
     * O idioma do `!isset() || $CFG->x` e o mesmo do core em
     * admin/settings/appearance.php: setting ausente conta como LIGADO. Trocar
     * por empty() inverteria o comportamento de um site que nunca salvou a tela
     * de navegacao.
     *
     * @return array
     */
    protected function navigation_items(): array {
        global $CFG;

        $default = get_default_home_page();
        $items = [];

        if (!isset($CFG->enablemyhome) || $CFG->enablemyhome) {
            $items[] = $this->item(
                get_string('home'),
                new moodle_url('/'),
                'fa-house',
                $default == HOMEPAGE_SITE
            );
        }

        if (!isset($CFG->enabledashboard) || $CFG->enabledashboard) {
            $items[] = $this->item(
                get_string('mymoodle', 'admin'),
                new moodle_url('/my/'),
                'fa-table-columns',
                $default == HOMEPAGE_MY
            );
        }

        if (!isset($CFG->enablemycourses) || $CFG->enablemycourses) {
            $items[] = $this->item(
                get_string('mycourses', 'admin'),
                new moodle_url('/my/courses.php'),
                'fa-graduation-cap',
                $default == HOMEPAGE_MYCOURSES
            );
        }

        return $items;
    }

    /**
     * Grupo Conta e itens de rodape, a partir do customusermenuitems.
     *
     * @param renderer_base $output O renderer.
     * @return array [itens do grupo, itens do rodape]
     */
    protected function account_items(renderer_base $output): array {
        global $USER;

        $info = user_get_user_navigation_info($USER, $this->page);

        // Visitante anonimo nao tem menu de conta: a funcao devolve
        // unauthenticateduser e navitems vazio.
        if (isset($info->unauthenticateduser) || empty($info->navitems)) {
            return [[], []];
        }

        $account = [];
        $footer = [];

        foreach ($info->navitems as $navitem) {
            // Divisor nao tem destino, e 'invalid' e item mal escrito na
            // configuracao - nenhum dos dois vira linha de menu.
            if ($navitem->itemtype !== 'link' || empty($navitem->url)) {
                continue;
            }

            $identifier = $navitem->titleidentifier ?? '';
            $item = $this->item(
                $navitem->title,
                $navitem->url,
                self::ICONS[$identifier] ?? 'fa-circle-dot',
                false
            );

            if (in_array($identifier, self::FOOTER_ITEMS, true)) {
                $footer[] = $item;
            } else {
                $account[] = $item;
            }
        }

        return [$account, $footer];
    }

    /**
     * Monta um item do menu.
     *
     * @param string $label Texto visivel.
     * @param moodle_url $url Destino.
     * @param string $icon Classe do Font Awesome.
     * @param bool $isdefaulthome Se este item e a home padrao do site.
     * @return array
     */
    protected function item(string $label, moodle_url $url, string $icon, bool $isdefaulthome = false): array {
        return [
            'label' => $label,
            'url' => $url->out(false),
            'icon' => $icon,
            'active' => $this->is_active($url),
            'isdefaulthome' => $isdefaulthome,
        ];
    }

    /**
     * O item aponta para a pagina que esta sendo exibida?
     *
     * Compara so o CAMINHO, e nao a URL inteira. Comparar com parametros faria
     * /my/courses.php?sort=x deixar de marcar o proprio item de Meus cursos.
     *
     * @param moodle_url $url URL do item.
     * @return bool
     */
    protected function is_active(moodle_url $url): bool {
        if (!$this->page->has_set_url()) {
            return false;
        }

        return $this->normalise_path($this->page->url) === $this->normalise_path($url);
    }

    /**
     * Caminho de uma URL numa forma comparavel.
     *
     * O item do Painel aponta para /my/, mas o $PAGE->url da propria pagina do
     * Painel e /my/index.php - o Moodle resolve o indice. Sem colapsar os dois,
     * o item nunca se marcaria como ativo na pagina que ele mesmo abre.
     *
     * @param moodle_url $url URL a normalizar.
     * @return string
     */
    protected function normalise_path(moodle_url $url): string {
        $path = $url->get_path();
        $path = preg_replace('#/index\.php$#', '/', $path);

        return rtrim($path, '/');
    }
}
