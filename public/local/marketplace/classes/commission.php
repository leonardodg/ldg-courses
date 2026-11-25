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
 * Os termos da comissao de uma venda: percentual, base de calculo e origem.
 *
 * Existe para os dois primeiros NUNCA viajarem separados. "9,9%" sozinho nao
 * diz quanto a plataforma recebe - 9,9% de R$ 100 e R$ 9,90 sobre o bruto e
 * R$ 9,65 sobre o liquido do Asaas. Enquanto isso eram duas variaveis soltas,
 * o percentual vinha do plano e a base vinha de outro lugar sem ninguem notar.
 *
 * A origem viaja junto porque a tela de empresas precisa dizer DE ONDE o numero
 * veio, e porque um relatorio que mostra "9,9%" sem dizer se foi negociado ou
 * herdado do plano nao permite conferir nada.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class commission {
    /** @var string Sobre o valor cheio da venda. */
    public const BASE_GROSS = 'gross';

    /** @var string Sobre o que sobra depois de o gateway descontar a taxa dele. */
    public const BASE_NET = 'net';

    /** @var string Veio da politica de um curso especifico. */
    public const SOURCE_POLICY = 'policy';

    /** @var string Negociada com a empresa. */
    public const SOURCE_COMPANY = 'company';

    /** @var string Veio do plano contratado. */
    public const SOURCE_PLAN = 'plan';

    /** @var string Padrao do site. */
    public const SOURCE_SITE = 'site';

    /** @var float Percentual, de 0 a 100. */
    public readonly float $percent;

    /** @var string Base de calculo, BASE_GROSS ou BASE_NET. */
    public readonly string $base;

    /** @var string De onde o percentual veio, uma das SOURCE_*. */
    public readonly string $source;

    /**
     * Construtor.
     *
     * @param float $percent
     * @param string|null $base Nulo cai no padrao do site.
     * @param string $source
     */
    public function __construct(float $percent, ?string $base, string $source) {
        $this->percent = max(0.0, min(100.0, $percent));
        $this->base = self::normalise_base($base);
        $this->source = $source;
    }

    /**
     * Bases aceitas, para validacao e para montar seletor de formulario.
     *
     * @return string[]
     */
    public static function bases(): array {
        return [self::BASE_GROSS, self::BASE_NET];
    }

    /**
     * Reduz qualquer entrada a uma base valida.
     *
     * Nulo, vazio e lixo caem no padrao do site em vez de virar excecao: isto e
     * chamado no meio de uma compra, e derrubar o checkout porque uma coluna
     * ficou com valor inesperado seria pior que cobrar pela base padrao.
     *
     * @param string|null $base
     * @return string
     */
    public static function normalise_base(?string $base): string {
        if ($base !== null && in_array($base, self::bases(), true)) {
            return $base;
        }

        return self::default_base();
    }

    /**
     * Base padrao do site.
     *
     * @return string
     */
    public static function default_base(): string {
        $configured = get_config('local_marketplace', 'commissionbase');

        return in_array($configured, self::bases(), true) ? $configured : self::BASE_GROSS;
    }

    /**
     * Calcula a comissao em moeda sobre o valor informado.
     *
     * Recebe o valor da base ja escolhido por quem chama, porque so o gateway
     * sabe o liquido - e na criacao da cobranca ele ainda nao existe.
     *
     * @param float $value
     * @return float
     */
    public function amount_on(float $value): float {
        return round($value * ($this->percent / 100), 2);
    }

    /**
     * A comissao incide sobre o valor cheio?
     *
     * @return bool
     */
    public function is_gross(): bool {
        return $this->base === self::BASE_GROSS;
    }

    /**
     * Os mesmos termos, com a base trocada.
     *
     * Serve ao gateway que nao consegue aplicar a base configurada e precisa
     * registrar a que de fato aplicou - o Mercado Pago nao tem como cobrar
     * sobre o liquido, porque o marketplace_fee e valor absoluto e a taxa dele
     * so e conhecida depois.
     *
     * @param string $base
     * @return commission
     */
    public function with_base(string $base): commission {
        return new commission($this->percent, $base, $this->source);
    }

    /**
     * Nome traduzido da base, para tela e relatorio.
     *
     * @param string|null $base Nulo usa a base destes termos.
     * @return string
     */
    public function base_name(?string $base = null): string {
        return get_string('commissionbase' . ($base ?? $this->base), 'local_marketplace');
    }

    /**
     * Nome traduzido da origem, para a coluna "de onde veio".
     *
     * @return string
     */
    public function source_name(): string {
        return get_string('commissionsource' . $this->source, 'local_marketplace');
    }
}
