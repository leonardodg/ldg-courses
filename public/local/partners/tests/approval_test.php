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

namespace local_partners;

use PHPUnit\Framework\Attributes\CoversMethod;
use local_marketplace\api as marketplace;
use local_marketplace\company;
use local_marketplace\offer;
use local_marketplace\plan;

/**
 * Aprovacao e recusa de candidaturas.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\local_partners\api::class, 'approve')]
#[CoversMethod(\local_partners\api::class, 'reject')]
#[CoversMethod(\local_partners\api::class, 'suggest_shortname')]
final class approval_test extends \advanced_testcase {
    /** @var \stdClass Usuario que sera dono da empresa. */
    private $owner;

    /**
     * Ambiente comum.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->owner = $this->getDataGenerator()->create_user();
    }

    /**
     * Cria uma candidatura na fila.
     *
     * @param array $overrides
     * @return application
     */
    private function make_application(array $overrides = []): application {
        // Envia como VISITANTE, que e o caso que estes testes exercitam: se
        // enviasse autenticado, a candidatura ja nasceria com dono e nenhum dos
        // caminhos de criacao de conta seria exercido.
        //
        // A confirmacao de e-mail fica desligada aqui de proposito - o que se
        // testa e a aprovacao, e nao o caminho do link, que tem teste proprio.
        set_config('requireemailconfirmation', 0, 'local_partners');
        $this->setUser(null);

        $application = api::submit((object) array_merge([
            'companyname' => 'Editora Teste',
            'contactname' => 'Maria Silva',
            'contactemail' => 'maria' . random_int(100, 999) . '@exemplo.com',
        ], $overrides));

        // Aprovar exige administrador.
        $this->setAdminUser();

        return $application;
    }

    /**
     * Aprovar cria a empresa, a categoria e marca a candidatura.
     *
     * @return void
     */
    public function test_aprovar_provisiona_a_empresa(): void {
        $application = $this->make_application(['cnpj' => '11222333000181']);
        $plan = plan::get_record_by_shortname('start_free');

        $company = api::approve($application, (object) [
            'shortname' => 'editorateste',
            'ownerid' => (int) $this->owner->id,
            'planid' => (int) $plan->get('id'),
            'reviewnote' => 'Tudo certo.',
        ]);

        $this->assertSame('Editora Teste', $company->get('name'));
        $this->assertSame('editorateste', $company->get('shortname'));
        // O CNPJ vem da candidatura, e nao e redigitado na aprovacao.
        $this->assertSame('11222333000181', $company->get('cnpj'));
        // A categoria e o que torna a empresa utilizavel: sem ela nao ha onde
        // publicar curso, nem contexto para o papel de vendedor.
        $this->assertNotEmpty($company->get('categoryid'));

        $application = application::get_record(['id' => $application->get('id')]);
        $this->assertSame(application::STATUS_APPROVED, $application->get('status'));
        $this->assertEquals($company->get('id'), $application->get('companyid'));
        $this->assertNotEmpty($application->get('timereviewed'));
    }

    /**
     * A empresa nasce sem comissao negociada, para o plano governar.
     *
     * Copiar o percentual do plano congelaria o numero: a empresa pararia de
     * acompanhar uma mudanca de plano e ninguem entenderia por que.
     *
     * @return void
     */
    public function test_empresa_nova_deixa_o_plano_governar(): void {
        $application = $this->make_application();
        $plan = plan::get_record_by_shortname('start_free');

        $company = api::approve($application, (object) [
            'shortname' => 'semcomissao',
            'ownerid' => (int) $this->owner->id,
            'planid' => (int) $plan->get('id'),
        ]);

        $this->assertNull($company->get('commissionpct'));

        // E a cadeia de comissao de fato resolve para o percentual do plano.
        $course = $this->getDataGenerator()->create_course(['category' => $company->get('categoryid')]);
        $offer = new offer();
        $offer->set('companyid', (int) $company->get('id'));
        $offer->set('name', 'Oferta');
        $offer->set('offertype', offer::TYPE_SINGLE);
        $offer->set('price', 100.0);
        $offer->set('currency', 'BRL');
        $offer->set('accessmode', offer::ACCESS_LIFETIME);
        $offer->set('status', offer::STATUS_PUBLISHED);
        $offer->create();
        $offer->add_course((int) $course->id);

        $this->assertEquals(
            (float) $plan->get('commissionpct'),
            marketplace::resolve_commission_percent($offer)
        );
    }

    /**
     * Aprovar duas vezes nao cria duas empresas.
     *
     * E o teste mais importante desta tela: um duplo clique ou um F5 nao pode
     * produzir duas categorias de curso, que sao objeto global do site.
     *
     * @return void
     */
    public function test_aprovar_duas_vezes_e_recusado(): void {
        $application = $this->make_application();

        $decision = (object) [
            'shortname' => 'primeira',
            'ownerid' => (int) $this->owner->id,
        ];

        api::approve($application, $decision);

        $antes = count(company::get_records());

        try {
            api::approve($application, (object) [
                'shortname' => 'segunda',
                'ownerid' => (int) $this->owner->id,
            ]);
            $this->fail('a segunda aprovacao deveria ter sido recusada');
        } catch (\moodle_exception $e) {
            $this->assertSame('erroralreadydecided', $e->errorcode);
        }

        $this->assertCount($antes, company::get_records());
    }

    /**
     * Recusar nao cria empresa e guarda a observacao.
     *
     * @return void
     */
    public function test_recusar_nao_cria_empresa(): void {
        $application = $this->make_application();
        $antes = count(company::get_records());

        api::reject($application, (object) ['reviewnote' => 'Falta o CNPJ.']);

        $application = application::get_record(['id' => $application->get('id')]);

        $this->assertSame(application::STATUS_REJECTED, $application->get('status'));
        $this->assertNull($application->get('companyid'));
        $this->assertSame('Falta o CNPJ.', $application->get('reviewnote'));
        $this->assertCount($antes, company::get_records());
    }

    /**
     * Recusada nao pode ser aprovada depois.
     *
     * Reenvio e linha nova, e nao reabertura desta - o historico de quem tentou
     * nao pode sumir.
     *
     * @return void
     */
    public function test_recusada_nao_volta_a_ser_decidivel(): void {
        $application = $this->make_application();

        api::reject($application, (object) []);

        $this->expectException(\moodle_exception::class);

        api::approve($application, (object) [
            'shortname' => 'depois',
            'ownerid' => (int) $this->owner->id,
        ]);
    }

    /**
     * A sugestao de atalho vira slug e desvia de colisao.
     *
     * @return void
     */
    public function test_sugestao_de_atalho(): void {
        $this->assertSame('editorasaopaulo', api::suggest_shortname('Editora São Paulo'));
        $this->assertSame('empresa', api::suggest_shortname('!!!'));

        $application = $this->make_application(['companyname' => 'Colisao']);
        api::approve($application, (object) [
            'shortname' => 'colisao',
            'ownerid' => (int) $this->owner->id,
        ]);

        // Com 'colisao' ocupado, a sugestao seguinte ganha sufixo em vez de
        // devolver algo que o formulario recusaria.
        $this->assertSame('colisao2', api::suggest_shortname('Colisao'));
    }

    /**
     * Sem dono escolhido, a conta do candidato e criada e convidada.
     *
     * Fecha o buraco do fluxo: quem se candidata pela landing normalmente NAO
     * tem conta no site, e sem conta nao ha dono - e sem dono nao ha empresa.
     *
     * @return void
     */
    public function test_aprovar_sem_dono_cria_a_conta(): void {
        global $DB;

        $application = $this->make_application(['contactemail' => 'novoparceiro@exemplo.com']);

        $this->assertFalse($DB->record_exists('user', ['email' => 'novoparceiro@exemplo.com']));

        $company = api::approve($application, (object) [
            'shortname' => 'novoparceiro',
            'ownerid' => null,
        ]);

        $novo = $DB->get_record('user', ['email' => 'novoparceiro@exemplo.com']);
        $this->assertNotFalse($novo);

        // A conta nasce com senha que ninguem conhece e um token de
        // redefinicao: o acesso e pelo link do convite, e nao por senha em
        // texto puro no e-mail.
        $this->assertTrue($DB->record_exists('user_password_resets', ['userid' => $novo->id]));

        // E ela e de fato a dona da empresa.
        $this->assertTrue($DB->record_exists('local_marketplace_member', [
            'companyid' => $company->get('id'),
            'userid' => $novo->id,
            'memberrole' => 'owner',
        ]));
    }

    /**
     * Existindo conta com o e-mail do contato, ela e reaproveitada.
     *
     * Criar uma segunda conta para a mesma pessoa seria pior do que nao criar
     * nenhuma: ela receberia o convite e nao entenderia por que tem dois
     * logins.
     *
     * @return void
     */
    public function test_conta_existente_e_reaproveitada(): void {
        global $DB;

        $existente = $this->getDataGenerator()->create_user(['email' => 'jaexiste@exemplo.com']);
        $application = $this->make_application(['contactemail' => 'jaexiste@exemplo.com']);

        $antes = $DB->count_records('user', ['deleted' => 0]);

        $company = api::approve($application, (object) [
            'shortname' => 'jaexiste',
            'ownerid' => null,
        ]);

        $this->assertEquals($antes, $DB->count_records('user', ['deleted' => 0]));
        $this->assertTrue($DB->record_exists('local_marketplace_member', [
            'companyid' => $company->get('id'),
            'userid' => $existente->id,
            'memberrole' => 'owner',
        ]));
    }

    /**
     * O dono escolhido na tela vence a busca por e-mail.
     *
     * A empresa pode pertencer a outra pessoa que nao a que preencheu o
     * formulario - um socio, um responsavel administrativo.
     *
     * @return void
     */
    public function test_dono_escolhido_vence(): void {
        global $DB;

        $this->getDataGenerator()->create_user(['email' => 'contato@exemplo.com']);
        $escolhido = $this->getDataGenerator()->create_user();
        $application = $this->make_application(['contactemail' => 'contato@exemplo.com']);

        $company = api::approve($application, (object) [
            'shortname' => 'escolhido',
            'ownerid' => (int) $escolhido->id,
        ]);

        $this->assertTrue($DB->record_exists('local_marketplace_member', [
            'companyid' => $company->get('id'),
            'userid' => $escolhido->id,
            'memberrole' => 'owner',
        ]));
    }

    /**
     * Aprovar com o dono em BRANCO funciona, e cria a conta.
     *
     * Regressao encontrada por behat: o autocomplete do moodleform manda string
     * vazia quando ninguem foi escolhido, e nao null. O `?? null` do chamador
     * nao pegava, '' chegava num parametro ?int e estourava TypeError -
     * quebrando justamente o caminho que a propria tela instrui a usar quando o
     * candidato ainda nao tem conta.
     *
     * @return void
     */
    public function test_aprovar_com_dono_vazio_como_o_formulario_manda(): void {
        global $DB;

        $application = $this->make_application(['contactemail' => 'semconta@exemplo.com']);
        $antes = $DB->count_records('user', ['deleted' => 0]);

        // Exatamente o que chega do formulario: string vazia, nao null.
        $company = api::approve($application, (object) [
            'shortname' => 'semconta',
            'ownerid' => '',
        ]);

        $this->assertNotEmpty($company->get('id'));
        $this->assertEquals($antes + 1, $DB->count_records('user', ['deleted' => 0]));
    }

    /**
     * Zero tambem nao e um usuario.
     *
     * @return void
     */
    public function test_aprovar_com_dono_zero(): void {
        global $DB;

        $application = $this->make_application(['contactemail' => 'zero@exemplo.com']);

        $company = api::approve($application, (object) [
            'shortname' => 'donozero',
            'ownerid' => '0',
        ]);

        $owner = $DB->get_record('local_marketplace_member', [
            'companyid' => $company->get('id'),
            'memberrole' => 'owner',
        ]);

        $this->assertNotFalse($owner, 'a empresa precisa ter dono');
        $this->assertNotEquals(0, (int) $owner->userid);
    }
}
