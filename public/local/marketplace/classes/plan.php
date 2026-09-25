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
 * Plano comercial da plataforma.
 *
 * O plano diz quanto a empresa paga por mes, que comissao pratica e como o
 * video dela e hospedado. Ele NAO cobra nada: a mensalidade e informativa ate
 * existir a paymentarea 'plan' no service_provider.
 *
 * Os valores nascem de um seed e sao ajustaveis pela tela de administracao. O
 * seed e semente de instalacao, e nao fonte da verdade - preco muda por decisao
 * comercial, e nao por deploy.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan extends persistent {
    /** @var string Tabela. */
    public const TABLE = 'local_marketplace_plan';

    /** @var string Plano em uso. */
    public const STATUS_ACTIVE = 'active';

    /** @var string Plano fora de venda, mas preservado por causa do historico. */
    public const STATUS_ARCHIVED = 'archived';

    /** @var string A plataforma hospeda o video e paga a banda. */
    public const HOSTING_NATIVE = 'native';

    /** @var string O produtor conecta a chave da propria conta de streaming. */
    public const HOSTING_BYOS = 'byos';

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'shortname' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'description' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'monthlyfee' => [
                'type' => PARAM_FLOAT,
                'default' => 0,
            ],
            'commissionpct' => [
                'type' => PARAM_FLOAT,
                'default' => 0,
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
            'country' => [
                'type' => PARAM_ALPHA,
                'default' => country::DEFAULT_COUNTRY,
            ],
            'currency' => [
                'type' => PARAM_ALPHA,
                'default' => 'BRL',
            ],
            'hostingmodel' => [
                'type' => PARAM_ALPHA,
                'default' => self::HOSTING_NATIVE,
                'choices' => [self::HOSTING_NATIVE, self::HOSTING_BYOS],
            ],
            'ispublic' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'status' => [
                'type' => PARAM_ALPHA,
                'default' => self::STATUS_ACTIVE,
                'choices' => [self::STATUS_ACTIVE, self::STATUS_ARCHIVED],
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * O nome curto e chave de codigo: precisa ser unico.
     *
     * E por ele que o seed sabe o que ja existe. Dois planos com o mesmo
     * shortname fariam o seed de uma instalacao nova duplicar linhas.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_shortname($value) {
        $existing = $this->get_record_by_shortname($value);

        if ($existing && (int) $existing->get('id') !== (int) $this->get('id')) {
            return new lang_string('errorplanshortnametaken', 'local_marketplace');
        }

        return true;
    }

    /**
     * A comissao do plano tem que caber entre 0 e 100.
     *
     * @param float $value
     * @return true|lang_string
     */
    protected function validate_commissionpct($value) {
        if ($value < 0 || $value > 100) {
            return new lang_string('errorcommissionrange', 'local_marketplace');
        }

        return true;
    }

    /**
     * Mensalidade negativa nao existe.
     *
     * @param float $value
     * @return true|lang_string
     */
    protected function validate_monthlyfee($value) {
        if ($value < 0) {
            return new lang_string('errorplanfeenegative', 'local_marketplace');
        }

        return true;
    }

    /**
     * O pais precisa ser um dos que a plataforma atende.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_country($value) {
        if (!in_array($value, country::codes(), true)) {
            return new lang_string('errorcountryunsupported', 'local_marketplace', $value);
        }

        return true;
    }

    /**
     * Busca um plano pelo nome curto.
     *
     * @param string $shortname
     * @return plan|false
     */
    public static function get_record_by_shortname(string $shortname) {
        return self::get_record(['shortname' => $shortname]);
    }

    /**
     * Planos que a landing pode exibir, na ordem de exibicao.
     *
     * @return plan[]
     */
    public static function get_public_plans(): array {
        return self::get_records(
            ['status' => self::STATUS_ACTIVE, 'ispublic' => 1],
            'sortorder, id'
        );
    }

    /**
     * Faixas de resolucao deste plano, da menor para a maior.
     *
     * @return plan_tier[]
     */
    public function get_tiers(): array {
        return plan_tier::get_records(['planid' => (int) $this->get('id')], 'sortorder, id');
    }

    /**
     * Resolucao maxima liberada para um ticket.
     *
     * A faixa sem teto (maxprice nulo) e o ultimo recurso, e por isso e
     * avaliada por ultimo: ela cobre tudo o que sobrou.
     *
     * @param float $price Valor do curso.
     * @return string|null Resolucao, ou nulo quando o plano nao tem faixas.
     */
    public function max_resolution_for(float $price): ?string {
        $unlimited = null;

        foreach ($this->get_tiers() as $tier) {
            $max = $tier->get('maxprice');

            if ($max === null) {
                $unlimited = $tier->get('maxresolution');
                continue;
            }

            if ($price <= (float) $max) {
                return $tier->get('maxresolution');
            }
        }

        return $unlimited;
    }

    /**
     * Teto de resolucao do PLANO (mensalidade do vendedor), sem olhar preco
     * de curso nenhum - e o que a Frente C trava (ADR-0014).
     *
     * So a faixa "sem teto de preco" (maxprice nulo) conta: e ela que
     * representa o degrau contratado pela empresa. Faixas com maxprice
     * preenchido sao o modelo antigo, por ticket (ADR-0005) - ainda podem
     * existir no banco por historico, mas nao valem para a trava por
     * mensalidade.
     *
     * @return string|null Resolucao, ou nulo quando o plano nao trava nada
     *                      (ex.: BYOS, que nao consome banda da plataforma).
     */
    public function max_resolution(): ?string {
        foreach ($this->get_tiers() as $tier) {
            if ($tier->get('maxprice') === null) {
                return $tier->get('maxresolution');
            }
        }

        return null;
    }
}
