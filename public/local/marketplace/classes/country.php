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

/**
 * Paises em que o marketplace opera, e a moeda de cada um.
 *
 * Por que o pais e um conceito do NUCLEO e nao de cada gateway: o dinheiro de
 * uma venda cai numa conta, a conta e presa a um pais e so guarda a moeda dele.
 * Nao ha cambio no caminho do split. Entao "em que pais esta oferta vende"
 * decide, de uma vez, a moeda, a conta que recebe e quais gateways podem
 * aparecer no checkout.
 *
 * A chave e o codigo ISO-3166 alpha-2, e nao o codigo de um fornecedor. O
 * Mercado Pago chama o Brasil de MLB; o Asaas e o Pagar.me so existem no
 * Brasil e nao tem codigo nenhum. Se o nucleo falasse MLB, o paygw_asaas teria
 * que aprender o vocabulario do concorrente para responder uma pergunta que
 * nao e do Mercado Pago. A traducao ISO -> codigo do fornecedor fica dentro do
 * cliente HTTP de cada gateway, que e onde ela importa.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class country {
    /**
     * Pais ISO-3166 alpha-2 => moeda ISO-4217.
     *
     * A lista e a intersecao do que os gateways integrados atendem. Ela cresce
     * quando um gateway novo abre um pais novo, e nao antes: um pais aqui sem
     * gateway que o atenda produziria oferta que ninguem consegue pagar.
     *
     * @var array<string, string>
     */
    public const CURRENCIES = [
        'AR' => 'ARS',
        'BR' => 'BRL',
        'CL' => 'CLP',
        'CO' => 'COP',
        'MX' => 'MXN',
        'PE' => 'PEN',
        'UY' => 'UYU',
    ];

    /** @var string Usado quando ninguem escolheu, e no provisionamento da primeira conta. */
    public const DEFAULT_COUNTRY = 'BR';

    /**
     * Moeda em que se vende neste pais.
     *
     * @param string $country Codigo ISO-3166 alpha-2, maiusculo ou nao.
     * @return string Codigo ISO-4217, ou vazio se o pais nao e atendido.
     */
    public static function currency_for(string $country): string {
        return self::CURRENCIES[self::normalize($country)] ?? '';
    }

    /**
     * O marketplace opera neste pais?
     *
     * @param string $country
     * @return bool
     */
    public static function is_supported(string $country): bool {
        return isset(self::CURRENCIES[self::normalize($country)]);
    }

    /**
     * Codigos atendidos.
     *
     * @return string[]
     */
    public static function codes(): array {
        return array_keys(self::CURRENCIES);
    }

    /**
     * Deixa o codigo na forma canonica: duas letras maiusculas.
     *
     * Existe porque o codigo chega de toda parte - formulario, CLI, resposta de
     * API, coluna antiga do banco - e uma comparacao de string entre 'br' e
     * 'BR' falharia em silencio, escolhendo a conta errada.
     *
     * @param string $value
     * @return string
     */
    public static function normalize(string $value): string {
        return strtoupper(trim($value));
    }

    /**
     * Paises atendidos, para um select, com o nome no idioma do usuario.
     *
     * O nome vem do Moodle e nao de uma lista nossa: traduzir "Brasil" em tres
     * idiomas seria manter a mao um dado que o core ja tem completo.
     *
     * @return array<string, string> codigo => nome
     */
    public static function menu(): array {
        $names = get_string_manager()->get_list_of_countries(true);

        $menu = [];
        foreach (self::codes() as $code) {
            $menu[$code] = $names[$code] ?? $code;
        }

        return $menu;
    }

    /**
     * Nome do pais no idioma do usuario, com o codigo e a moeda.
     *
     * @param string $country
     * @return string Ex.: "Brasil (BR - BRL)".
     */
    public static function describe(string $country): string {
        $code = self::normalize($country);
        $names = get_string_manager()->get_list_of_countries(true);

        return ($names[$code] ?? $code) . ' (' . $code . ' - ' . (self::currency_for($code) ?: '?') . ')';
    }
}
