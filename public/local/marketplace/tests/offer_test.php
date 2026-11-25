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
 * Regras de acesso e de cobranca da oferta.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\offer::class)]
final class offer_test extends \advanced_testcase {
    /**
     * Monta uma oferta sem gravar: as regras testadas nao tocam o banco.
     *
     * @param array $props
     * @return offer
     */
    protected function make(array $props): offer {
        $o = new offer();
        foreach ($props as $key => $value) {
            $o->set($key, $value);
        }
        return $o;
    }

    /**
     * Vitalicio nunca vence, mesmo com accessdays preenchido.
     *
     * O campo pode ficar com lixo de uma edicao anterior; quem manda e o modo.
     *
     * @return void
     */
    public function test_lifetime_never_expires(): void {
        $o = $this->make([
            'accessmode' => offer::ACCESS_LIFETIME,
            'accessdays' => 30,
        ]);

        $this->assertSame(0, $o->calculate_expiry(1000000));
    }

    /**
     * Prazo conta a partir do momento informado, e nao de agora.
     *
     * Importa na renovacao: somar a partir de "agora" encurtaria o que ja foi
     * pago quando o aluno renova antes de vencer.
     *
     * @return void
     */
    public function test_days_expiry_counts_from_given_moment(): void {
        $o = $this->make([
            'accessmode' => offer::ACCESS_DAYS,
            'accessdays' => 30,
        ]);

        $from = 1700000000;
        $this->assertSame($from + (30 * DAYSECS), $o->calculate_expiry($from));
    }

    /**
     * Assinatura usa accessdays, NAO billingdays.
     *
     * Este e o coracao da carencia: cobrar a cada 30 dias liberando 35 da
     * margem para o pagamento atrasar sem cortar o aluno. Se o vencimento
     * seguisse billingdays, a carencia nao existiria.
     *
     * @return void
     */
    public function test_recurring_expiry_uses_access_not_billing(): void {
        $o = $this->make([
            'accessmode' => offer::ACCESS_RECURRING,
            'accessdays' => 35,
            'billingdays' => 30,
        ]);

        $from = 1700000000;
        $this->assertSame($from + (35 * DAYSECS), $o->calculate_expiry($from));
    }

    /**
     * accessdays zero em modo com prazo produz acesso vitalicio.
     *
     * Documenta o comportamento atual para que uma mudanca futura seja
     * deliberada: hoje um cadastro incompleto libera acesso para sempre, que e
     * o erro mais caro possivel nesta funcao.
     *
     * @return void
     */
    public function test_zero_days_falls_back_to_no_expiry(): void {
        $o = $this->make([
            'accessmode' => offer::ACCESS_DAYS,
            'accessdays' => 0,
        ]);

        $this->assertSame(0, $o->calculate_expiry(1000000));
    }

    /**
     * Sem limite de ciclos, aceita sempre.
     *
     * @return void
     */
    public function test_unlimited_cycles_always_accepted(): void {
        $o = $this->make([
            'accessmode' => offer::ACCESS_RECURRING,
            'maxcycles' => 0,
        ]);

        $this->assertTrue($o->accepts_cycle(0));
        $this->assertTrue($o->accepts_cycle(9999));
    }

    /**
     * O limite corta exatamente no numero contratado.
     *
     * Mensal por 12 meses aceita a 12a cobranca e recusa a 13a. Um erro de um
     * aqui cobra um mes a mais de todo assinante.
     *
     * @return void
     */
    public function test_max_cycles_boundary(): void {
        $o = $this->make([
            'accessmode' => offer::ACCESS_RECURRING,
            'maxcycles' => 12,
        ]);

        $this->assertTrue($o->accepts_cycle(11), '12a cobranca deve ser aceita');
        $this->assertFalse($o->accepts_cycle(12), '13a cobranca deve ser recusada');
        $this->assertFalse($o->accepts_cycle(50));
    }

    /**
     * Limite de ciclos so vale para assinatura.
     *
     * Compra avulsa nao tem ciclo a limitar; herdar o limite impediria o aluno
     * de comprar de novo um curso vitalicio.
     *
     * @return void
     */
    public function test_cycle_limit_ignored_outside_recurring(): void {
        $o = $this->make([
            'accessmode' => offer::ACCESS_LIFETIME,
            'maxcycles' => 1,
        ]);

        $this->assertTrue($o->accepts_cycle(5));
    }

    /**
     * Cada opcao de ordenacao vira uma clausula SQL propria.
     *
     * @return void
     */
    public function test_sort_clause_per_option(): void {
        $this->assertSame('sortorder, name', offer::sort_clause(offer::SORT_MANUAL));
        $this->assertSame('name', offer::sort_clause(offer::SORT_NAME));
        $this->assertSame('price, name', offer::sort_clause(offer::SORT_PRICE));
        $this->assertSame('price DESC, name', offer::sort_clause(offer::SORT_PRICEDESC));
        $this->assertStringStartsWith('timecreated DESC', offer::sort_clause(offer::SORT_NEWEST));
    }

    /**
     * Opcao desconhecida cai na ordem do vendedor.
     *
     * A vitrine e publica: o parametro vem da URL e qualquer um digita o que
     * quiser. Cair no padrao e melhor que devolver erro ou, pior, montar SQL
     * com o que chegou.
     *
     * @return void
     */
    public function test_unknown_sort_falls_back_to_manual(): void {
        $this->assertSame('sortorder, name', offer::sort_clause('lixo'));
        $this->assertSame('sortorder, name', offer::sort_clause(''));
        $this->assertSame('sortorder, name', offer::sort_clause('price; DROP TABLE'));
    }

    /**
     * Lancamentos desempata por id.
     *
     * Ofertas criadas no mesmo segundo - o que acontece no seed e em importacao
     * - sairiam em ordem aleatoria sem isto, e a vitrine mudaria de ordem a
     * cada carregamento.
     *
     * @return void
     */
    public function test_newest_breaks_ties_by_id(): void {
        $this->assertSame('timecreated DESC, id DESC', offer::sort_clause(offer::SORT_NEWEST));
    }

    /**
     * Preco zero e gratuito; qualquer valor positivo nao e.
     *
     * @return void
     */
    public function test_is_free(): void {
        $this->assertTrue($this->make(['price' => 0])->is_free());
        $this->assertTrue($this->make(['price' => 0.0])->is_free());
        $this->assertFalse($this->make(['price' => 0.01])->is_free());
    }
}
