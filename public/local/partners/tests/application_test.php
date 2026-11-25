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
use local_marketplace\plan;

/**
 * Candidatura de parceria.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_partners\application::class)]
#[CoversClass(\local_partners\api::class)]
final class application_test extends \advanced_testcase {
    /**
     * Coloca a sessao no estado de visitante anonimo, sem confirmacao de
     * e-mail exigida - o comportamento que estes testes assumem.
     *
     * @return void
     */
    private function as_anonymous_visitor(): void {
        set_config('requireemailconfirmation', 0, 'local_partners');
        $this->setUser(null);
    }

    /**
     * Dados minimos de uma candidatura.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function data(array $overrides = []): \stdClass {
        return (object) array_merge([
            'companyname' => 'Editora Teste',
            'contactname' => 'Maria Silva',
            'contactemail' => 'maria@exemplo.com',
        ], $overrides);
    }

    /**
     * A candidatura entra na fila com o CNPJ normalizado.
     *
     * @return void
     */
    public function test_submit_grava_na_fila(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $application = api::submit($this->data(['cnpj' => '11.222.333/0001-81']));

        $this->assertSame(application::STATUS_PENDING, $application->get('status'));
        // O que entra no banco sao os digitos: a pontuacao e do formulario, e
        // duas grafias do mesmo CNPJ nao podem virar duas empresas.
        $this->assertSame('11222333000181', $application->get('cnpj'));
    }

    /**
     * CNPJ mal formado nao entra.
     *
     * @return void
     */
    public function test_cnpj_invalido_e_recusado(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $this->expectException(\core\invalid_persistent_exception::class);

        api::submit($this->data(['cnpj' => '11222333000180']));
    }

    /**
     * Plano inexistente nao entra.
     *
     * @return void
     */
    public function test_plano_inexistente_e_recusado(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $this->expectException(\core\invalid_persistent_exception::class);

        api::submit($this->data(['planid' => 999999]));
    }

    /**
     * Plano existente e aceito como INTENCAO.
     *
     * @return void
     */
    public function test_plano_escolhido_e_guardado(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $plan = plan::get_record_by_shortname('start_free');
        $this->assertNotFalse($plan, 'o seed da instalacao deveria ter criado o plano start_free');

        $application = api::submit($this->data(['planid' => (int) $plan->get('id')]));

        $this->assertEquals($plan->get('id'), $application->get('planid'));
        // Escolher plano NAO cria empresa: a fila e um pedido, e o vinculo so
        // acontece na aprovacao.
        $this->assertNull($application->get('companyid'));
    }

    /**
     * Duplicidade em aberto e detectada por e-mail e por CNPJ.
     *
     * @return void
     */
    public function test_duplicidade_em_aberto(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        api::submit($this->data(['cnpj' => '11222333000181']));

        $this->assertTrue(application::has_pending_for('maria@exemplo.com'));
        $this->assertTrue(application::has_pending_for('outro@exemplo.com', '11222333000181'));
        $this->assertFalse(application::has_pending_for('outro@exemplo.com', null));
    }

    /**
     * Candidatura ja decidida deixa de bloquear novo envio.
     *
     * Reenvio depois de recusa e caso legitimo: a empresa corrigiu o que faltava.
     *
     * @return void
     */
    public function test_candidatura_decidida_nao_bloqueia(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $application = api::submit($this->data());
        $application->set('status', application::STATUS_REJECTED);
        $application->update();

        $this->assertFalse(application::has_pending_for('maria@exemplo.com'));
    }

    /**
     * O limite de taxa conta so a ultima hora.
     *
     * @return void
     */
    public function test_limite_de_taxa_por_ip(): void {
        global $DB;

        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $application = api::submit($this->data());
        $ip = $application->get('submitterip');

        $this->assertSame(1, application::count_recent_from_ip($ip));

        // Envelhece a linha para alem da janela.
        $DB->set_field(application::TABLE, 'timecreated', time() - (2 * HOURSECS), [
            'id' => $application->get('id'),
        ]);

        $this->assertSame(0, application::count_recent_from_ip($ip));
    }

    /**
     * A fila sai em ordem de chegada.
     *
     * @return void
     */
    public function test_fila_em_ordem_de_chegada(): void {
        global $DB;

        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $primeira = api::submit($this->data(['contactemail' => 'a@exemplo.com']));
        $segunda = api::submit($this->data(['contactemail' => 'b@exemplo.com']));

        // As duas nascem no mesmo segundo no teste; envelhece a primeira para a
        // ordem ficar deterministica.
        $DB->set_field(application::TABLE, 'timecreated', time() - MINSECS, ['id' => $primeira->get('id')]);

        $fila = array_values(application::get_pending());

        $this->assertCount(2, $fila);
        $this->assertEquals($primeira->get('id'), $fila[0]->get('id'));
        $this->assertEquals($segunda->get('id'), $fila[1]->get('id'));
    }

    /**
     * Visitante anonimo com confirmacao ligada NAO entra na fila.
     *
     * E o ponto da confirmacao: enquanto o e-mail nao for provado, aquilo nao e
     * uma candidatura - e o que alguem digitou.
     *
     * @return void
     */
    public function test_anonimo_com_confirmacao_fica_fora_da_fila(): void {
        $this->resetAfterTest();
        $this->setUser(null);
        set_config('requireemailconfirmation', 1, 'local_partners');

        $application = api::submit($this->data());

        $this->assertSame(application::STATUS_UNCONFIRMED, $application->get('status'));
        $this->assertNotEmpty($application->get('confirmtoken'));
        $this->assertEmpty(application::get_pending());
    }

    /**
     * Confirmar o link coloca a candidatura na fila e queima o token.
     *
     * @return void
     */
    public function test_confirmar_coloca_na_fila(): void {
        $this->resetAfterTest();
        $this->setUser(null);
        set_config('requireemailconfirmation', 1, 'local_partners');

        $application = api::submit($this->data());
        $token = $application->get('confirmtoken');

        api::confirm(application::get_by_token($token));

        $application = application::get_record(['id' => $application->get('id')]);

        $this->assertSame(application::STATUS_PENDING, $application->get('status'));
        $this->assertNotEmpty($application->get('timeconfirmed'));
        // Token de uso unico: o mesmo link nao confirma duas vezes.
        $this->assertNull($application->get('confirmtoken'));
        $this->assertFalse(application::get_by_token($token));
        $this->assertCount(1, application::get_pending());
    }

    /**
     * Usuario autenticado nao confirma nada, e a candidatura fica no perfil.
     *
     * @return void
     */
    public function test_autenticado_dispensa_confirmacao(): void {
        $this->resetAfterTest();
        set_config('requireemailconfirmation', 1, 'local_partners');

        $user = $this->getDataGenerator()->create_user(['email' => 'dono@exemplo.com']);
        $this->setUser($user);

        // O e-mail digitado e IGNORADO: vale o do perfil. Aceitar outro criaria
        // uma candidatura que parece de terceiro, e o dono da empresa sai daqui.
        $application = api::submit($this->data(['contactemail' => 'outro@exemplo.com']));

        $this->assertSame(application::STATUS_PENDING, $application->get('status'));
        $this->assertEquals($user->id, $application->get('userid'));
        $this->assertSame('dono@exemplo.com', $application->get('contactemail'));
        $this->assertNull($application->get('confirmtoken'));
    }

    /**
     * Pais fora da lista do Moodle nao entra.
     *
     * O pais nao e enfeite de formulario: e ele que decide moeda e conta de
     * split la na frente, e o mesmo alfabeto ISO que a oferta usa.
     *
     * @return void
     */
    public function test_pais_invalido_e_recusado(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $this->expectException(\core\invalid_persistent_exception::class);

        api::submit($this->data(['country' => 'XX']));
    }

    /**
     * Faixa de alunos fora da lista nao entra.
     *
     * @return void
     */
    public function test_faixa_de_alunos_fora_da_lista_e_recusada(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $this->expectException(\core\invalid_persistent_exception::class);

        api::submit($this->data(['learnersband' => 'muitos']));
    }

    /**
     * Pais e faixa chegam a linha do jeito que foram informados.
     *
     * @return void
     */
    public function test_pais_e_faixa_chegam_a_linha(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $application = api::submit($this->data([
            'country' => 'BR',
            'learnersband' => application::BAND_100_TO_1000,
        ]));

        $this->assertSame('BR', $application->get('country'));
        $this->assertSame(application::BAND_100_TO_1000, $application->get('learnersband'));
    }

    /**
     * O aceite grava o MOMENTO, e nao um "sim".
     *
     * Um booleano valendo 1 em toda linha e redundante com a existencia da
     * linha - nao prova nada. O que tem valor probatorio e quando.
     *
     * @return void
     */
    public function test_o_aceite_grava_o_momento_e_nao_um_sim(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $before = time();
        $application = api::submit($this->data(['termsaccepted' => 1]));

        $accepted = (int) $application->get('termsaccepted');

        $this->assertGreaterThanOrEqual($before, $accepted);
        $this->assertLessThanOrEqual(time(), $accepted);
    }

    /**
     * Candidatura sem aceite fica com nulo, e nao com zero.
     *
     * As que ja estao na fila entraram antes de o aceite existir. Retroagir com
     * 1 inventaria um consentimento que ninguem deu, e zero diria "recusou",
     * que tambem nao aconteceu. Nulo e a unica verdade disponivel.
     *
     * @return void
     */
    public function test_candidatura_sem_aceite_fica_com_nulo(): void {
        $this->resetAfterTest();
        $this->as_anonymous_visitor();

        $application = api::submit($this->data());

        $this->assertNull($application->get('termsaccepted'));
    }

    /**
     * Reenvio substitui a nao confirmada, em vez de acumular ou travar.
     *
     * Quem nao recebeu o e-mail precisa poder tentar de novo; sem isto, o
     * primeiro envio trancaria a pessoa para sempre.
     *
     * @return void
     */
    public function test_reenvio_substitui_a_nao_confirmada(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser(null);
        set_config('requireemailconfirmation', 1, 'local_partners');

        api::submit($this->data());
        $segunda = api::submit($this->data());

        $this->assertEquals(1, $DB->count_records(application::TABLE));
        $this->assertSame(application::STATUS_UNCONFIRMED, $segunda->get('status'));
    }
}
