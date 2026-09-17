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
 * O template de trocar Pix/boleto por cartao.
 *
 * Reusa a partial card_fields, a mesma de subscribe.mustache - o teste aqui
 * confirma que o INCLUDE renderiza (`{{> }}` que aponta para um arquivo
 * inexistente falha calado em alguns casos, e so aparece renderizando).
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class switch_to_card_template_test extends \advanced_testcase {
    /**
     * Contexto minimo, num modo de cada vez.
     *
     * @param string $modo
     * @return array
     */
    protected function contexto(string $modo): array {
        return [
            'formaction' => 'https://exemplo.test/payment/gateway/mercadopago/switch_to_card.php?ref=mdlsub-1-2-abc',
            'sesskey' => 'abc123',
            'cancelurl' => 'https://exemplo.test/local/marketplace/mysubscriptions.php',
            'brick' => $modo === card_capture::MODE_BRICK,
            'direct' => $modo === card_capture::MODE_DIRECT,
            'native' => $modo === card_capture::MODE_NATIVE,
        ];
    }

    /**
     * Renderiza nos tres modos sem lancar, e a partial aparece de verdade -
     * nao so o texto de explicacao.
     *
     * @return void
     */
    public function test_o_template_renderiza_nos_tres_modos(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        foreach (card_capture::MODES as $modo) {
            $html = $output->render_from_template('paygw_mercadopago/switch_to_card', $this->contexto($modo));

            $this->assertStringContainsString('name="cardtoken"', $html, "modo $modo");
        }
    }

    /**
     * O link para desistir volta para mysubscriptions.php, e nao para uma
     * URL escrita a mao.
     *
     * @return void
     */
    public function test_o_link_de_desistir_usa_a_url_do_contexto(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $html = $output->render_from_template(
            'paygw_mercadopago/switch_to_card',
            $this->contexto(card_capture::MODE_BRICK)
        );

        $this->assertStringContainsString(
            'https://exemplo.test/local/marketplace/mysubscriptions.php',
            $html
        );
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
        $html = $output->render_from_template(
            'paygw_mercadopago/switch_to_card',
            $this->contexto(card_capture::MODE_BRICK)
        );

        $this->assertStringNotContainsString('@template', $html);
        $this->assertStringNotContainsString('Example context', $html);
    }
}
