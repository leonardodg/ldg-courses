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

use PHPUnit\Framework\Attributes\CoversNothing;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../db/install.php');

/**
 * Migracao dos planos comerciais antigos (Starter/Pro/Scale) para o
 * desenho novo (Start/PRO), desenhado em 17/09/2026.
 *
 * O SEED de uma instalacao nova ja nasce so com Start/PRO -
 * local_marketplace_archive_legacy_plans() so roda no UPGRADE de quem ja
 * tinha os tres antigos.
 *
 * MEDIDO AO VIVO: a primeira versao rodava o seed ANTES de renomear o
 * shortname 'pro' do plano antigo, e o seed via 'pro' "ja existente" e
 * pulava a criacao do novo em silencio - nenhum erro, so o plano nunca
 * nascia. Este teste existe para essa ordem nunca mais inverter sem que
 * algo quebre visivelmente.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class plan_reconciliation_test extends \advanced_testcase {
    /**
     * O seed padrao (o de uma instalacao nova) cria os quatro planos novos,
     * com a comissao e o modelo de hospedagem certos.
     *
     * @return void
     */
    public function test_seed_cria_os_quatro_planos_novos(): void {
        $this->resetAfterTest();

        // O install.php da propria instalacao de teste ja rodou o seed - a
        // asserção confere o que ficou, sem rodar de novo (o seed e so
        // INSERT, rodar duas vezes e no-op por causa da guarda de shortname).
        $startfree = plan::get_record_by_shortname('start_free');
        $start50 = plan::get_record_by_shortname('start_50');
        $start100 = plan::get_record_by_shortname('start_100');
        $pro = plan::get_record_by_shortname('pro');

        $this->assertNotFalse($startfree);
        $this->assertNotFalse($start50);
        $this->assertNotFalse($start100);
        $this->assertNotFalse($pro);

        $this->assertSame(10.0, (float) $startfree->get('commissionpct'));
        $this->assertSame(10.0, (float) $start50->get('commissionpct'));
        $this->assertSame(10.0, (float) $start100->get('commissionpct'));
        $this->assertSame(5.0, (float) $pro->get('commissionpct'));

        $this->assertSame(plan::HOSTING_NATIVE, $startfree->get('hostingmodel'));
        $this->assertSame(plan::HOSTING_BYOS, $pro->get('hostingmodel'));

        $this->assertSame('720p', $startfree->max_resolution_for(1.0));
        $this->assertSame('1080p', $start50->max_resolution_for(999999.0));
        $this->assertSame('4k', $start100->max_resolution_for(0.0));
    }

    /**
     * Uma instalacao que ainda tem os tres planos antigos: eles saem
     * arquivados, com o shortname renomeado, e os quatro novos passam a
     * existir - inclusive o 'pro' novo, que e o caso que quebrou em
     * silencio na primeira versao.
     *
     * @return void
     */
    public function test_arquiva_os_antigos_e_semeia_os_novos_por_cima(): void {
        $this->resetAfterTest();

        // O install.php da instalacao de teste ja criou start_free/50/100/
        // pro - apaga para simular uma instalacao ANTIGA, que so tinha os
        // tres de antes.
        foreach (['start_free', 'start_50', 'start_100', 'pro'] as $shortname) {
            $plan = plan::get_record_by_shortname($shortname);
            if ($plan) {
                $plan->delete();
            }
        }

        $antigo = new plan(0, (object) [
            'shortname' => 'pro',
            'name' => 'Pro (antigo)',
            'monthlyfee' => 97,
            'commissionpct' => 3.9,
            'hostingmodel' => plan::HOSTING_BYOS,
        ]);
        $antigo->create();

        local_marketplace_archive_legacy_plans();

        $renomeado = plan::get_record_by_shortname('pro_legado');
        $this->assertNotFalse($renomeado, 'o antigo devia ter sido renomeado');
        $this->assertSame(plan::STATUS_ARCHIVED, $renomeado->get('status'));
        $this->assertSame((int) $antigo->get('id'), (int) $renomeado->get('id'), 'e a MESMA linha, so renomeada');

        // O caso que quebrou ao vivo: o 'pro' NOVO precisa existir, com a
        // comissao nova (5%) - nao pode ter sido pulado por achar o
        // shortname "ja ocupado" pelo antigo.
        $novo = plan::get_record_by_shortname('pro');
        $this->assertNotFalse($novo, 'o pro novo nao pode ter sido pulado pelo seed');
        $this->assertSame(5.0, (float) $novo->get('commissionpct'));
        $this->assertNotSame((int) $antigo->get('id'), (int) $novo->get('id'), 'tem que ser uma linha NOVA');

        $this->assertNotFalse(plan::get_record_by_shortname('start_free'));
        $this->assertNotFalse(plan::get_record_by_shortname('start_50'));
        $this->assertNotFalse(plan::get_record_by_shortname('start_100'));
    }

    /**
     * Uma empresa que ainda apontava para o plano antigo migra para o
     * equivalente novo, pelo id - nao fica "contratando" um plano
     * arquivado, fora de venda.
     *
     * @return void
     */
    public function test_empresa_no_plano_antigo_migra_para_o_novo(): void {
        $this->resetAfterTest();

        foreach (['start_free', 'start_50', 'start_100', 'pro'] as $shortname) {
            $plan = plan::get_record_by_shortname($shortname);
            if ($plan) {
                $plan->delete();
            }
        }

        $starterantigo = new plan(0, (object) [
            'shortname' => 'starter',
            'name' => 'Starter (antigo)',
            'monthlyfee' => 0,
            'commissionpct' => 9.9,
            'hostingmodel' => plan::HOSTING_NATIVE,
        ]);
        $starterantigo->create();

        $company = new company(0, (object) [
            'name' => 'Empresa no Starter antigo',
            'shortname' => 'starterantigo' . random_int(100000, 999999),
            'planid' => (int) $starterantigo->get('id'),
        ]);
        $company->create();

        local_marketplace_archive_legacy_plans();

        $startfree = plan::get_record_by_shortname('start_free');
        $this->assertNotFalse($startfree);

        $company->read();
        $this->assertSame(
            (int) $startfree->get('id'),
            (int) $company->get('planid'),
            'a empresa nao pode continuar apontando para um plano arquivado'
        );
    }
}
