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
 * Os papeis de empresa, e a fronteira que sustenta a margem do plano Free.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\roles::class)]
final class roles_test extends \advanced_testcase {
    /**
     * Os dois papeis nascem, e vivem no contexto de CATEGORIA.
     *
     * A categoria e onde a empresa mora. Papel de vendedor no sistema daria a
     * ele a plataforma inteira.
     *
     * @return void
     */
    public function test_ensure_cria_os_dois_papeis_em_categoria(): void {
        global $DB;

        $this->resetAfterTest();

        $ids = roles::ensure();

        $this->assertArrayHasKey(roles::MANAGER, $ids);
        $this->assertArrayHasKey(roles::SELLER, $ids);

        foreach ($ids as $shortname => $roleid) {
            $this->assertTrue($DB->record_exists('role', ['id' => $roleid, 'shortname' => $shortname]));
            // O get_role_contextlevels devolve os niveis como string.
            $this->assertSame(
                [CONTEXT_COURSECAT],
                array_map('intval', array_values(get_role_contextlevels($roleid))),
                "o papel {$shortname} tem que ser atribuivel so em categoria"
            );
        }
    }

    /**
     * Rodar duas vezes nao duplica nada.
     *
     * O upgrade chama o mesmo metodo do install, e um upgrade que morre no meio
     * e rodado de novo. Sem isto, a segunda passada criaria papel repetido.
     *
     * @return void
     */
    public function test_ensure_e_idempotente(): void {
        global $DB;

        $this->resetAfterTest();

        $first = roles::ensure();
        $before = $DB->count_records('role');

        $second = roles::ensure();

        $this->assertSame($first, $second);
        $this->assertSame($before, $DB->count_records('role'));
    }

    /**
     * Toda capability da lista esta proibida NOS DOIS papeis.
     *
     * E o teste que a regra de negocio nunca teve. Uma capability que escape da
     * lista abre um caminho para o moodledata, e a margem do plano Free vai
     * junto - o vendedor passa a poder servir video pela nossa banda.
     *
     * @return void
     */
    public function test_a_proibicao_vale_nos_dois_papeis(): void {
        global $DB;

        $this->resetAfterTest();

        $ids = roles::ensure();
        $syscontext = \context_system::instance();

        foreach ($ids as $shortname => $roleid) {
            foreach (roles::PROHIBIT as $capability) {
                $record = $DB->get_record('role_capabilities', [
                    'roleid' => $roleid,
                    'contextid' => $syscontext->id,
                    'capability' => $capability,
                ]);

                $this->assertNotFalse($record, "{$capability} nao esta definida em {$shortname}");
                $this->assertEquals(
                    CAP_PROHIBIT,
                    $record->permission,
                    "{$capability} tem que ser PROHIBIT em {$shortname}, e nao PREVENT"
                );
            }
        }
    }

    /**
     * ensure() RECONCILIA: o que nao esta na lista sai do papel.
     *
     * ESTE TESTE NASCEU DE UM DEFEITO REAL, encontrado em 04/09/2026 rodando o
     * upgrade contra um banco com dado. A primeira versao do ensure() so
     * chamava assign_capability, e assign_capability nao remove nada - entao,
     * numa base que JA TINHA o papel de vendedor, ele continuava com
     * managepayment, managecompany, viewreport e role:assign do desenho antigo.
     * A separacao de papeis funcionava no banco de teste, que nasce limpo, e
     * nao fazia absolutamente nada em producao.
     *
     * O mesmo mecanismo fecha um buraco maior: uma capability de repositorio
     * acrescentada a mao ao papel some no upgrade seguinte. O conjunto de
     * capabilities deste papel e regra de negocio escrita em codigo, e nao
     * preferencia de administrador.
     *
     * @return void
     */
    public function test_ensure_remove_o_que_saiu_da_lista(): void {
        $this->resetAfterTest();

        $ids = roles::ensure();
        $syscontext = \context_system::instance();
        $sellerid = $ids[roles::SELLER];

        // O estado da PRODUCAO antes da correcao: o vendedor com as
        // capabilities comerciais do desenho antigo.
        assign_capability('local/marketplace:managepayment', CAP_ALLOW, $sellerid, $syscontext->id, true);
        assign_capability('moodle/role:assign', CAP_ALLOW, $sellerid, $syscontext->id, true);

        // E o pior caso: alguem abriu um caminho para o moodledata a mao.
        assign_capability('repository/dropbox:view', CAP_ALLOW, $sellerid, $syscontext->id, true);

        roles::ensure();

        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);
        $editor = $this->getDataGenerator()->create_user();
        role_assign($sellerid, $editor->id, $context->id);

        $this->assertFalse(has_capability('local/marketplace:managepayment', $context, $editor));
        $this->assertFalse(has_capability('moodle/role:assign', $context, $editor));
        $this->assertFalse(has_capability('repository/dropbox:view', $context, $editor));

        // E o que deve ficar continua la.
        $this->assertTrue(has_capability('moodle/course:manageactivities', $context, $editor));
    }

    /**
     * O PROHIBIT vence o ALLOW do usuario autenticado.
     *
     * E a razao inteira de a escolha ser PROHIBIT. O repository/upload:view tem
     * archetype 'user' => CAP_ALLOW, entao todo usuario logado pode subir
     * arquivo; e no Moodle, quando dois papeis se contradizem no mesmo
     * contexto, o ALLOW vence o PREVENT. So o PROHIBIT nao e sobreponivel.
     *
     * O teste prova os dois lados: sem o papel, o upload passa.
     *
     * @return void
     */
    public function test_prohibit_vence_o_allow_do_usuario_autenticado(): void {
        $this->resetAfterTest();

        roles::ensure();

        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);

        $anyone = $this->getDataGenerator()->create_user();
        $seller = $this->getDataGenerator()->create_user();

        role_assign(roles::get_id(roles::SELLER), $seller->id, $context->id);

        // O controle: sem o papel, o usuario logado PODE subir arquivo. Sem
        // esta assercao o teste passaria mesmo que a capability nao existisse.
        $this->assertTrue(has_capability('repository/upload:view', $context, $anyone));

        $this->assertFalse(has_capability('repository/upload:view', $context, $seller));
        $this->assertFalse(has_capability('repository/user:view', $context, $seller));
        $this->assertFalse(has_capability('moodle/restore:uploadfile', $context, $seller));
    }

    /**
     * ATE ONDE a proibicao alcanca - e ate onde NAO alcanca.
     *
     * O papel e atribuido no contexto da CATEGORIA da empresa, entao a
     * proibicao vale ali e em tudo que esta dentro: os cursos, as secoes, as
     * atividades. E e exatamente onde ela precisa valer, porque e ali que o
     * conteudo do curso e montado e servido ao aluno.
     *
     * NAO alcanca o contexto pessoal do usuario. O vendedor continua podendo
     * subir arquivo nos proprios Arquivos privados - descoberto pelo Behat em
     * 04/09/2026, quando um cenario afirmou o contrario e falhou.
     *
     * ISSO NAO ABRE A MARGEM, e vale entender por que: arquivo em Arquivos
     * privados nao chega ao aluno. Para servi-lo num curso seria preciso passar
     * pelo seletor de arquivos DENTRO da categoria, e ali a proibicao vale. O
     * que sobra e um pouco de disco nosso, limitado pela cota do usuario - e
     * nao banda de video, que e o que o plano Free existe para nao gastar.
     *
     * Este teste existe para a proxima sessao nao "consertar" o escopo achando
     * que e defeito, nem confiar que a proibicao vale em todo lugar.
     *
     * @return void
     */
    public function test_a_proibicao_vale_na_categoria_e_nao_no_usuario(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        roles::ensure();

        $owner = $this->getDataGenerator()->create_user();
        $company = $this->create_company($owner->id);

        $category = $company->get_context();
        $personal = \context_user::instance($owner->id);

        // Onde o curso e montado: trancado.
        $this->assertFalse(has_capability('repository/upload:view', $category, $owner));
        $this->assertFalse(has_capability('moodle/user:manageownfiles', $category, $owner));

        // No proprio perfil: aberto, e de proposito - o papel nao esta la.
        $this->assertTrue(has_capability('repository/upload:view', $personal, $owner));
    }

    /**
     * O editor monta curso; quem mexe em dinheiro e o gerente.
     *
     * @return void
     */
    public function test_o_editor_nao_alcanca_a_conta_de_pagamento(): void {
        $this->resetAfterTest();

        roles::ensure();

        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);

        $editor = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();

        role_assign(roles::get_id(roles::SELLER), $editor->id, $context->id);
        role_assign(roles::get_id(roles::MANAGER), $manager->id, $context->id);

        // O que os dois compartilham: montar curso.
        $this->assertTrue(has_capability('moodle/course:manageactivities', $context, $editor));
        $this->assertTrue(has_capability('moodle/course:manageactivities', $context, $manager));

        // O que so o gerente tem.
        $this->assertFalse(has_capability('local/marketplace:managepayment', $context, $editor));
        $this->assertTrue(has_capability('local/marketplace:managepayment', $context, $manager));

        $this->assertFalse(has_capability('local/marketplace:managecompany', $context, $editor));
        $this->assertTrue(has_capability('local/marketplace:managecompany', $context, $manager));

        // Quem pode atribuir papel pode tentar se dar um que permita upload.
        // Nao funciona - o PROHIBIT nao e sobreponivel -, mas a capability nao
        // tem por que estar nas maos de quem so monta curso.
        $this->assertFalse(has_capability('moodle/role:assign', $context, $editor));
        $this->assertTrue(has_capability('moodle/role:assign', $context, $manager));
    }

    /**
     * O papel do Moodle sai do memberrole, e nao de um if espalhado.
     *
     * @return void
     */
    public function test_o_memberrole_escolhe_o_papel(): void {
        $this->assertSame(roles::MANAGER, roles::shortname_for(member::ROLE_OWNER));
        $this->assertSame(roles::SELLER, roles::shortname_for(member::ROLE_SELLER));
    }

    /**
     * A migracao troca o papel de quem ja era dono, e roda duas vezes sem mexer
     * mais nada.
     *
     * O install.php nao roda em base que ja existe. Sem este passo, a producao
     * fica com todo mundo no papel de editor, dono inclusive - e o dono perde o
     * acesso a conta de pagamento sem ninguem perceber.
     *
     * @return void
     */
    public function test_migrar_donos_e_idempotente(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        roles::ensure();

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $company = $this->create_company($owner->id);
        api::add_member($company, $other->id);

        $context = $company->get_context();

        // O estado ANTES da correcao: os dois no papel de editor, que e o que a
        // base em producao tem hoje.
        role_unassign(roles::get_id(roles::MANAGER), $owner->id, $context->id);
        role_assign(roles::get_id(roles::SELLER), $owner->id, $context->id);

        $this->assertSame(1, roles::migrate_owners());

        $this->assertTrue($this->has_role(roles::MANAGER, $owner->id, $context->id));
        $this->assertFalse($this->has_role(roles::SELLER, $owner->id, $context->id));

        // Quem nao e dono nao foi tocado.
        $this->assertTrue($this->has_role(roles::SELLER, $other->id, $context->id));
        $this->assertFalse($this->has_role(roles::MANAGER, $other->id, $context->id));

        // A segunda passada nao acha nada para migrar.
        $this->assertSame(0, roles::migrate_owners());
        $this->assertTrue($this->has_role(roles::MANAGER, $owner->id, $context->id));
    }

    /**
     * Desfazer o vinculo tira os DOIS papeis.
     *
     * Tirar so um deixa o outro grudado, e a pessoa continua enxergando a
     * empresa depois de ter sido removida dela.
     *
     * @return void
     */
    public function test_remover_membro_tira_os_dois_papeis(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        roles::ensure();

        $owner = $this->getDataGenerator()->create_user();
        $company = $this->create_company($owner->id);
        $context = $company->get_context();

        // O pior caso: alguem com os dois papeis, que e o que uma troca de
        // memberrole mal feita produzia.
        role_assign(roles::get_id(roles::SELLER), $owner->id, $context->id);

        api::remove_member($company, $owner->id);

        $this->assertFalse($this->has_role(roles::MANAGER, $owner->id, $context->id));
        $this->assertFalse($this->has_role(roles::SELLER, $owner->id, $context->id));
    }

    /**
     * Trocar o memberrole troca o papel do Moodle junto.
     *
     * O vinculo sao duas coisas inseparaveis: a linha na tabela e o papel no
     * contexto. Gravar so a linha produz um dono que nao alcanca a conta de
     * pagamento.
     *
     * @return void
     */
    public function test_trocar_o_memberrole_troca_o_papel(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        roles::ensure();

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $company = $this->create_company($owner->id);
        api::add_member($company, $other->id);

        $context = $company->get_context();

        api::set_member_role($company, $other->id, member::ROLE_OWNER);

        $this->assertTrue($this->has_role(roles::MANAGER, $other->id, $context->id));
        $this->assertFalse($this->has_role(roles::SELLER, $other->id, $context->id));

        // E o caminho de volta.
        api::set_member_role($company, $other->id, member::ROLE_SELLER);

        $this->assertFalse($this->has_role(roles::MANAGER, $other->id, $context->id));
        $this->assertTrue($this->has_role(roles::SELLER, $other->id, $context->id));
    }

    /**
     * Todo repositorio habilitado tem que estar na lista de proibicao.
     *
     * Este teste nao protege a producao - ele protege o CODIGO. Se um upgrade
     * do Moodle trouxer repositorio novo habilitado por padrao, e ele aparece
     * aqui antes de aparecer numa fatura de banda.
     *
     * @return void
     */
    public function test_nenhum_repositorio_habilitado_fica_de_fora(): void {
        $this->resetAfterTest();

        $this->assertSame([], roles::open_repositories());
    }

    /**
     * Cria uma empresa de teste com dono.
     *
     * @param int $ownerid
     * @return company
     */
    private function create_company(int $ownerid): company {
        return $this->getDataGenerator()
            ->get_plugin_generator('local_marketplace')
            ->create_company(['ownerid' => $ownerid]);
    }

    /**
     * Se o usuario carrega o papel naquele contexto.
     *
     * @param string $shortname
     * @param int $userid
     * @param int $contextid
     * @return bool
     */
    private function has_role(string $shortname, int $userid, int $contextid): bool {
        global $DB;

        return $DB->record_exists('role_assignments', [
            'roleid' => roles::get_id($shortname),
            'userid' => $userid,
            'contextid' => $contextid,
        ]);
    }

    /**
     * O gerente gere vendas; o editor, nao.
     *
     * Ver quem assinou e leitura, e cabe na viewreport. Cancelar a assinatura
     * de outra pessoa mexe no dinheiro e no acesso dela - por isso e
     * capability propria, e por isso quem so monta curso nao a tem.
     *
     * @return void
     */
    public function test_gerir_vendas_e_do_gerente(): void {
        $this->assertContains('local/marketplace:managesales', roles::ALLOW_MANAGER);
        $this->assertNotContains('local/marketplace:managesales', roles::ALLOW_EDITOR);
    }

    /**
     * O estorno nao vai para papel nenhum da empresa, e a ausencia e a regra.
     *
     * Ele devolve dinheiro de verdade e revoga acesso, e nao tem desfazer.
     * Fica com o administrador da plataforma, que o concede por empresa quando
     * confiar em quem vai usar. Este teste existe para a concessao ser um ato
     * deliberado, e nao um efeito colateral de alguem acrescentar a capability
     * a lista do gerente sem pensar.
     *
     * @return void
     */
    public function test_estorno_nao_e_de_nenhum_papel_de_empresa(): void {
        $this->assertNotContains('local/marketplace:refundsale', roles::ALLOW_MANAGER);
        $this->assertNotContains('local/marketplace:refundsale', roles::ALLOW_EDITOR);
    }

    /**
     * As duas capabilities novas existem de fato no db/access.php.
     *
     * Declarar na lista do papel sem declarar em access.php produz um papel que
     * aponta para capability inexistente - o Moodle nao reclama, e a permissao
     * simplesmente nunca vale.
     *
     * @return void
     */
    public function test_as_capabilities_novas_estao_declaradas(): void {
        $all = array_keys(get_all_capabilities());

        $this->assertContains('local/marketplace:managesales', $all);
        $this->assertContains('local/marketplace:refundsale', $all);
    }
}
