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

namespace paygw_mercadopago;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * O template de confirmar um ciclo de cartao com o CVV.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class confirm_cycle_template_test extends \advanced_testcase {
    /**
     * Renderiza sem lancar, com o campo de CVV e sem campo de numero de
     * cartao nenhum - o cartao ja esta guardado, so falta o codigo.
     *
     * @return void
     */
    public function test_o_template_renderiza_so_com_cvv(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $html = $output->render_from_template('paygw_mercadopago/confirm_cycle', [
            'formaction' => 'https://exemplo.test/payment/gateway/mercadopago/confirm_cycle.php?ref=mdlsub-1-2-abc',
            'sesskey' => 'abc123',
            'amount' => 'R$ 49,90',
            'cancelurl' => 'https://exemplo.test/local/marketplace/mysubscriptions.php',
        ]);

        $this->assertStringContainsString('data-region="mp-card-form"', $html);
        $this->assertStringContainsString('id="mp-field-cvv"', $html);
        $this->assertStringContainsString('name="cardtoken"', $html);
        $this->assertStringNotContainsString('name="cardnumber"', $html, 'o cartao ja esta guardado');
    }

    /**
     * O docblock do template nao vaza para a tela.
     *
     * @return void
     */
    public function test_o_docblock_nao_vaza_para_a_tela(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $html = $output->render_from_template('paygw_mercadopago/confirm_cycle', [
            'formaction' => 'https://exemplo.test/payment/gateway/mercadopago/confirm_cycle.php?ref=mdlsub-1-2-abc',
            'sesskey' => 'abc123',
            'amount' => 'R$ 49,90',
            'cancelurl' => 'https://exemplo.test/local/marketplace/mysubscriptions.php',
        ]);

        $this->assertStringNotContainsString('@template', $html);
        $this->assertStringNotContainsString('Example context', $html);
    }
}
