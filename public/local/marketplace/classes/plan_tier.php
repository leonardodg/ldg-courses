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

use core\persistent;
use lang_string;

/**
 * Faixa de resolucao de video por valor de ticket.
 *
 * A regra comercial e simples: quanto mais barato o curso, menor a resolucao
 * maxima que a plataforma entrega. Isso protege a margem no plano em que a
 * banda e paga por nos.
 *
 * O teto NULO e a faixa final, a que nao tem limite de preco. Guardar um numero
 * enorme no lugar transformaria "sem limite" num limite que alguem escolheu, e
 * um dia esse numero seria pequeno.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_tier extends persistent {
    /** @var string Tabela. */
    public const TABLE = 'local_marketplace_plan_tier';

    /** @var array Resolucoes aceitas, da menor para a maior. */
    public const RESOLUTIONS = ['720p', '1080p', '1440p', '4k'];

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'planid' => [
                'type' => PARAM_INT,
            ],
            'maxprice' => [
                'type' => PARAM_FLOAT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'maxresolution' => [
                'type' => PARAM_ALPHANUM,
                'default' => '720p',
                'choices' => self::RESOLUTIONS,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * A faixa precisa apontar para um plano que existe.
     *
     * A chave estrangeira do XMLDB e documental no Moodle - quem garante a
     * integridade e este validador.
     *
     * @param int $value
     * @return true|lang_string
     */
    protected function validate_planid($value) {
        if (!plan::record_exists((int) $value)) {
            return new lang_string('errorplannotfound', 'local_marketplace');
        }

        return true;
    }

    /**
     * Teto negativo nao existe. O nulo e permitido e significa "sem teto".
     *
     * @param float|null $value
     * @return true|lang_string
     */
    protected function validate_maxprice($value) {
        if ($value !== null && $value < 0) {
            return new lang_string('errorplantiernegative', 'local_marketplace');
        }

        return true;
    }
}
