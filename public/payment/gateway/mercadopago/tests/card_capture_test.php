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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Onde o cartao e digitado, e o que isso custa.
 *
 * Sao dois modos, e a diferenca entre eles nao e de aparencia: ela decide se o
 * numero do cartao passa pelo nosso servidor, e com isso se o projeto entra em
 * escopo PCI DSS SAQ D.
 *
 * O que estes testes protegem e a propriedade que torna a escolha aceitavel: o
 * modo inseguro NAO depende de o administrador acertar a configuracao. Sem
 * HTTPS ele simplesmente nao vale, e o codigo cai no modo seguro sozinho.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_mercadopago\card_capture::class)]
final class card_capture_test extends \advanced_testcase {
    /**
     * Sem configurar nada, o cartao e digitado nos campos do Mercado Pago.
     *
     * O padrao precisa ser o que mantem o projeto FORA de escopo PCI: quem
     * instala o plugin e nao le a documentacao nao pode acabar com numero de
     * cartao trafegando pelo proprio servidor sem ter escolhido isso.
     *
     * @return void
     */
    public function test_o_padrao_e_o_modo_que_nao_toca_no_cartao(): void {
        $this->resetAfterTest();

        $this->assertSame(card_capture::MODE_BRICK, card_capture::current());
    }

    /**
     * Com HTTPS e escolha explicita, o modo nativo vale.
     *
     * @return void
     */
    public function test_o_modo_nativo_vale_quando_escolhido_e_sob_https(): void {
        $this->resetAfterTest();

        set_config('cardcapture', card_capture::MODE_NATIVE, 'paygw_mercadopago');
        $this->set_https(true);

        $this->assertSame(card_capture::MODE_NATIVE, card_capture::current());
    }

    /**
     * SEM HTTPS o modo nativo nao vale, mesmo escolhido.
     *
     * Este e o teste que da sentido a existencia do modo nativo. Sem ele, a
     * seguranca do projeto dependeria de a configuracao estar certa - e uma
     * caixa marcada por engano mandaria numero de cartao em texto claro pela
     * rede. Aqui a protecao e estrutural: o codigo recusa, e nao avisa.
     *
     * @return void
     */
    public function test_sem_https_o_modo_nativo_e_ignorado(): void {
        $this->resetAfterTest();

        set_config('cardcapture', card_capture::MODE_NATIVE, 'paygw_mercadopago');
        $this->set_https(false);

        $this->assertSame(
            card_capture::MODE_BRICK,
            card_capture::current(),
            'sem HTTPS o modo nativo nao pode valer, mesmo configurado'
        );
        $this->assertFalse(card_capture::native_is_allowed());
    }

    /**
     * Valor desconhecido na configuracao cai no modo seguro.
     *
     * @return void
     */
    public function test_valor_desconhecido_cai_no_modo_seguro(): void {
        $this->resetAfterTest();

        set_config('cardcapture', 'qualquer-coisa', 'paygw_mercadopago');
        $this->set_https(true);

        $this->assertSame(card_capture::MODE_BRICK, card_capture::current());
    }

    /**
     * A tabela do gateway nao tem onde guardar dado de cartao.
     *
     * Le o TEXTO do install.xml, e nao o banco: o que se quer impedir e alguem
     * ACRESCENTAR uma coluna dessas amanha. Um teste que consultasse o banco
     * passaria hoje e continuaria passando depois de a coluna existir, desde
     * que estivesse vazia - e coluna vazia hoje e coluna cheia no mes que vem.
     *
     * Vale nos dois modos de captura. No nativo o numero do cartao transita,
     * mas nao pode PARAR em lugar nenhum: nem em banco, nem em sessao, nem em
     * log. Persistir e o que transforma um incidente de rede num vazamento.
     *
     * @return void
     */
    public function test_nao_ha_coluna_capaz_de_guardar_cartao(): void {
        $xml = file_get_contents(__DIR__ . '/../db/install.xml');
        $this->assertNotFalse($xml);

        preg_match_all('/<FIELD NAME="([^"]+)"/', $xml, $achados);
        $colunas = array_map('strtolower', $achados[1]);

        $proibidas = [
            'cardnumber', 'card_number', 'pan', 'securitycode', 'security_code',
            'cvv', 'cvc', 'expirationmonth', 'expirationyear', 'cardholder',
        ];

        foreach ($proibidas as $proibida) {
            $this->assertNotContains(
                $proibida,
                $colunas,
                "a coluna '$proibida' guardaria dado de cartao, e isso nao entra neste banco"
            );
        }

        // As duas que EXISTEM, e por que sao legitimas: sao identificadores
        // devolvidos pela API, e nao o instrumento.
        $this->assertContains('mpcardid', $colunas);
        $this->assertContains('mpcustomerid', $colunas);
    }

    /**
     * Finge que a requisicao chegou por HTTPS, ou nao.
     *
     * O is_https() do core olha o wwwroot depois do sslproxy, entao mexer no
     * wwwroot e o jeito de exercitar os dois lados sem servidor web.
     *
     * @param bool $seguro
     * @return void
     */
    protected function set_https(bool $seguro): void {
        global $CFG;

        $CFG->wwwroot = ($seguro ? 'https' : 'http') . '://exemplo.test';
        unset($CFG->sslproxy);
    }
}
