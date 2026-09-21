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

/**
 * Matricula guiada pelos direitos de acesso.
 *
 * @package    enrol_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use local_marketplace\entitlement;

/**
 * Plugin de matricula do marketplace.
 *
 * Nao tem tela de inscricao: ninguem se matricula aqui por vontade propria.
 * A matricula e consequencia de um direito de acesso, e some quando o direito
 * some. Por isso o plugin e um SINCRONIZADOR, nao um formulario.
 *
 * A instancia no curso e criada sob demanda: um curso so ganha a sua quando o
 * primeiro aluno adquire direito a ele.
 */
class enrol_marketplace_plugin extends enrol_plugin {
    /**
     * O admin nao adiciona instancia manualmente: quem cria e o sincronizador.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        return false;
    }

    /**
     * Desmatricular na mao quebraria a correspondencia com o direito: o aluno
     * continuaria pagando e sem acesso, e o proximo sync o traria de volta.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return false;
    }

    /**
     * Mesma razao do allow_unenrol.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return false;
    }

    /**
     * O plugin roda sozinho pelo cron.
     *
     * @return bool
     */
    public function roles_protected() {
        return true;
    }

    /**
     * Nome exibido da instancia.
     *
     * @param stdClass $instance
     * @return string
     */
    public function get_instance_name($instance) {
        return get_string('pluginname', 'enrol_marketplace');
    }

    /**
     * Devolve a instancia do curso, criando se ainda nao existir.
     *
     * @param int $courseid
     * @return stdClass
     */
    public function get_or_create_instance(int $courseid): stdClass {
        global $DB;

        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'marketplace']);
        if ($instance) {
            return $instance;
        }

        // A tabela enrol do core nao tem indice unico em (courseid, enrol),
        // so um indice nao-unico em 'enrol' sozinho - entao duas compras
        // quase simultaneas de um curso NUNCA vendido antes liam ambas "nao
        // existe" e cada uma criava a propria instancia, deixando o curso com
        // duas instancias de matricula 'marketplace' (allow_manage() bloqueia
        // remover a duplicata pela UI depois). Trava a linha do CURSO (que ja
        // existe) ate o commit, serializando as duas.
        $transaction = $DB->start_delegated_transaction();
        $DB->get_record_sql('SELECT id FROM {course} WHERE id = ? FOR UPDATE', [$courseid]);

        // Reconfere DEPOIS de travar: se a outra compra ja criou a instancia
        // enquanto esta esperava a trava, usa a dela em vez de criar uma segunda.
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'marketplace']);
        if ($instance) {
            $transaction->allow_commit();
            return $instance;
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $instanceid = $this->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $this->get_config('roleid', 0) ?: $this->get_student_roleid(),
        ]);
        $transaction->allow_commit();

        return $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * ID do papel de estudante, usado como padrao da matricula.
     *
     * @return int
     */
    protected function get_student_roleid(): int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        return $roleid ?: 0;
    }

    /**
     * Sincroniza as matriculas de um usuario com os direitos dele.
     *
     * Compara o que o usuario DEVE ter com o que ele TEM, e ajusta. Trabalhar
     * por diferenca, e nao por evento, e o que torna a operacao idempotente:
     * pode rodar mil vezes, ou depois de uma falha no meio, e o resultado e o
     * mesmo.
     *
     * Matricula existente nao e apagada, e SUSPENSA. Apagar levaria junto
     * notas e progresso - e quem perdeu acesso por vencimento costuma
     * renovar.
     *
     * @param int $userid
     * @return array [matriculados, suspensos, reativados]
     */
    public function sync_user(int $userid): array {
        global $DB;

        // Trava a linha do USUARIO ate o commit no fim da funcao: esta funcao
        // e chamada tanto pelo cron horario quanto por todo webhook de
        // pagamento, sem mutex entre eles. Cada escrita por curso e
        // individualmente idempotente hoje, mas sem isolamento nenhum uma
        // mudanca futura que tornasse a logica por curso multi-etapa herdaria
        // essa corrida em silencio.
        $transaction = $DB->start_delegated_transaction();
        $DB->get_record_sql('SELECT id FROM {user} WHERE id = ? FOR UPDATE', [$userid]);

        // Curso => ate quando o acesso vale. Zero e vitalicio.
        //
        // O prazo vai para a MATRICULA, e nao so para o direito. Sem isso o
        // acesso dependia inteiramente desta tarefa rodar: entre o vencimento
        // e a proxima passada o aluno continuava entrando, e com o cron parado
        // continuava indefinidamente - falha silenciosa, porque o sintoma e
        // aluno acessando de graca, e disso ninguem reclama.
        //
        // Nao vira segunda fonte da verdade: e PROJECAO do direito, escrita
        // sempre por aqui. O sync trabalha por diferenca, entao qualquer
        // divergencia se conserta sozinha na passada seguinte.
        $shouldhave = [];
        foreach (entitlement::get_active_for_user($userid) as $ent) {
            $offer = new \local_marketplace\offer($ent->get('offerid'));
            $end = (int) $ent->get('timeend');
            foreach ($offer->get_course_ids() as $courseid) {
                if (!array_key_exists($courseid, $shouldhave)) {
                    $shouldhave[$courseid] = $end;
                    continue;
                }
                // Dois direitos podem liberar o mesmo curso - um combo e uma
                // assinatura, por exemplo. Vale o mais generoso, e vitalicio
                // ganha de qualquer data.
                if ($shouldhave[$courseid] === 0 || $end === 0) {
                    $shouldhave[$courseid] = 0;
                } else {
                    $shouldhave[$courseid] = max($shouldhave[$courseid], $end);
                }
            }
        }

        $sql = "SELECT e.courseid, ue.status, ue.timeend, e.id AS enrolid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND e.enrol = 'marketplace'";
        $current = $DB->get_records_sql($sql, ['userid' => $userid]);

        $enrolled = $suspended = $reactivated = 0;

        foreach ($shouldhave as $courseid => $end) {
            if (!isset($current[$courseid])) {
                $instance = $this->get_or_create_instance($courseid);
                $this->enrol_user($instance, $userid, null, 0, $end, ENROL_USER_ACTIVE);
                $enrolled++;
                continue;
            }

            $suspensa = (int) $current[$courseid]->status !== ENROL_USER_ACTIVE;
            $prazomudou = (int) $current[$courseid]->timeend !== $end;

            if (!$suspensa && !$prazomudou) {
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $current[$courseid]->enrolid], '*', MUST_EXIST);
            $this->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE, null, $end);

            // So conta reativacao quando havia suspensao. Renovar estende o
            // prazo de uma matricula que ja estava ativa, e isso nao e
            // reativar - misturar os dois faria o log do cron mentir.
            if ($suspensa) {
                $reactivated++;
            }
        }

        foreach ($current as $courseid => $record) {
            if (!isset($shouldhave[$courseid]) && (int) $record->status === ENROL_USER_ACTIVE) {
                $instance = $DB->get_record('enrol', ['id' => $record->enrolid], '*', MUST_EXIST);
                $this->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
                $suspended++;
            }
        }

        $transaction->allow_commit();

        return [$enrolled, $suspended, $reactivated];
    }
}
