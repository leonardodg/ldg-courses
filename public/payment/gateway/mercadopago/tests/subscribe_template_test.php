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
 * O template da pagina do cartao.
 *
 * Nao ha tarefa de mustache lint nesta versao do Moodle, entao o que pega erro
 * de template e RENDERIZAR. Um teste que so monta contexto nao ve chave errada.
 *
 * O segundo teste existe por um defeito ja cometido neste projeto: um docblock
 * de mustache virou paragrafo na pagina publica, porque o comentario `{{! }}`
 * termina no PRIMEIRO `}}` e nao no que fecha o bloco. O PHPUnit passava, e o
 * defeito so apareceu na captura de tela.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class subscribe_template_test extends \advanced_testcase {
    /**
     * Contexto minimo da pagina, num modo de cada vez.
     *
     * @param string $modo
     * @return array
     */
    protected function contexto(string $modo): array {
        return [
            'formaction' => 'https://exemplo.test/payment/gateway/mercadopago/subscribe.php?ref=mdlsub-1-2-abc',
            'sesskey' => 'abc123',
            'amount' => 'R$ 49,90',
            'brick' => $modo === card_capture::MODE_BRICK,
            'direct' => $modo === card_capture::MODE_DIRECT,
            'native' => $modo === card_capture::MODE_NATIVE,
        ];
    }

    /**
     * Renderiza nos tres modos sem lancar.
     *
     * @return void
     */
    public function test_o_template_renderiza_nos_tres_modos(): void {
        global $PAGE;

        $this->resetAfterTest();

        // Sem o alvo explicito o Moodle entrega core_renderer_cli em CLI, e o
        // teste passa verificando quase nada.
        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        foreach (card_capture::MODES as $modo) {
            $html = $output->render_from_template('paygw_mercadopago/subscribe', $this->contexto($modo));

            $this->assertStringContainsString('name="cardtoken"', $html, "modo $modo");
            $this->assertStringContainsString('data-action="mp-pay"', $html, "modo $modo");
        }
    }

    /**
     * O docblock do template nao aparece na pagina.
     *
     * @return void
     */
    public function test_o_docblock_do_template_nao_vaza_para_a_tela(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $html = $output->render_from_template(
            'paygw_mercadopago/subscribe',
            $this->contexto(card_capture::MODE_BRICK)
        );

        $this->assertStringNotContainsString('@template', $html);
        $this->assertStringNotContainsString('Example context', $html);
        $this->assertStringNotContainsString('Context variables required', $html);
    }

    /**
     * So o modo nativo tem campo de numero de cartao no HTML.
     *
     * E a fronteira do escopo PCI expressa em teste: nos outros dois modos o
     * numero nao existe como campo desta pagina - o que existe sao recipientes
     * para iframes do Mercado Pago.
     *
     * @return void
     */
    public function test_so_o_modo_nativo_tem_campo_de_numero_de_cartao(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        $brick = $output->render_from_template(
            'paygw_mercadopago/subscribe',
            $this->contexto(card_capture::MODE_BRICK)
        );
        $direct = $output->render_from_template(
            'paygw_mercadopago/subscribe',
            $this->contexto(card_capture::MODE_DIRECT)
        );
        $native = $output->render_from_template(
            'paygw_mercadopago/subscribe',
            $this->contexto(card_capture::MODE_NATIVE)
        );

        $this->assertStringNotContainsString('name="cardnumber"', $brick);
        $this->assertStringNotContainsString('name="cardnumber"', $direct);
        $this->assertStringContainsString('name="cardnumber"', $native);

        $this->assertStringNotContainsString('name="securitycode"', $direct);
        $this->assertStringContainsString('name="securitycode"', $native);
    }
}
