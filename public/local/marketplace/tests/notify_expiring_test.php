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

use local_marketplace\task\notify_expiring;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Os marcos do aviso de vencimento.
 *
 * Sem debito automatico, o aviso e o que separa o aluno que renova do que
 * perde o acesso sem perceber. Sao dois marcos de proposito: o primeiro
 * lembra, o ultimo diz que vai bloquear.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\task\notify_expiring::class)]
final class notify_expiring_test extends \advanced_testcase {
    /**
     * A janela de renovacao tem que cobrir o marco mais distante.
     *
     * Sao duas constantes, e elas podem divergir numa edicao distraida. Se o
     * maior marco passar da NOTICE_DAYS, o aviso sai antes de o botao de
     * renovar existir - e manda o aluno para uma vitrine onde nao ha o que
     * clicar. offers.php, mysubscriptions.php e o block_marketplace leem a
     * NOTICE_DAYS para decidir isso.
     *
     * @return void
     */
    public function test_a_janela_cobre_o_marco_mais_distante(): void {
        $this->assertSame(
            notify_expiring::NOTICE_DAYS,
            max(notify_expiring::NOTICE_MILESTONES),
            'o maior marco precisa caber na janela em que a renovacao e oferecida'
        );
    }

    /**
     * Cada distancia do vencimento cai no seu marco.
     *
     * O marco escolhido e sempre o MAIS APERTADO que ainda cobre o tempo que
     * falta. Faltando tres dias vale o de cinco; faltando horas, o de um.
     *
     * @return void
     */
    public function test_o_marco_e_o_mais_apertado_que_cobre(): void {
        $agora = time();

        $this->assertSame(5, notify_expiring::milestone_for($agora + (4 * DAYSECS), $agora));
        $this->assertSame(5, notify_expiring::milestone_for($agora + (2 * DAYSECS), $agora));
        $this->assertSame(1, notify_expiring::milestone_for($agora + (12 * HOURSECS), $agora));
        $this->assertSame(1, notify_expiring::milestone_for($agora + MINSECS, $agora));
    }

    /**
     * Fora da janela nao ha marco, e portanto nao ha aviso.
     *
     * @return void
     */
    public function test_longe_do_vencimento_nao_tem_marco(): void {
        $agora = time();

        $this->assertSame(0, notify_expiring::milestone_for($agora + (30 * DAYSECS), $agora));
        $this->assertSame(0, notify_expiring::milestone_for($agora + (6 * DAYSECS), $agora));
    }

    /**
     * Ja vencido nao recebe aviso de que vai vencer.
     *
     * "Seu acesso vai vencer" depois do fato so confunde - quem venceu precisa
     * e do caminho de voltar, que e outra conversa.
     *
     * @return void
     */
    public function test_vencido_nao_tem_marco(): void {
        $agora = time();

        $this->assertSame(0, notify_expiring::milestone_for($agora - 1, $agora));
        $this->assertSame(0, notify_expiring::milestone_for($agora, $agora));
    }

    /**
     * O marco exatamente no limite conta para a janela mais apertada.
     *
     * Sem isto, o aluno a exatamente um dia do vencimento receberia o aviso
     * brando em vez do "vai bloquear", e o ultimo aviso nunca chegaria.
     *
     * @return void
     */
    public function test_o_limite_exato_conta_para_o_marco_menor(): void {
        $agora = time();

        $this->assertSame(1, notify_expiring::milestone_for($agora + DAYSECS, $agora));
        $this->assertSame(5, notify_expiring::milestone_for($agora + (5 * DAYSECS), $agora));
    }

    /**
     * Os dois textos existem, nas tres linguas, e nao sao o mesmo.
     *
     * Repetir a mesma mensagem duas vezes ensinaria o aluno a ignorar as duas.
     *
     * @return void
     */
    public function test_o_ultimo_aviso_tem_texto_proprio(): void {
        $a = (object) [
            'offer' => 'Assinatura',
            'company' => 'Empresa',
            'date' => 'hoje',
            'days' => 1,
            'url' => 'https://exemplo.test',
        ];

        foreach (['en', 'pt_br', 'es'] as $lingua) {
            $brando = get_string_manager()->get_string('expiringsubject', 'local_marketplace', $a, $lingua);
            $ultimo = get_string_manager()->get_string('expiringlastsubject', 'local_marketplace', $a, $lingua);

            $this->assertNotSame($brando, $ultimo, "os dois avisos em {$lingua} nao podem ser iguais");
            $this->assertNotEmpty(
                get_string_manager()->get_string('expiringlastbody', 'local_marketplace', $a, $lingua)
            );
            $this->assertNotEmpty(
                get_string_manager()->get_string('expiringlastbodyhtml', 'local_marketplace', $a, $lingua)
            );
        }
    }
}
