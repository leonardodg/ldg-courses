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
 * O template do QR code / boleto pendente, em return.php.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class pay_invoice_template_test extends \advanced_testcase {
    /**
     * O Pix mostra o QR code e o codigo copia-e-cola, e nao o codigo de
     * barras do boleto.
     *
     * @return void
     */
    public function test_pix_mostra_qrcode_e_nao_codigo_de_barras(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $html = $output->render_from_template('paygw_mercadopago/pay_invoice', [
            'pix' => true,
            'qrcode' => '00020126...',
            'qrcodeimage' => 'data:image/png;base64,abc',
            'waitingmessage' => 'Esperando o pagamento.',
        ]);

        $this->assertStringContainsString('00020126...', $html);
        $this->assertStringContainsString('data:image/png;base64,abc', $html);
        $this->assertStringNotContainsString('font-monospace', $html, 'sem codigo de barras no Pix');
    }

    /**
     * O boleto mostra a linha digitavel e o link de abrir, e nao QR code.
     *
     * @return void
     */
    public function test_boleto_mostra_linha_digitavel_e_link(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $html = $output->render_from_template('paygw_mercadopago/pay_invoice', [
            'boleto' => true,
            'barcode' => '37691157600000020000001000105671000016498766',
            'ticketurl' => 'https://www.mercadopago.com.br/payments/1/ticket',
            'waitingmessage' => 'Esperando o pagamento.',
        ]);

        $this->assertStringContainsString('37691157600000020000001000105671000016498766', $html);
        $this->assertStringContainsString('https://www.mercadopago.com.br/payments/1/ticket', $html);
        $this->assertStringNotContainsString('img-fluid', $html, 'sem imagem de QR code no boleto');
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
        $html = $output->render_from_template('paygw_mercadopago/pay_invoice', [
            'pix' => true,
            'waitingmessage' => 'Esperando o pagamento.',
        ]);

        $this->assertStringNotContainsString('@template', $html);
        $this->assertStringNotContainsString('Example context', $html);
    }
}
