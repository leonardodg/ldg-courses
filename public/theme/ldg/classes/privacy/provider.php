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
 * Privacidade do theme_ldg.
 *
 * @package    theme_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_ldg\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;

/**
 * Provedor de privacidade do tema LDG.
 *
 * Este arquivo ja foi um null_provider, e a justificativa era que as
 * preferencias pertenciam ao theme_moove - quem as declarava era o provider
 * dele, e este tema so as lia. Isso deixou de ser verdade quando o tema saiu da
 * heranca do Moove: hoje quem declara as cinco preferencias e o proprio
 * theme_ldg_user_preferences(), em lib.php.
 *
 * A consequencia de ter ficado para tras era silenciosa e por isso pior: o
 * registro de privacidade afirmava que o tema nao guarda nada, e o que a pessoa
 * escolheu na barra de acessibilidade nao entrava na exportacao de dados dela.
 * Nenhuma tela dava erro.
 *
 * Duas das preferencias - o tamanho de fonte e o contraste - tem o mesmo nome
 * das do Moove, sem prefixo de componente, porque a barra de acessibilidade veio
 * de la e trocar o nome apagaria a escolha de quem ja usa o site. Com os dois
 * temas instalados, os dois providers declaram a mesma preferencia; o Moodle
 * aceita isso e a pessoa ve a entrada duas vezes, o que e melhor do que nao ver
 * nenhuma.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider, user_preference_provider {
    /** @var string Menu lateral recolhido. */
    const NAVMENU = 'theme_ldg-navmenu-collapsed';

    /** @var string Modo escuro ligado. */
    const DARKMODE = 'dark-mode-on';

    /** @var string Barra de acessibilidade visivel. */
    const ACCESSIBILITYBAR = 'theme_ldg-accessibilitybar';

    /** @var string Tamanho de fonte da barra de acessibilidade. */
    const FONTSIZE = 'accessibilitystyles_fontsizeclass';

    /** @var string Contraste da barra de acessibilidade. */
    const SITECOLOR = 'accessibilitystyles_sitecolorclass';

    /**
     * Preferencias que o tema guarda, e a string que explica cada uma.
     *
     * Uma lista so, consumida pelos dois metodos abaixo. Manter duas listas
     * paralelas foi o que deixou este arquivo desatualizado da primeira vez.
     *
     * @return array<string, string> nome da preferencia => sufixo da string
     */
    protected static function preferences(): array {
        return [
            self::NAVMENU => 'navmenucollapsed',
            self::DARKMODE => 'darkmode',
            self::ACCESSIBILITYBAR => 'accessibilitybar',
            self::FONTSIZE => 'fontsizeclass',
            self::SITECOLOR => 'sitecolorclass',
        ];
    }

    /**
     * Declara o que o tema guarda.
     *
     * @param collection $items
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        foreach (self::preferences() as $name => $suffix) {
            $items->add_user_preference($name, 'privacy:metadata:preference:' . $suffix);
        }

        return $items;
    }

    /**
     * Exporta as preferencias da pessoa.
     *
     * Cada uma e testada por si. O tema de onde a barra veio aninha a
     * exportacao do tamanho de fonte dentro do "a barra esta ligada", e com isso
     * quem escolheu uma fonte maior e depois escondeu a barra nunca ve a propria
     * escolha exportada.
     *
     * @param int $userid
     * @return void
     */
    public static function export_user_preferences(int $userid) {
        foreach (self::preferences() as $name => $suffix) {
            $value = get_user_preferences($name, null, $userid);

            if ($value === null) {
                continue;
            }

            writer::export_user_preference(
                'theme_ldg',
                $name,
                $value,
                get_string('privacy:preference:' . $suffix, 'theme_ldg', $value)
            );
        }
    }
}
