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

namespace theme_ldg;

use theme_ldg\util\langmenu;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * O menu de idiomas encurtado para sigla.
 *
 * O que estes testes seguram e uma armadilha de mustache, e nao de PHP: item sem
 * `short` proprio faz o template subir no contexto e achar o `short` do MENU, e
 * entao todo idioma da lista aparece com a sigla do idioma corrente. O bug e
 * invisivel enquanto so um idioma esta instalado - que e o caso do ambiente de
 * desenvolvimento, e por isso ele passou aqui uma vez.
 *
 * @package    theme_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(langmenu::class)]
final class langmenu_test extends \advanced_testcase {
    /**
     * Um menu com tres idiomas, no formato que o core exporta.
     *
     * O item do idioma corrente aponta para "#": o core nao poe lang na URL de
     * quem ja esta selecionado.
     *
     * @return array
     */
    protected function menu_de_exemplo(): array {
        return [
            'title' => 'English ‎(en)‎',
            'items' => [
                [
                    'link' => true,
                    'url' => '#',
                    'text' => 'English ‎(en)‎',
                    'isactive' => true,
                ],
                [
                    'link' => true,
                    'url' => 'https://exemplo.test/login/signup.php?lang=pt_br',
                    'text' => 'Português - Brasil ‎(pt_br)‎',
                    'isactive' => false,
                ],
                [
                    'link' => true,
                    'url' => 'https://exemplo.test/login/signup.php?lang=es',
                    'text' => 'Español - Internacional ‎(es)‎',
                    'isactive' => false,
                ],
            ],
        ];
    }

    /**
     * Cada idioma ganha a propria sigla, tirada do parametro lang da URL.
     *
     * @return void
     */
    public function test_cada_idioma_ganha_a_propria_sigla(): void {
        $this->resetAfterTest();

        $menu = langmenu::shorten($this->menu_de_exemplo());

        $this->assertSame('PT', $menu['items'][1]['short']);
        $this->assertSame('ES', $menu['items'][2]['short']);
    }

    /**
     * Nenhum item fica sem `short`, para o mustache nao subir no contexto.
     *
     * E o teste que existe pelo bug: sem o campo no item, o template acha o
     * `short` do menu e desenha a sigla do idioma corrente em todos.
     *
     * @return void
     */
    public function test_todo_item_traz_o_proprio_campo_short(): void {
        $this->resetAfterTest();

        $menu = langmenu::shorten($this->menu_de_exemplo());

        foreach ($menu['items'] as $item) {
            $this->assertArrayHasKey('short', $item);
        }

        $siglas = array_column($menu['items'], 'short');
        $this->assertSame(['EN', 'PT', 'ES'], $siglas);
    }

    /**
     * O idioma corrente aponta para "#", e mesmo assim tem sigla.
     *
     * @return void
     */
    public function test_o_idioma_corrente_tira_a_sigla_da_sessao(): void {
        $this->resetAfterTest();

        $menu = langmenu::shorten($this->menu_de_exemplo());

        $this->assertSame('EN', $menu['items'][0]['short']);
        $this->assertSame('EN', $menu['short']);
    }

    /**
     * O nome por extenso sobrevive, para o leitor de tela anuncia-lo.
     *
     * A sigla e para quem enxerga. Quem nao enxerga precisa ouvir o idioma
     * inteiro, e e o `fulltext` que o template entrega escondido.
     *
     * @return void
     */
    public function test_o_nome_por_extenso_continua_no_contexto(): void {
        $this->resetAfterTest();

        $menu = langmenu::shorten($this->menu_de_exemplo());

        $this->assertSame('Português - Brasil ‎(pt_br)‎', $menu['items'][1]['fulltext']);
        $this->assertSame('English ‎(en)‎', $menu['title']);
    }

    /**
     * Menu vazio ou ausente volta como veio, sem aviso.
     *
     * Layout de tema pode ser renderizado com o menu de idiomas desligado, e o
     * encurtador e chamado antes de qualquer conferencia.
     *
     * @return void
     */
    public function test_menu_vazio_atravessa_intacto(): void {
        $this->resetAfterTest();

        $this->assertNull(langmenu::shorten(null));
        $this->assertSame([], langmenu::shorten([]));
        $this->assertSame(['items' => []], langmenu::shorten(['items' => []]));
    }

    /**
     * Item sem lang reconhecivel na URL fica sem sigla, e sem herdar a de outro.
     *
     * @return void
     */
    public function test_item_sem_lang_na_url_nao_herda_sigla(): void {
        $this->resetAfterTest();

        $menu = langmenu::shorten([
            'title' => 'English ‎(en)‎',
            'items' => [
                ['link' => true, 'url' => '#', 'text' => 'English ‎(en)‎', 'isactive' => true],
                ['link' => true, 'url' => 'https://exemplo.test/', 'text' => 'Outro', 'isactive' => false],
            ],
        ]);

        $this->assertSame('EN', $menu['items'][0]['short']);
        $this->assertFalse($menu['items'][1]['short']);
        $this->assertSame('Outro', $menu['items'][1]['fulltext']);
    }
}
