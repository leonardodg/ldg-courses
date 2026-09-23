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

namespace theme_ldg\util;

/**
 * O menu de idiomas, encurtado para sigla.
 *
 * "Portugues - Brasil (pt_br)" ocupa meia navbar para dizer o que "PT" diz em
 * duas letras. O nome por extenso NAO some: ele vai junto, escondido
 * visualmente, e o template o entrega ao leitor de tela - quem enxerga
 * reconhece a sigla, quem nao enxerga ouve o idioma.
 *
 * Mesmo desenho do seletor da landing de captacao, para as duas superficies nao
 * ensinarem dois gestos diferentes.
 *
 * @package    theme_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class langmenu {
    /**
     * Acrescenta a sigla ao contexto do menu de idiomas.
     *
     * @param array|null $menu O contexto vindo do primary::export_for_template.
     * @return array|null O mesmo contexto, com 'short' em cada item e no titulo.
     */
    public static function shorten($menu) {
        if (empty($menu) || empty($menu['items'])) {
            return $menu;
        }

        foreach ($menu['items'] as $indice => $item) {
            $codigo = self::code_from($item);

            if ($codigo === '' && !empty($item['isactive'])) {
                // O item do idioma corrente aponta para "#" - nao ha lang na URL
                // de onde tirar a sigla. Ela sai da sessao.
                $codigo = self::sigla(current_language());
            }

            // Sempre os DOIS campos, mesmo vazios. Sem 'short' no item, o
            // mustache sobe no contexto e acha o 'short' do menu - todo idioma
            // apareceria com a sigla do idioma corrente.
            $menu['items'][$indice]['short'] = $codigo !== '' ? $codigo : false;
            $menu['items'][$indice]['fulltext'] = $item['fulltext'] ?? ($item['text'] ?? $codigo);

            if ($codigo !== '' && !empty($item['isactive'])) {
                $menu['short'] = $codigo;
            }
        }

        // Sem item ativo marcado, a sigla sai do idioma em uso.
        if (empty($menu['short'])) {
            $menu['short'] = self::sigla(current_language());
        }

        return $menu;
    }

    /**
     * A sigla de um item, tirada do parametro lang da URL dele.
     *
     * Vem da URL, e nao do texto: o texto e o nome traduzido do idioma e muda
     * de forma em cada pacote, enquanto o parametro e sempre o codigo.
     *
     * @param array $item
     * @return string
     */
    protected static function code_from(array $item): string {
        $url = (string) ($item['url'] ?? '');

        if ($url !== '' && preg_match('/[?&]lang=([A-Za-z0-9_-]+)/', $url, $captura)) {
            return self::sigla($captura[1]);
        }

        return '';
    }

    /**
     * O codigo do Moodle reduzido a sigla de exibicao.
     *
     * A regra mora em local_partners\output\landing_page::language_short()
     * - o seletor da landing de captacao. Este tema repassa quando o plugin
     * existe (nao declara dependencia dele: moodle-plugin-ci instala o tema
     * sozinho). Sem o plugin, a mesma formula local evita menu sem sigla.
     *
     * @param string $lang
     * @return string
     */
    public static function sigla(string $lang): string {
        if (class_exists(\local_partners\output\landing_page::class)) {
            return \local_partners\output\landing_page::language_short($lang);
        }

        return strtoupper(explode('_', str_replace('-', '_', $lang))[0]);
    }
}
