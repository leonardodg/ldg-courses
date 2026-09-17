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
 * Plano comercial e as faixas de resolucao.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\plan::class)]
#[CoversClass(\local_marketplace\plan_tier::class)]
final class plan_test extends \advanced_testcase {
    /**
     * Cria um plano de teste.
     *
     * @param array $overrides Campos a sobrescrever.
     * @return plan
     */
    private function make_plan(array $overrides = []): plan {
        $plan = new plan(0, (object) array_merge([
            'shortname' => 'teste',
            'name' => 'Plano de teste',
            'monthlyfee' => 0,
            'commissionpct' => 10,
        ], $overrides));
        $plan->create();

        return $plan;
    }

    /**
     * O nome curto e unico: e por ele que o seed sabe o que ja existe.
     *
     * @return void
     */
    public function test_shortname_e_unico(): void {
        $this->resetAfterTest();

        // NAO usar 'start_free', 'start_50', 'start_100' ou 'pro': o seed da
        // instalacao ja criou esses quatro, e o teste falharia na PRIMEIRA
        // criacao, provando outra coisa que nao a unicidade.
        $this->make_plan(['shortname' => 'unicidade']);

        $duplicado = new plan(0, (object) [
            'shortname' => 'unicidade',
            'name' => 'Outro',
        ]);

        $this->expectException(\core\invalid_persistent_exception::class);
        $duplicado->create();
    }

    /**
     * Comissao fora de 0 a 100 nao entra.
     *
     * @return void
     */
    public function test_comissao_fora_da_faixa_e_recusada(): void {
        $this->resetAfterTest();

        $plan = new plan(0, (object) [
            'shortname' => 'invalido',
            'name' => 'Invalido',
            'commissionpct' => 120,
        ]);

        $this->expectException(\core\invalid_persistent_exception::class);
        $plan->create();
    }

    /**
     * A faixa precisa apontar para um plano que existe.
     *
     * A chave estrangeira do XMLDB e documental no Moodle - quem garante e o
     * validador do persistent, e este teste e a prova disso.
     *
     * @return void
     */
    public function test_faixa_exige_plano_existente(): void {
        $this->resetAfterTest();

        $tier = new plan_tier(0, (object) [
            'planid' => 999999,
            'maxresolution' => '720p',
        ]);

        $this->expectException(\core\invalid_persistent_exception::class);
        $tier->create();
    }

    /**
     * A trava de resolucao escolhe a faixa pelo preco do curso.
     *
     * @return void
     */
    public function test_resolucao_por_faixa_de_preco(): void {
        $this->resetAfterTest();

        $plan = $this->make_plan();
        $planid = (int) $plan->get('id');

        foreach (
            [
            ['maxprice' => 49.90, 'maxresolution' => '720p', 'sortorder' => 10],
            ['maxprice' => 200.00, 'maxresolution' => '1080p', 'sortorder' => 20],
            ['maxprice' => null, 'maxresolution' => '4k', 'sortorder' => 30],
            ] as $tier
        ) {
            $tier['planid'] = $planid;
            (new plan_tier(0, (object) $tier))->create();
        }

        $this->assertSame('720p', $plan->max_resolution_for(30.00));
        // O teto e inclusive: exatamente 49,90 ainda e a faixa de 720p.
        $this->assertSame('720p', $plan->max_resolution_for(49.90));
        $this->assertSame('1080p', $plan->max_resolution_for(50.00));
        $this->assertSame('1080p', $plan->max_resolution_for(199.90));
        // A faixa de dez centavos entre 199,90 e 200,00 que a regra comercial
        // nao cobre: cai no 1080p, e nao no 4K por omissao.
        $this->assertSame('1080p', $plan->max_resolution_for(199.95));
        $this->assertSame('1080p', $plan->max_resolution_for(200.00));
        // Acima da ultima faixa com teto vale a faixa sem teto.
        $this->assertSame('4k', $plan->max_resolution_for(200.01));
        $this->assertSame('4k', $plan->max_resolution_for(250.00));
    }

    /**
     * Plano sem faixas nao trava nada - e o caso do BYOS, em que a banda nao e
     * paga pela plataforma.
     *
     * @return void
     */
    public function test_plano_sem_faixas_nao_trava(): void {
        $this->resetAfterTest();

        $plan = $this->make_plan(['hostingmodel' => plan::HOSTING_BYOS]);

        $this->assertNull($plan->max_resolution_for(30.00));
        $this->assertNull($plan->max_resolution_for(5000.00));
    }

    /**
     * A vitrine so lista plano ativo e publico.
     *
     * @return void
     */
    public function test_vitrine_ignora_arquivado_e_privado(): void {
        $this->resetAfterTest();

        $this->make_plan(['shortname' => 'vitrinepublico', 'sortorder' => 10]);
        $this->make_plan(['shortname' => 'vitrineprivado', 'ispublic' => 0, 'sortorder' => 20]);
        $this->make_plan(['shortname' => 'vitrinearquivado', 'status' => plan::STATUS_ARCHIVED, 'sortorder' => 30]);

        // Presenca e ausencia, e nao contagem: o banco de teste ja vem com os
        // planos do seed, entao um assertCount amarraria o teste ao numero de
        // planos semeados e quebraria ao acrescentar o quarto.
        $shortnames = array_map(
            fn($p) => $p->get('shortname'),
            plan::get_public_plans()
        );

        $this->assertContains('vitrinepublico', $shortnames);
        $this->assertNotContains('vitrineprivado', $shortnames);
        $this->assertNotContains('vitrinearquivado', $shortnames);
    }
}
