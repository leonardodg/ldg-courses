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
 * Validacao de CNPJ.
 *
 * Mora no local_marketplace, e nao no plugin que coleta o cadastro, porque o
 * dono do campo e quem valida: `local_marketplace_company.cnpj` existe desde a
 * primeira versao e ate agora era so PARAM_ALPHANUM - qualquer sequencia de
 * catorze digitos passava.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cnpj {
    /**
     * Deixa so os digitos.
     *
     * @param string $value CNPJ como o usuario digitou.
     * @return string
     */
    public static function normalise(string $value): string {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * O CNPJ e valido?
     *
     * Confere os dois digitos verificadores pelo modulo 11. Nao consulta a
     * Receita: isso responde "existe", e aqui a pergunta e "e um numero bem
     * formado" - o suficiente para pegar erro de digitacao antes de a
     * candidatura chegar ao administrador.
     *
     * @param string $value CNPJ, com ou sem pontuacao.
     * @return bool
     */
    public static function is_valid(string $value): bool {
        $digits = self::normalise($value);

        if (strlen($digits) !== 14) {
            return false;
        }

        // Sequencias repetidas passam no modulo 11 por acidente aritmetico, e
        // 00000000000000 e o valor que um formulario mal preenchido produz.
        if (preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        foreach ([12, 13] as $position) {
            if ((int) $digits[$position] !== self::check_digit($digits, $position)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Digito verificador de uma das duas posicoes.
     *
     * @param string $digits Os catorze digitos.
     * @param int $position 12 para o primeiro digito, 13 para o segundo.
     * @return int
     */
    private static function check_digit(string $digits, int $position): int {
        $sum = 0;
        $weight = 2;

        for ($i = $position - 1; $i >= 0; $i--) {
            $sum += (int) $digits[$i] * $weight;
            // Os pesos vao de 2 a 9 e voltam para 2 - nao seguem ate o fim.
            $weight = $weight === 9 ? 2 : $weight + 1;
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
