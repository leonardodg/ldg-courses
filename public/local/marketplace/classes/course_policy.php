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
 * Politica de hospedagem e comissao de um curso.
 *
 * A comissao depende de onde o video mora, e nao do preco:
 *   external -> 25%, porque o custo de banda e do vendedor
 *   platform -> em aberto, porque armazenamento e streaming escalam com o
 *               numero de alunos, o oposto de um percentual fixo
 *
 * Enquanto o modelo de "platform" nao for decidido, so "external" e aceito.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_policy extends persistent {
    /** @var string Tabela. */
    public const TABLE = 'local_marketplace_course';

    /** @var string Video fora da plataforma. */
    public const HOSTING_EXTERNAL = 'external';

    /** @var string Video no moodledata. Ainda nao disponivel. */
    public const HOSTING_PLATFORM = 'platform';

    /** @var float Comissao para conteudo hospedado fora. */
    public const DEFAULT_COMMISSION = 25.00;

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'courseid' => [
                'type' => PARAM_INT,
            ],
            'companyid' => [
                'type' => PARAM_INT,
            ],
            'hostingtype' => [
                'type' => PARAM_ALPHA,
                'default' => self::HOSTING_EXTERNAL,
                'choices' => [self::HOSTING_EXTERNAL, self::HOSTING_PLATFORM],
            ],
            'commissionpct' => [
                'type' => PARAM_FLOAT,
                'default' => self::DEFAULT_COMMISSION,
            ],
            'commissionbase' => [
                'type' => PARAM_ALPHA,
                'null' => NULL_ALLOWED,
                'default' => null,
                // O nulo entra na lista de proposito: o validador de choices do
                // persistent roda mesmo com NULL_ALLOWED, e sem ele "herda a
                // base do site" seria recusado como valor invalido.
                'choices' => [null, commission::BASE_GROSS, commission::BASE_NET],
            ],
        ];
    }

    /**
     * Recusa "platform" ate o modelo de cobranca existir.
     *
     * A alternativa seria aceitar e cobrar 25%, mas isso significaria a
     * plataforma pagando armazenamento e banda que crescem com a audiencia,
     * recebendo um percentual que nao cresce.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_hostingtype($value) {
        if ($value === self::HOSTING_PLATFORM) {
            return new lang_string('errorplatformhostingunavailable', 'local_marketplace');
        }
        return true;
    }

    /**
     * Politica de um curso, ou false se o curso nao e do marketplace.
     *
     * @param int $courseid
     * @return course_policy|false
     */
    public static function get_by_course(int $courseid) {
        return self::get_record(['courseid' => $courseid]);
    }

    /**
     * Comissao em dinheiro sobre um valor bruto.
     *
     * ATENCAO: no Mercado Pago a taxa DELE sai primeiro, e a comissao da
     * plataforma incide sobre o que sobra. Este calculo e sobre o bruto e
     * serve para exibicao; o valor efetivamente repassado vem da resposta do
     * gateway, e o relatorio precisa usar aquele, nao este.
     *
     * @param float $amount
     * @return float
     */
    public function commission_on(float $amount): float {
        return round($amount * ((float) $this->get('commissionpct') / 100), 2);
    }
}
