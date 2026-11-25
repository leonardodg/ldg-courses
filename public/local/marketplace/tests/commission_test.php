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
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Hierarquia da comissao: curso, empresa, site.
 *
 * Cada asserção aqui vale dinheiro real de alguem. Um erro nesta resolucao
 * cobra a mais do vendedor ou deixa a plataforma sem receber, e so apareceria
 * na conciliacao do mes seguinte.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\local_marketplace\api::class, 'resolve_commission_percent')]
#[CoversMethod(\local_marketplace\api::class, 'commission_for')]
#[CoversMethod(\local_marketplace\api::class, 'resolve_commission')]
#[CoversClass(\local_marketplace\commission::class)]
#[CoversMethod(\local_marketplace\api::class, 'default_commission_percent')]
final class commission_test extends \advanced_testcase {
    /** @var company */
    protected $company;

    /** @var \stdClass */
    protected $course;

    /**
     * Empresa e curso de teste, com o padrao do site em 25.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('defaultfeepercent', 25, 'local_marketplace');

        $owner = $this->getDataGenerator()->create_user();
        $this->company = api::create_company((object) [
            'name' => 'Empresa Teste',
            'shortname' => 'com' . random_int(1000, 9999),
        ], (int) $owner->id);

        $this->course = $this->getDataGenerator()->create_course([
            'category' => $this->company->get('categoryid'),
        ]);
    }

    /**
     * Cria uma oferta da empresa.
     *
     * @param string $type
     * @param int[] $courseids
     * @return offer
     */
    protected function make_offer(string $type, array $courseids): offer {
        $o = new offer();
        $o->set('companyid', (int) $this->company->get('id'));
        $o->set('name', 'Oferta');
        $o->set('offertype', $type);
        $o->set('price', 100.0);
        $o->set('currency', 'BRL');
        $o->set('accessmode', offer::ACCESS_LIFETIME);
        $o->set('status', offer::STATUS_PUBLISHED);
        $o->create();
        foreach ($courseids as $cid) {
            $o->add_course($cid);
        }
        return $o;
    }

    /**
     * Grava uma politica para o curso.
     *
     * @param float $pct
     * @param string|null $base Base de calculo, ou null para herdar a do site.
     * @return void
     */
    protected function set_course_policy(float $pct, ?string $base = null): void {
        $p = new course_policy();
        $p->set('courseid', (int) $this->course->id);
        $p->set('companyid', (int) $this->company->get('id'));
        $p->set('hostingtype', course_policy::HOSTING_EXTERNAL);
        $p->set('commissionpct', $pct);
        $p->set('commissionbase', $base);
        $p->create();
    }

    /**
     * Sem nada negociado, vale o padrao do site.
     *
     * @return void
     */
    public function test_falls_back_to_site_default(): void {
        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(25.0, api::resolve_commission_percent($offer));
    }

    /**
     * A comissao da empresa vence o padrao do site.
     *
     * @return void
     */
    public function test_company_overrides_site(): void {
        $this->company->set('commissionpct', 15.0);
        $this->company->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(15.0, api::resolve_commission_percent($offer));
    }

    /**
     * Zero e um valor, nao ausencia.
     *
     * Se zero fosse tratado como vazio, o parceiro isento voltaria a pagar o
     * padrao do site sem ninguem ter mudado nada. E o bug mais silencioso
     * possivel neste codigo.
     *
     * @return void
     */
    public function test_zero_percent_is_honoured(): void {
        $this->company->set('commissionpct', 0.0);
        $this->company->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(0.0, api::resolve_commission_percent($offer));
    }

    /**
     * A politica do curso vence a da empresa em oferta de curso unico.
     *
     * @return void
     */
    public function test_course_policy_wins_for_single_course(): void {
        $this->company->set('commissionpct', 15.0);
        $this->company->update();
        $this->set_course_policy(5.0);

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(5.0, api::resolve_commission_percent($offer));
    }

    /**
     * A politica do curso leva junto a BASE que ela negociou.
     *
     * Este teste existe por um defeito de banco, e nao de logica. O passo de
     * upgrade 2026090110 acrescentou `commissionbase` a uma tabela com nome
     * errado, entao em site ATUALIZADO a coluna nunca existiu - e o
     * `insert_record` do Moodle descarta em silencio campo que a tabela nao tem.
     * A politica gravava sem base, a leitura devolvia nulo, e nulo significa
     * "herda a do site".
     *
     * O resultado era dinheiro: base liquida negociada, comissao cobrada sobre
     * o BRUTO. Em R$ 100 a 25%, R$ 25,00 no lugar de R$ 24,38 - sempre contra o
     * vendedor, e sem nada na tela indicando o problema.
     *
     * Nenhum teste pegava porque o PHPUnit instala do zero, e a instalacao nova
     * sempre teve a coluna. O que faltava era exercitar a base pela politica,
     * que e o degrau que ninguem cobria.
     *
     * @return void
     */
    public function test_course_policy_carries_its_own_base(): void {
        set_config('commissionbase', commission::BASE_GROSS, 'local_marketplace');
        $this->set_course_policy(5.0, commission::BASE_NET);

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);
        $resolved = api::resolve_commission($offer);

        $this->assertEquals(5.0, $resolved->percent);
        $this->assertSame(commission::BASE_NET, $resolved->base);
        $this->assertSame(commission::SOURCE_POLICY, $resolved->source);
    }

    /**
     * Politica sem base declarada herda a do site, e nao inventa uma.
     *
     * Nulo na coluna e "este contrato so define a taxa". E diferente de
     * escolher bruto, e a diferenca precisa sobreviver a ida ao banco.
     *
     * @return void
     */
    public function test_course_policy_without_base_inherits_the_site(): void {
        set_config('commissionbase', commission::BASE_NET, 'local_marketplace');
        $this->set_course_policy(5.0);

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);
        $resolved = api::resolve_commission($offer);

        $this->assertSame(commission::BASE_NET, $resolved->base);
        $this->assertSame(commission::SOURCE_POLICY, $resolved->source);
    }

    /**
     * Em combo, a politica de curso NAO se aplica.
     *
     * Tres cursos com percentuais diferentes nao produzem um percentual do
     * pacote. Vale o que a empresa negociou.
     *
     * @return void
     */
    public function test_course_policy_ignored_in_bundle(): void {
        $this->company->set('commissionpct', 15.0);
        $this->company->update();
        $this->set_course_policy(5.0);

        $second = $this->getDataGenerator()->create_course([
            'category' => $this->company->get('categoryid'),
        ]);
        $offer = $this->make_offer(offer::TYPE_BUNDLE, [(int) $this->course->id, (int) $second->id]);

        $this->assertEquals(15.0, api::resolve_commission_percent($offer));
    }

    /**
     * Valores fora da faixa sao contidos em 0 e 100.
     *
     * Um percentual acima de 100 produziria marketplace_fee maior que o preco,
     * e o Mercado Pago recusaria a preferencia com o aluno ja no checkout.
     *
     * @return void
     */
    public function test_out_of_range_is_clamped(): void {
        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->set_course_policy(150.0);
        $this->assertEquals(100.0, api::resolve_commission_percent($offer));
    }

    /**
     * Um 0% configurado no site continua sendo 0%.
     *
     * Ate a versao anterior o padrao vinha de um `?: 25`, que trata zero como
     * ausencia. Uma plataforma que decidisse nao cobrar comissao nenhuma
     * passaria a cobrar 25% sem ninguem ter mudado nada - e so descobriria no
     * extrato do vendedor.
     *
     * @return void
     */
    public function test_site_default_zero_is_honoured(): void {
        set_config('defaultfeepercent', 0, 'local_marketplace');

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(0.0, api::resolve_commission_percent($offer));
    }

    /**
     * Site sem a configuracao cai no padrao de fabrica.
     *
     * @return void
     */
    public function test_site_default_unset_falls_back_to_factory(): void {
        unset_config('defaultfeepercent', 'local_marketplace');

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(25.0, api::resolve_commission_percent($offer));
    }

    /**
     * O ponto de entrada dos gateways devolve o mesmo que a resolucao interna.
     *
     * @return void
     */
    public function test_commission_for_matches_resolver(): void {
        $this->company->set('commissionpct', 15.0);
        $this->company->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(
            api::resolve_commission_percent($offer),
            api::commission_for('local_marketplace', (int) $offer->get('id'))
        );
    }

    /**
     * Item que nao e do marketplace nao herda a comissao de uma oferta.
     *
     * O itemid de outro componente - uma taxa de matricula do enrol_fee, por
     * exemplo - pode coincidir com o id de uma oferta. Sem o guarda de
     * componente, aquele pagamento sairia cobrando a comissao negociada de uma
     * empresa que nao tem nada a ver com ele.
     *
     * @return void
     */
    public function test_commission_for_ignores_other_components(): void {
        $this->company->set('commissionpct', 15.0);
        $this->company->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(25.0, api::commission_for('enrol_fee', (int) $offer->get('id')));
    }

    /**
     * Cria um plano e vincula a empresa a ele.
     *
     * @param float $pct Comissao do plano.
     * @param string $status Situacao do plano.
     * @return plan
     */
    protected function assign_plan(float $pct): plan {
        $plan = new plan(0, (object) [
            'shortname' => 'p' . random_int(1000, 9999),
            'name' => 'Plano',
            'commissionpct' => $pct,
        ]);
        $plan->create();

        $this->company->set('planid', (int) $plan->get('id'));
        $this->company->update();

        return $plan;
    }

    /**
     * Sem comissao negociada, vale a do plano.
     *
     * @return void
     */
    public function test_plan_overrides_site(): void {
        $this->assign_plan(9.9);

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(9.9, api::resolve_commission_percent($offer));
    }

    /**
     * A comissao negociada com a empresa vence a do plano.
     *
     * E a garantia de que ligar planos nao mexe em contrato ja fechado: quem
     * tinha valor negociado continua com ele, mesmo entrando num plano.
     *
     * @return void
     */
    public function test_company_overrides_plan(): void {
        $this->assign_plan(9.9);
        $this->company->set('commissionpct', 15.0);
        $this->company->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(15.0, api::resolve_commission_percent($offer));
    }

    /**
     * Plano arquivado nao governa comissao: cai no padrao do site.
     *
     * Arquivar existe para tirar um plano de circulacao. Se ele continuasse
     * valendo, arquivar seria so um rotulo.
     *
     * @return void
     */
    public function test_archived_plan_falls_back_to_site(): void {
        // Arquivar DEPOIS de vincular, que e como acontece na pratica: o
        // administrador tira de circulacao um plano que empresas ja usam.
        // Vincular um plano ja arquivado e recusado pelo validate_planid.
        $plan = $this->assign_plan(9.9);
        $plan->set('status', plan::STATUS_ARCHIVED);
        $plan->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(25.0, api::resolve_commission_percent($offer));
    }

    /**
     * A politica do curso continua vencendo o plano.
     *
     * @return void
     */
    public function test_course_policy_still_wins_over_plan(): void {
        $this->assign_plan(9.9);
        $this->set_course_policy(5.0);

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(5.0, api::resolve_commission_percent($offer));
    }

    /**
     * Empresa sem plano se comporta exatamente como antes de planos existirem.
     *
     * E o teste que prova que a migracao foi neutra.
     *
     * @return void
     */
    public function test_no_plan_behaves_as_before(): void {
        $this->assertNull($this->company->get('planid'));

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);

        $this->assertEquals(25.0, api::resolve_commission_percent($offer));
    }

    /**
     * A base sai do MESMO degrau que deu o percentual.
     *
     * E a regra central deste desenho. Resolver base e percentual em cadeias
     * separadas produziria "taxa do plano com base do site" - uma combinacao
     * que nenhum contrato tem, e que o parceiro nao conseguiria conferir.
     *
     * @return void
     */
    public function test_a_base_vem_do_degrau_que_deu_a_taxa(): void {
        set_config('commissionbase', commission::BASE_GROSS, 'local_marketplace');

        // O plano diz liquido; a empresa nao negociou nada, entao o plano vence.
        $plan = plan::get_record_by_shortname('start_free');
        $plan->set('commissionpct', 9.9);
        $plan->set('commissionbase', commission::BASE_NET);
        $plan->update();

        $this->company->set('planid', (int) $plan->get('id'));
        $this->company->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);
        $terms = api::resolve_commission($offer);

        $this->assertEquals(9.9, $terms->percent);
        $this->assertSame(commission::BASE_NET, $terms->base);
        $this->assertSame(commission::SOURCE_PLAN, $terms->source);
    }

    /**
     * Negociar com a empresa troca taxa E base, e o plano deixa de valer.
     *
     * @return void
     */
    public function test_empresa_traz_a_propria_base(): void {
        set_config('commissionbase', commission::BASE_NET, 'local_marketplace');

        $plan = plan::get_record_by_shortname('start_free');
        $plan->set('commissionbase', commission::BASE_NET);
        $plan->update();

        $this->company->set('planid', (int) $plan->get('id'));
        $this->company->set('commissionpct', 15.0);
        $this->company->set('commissionbase', commission::BASE_GROSS);
        $this->company->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);
        $terms = api::resolve_commission($offer);

        $this->assertEquals(15.0, $terms->percent);
        $this->assertSame(commission::BASE_GROSS, $terms->base);
        $this->assertSame(commission::SOURCE_COMPANY, $terms->source);
    }

    /**
     * Degrau que nao declara base herda a do site.
     *
     * O NULO e o que permite o contrato acompanhar uma mudanca de politica da
     * plataforma. Gravar a base padrao em toda linha congelaria isso.
     *
     * @return void
     */
    public function test_base_nula_herda_a_do_site(): void {
        set_config('commissionbase', commission::BASE_NET, 'local_marketplace');

        $this->company->set('commissionpct', 15.0);
        $this->company->set('commissionbase', null);
        $this->company->update();

        $offer = $this->make_offer(offer::TYPE_SINGLE, [(int) $this->course->id]);
        $terms = api::resolve_commission($offer);

        $this->assertEquals(15.0, $terms->percent);
        $this->assertSame(commission::BASE_NET, $terms->base);
        // A taxa continua sendo a negociada, mesmo com a base herdada.
        $this->assertSame(commission::SOURCE_COMPANY, $terms->source);
    }

    /**
     * Base invalida no banco nao derruba a compra.
     *
     * Isto e resolvido no meio de um checkout. Lancar excecao porque uma coluna
     * ficou com valor inesperado deixaria o aluno com o carrinho na mao.
     *
     * @return void
     */
    public function test_base_invalida_cai_no_padrao(): void {
        set_config('commissionbase', commission::BASE_GROSS, 'local_marketplace');

        $this->assertSame(commission::BASE_GROSS, commission::normalise_base('qualquer-coisa'));
        $this->assertSame(commission::BASE_GROSS, commission::normalise_base(null));
        $this->assertSame(commission::BASE_GROSS, commission::normalise_base(''));
    }

    /**
     * O percentual continua contido entre 0 e 100 no objeto de valor.
     *
     * @return void
     */
    public function test_objeto_de_valor_contem_o_percentual(): void {
        $this->assertEquals(100.0, (new commission(150.0, null, commission::SOURCE_SITE))->percent);
        $this->assertEquals(0.0, (new commission(-5.0, null, commission::SOURCE_SITE))->percent);
    }

    /**
     * A comissao em moeda sai da base que quem chama escolheu.
     *
     * @return void
     */
    public function test_amount_on(): void {
        $terms = new commission(9.9, commission::BASE_GROSS, commission::SOURCE_PLAN);

        $this->assertEqualsWithDelta(9.90, $terms->amount_on(100.00), 0.001);
        $this->assertEqualsWithDelta(9.65, $terms->amount_on(97.52), 0.001);
    }
}
