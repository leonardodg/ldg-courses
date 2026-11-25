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

namespace local_marketplace;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Mapa de pais para moeda.
 *
 * Parece trivial e nao e: e este mapa que decide em que moeda a oferta cobra e,
 * por consequencia, qual conta recebe. Um pais mapeado para a moeda errada
 * produziria cobranca em moeda que a conta do vendedor nao guarda, e o erro so
 * apareceria no checkout.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\country::class)]
final class country_test extends \advanced_testcase {
    /**
     * Cada pais atendido devolve a moeda dele.
     *
     * @return void
     */
    public function test_currency_for_known_countries(): void {
        $this->assertSame('BRL', country::currency_for('BR'));
        $this->assertSame('ARS', country::currency_for('AR'));
        $this->assertSame('MXN', country::currency_for('MX'));
    }

    /**
     * Minusculas e espaco em volta nao mudam a resposta.
     *
     * O codigo chega de formulario, de CLI, de resposta de API e de coluna
     * antiga do banco. Uma comparacao entre 'br' e 'BR' falharia em silencio,
     * escolhendo a conta errada em vez de reclamar.
     *
     * @return void
     */
    public function test_currency_for_is_case_insensitive(): void {
        $this->assertSame('BRL', country::currency_for('br'));
        $this->assertSame('BRL', country::currency_for(' Br '));
    }

    /**
     * Pais fora da lista devolve vazio, e nao um palpite.
     *
     * @return void
     */
    public function test_currency_for_unknown_country(): void {
        $this->assertSame('', country::currency_for('US'));
        $this->assertSame('', country::currency_for(''));
    }

    /**
     * A lista de suportados e a mesma que a do mapa.
     *
     * @return void
     */
    public function test_is_supported(): void {
        $this->assertTrue(country::is_supported('BR'));
        $this->assertTrue(country::is_supported('ar'));
        $this->assertFalse(country::is_supported('US'));
    }

    /**
     * Todo pais do mapa tem moeda de tres letras.
     *
     * @return void
     */
    public function test_every_country_has_a_three_letter_currency(): void {
        foreach (country::CURRENCIES as $code => $currency) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $code);
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $currency);
        }
    }

    /**
     * O menu traz todos os codigos e nada alem deles.
     *
     * @return void
     */
    public function test_menu_covers_every_country(): void {
        $menu = country::menu();

        $this->assertSame(country::codes(), array_keys($menu));
        foreach ($menu as $label) {
            $this->assertNotSame('', $label);
        }
    }

    /**
     * A descricao carrega codigo e moeda, que e o que desambigua na tela.
     *
     * @return void
     */
    public function test_describe_carries_code_and_currency(): void {
        $described = country::describe('BR');

        $this->assertStringContainsString('BR', $described);
        $this->assertStringContainsString('BRL', $described);
    }

    /**
     * O padrao de fabrica e um pais atendido.
     *
     * @return void
     */
    public function test_default_country_is_supported(): void {
        $this->assertTrue(country::is_supported(country::DEFAULT_COUNTRY));
    }
}
