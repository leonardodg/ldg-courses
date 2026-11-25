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
 * Vigencia do direito de acesso.
 *
 * E a fonte da verdade do acesso: o enrol e o availability consultam isto. Um
 * erro aqui libera curso pago ou tira curso de quem pagou.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\entitlement::class)]
final class entitlement_test extends \advanced_testcase {
    /**
     * Monta um direito sem gravar.
     *
     * @param array $props
     * @return entitlement
     */
    protected function make(array $props): entitlement {
        $e = new entitlement();
        foreach ($props as $key => $value) {
            $e->set($key, $value);
        }
        return $e;
    }

    /**
     * Vitalicio vale para sempre.
     *
     * @return void
     */
    public function test_lifetime_is_active(): void {
        $e = $this->make([
            'status' => entitlement::STATUS_ACTIVE,
            'timestart' => 1000,
            'timeend' => 0,
        ]);

        $this->assertTrue($e->is_active(2000));
        $this->assertTrue($e->is_active(PHP_INT_MAX - 1));
    }

    /**
     * A data e conferida ALEM do status.
     *
     * O status so muda quando a task de expiracao roda. Entre o vencimento e a
     * proxima execucao do cron, confiar so no status daria acesso a quem ja
     * venceu - uma janela de horas em que o curso pago fica de graca.
     *
     * @return void
     */
    public function test_expired_by_date_even_while_status_active(): void {
        $e = $this->make([
            'status' => entitlement::STATUS_ACTIVE,
            'timestart' => 1000,
            'timeend' => 2000,
        ]);

        $this->assertTrue($e->is_active(1999));
        $this->assertFalse($e->is_active(2000), 'no instante do vencimento ja nao vale');
        $this->assertFalse($e->is_active(2001));
    }

    /**
     * Antes do inicio nao vale.
     *
     * @return void
     */
    public function test_not_active_before_start(): void {
        $e = $this->make([
            'status' => entitlement::STATUS_ACTIVE,
            'timestart' => 5000,
            'timeend' => 0,
        ]);

        $this->assertFalse($e->is_active(4999));
        $this->assertTrue($e->is_active(5000));
    }

    /**
     * Revogado nao vale, mesmo dentro do prazo.
     *
     * E o caso de estorno: o dinheiro voltou, o acesso sai na hora.
     *
     * @return void
     */
    public function test_cancelled_is_never_active(): void {
        $e = $this->make([
            'status' => entitlement::STATUS_CANCELLED,
            'timestart' => 1000,
            'timeend' => 0,
        ]);

        $this->assertFalse($e->is_active(2000));
    }

    /**
     * Cancelar a assinatura NAO revoga o acesso.
     *
     * norenew e status sao coisas distintas de proposito: quem pagou 30 dias e
     * cancela no dia 10 fica com os 20 que restam. Se esta asserção quebrar,
     * a plataforma passou a cobrar por servico nao prestado.
     *
     * @return void
     */
    public function test_norenew_does_not_revoke_access(): void {
        $e = $this->make([
            'status' => entitlement::STATUS_ACTIVE,
            'timestart' => 1000,
            'timeend' => 9000,
            'norenew' => 1,
        ]);

        $this->assertTrue($e->is_active(2000));
    }

    /**
     * A consulta por usuario respeita status e datas.
     *
     * @return void
     */
    public function test_get_active_for_user_filters(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $rows = [
            // Vigente.
            ['timestart' => $now - 100, 'timeend' => 0, 'status' => entitlement::STATUS_ACTIVE],
            // Vencido.
            ['timestart' => $now - 100, 'timeend' => $now - 10, 'status' => entitlement::STATUS_ACTIVE],
            // Revogado.
            ['timestart' => $now - 100, 'timeend' => 0, 'status' => entitlement::STATUS_CANCELLED],
            // Ainda nao comecou.
            ['timestart' => $now + 100, 'timeend' => 0, 'status' => entitlement::STATUS_ACTIVE],
        ];

        foreach ($rows as $i => $row) {
            $DB->insert_record('local_marketplace_entitlement', (object) array_merge($row, [
                'userid' => $user->id,
                'offerid' => $i + 1,
                'companyid' => 1,
                'cycles' => 1,
                'norenew' => 0,
                'usermodified' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]));
        }

        $active = entitlement::get_active_for_user((int) $user->id);

        $this->assertCount(1, $active);
        $this->assertSame(1, (int) reset($active)->get('offerid'));
    }
}
