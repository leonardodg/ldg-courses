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

namespace block_marketplace;

use local_marketplace\api;
use local_marketplace\entitlement;
use local_marketplace\offer;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/marketplace/block_marketplace.php');

/**
 * O que o bloco de assinaturas mostra, e o que ele decide não mostrar.
 *
 * O bloco é a única tela onde o aluno vê que uma assinatura vence. Deixar de
 * mostrar um vencimento é pior do que mostrar informação demais: ele descobre
 * quando perde o acesso.
 *
 * @package    block_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_marketplace::class)]
final class content_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $user;

    /** @var \local_marketplace\company */
    protected $company;

    /**
     * Empresa e aluno, com o aluno autenticado — o bloco lê o $USER global.
     *
     * @return void
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->company = api::create_company((object) [
            'name' => 'Empresa Teste',
            'shortname' => 'teste' . random_int(1000, 9999),
            'cnpj' => null,
            'themename' => null,
            'hostname' => null,
        ], 2);

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        $PAGE->set_url('/my/index.php');
    }

    /**
     * Cria uma oferta da empresa de teste.
     *
     * @param string $accessmode
     * @return offer
     */
    protected function make_offer(string $accessmode = offer::ACCESS_DAYS): offer {
        $o = new offer();
        $o->set('companyid', (int) $this->company->get('id'));
        $o->set('name', 'Curso de Xadrez');
        $o->set('offertype', offer::TYPE_SINGLE);
        $o->set('price', 50.0);
        $o->set('currency', 'BRL');
        $o->set('accessmode', $accessmode);
        $o->set('accessdays', $accessmode === offer::ACCESS_LIFETIME ? 0 : 30);
        $o->set('status', offer::STATUS_PUBLISHED);
        $o->create();

        return $o;
    }

    /**
     * Concede o direito ao aluno de teste.
     *
     * @param offer $offer
     * @param int $timeend 0 = vitalício.
     * @param int $norenew 1 = cancelado, sem renovação.
     * @return entitlement
     */
    protected function grant(offer $offer, int $timeend, int $norenew = 0): entitlement {
        $e = new entitlement();
        $e->set('userid', (int) $this->user->id);
        $e->set('offerid', (int) $offer->get('id'));
        $e->set('companyid', (int) $this->company->get('id'));
        $e->set('timestart', time() - DAYSECS);
        $e->set('timeend', $timeend);
        $e->set('status', entitlement::STATUS_ACTIVE);
        $e->set('norenew', $norenew);
        $e->create();

        return $e;
    }

    /**
     * Renderiza o bloco.
     *
     * No uso real o block_manager atribui $block->page antes de chamar
     * get_content() - sem isto $this->page fica null dentro do bloco.
     *
     * @return \stdClass
     */
    protected function content(): \stdClass {
        global $PAGE;

        $block = new \block_marketplace();
        $block->init();
        $block->page = $PAGE;

        return $block->get_content();
    }

    /**
     * Sem direito nenhum, o bloco fica vazio.
     *
     * @return void
     */
    public function test_sem_assinatura_fica_vazio(): void {
        $this->assertSame('', $this->content()->text);
    }

    /**
     * Visitante não vê nada.
     *
     * @return void
     */
    public function test_visitante_nao_ve_nada(): void {
        $offer = $this->make_offer();
        $this->grant($offer, time() + (10 * DAYSECS));

        $this->setGuestUser();

        $this->assertSame('', $this->content()->text);
    }

    /**
     * Assinatura com vencimento aparece, com o nome da oferta e da empresa.
     *
     * @return void
     */
    public function test_assinatura_com_vencimento_aparece(): void {
        $offer = $this->make_offer();
        $this->grant($offer, time() + (10 * DAYSECS));

        $content = $this->content();

        $this->assertStringContainsString('Curso de Xadrez', $content->text);
        $this->assertStringContainsString('Empresa Teste', $content->text);
        $this->assertNotSame('', $content->footer, 'o rodape leva ao historico');
    }

    /**
     * Vitalícia não cancelada NÃO aparece.
     *
     * É decisão de desenho, e não esquecimento: ela não pede nada do aluno, e
     * ocuparia espaço no bloco para dizer "está tudo bem". O bloco existe para
     * o que exige ação.
     *
     * @return void
     */
    public function test_vitalicia_tranquila_nao_ocupa_espaco(): void {
        $offer = $this->make_offer(offer::ACCESS_LIFETIME);
        $this->grant($offer, 0);

        $this->assertSame('', $this->content()->text);
    }

    /**
     * Vitalícia CANCELADA aparece.
     *
     * O aluno cancelou a renovação: mesmo sem data, ele precisa ver que a
     * assinatura não continua.
     *
     * @return void
     */
    public function test_vitalicia_cancelada_aparece(): void {
        $offer = $this->make_offer(offer::ACCESS_LIFETIME);
        $this->grant($offer, 0, 1);

        $this->assertStringContainsString('Curso de Xadrez', $this->content()->text);
    }

    /**
     * O direito de OUTRO aluno não vaza para este bloco.
     *
     * @return void
     */
    public function test_nao_mostra_assinatura_de_outro_aluno(): void {
        $outro = $this->getDataGenerator()->create_user();
        $offer = $this->make_offer();

        $e = new entitlement();
        $e->set('userid', (int) $outro->id);
        $e->set('offerid', (int) $offer->get('id'));
        $e->set('companyid', (int) $this->company->get('id'));
        $e->set('timestart', time() - DAYSECS);
        $e->set('timeend', time() + (10 * DAYSECS));
        $e->set('status', entitlement::STATUS_ACTIVE);
        $e->create();

        $this->assertSame('', $this->content()->text);
    }

    /**
     * Direito vencido some do bloco.
     *
     * @return void
     */
    public function test_vencido_some(): void {
        $offer = $this->make_offer();
        $this->grant($offer, time() - 1);

        $this->assertSame('', $this->content()->text);
    }

    /**
     * O bloco vale no Dashboard, na home e no curso.
     *
     * @return void
     */
    public function test_onde_o_bloco_pode_entrar(): void {
        $block = new \block_marketplace();

        $this->assertSame(
            ['my' => true, 'site-index' => true, 'course-view' => true],
            $block->applicable_formats()
        );
        $this->assertFalse($block->instance_allow_multiple());
    }

    /**
     * Dono de empresa incompleta ve o checklist, nao o widget de assinatura.
     *
     * @return void
     */
    public function test_dono_de_empresa_incompleta_ve_checklist(): void {
        // A propria empresa de teste ja nasce sem plano e sem conta - api::create_company()
        // no setUp() nao define planid nem conta de pagamento.
        $this->setUser($this->company_owner());

        $content = $this->content();

        $this->assertNotSame('', $content->text);
        $this->assertStringContainsString('0%', $content->text);
        $this->assertStringNotContainsString('Curso de Xadrez', $content->text);
    }

    /**
     * Empresa com checklist completo nao mostra nada (sem gateway/plano pendente
     * e sem assinatura de aluno) - mesmo comportamento vazio de sempre.
     *
     * @return void
     */
    public function test_dono_de_empresa_completa_nao_ve_checklist(): void {
        // A empresa de teste ja nasce com uma conta de pagamento no BR -
        // api::create_company() no setUp() chama create_payment_account().
        // So falta habilitar um gateway nela: criar uma SEGUNDA conta e
        // vincula-la de novo violaria o indice unico (companyid, country) de
        // local_marketplace_account (t_locamarkacco_comcou_uix).
        // O is_available() da conta exige o gateway habilitado no SITE, nao
        // so na conta (armadilha ja documentada no CLAUDE.md) - sem isto
        // get_enabled_plugins() filtra o mercadopago fora e a etapa nunca
        // fecha.
        $this->complete_test_company();

        $this->setUser($this->company_owner());

        $this->assertSame('', $this->content()->text);
    }

    /**
     * Dono de empresa JA COMPLETA, que tambem e aluno com assinatura ativa em
     * OUTRA empresa/oferta, continua vendo o proprio vencimento (item A).
     *
     * Antes da correcao, content_for_owner() devolvia $this->content vazio
     * assim que a empresa do dono estava completa, e o widget de aluno nunca
     * aparecia para quem e dono/vendedor de alguma empresa.
     *
     * @return void
     */
    public function test_dono_de_empresa_completa_ve_propria_assinatura_de_aluno(): void {
        $this->complete_test_company();

        // Dono de OUTRA empresa, e nao o mesmo usuario 2 do teste: senao o
        // dono da "Empresa Teste" (completa) passaria a gerenciar tambem esta
        // segunda empresa incompleta, e o bloco mostraria o checklist dela em
        // vez da propria assinatura - correto pelo novo desenho, mas nao e o
        // que este teste quer exercitar.
        $outroowner = $this->getDataGenerator()->create_user();
        $outracompany = api::create_company((object) [
            'name' => 'Outra Empresa',
            'shortname' => 'outra' . random_int(1000, 9999),
            'cnpj' => null,
            'themename' => null,
            'hostname' => null,
        ], (int) $outroowner->id);

        $offer = new offer();
        $offer->set('companyid', (int) $outracompany->get('id'));
        $offer->set('name', 'Curso de Go');
        $offer->set('offertype', offer::TYPE_SINGLE);
        $offer->set('price', 50.0);
        $offer->set('currency', 'BRL');
        $offer->set('accessmode', offer::ACCESS_DAYS);
        $offer->set('accessdays', 30);
        $offer->set('status', offer::STATUS_PUBLISHED);
        $offer->create();

        $owner = $this->company_owner();

        $ent = new entitlement();
        $ent->set('userid', (int) $owner->id);
        $ent->set('offerid', (int) $offer->get('id'));
        $ent->set('companyid', (int) $outracompany->get('id'));
        $ent->set('timestart', time() - DAYSECS);
        $ent->set('timeend', time() + (10 * DAYSECS));
        $ent->set('status', entitlement::STATUS_ACTIVE);
        $ent->set('norenew', 0);
        $ent->create();

        $this->setUser($owner);

        $content = $this->content();

        $this->assertStringContainsString('Curso de Go', $content->text);
        $this->assertStringNotContainsString('Ativação da conta', $content->text);
    }

    /**
     * Dono de DUAS empresas, uma completa e outra incompleta, ve o checklist
     * da incompleta - mesmo a completa vindo primeiro em ordem alfabetica.
     *
     * Antes da correcao, get_content() usava reset(company::get_by_member())
     * e so olhava a PRIMEIRA empresa em ordem alfabetica de nome: "Empresa
     * Teste" (completa) vem antes de "Zeta Corp" (incompleta), entao o
     * checklist da Zeta Corp nunca aparecia para o unico usuario que podia
     * completa-lo - o bloco simplesmente ficava vazio ou mostrava a
     * assinatura de aluno.
     *
     * @return void
     */
    public function test_dono_de_duas_empresas_ve_checklist_da_incompleta(): void {
        $this->complete_test_company();

        api::create_company((object) [
            'name' => 'Zeta Corp',
            'shortname' => 'zeta' . random_int(1000, 9999),
            'cnpj' => null,
            'themename' => null,
            'hostname' => null,
        ], 2);

        $this->setUser($this->company_owner());

        $content = $this->content();

        $this->assertStringContainsString('0%', $content->text);
    }

    /**
     * Vendedor (sem managecompany) de empresa incompleta nao ve o checklist -
     * a pagina que ele levaria a exigir a capability que so o dono tem
     * (item B). Sem assinatura de aluno, o bloco fica vazio, como sempre.
     *
     * @return void
     */
    public function test_vendedor_sem_managecompany_nao_ve_checklist(): void {
        $seller = $this->getDataGenerator()->create_user();
        api::add_member($this->company, (int) $seller->id);

        $this->setUser($seller);

        $this->assertSame('', $this->content()->text);
    }

    /**
     * No contexto de curso, o checklist de ativacao nao aparece mesmo para o
     * dono de empresa incompleta (item C) - o dono cai no comportamento de
     * aluno, igual a quem nao tem empresa nenhuma.
     *
     * @return void
     */
    public function test_checklist_nao_aparece_no_contexto_de_curso(): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        // Set_url() so define o pagetype na primeira chamada (initialise_default_pagetype
        // so roda quando _pagetype ainda e null) - o setUp() ja chamou set_url() para
        // 'my-index', entao aqui e preciso o set_pagetype() explicito do core.
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_pagetype('course-view');

        $this->setUser($this->company_owner());

        $this->assertSame('', $this->content()->text);
    }

    /**
     * Completa a empresa de teste: cria plano e habilita um gateway na conta
     * ja criada por api::create_company() no setUp().
     *
     * @return void
     */
    protected function complete_test_company(): void {
        $plan = new \local_marketplace\plan(0, (object) [
            'shortname' => 'contenttest_plan2_' . random_int(1000, 9999),
            'name' => 'Plano de teste',
            'monthlyfee' => 0,
            'commissionpct' => 10,
        ]);
        $plan->create();
        $this->company->set('planid', (int) $plan->get('id'));
        $this->company->update();

        $account = $this->company->get_payment_account('BR');
        $gateway = new \core_payment\account_gateway(0, (object) [
            'accountid' => $account->get('id'),
            'gateway' => 'mercadopago',
            'enabled' => true,
        ]);
        $gateway->create();
        \core\plugininfo\paygw::enable_plugin('mercadopago', 1);
    }

    /**
     * O usuario dono da empresa de teste - api::create_company() no setUp()
     * usa ownerid=2, que e sempre o admin num Moodle recem-instalado/testado.
     *
     * @return \stdClass
     */
    protected function company_owner(): \stdClass {
        global $DB;

        return $DB->get_record('user', ['id' => 2]);
    }
}
