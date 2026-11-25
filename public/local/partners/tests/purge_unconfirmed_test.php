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
use local_partners\task\purge_unconfirmed;

/**
 * Limpeza das candidaturas nunca confirmadas.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_partners\task\purge_unconfirmed::class)]
final class purge_unconfirmed_test extends \advanced_testcase {
    /**
     * Grava uma candidatura crua, com a idade pedida.
     *
     * @param string $status
     * @param int $age Segundos de idade.
     * @return int
     */
    private function make(string $status, int $age): int {
        global $DB;

        return $DB->insert_record(application::TABLE, (object) [
            'companyname' => 'Editora Teste',
            'contactname' => 'Maria Silva',
            'contactemail' => 'maria' . random_int(1000, 9999) . '@exemplo.com',
            'status' => $status,
            'timecreated' => time() - $age,
            'timemodified' => time(),
            'usermodified' => 0,
        ]);
    }

    /**
     * Roda a tarefa engolindo o mtrace.
     *
     * @return void
     */
    private function run_task(): void {
        ob_start();
        (new purge_unconfirmed())->execute();
        ob_end_clean();
    }

    /**
     * So a nao confirmada VENCIDA e apagada.
     *
     * @return void
     */
    public function test_apaga_so_a_vencida(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('unconfirmedretentiondays', 7, 'local_partners');

        $vencida = $this->make(application::STATUS_UNCONFIRMED, 30 * DAYSECS);
        // Ainda dentro do prazo: a pessoa pode clicar no link hoje.
        $recente = $this->make(application::STATUS_UNCONFIRMED, 1 * DAYSECS);

        $this->run_task();

        $this->assertFalse($DB->record_exists(application::TABLE, ['id' => $vencida]));
        $this->assertTrue($DB->record_exists(application::TABLE, ['id' => $recente]));
    }

    /**
     * Candidatura decidida ou na fila nunca e tocada, por mais velha que seja.
     *
     * A tarefa apaga rascunho de e-mail nao provado, e nao historico: uma
     * pending antiga e alguem esperando resposta ha muito tempo, que e o pior
     * momento possivel para o registro sumir.
     *
     * @return void
     */
    public function test_nao_toca_no_que_ja_e_pedido(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('unconfirmedretentiondays', 7, 'local_partners');

        $ids = [
            $this->make(application::STATUS_PENDING, 365 * DAYSECS),
            $this->make(application::STATUS_APPROVED, 365 * DAYSECS),
            $this->make(application::STATUS_REJECTED, 365 * DAYSECS),
        ];

        $this->run_task();

        foreach ($ids as $id) {
            $this->assertTrue($DB->record_exists(application::TABLE, ['id' => $id]));
        }
    }

    /**
     * Zero desliga a limpeza.
     *
     * @return void
     */
    public function test_zero_desliga(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('unconfirmedretentiondays', 0, 'local_partners');

        $id = $this->make(application::STATUS_UNCONFIRMED, 365 * DAYSECS);

        $this->run_task();

        $this->assertTrue($DB->record_exists(application::TABLE, ['id' => $id]));
    }
}
