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

use PHPUnit\Framework\Attributes\CoversClass;
use core_privacy\local\request\approved_contextlist;
use local_partners\privacy\provider;

/**
 * Privacidade das candidaturas.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_partners\privacy\provider::class)]
final class privacy_test extends \advanced_testcase {
    /**
     * Envia uma candidatura em nome de um usuario autenticado.
     *
     * @param \stdClass $user
     * @param array $overrides
     * @return application
     */
    private function submit_as(\stdClass $user, array $overrides = []): application {
        $this->setUser($user);

        $application = api::submit((object) array_merge([
            'companyname' => 'Editora Teste',
            'contactname' => 'Maria Silva',
            'contactemail' => $user->email,
            'contactphone' => '51999998888',
        ], $overrides));

        return $application;
    }

    /**
     * Pede a exclusao dos dados do usuario no contexto do sistema.
     *
     * @param \stdClass $user
     * @return void
     */
    private function delete_for(\stdClass $user): void {
        provider::delete_data_for_user(new approved_contextlist(
            \core_user::get_user($user->id),
            'local_partners',
            [\core\context\system::instance()->id]
        ));
    }

    /**
     * Quem enviou autenticado aparece como dono de dado.
     *
     * @return void
     */
    public function test_quem_enviou_tem_contexto(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->submit_as($user);

        $contexts = provider::get_contexts_for_userid($user->id)->get_contextids();

        $this->assertNotEmpty($contexts);
    }

    /**
     * Candidatura ainda nao aprovada some por inteiro.
     *
     * @return void
     */
    public function test_nao_aprovada_e_apagada(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $application = $this->submit_as($user);

        $this->delete_for($user);

        $this->assertFalse($DB->record_exists(application::TABLE, ['id' => $application->get('id')]));
    }

    /**
     * Candidatura aprovada fica, mas sem o dado pessoal.
     *
     * A empresa criada a partir dela continua existindo com categoria e cursos:
     * apagar a linha apagaria a origem de um vinculo comercial vivo.
     *
     * @return void
     */
    public function test_aprovada_fica_sem_o_dado_pessoal(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $application = $this->submit_as($user, ['cnpj' => '11222333000181']);

        $this->setAdminUser();
        api::approve($application, (object) ['shortname' => 'editorateste', 'ownerid' => null]);

        $this->delete_for($user);

        $record = $DB->get_record(application::TABLE, ['id' => $application->get('id')]);

        $this->assertNotFalse($record, 'a candidatura aprovada nao pode sumir');
        // Sai o que identifica a pessoa.
        $this->assertNull($record->userid);
        $this->assertSame('', $record->contactname);
        $this->assertSame('', $record->contactemail);
        $this->assertNull($record->contactphone);
        $this->assertNull($record->submitterip);
        // Fica o que e da pessoa juridica.
        $this->assertSame('Editora Teste', $record->companyname);
        $this->assertSame('11222333000181', $record->cnpj);
        $this->assertNotEmpty($record->companyid);
    }

    /**
     * Pais, faixa e aceite sobrevivem ao esquecimento numa candidatura aprovada.
     *
     * Pais e faixa sao atributos da OPERACAO, como a razao social e o CNPJ -
     * nao identificam ninguem. O aceite e o caso forte: e o registro de um ato
     * juridico, e apaga-lo destruiria a prova de que a empresa consentiu, que e
     * exatamente o documento que a propria lei de protecao de dados espera que
     * exista.
     *
     * @return void
     */
    public function test_aprovada_guarda_pais_faixa_e_aceite(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $application = $this->submit_as($user, [
            'country' => 'BR',
            'learnersband' => application::BAND_1000_TO_5000,
            'termsaccepted' => 1,
        ]);

        $this->setAdminUser();
        api::approve($application, (object) ['shortname' => 'editoraaceite', 'ownerid' => null]);

        $this->delete_for($user);

        $record = $DB->get_record(application::TABLE, ['id' => $application->get('id')]);

        $this->assertSame('BR', $record->country);
        $this->assertSame(application::BAND_1000_TO_5000, $record->learnersband);
        $this->assertNotEmpty($record->termsaccepted);
    }

    /**
     * O que a pessoa enviou volta para ela por inteiro, com os campos novos.
     *
     * @return void
     */
    public function test_export_devolve_pais_faixa_e_aceite(): void {
        $this->resetAfterTest();

        \core_privacy\local\request\writer::reset();

        $user = $this->getDataGenerator()->create_user();
        $application = $this->submit_as($user, [
            'country' => 'BR',
            'learnersband' => application::BAND_UPTO_100,
            'termsaccepted' => 1,
        ]);

        $context = \core\context\system::instance();

        provider::export_user_data(new approved_contextlist(
            \core_user::get_user($user->id),
            'local_partners',
            [$context->id]
        ));

        $exported = \core_privacy\local\request\writer::with_context($context)->get_data([
            get_string('privacy:path:applications', 'local_partners'),
            $application->get('id'),
        ]);

        $this->assertSame('BR', $exported->country);
        $this->assertSame(application::BAND_UPTO_100, $exported->learnersband);
        // A data sai formatada, e nao como carimbo cru: quem pede os proprios
        // dados le uma data, nao um inteiro de epoch.
        $this->assertNotEmpty($exported->termsaccepted);
        $this->assertIsNotNumeric($exported->termsaccepted);
    }

    /**
     * A exclusao de um usuario nao encosta na candidatura de outro.
     *
     * @return void
     */
    public function test_nao_afeta_terceiros(): void {
        global $DB;

        $this->resetAfterTest();

        $alvo = $this->getDataGenerator()->create_user();
        $outro = $this->getDataGenerator()->create_user();

        $this->submit_as($alvo);
        $dooutro = $this->submit_as($outro);

        $this->delete_for($alvo);

        $this->assertTrue($DB->record_exists(application::TABLE, ['id' => $dooutro->get('id')]));
    }

    /**
     * Candidatura de visitante anonimo nao pertence a ninguem e nao e apagada.
     *
     * Ela nao tem userid: quem a remove e a tarefa de limpeza ou o
     * administrador, nunca um pedido de exclusao de outra pessoa.
     *
     * @return void
     */
    public function test_anonima_nao_e_afetada(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('requireemailconfirmation', 0, 'local_partners');

        $this->setUser(null);
        $anonima = api::submit((object) [
            'companyname' => 'Anonima',
            'contactname' => 'Zeca',
            'contactemail' => 'zeca@exemplo.com',
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->submit_as($user);
        $this->delete_for($user);

        $this->assertTrue($DB->record_exists(application::TABLE, ['id' => $anonima->get('id')]));
    }
}
