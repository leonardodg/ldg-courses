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

namespace enrol_marketplace;

use local_marketplace\api;
use local_marketplace\entitlement;
use local_marketplace\offer;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/marketplace/lib.php');

/**
 * Matricula por diferenca, a partir dos direitos de acesso.
 *
 * O direito e a fonte unica da verdade do acesso, e este e o codigo que traduz
 * direito em matricula. Um erro aqui matricula quem nao pagou, ou tira o curso
 * de quem pagou — e a segunda forma e a que ninguem percebe ate o aluno
 * reclamar.
 *
 * @package    enrol_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_marketplace_plugin::class)]
final class sync_user_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $user;

    /** @var \local_marketplace\company */
    protected $company;

    /** @var \enrol_marketplace_plugin */
    protected $plugin;

    /**
     * Empresa, aluno e o plugin sob teste.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->user = $this->getDataGenerator()->create_user();
        $this->company = api::create_company((object) [
            'name' => 'Empresa Teste',
            'shortname' => 'teste' . random_int(1000, 9999),
            'cnpj' => null,
            'themename' => null,
            'hostname' => null,
        ], (int) $this->user->id);

        $this->plugin = new \enrol_marketplace_plugin();
    }

    /**
     * Cria uma oferta da empresa com a quantidade de cursos pedida.
     *
     * @param int $ncursos
     * @return array [offer, int[] ids dos cursos]
     */
    protected function make_offer(int $ncursos = 1): array {
        $o = new offer();
        $o->set('companyid', (int) $this->company->get('id'));
        $o->set('name', 'Oferta');
        $o->set('offertype', offer::TYPE_SINGLE);
        $o->set('price', 50.0);
        $o->set('currency', 'BRL');
        $o->set('accessmode', offer::ACCESS_LIFETIME);
        $o->set('accessdays', 0);
        $o->set('status', offer::STATUS_PUBLISHED);
        $o->create();

        $courseids = [];
        for ($i = 0; $i < $ncursos; $i++) {
            $course = $this->getDataGenerator()->create_course([
                'category' => $this->company->get('categoryid'),
            ]);
            $o->add_course((int) $course->id);
            $courseids[] = (int) $course->id;
        }

        return [$o, $courseids];
    }

    /**
     * Concede o direito ao aluno de teste.
     *
     * @param offer $offer
     * @param int $timeend 0 = vitalicio.
     * @return entitlement
     */
    protected function grant(offer $offer, int $timeend = 0): entitlement {
        $e = new entitlement();
        $e->set('userid', (int) $this->user->id);
        $e->set('offerid', (int) $offer->get('id'));
        $e->set('companyid', (int) $this->company->get('id'));
        $e->set('timestart', time() - DAYSECS);
        $e->set('timeend', $timeend);
        $e->set('status', entitlement::STATUS_ACTIVE);
        $e->create();

        return $e;
    }

    /**
     * Situacao da matricula do aluno num curso.
     *
     * @param int $courseid
     * @return int|null null quando nao ha matricula pelo marketplace.
     */
    protected function status_in(int $courseid): ?int {
        global $DB;

        $sql = "SELECT ue.status
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND e.courseid = :courseid AND e.enrol = 'marketplace'";
        $status = $DB->get_field_sql($sql, ['userid' => $this->user->id, 'courseid' => $courseid]);

        return $status === false ? null : (int) $status;
    }

    /**
     * Direito ativo matricula o aluno.
     *
     * @return void
     */
    public function test_direito_ativo_matricula(): void {
        [$offer, $courseids] = $this->make_offer();
        $this->grant($offer);

        [$enrolled, $suspended, $reactivated] = $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame(1, $enrolled);
        $this->assertSame(0, $suspended);
        $this->assertSame(0, $reactivated);
        $this->assertSame(ENROL_USER_ACTIVE, $this->status_in($courseids[0]));
    }

    /**
     * Rodar de novo nao faz nada.
     *
     * A idempotencia e a razao de o metodo trabalhar por diferenca em vez de
     * por evento: a task roda no cron, e uma falha no meio tem que poder ser
     * corrigida rodando de novo, sem duplicar matricula.
     *
     * @return void
     */
    public function test_rodar_duas_vezes_nao_duplica(): void {
        global $DB;

        [$offer] = $this->make_offer();
        $this->grant($offer);

        $this->plugin->sync_user((int) $this->user->id);
        [$enrolled, $suspended, $reactivated] = $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame([0, 0, 0], [$enrolled, $suspended, $reactivated]);

        $sql = "SELECT COUNT(1)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND e.enrol = 'marketplace'";
        $this->assertSame(1, (int) $DB->count_records_sql($sql, ['userid' => $this->user->id]));
    }

    /**
     * Oferta com varios cursos matricula em todos.
     *
     * @return void
     */
    public function test_oferta_com_varios_cursos(): void {
        [$offer, $courseids] = $this->make_offer(3);
        $this->grant($offer);

        [$enrolled] = $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame(3, $enrolled);
        foreach ($courseids as $courseid) {
            $this->assertSame(ENROL_USER_ACTIVE, $this->status_in($courseid));
        }
    }

    /**
     * Perder o direito SUSPENDE, e nao apaga.
     *
     * Apagar a matricula levaria junto nota e progresso. Quem perdeu acesso por
     * vencimento costuma renovar, e voltaria para um curso zerado — o prejuizo
     * seria do aluno, e irreversivel.
     *
     * @return void
     */
    public function test_perder_o_direito_suspende_sem_apagar(): void {
        [$offer, $courseids] = $this->make_offer();
        $ent = $this->grant($offer);
        $this->plugin->sync_user((int) $this->user->id);

        $ent->set('status', entitlement::STATUS_CANCELLED);
        $ent->update();

        [$enrolled, $suspended] = $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame(0, $enrolled);
        $this->assertSame(1, $suspended);
        $this->assertSame(
            ENROL_USER_SUSPENDED,
            $this->status_in($courseids[0]),
            'a matricula tem que continuar existindo, so que suspensa'
        );
    }

    /**
     * Direito vencido pela DATA tambem suspende.
     *
     * O status so muda quando a task de expiracao roda. Entre o vencimento e o
     * proximo cron o registro ainda diz "active", e olhar so para o status
     * deixaria o curso pago aberto de graca nessa janela.
     *
     * @return void
     */
    public function test_vencido_pela_data_suspende(): void {
        [$offer, $courseids] = $this->make_offer();
        $ent = $this->grant($offer, time() + DAYSECS);
        $this->plugin->sync_user((int) $this->user->id);
        $this->assertSame(ENROL_USER_ACTIVE, $this->status_in($courseids[0]));

        // Vence, mas ninguem mexeu no status: continua 'active' na coluna.
        $ent->set('timeend', time() - 1);
        $ent->update();
        $this->assertSame(entitlement::STATUS_ACTIVE, $ent->get('status'));

        [, $suspended] = $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame(1, $suspended);
        $this->assertSame(ENROL_USER_SUSPENDED, $this->status_in($courseids[0]));
    }

    /**
     * Renovar reativa a matricula suspensa, sem criar outra.
     *
     * @return void
     */
    public function test_renovar_reativa(): void {
        global $DB;

        [$offer, $courseids] = $this->make_offer();
        $ent = $this->grant($offer);
        $this->plugin->sync_user((int) $this->user->id);

        $ent->set('status', entitlement::STATUS_CANCELLED);
        $ent->update();
        $this->plugin->sync_user((int) $this->user->id);

        $ent->set('status', entitlement::STATUS_ACTIVE);
        $ent->update();
        [$enrolled, $suspended, $reactivated] = $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame(0, $enrolled, 'reativar nao pode criar matricula nova');
        $this->assertSame(0, $suspended);
        $this->assertSame(1, $reactivated);
        $this->assertSame(ENROL_USER_ACTIVE, $this->status_in($courseids[0]));

        $sql = "SELECT COUNT(1)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND e.enrol = 'marketplace'";
        $this->assertSame(1, (int) $DB->count_records_sql($sql, ['userid' => $this->user->id]));
    }

    /**
     * Matricula de OUTRO metodo nao e tocada.
     *
     * O professor matriculado a mao, ou o aluno de um curso gratuito com
     * self-enrolment, nao tem direito no marketplace. Se o sync olhasse todas
     * as matriculas em vez de so as dele, suspenderia gente que nunca passou
     * por aqui.
     *
     * @return void
     */
    public function test_nao_mexe_em_matricula_de_outro_metodo(): void {
        $outro = $this->getDataGenerator()->create_course([
            'category' => $this->company->get('categoryid'),
        ]);
        $this->getDataGenerator()->enrol_user((int) $this->user->id, (int) $outro->id, 'student', 'manual');

        [$offer] = $this->make_offer();
        $this->grant($offer);

        [, $suspended] = $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame(0, $suspended);
        $this->assertTrue(
            is_enrolled(\context_course::instance((int) $outro->id), $this->user, '', true),
            'a matricula manual tinha que continuar ativa'
        );
    }

    /**
     * Sem direito nenhum, nada acontece.
     *
     * @return void
     */
    public function test_sem_direito_nao_matricula(): void {
        $this->make_offer();

        $this->assertSame([0, 0, 0], $this->plugin->sync_user((int) $this->user->id));
    }

    /**
     * A instancia do curso e reaproveitada, e nao recriada.
     *
     * @return void
     */
    public function test_instancia_do_curso_e_reaproveitada(): void {
        global $DB;

        [, $courseids] = $this->make_offer();

        $primeira = $this->plugin->get_or_create_instance($courseids[0]);
        $segunda = $this->plugin->get_or_create_instance($courseids[0]);

        $this->assertSame((int) $primeira->id, (int) $segunda->id);
        $this->assertSame(1, (int) $DB->count_records('enrol', [
            'courseid' => $courseids[0],
            'enrol' => 'marketplace',
        ]));
    }

    /**
     * Prazo da matricula do aluno num curso.
     *
     * @param int $courseid
     * @return int|null null quando nao ha matricula pelo marketplace.
     */
    protected function timeend_in(int $courseid): ?int {
        global $DB;

        $sql = "SELECT ue.timeend
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND e.courseid = :courseid AND e.enrol = 'marketplace'";
        $fim = $DB->get_field_sql($sql, ['userid' => $this->user->id, 'courseid' => $courseid]);

        return $fim === false ? null : (int) $fim;
    }

    /**
     * A matricula herda o prazo do direito.
     *
     * Sem isto o acesso dependia inteiramente da tarefa horaria rodar: entre o
     * vencimento e a proxima passada o aluno continuava entrando, e com o cron
     * parado continuava indefinidamente. Falha silenciosa - o sintoma e aluno
     * acessando de graca, e disso ninguem reclama.
     *
     * @return void
     */
    public function test_a_matricula_herda_o_prazo_do_direito(): void {
        $fim = time() + (30 * DAYSECS);
        [$offer, $courseids] = $this->make_offer();
        $this->grant($offer, $fim);

        $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame($fim, $this->timeend_in($courseids[0]));
    }

    /**
     * Direito vitalicio deixa a matricula sem prazo.
     *
     * Zero e "sem data" sao a mesma coisa no user_enrolments, e precisam
     * continuar sendo: uma data qualquer aqui faria o acesso vitalicio expirar.
     *
     * @return void
     */
    public function test_direito_vitalicio_deixa_a_matricula_sem_prazo(): void {
        [$offer, $courseids] = $this->make_offer();
        $this->grant($offer, 0);

        $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame(0, $this->timeend_in($courseids[0]));
    }

    /**
     * Renovar estende o prazo, e isso nao e reativacao.
     *
     * O contador do cron separa as duas coisas de proposito: estender o prazo
     * de uma matricula que ja estava ativa nao e trazer alguem de volta, e
     * misturar faria o log mentir sobre quantos alunos recuperaram acesso.
     *
     * @return void
     */
    public function test_renovar_estende_o_prazo_sem_contar_reativacao(): void {
        $primeiro = time() + (30 * DAYSECS);
        [$offer, $courseids] = $this->make_offer();
        $direito = $this->grant($offer, $primeiro);

        $this->plugin->sync_user((int) $this->user->id);
        $this->assertSame($primeiro, $this->timeend_in($courseids[0]));

        $segundo = $primeiro + (30 * DAYSECS);
        $direito->set('timeend', $segundo);
        $direito->update();

        [$matriculadas, $suspensas, $reativadas] = $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame($segundo, $this->timeend_in($courseids[0]));
        $this->assertSame(0, $reativadas, 'estender prazo nao e reativar');
        $this->assertSame(0, $matriculadas);
        $this->assertSame(0, $suspensas);
    }

    /**
     * Dois direitos sobre o mesmo curso: vale o mais generoso.
     *
     * Acontece com combo mais assinatura. O vitalicio tem que ganhar de
     * qualquer data, senao comprar uma assinatura mensal encurtaria um acesso
     * vitalicio ja pago.
     *
     * @return void
     */
    public function test_vitalicio_ganha_de_prazo_no_mesmo_curso(): void {
        [$mensal, $courseids] = $this->make_offer();
        $this->grant($mensal, time() + (30 * DAYSECS));

        // Segunda oferta, apontando para o MESMO curso.
        [$vitalicio] = $this->make_offer(0);
        $vitalicio->add_course($courseids[0]);
        $this->grant($vitalicio, 0);

        $this->plugin->sync_user((int) $this->user->id);

        $this->assertSame(0, $this->timeend_in($courseids[0]));
    }
}
