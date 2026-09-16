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

/**
 * A assinatura do webhook do Mercado Pago.
 *
 * O endpoint ja se defendia consultando o pagamento de volta na API - o status
 * nunca vem do corpo. A assinatura acrescenta a outra metade: impedir que
 * alguem faca o nosso servidor consultar ids arbitrarios.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_mercadopago\webhook_signature::class)]
final class webhook_signature_test extends \advanced_testcase {
    /**
     * A assinatura confere quando o segredo e o manifesto batem.
     *
     * O manifesto do Mercado Pago tem ordem e pontuacao fixas:
     * id:<data.id>;request-id:<x-request-id>;ts:<ts>;
     * Qualquer variacao muda o HMAC, e a notificacao legitima seria recusada.
     *
     * @return void
     */
    public function test_assinatura_valida_e_aceita(): void {
        $this->resetAfterTest();

        $segredo = 'segredo-do-painel';
        $ts = '1789000000';
        $manifesto = 'id:123;request-id:req-1;ts:' . $ts . ';';
        $v1 = hash_hmac('sha256', $manifesto, $segredo);

        $this->assertTrue(webhook_signature::is_valid(
            "ts=$ts,v1=$v1",
            'req-1',
            '123',
            $segredo
        ));
    }

    /**
     * Assinatura de outro segredo e recusada.
     *
     * @return void
     */
    public function test_assinatura_de_outro_segredo_e_recusada(): void {
        $this->resetAfterTest();

        $ts = '1789000000';
        $v1 = hash_hmac('sha256', 'id:123;request-id:req-1;ts:' . $ts . ';', 'outro-segredo');

        $this->assertFalse(webhook_signature::is_valid("ts=$ts,v1=$v1", 'req-1', '123', 'segredo-do-painel'));
    }

    /**
     * Trocar o id do pagamento invalida a assinatura.
     *
     * E o que a assinatura protege: sem ela, qualquer um POSTaria o id de um
     * pagamento alheio no nosso endpoint.
     *
     * @return void
     */
    public function test_trocar_o_id_invalida_a_assinatura(): void {
        $this->resetAfterTest();

        $segredo = 'segredo-do-painel';
        $ts = '1789000000';
        $v1 = hash_hmac('sha256', 'id:123;request-id:req-1;ts:' . $ts . ';', $segredo);

        $this->assertFalse(webhook_signature::is_valid("ts=$ts,v1=$v1", 'req-1', '999', $segredo));
    }

    /**
     * Cabecalho malformado nao passa, e nao estoura.
     *
     * @return void
     */
    public function test_cabecalho_malformado_nao_passa(): void {
        $this->resetAfterTest();

        foreach (['', 'lixo', 'ts=1', 'v1=abc', 'ts=,v1='] as $cabecalho) {
            $this->assertFalse(
                webhook_signature::is_valid($cabecalho, 'req-1', '123', 'segredo'),
                "cabecalho: '$cabecalho'"
            );
        }
    }

    /**
     * SEM SEGREDO CONFIGURADO, a validacao e pulada - e isso e deliberado.
     *
     * Recusar tudo faria a atualizacao do plugin derrubar o webhook de quem ja
     * estava no ar, e o sintoma seria aluno pagando e nao recebendo acesso - o
     * pior desfecho deste plugin. A defesa que resta e a que sempre existiu, e
     * nao e pequena: o status NUNCA vem do corpo, vem de uma consulta a API.
     *
     * @return void
     */
    public function test_sem_segredo_a_validacao_e_pulada(): void {
        $this->resetAfterTest();

        $this->assertTrue(webhook_signature::is_valid('qualquer-coisa', 'req-1', '123', ''));
    }

    /**
     * A comparacao do HMAC nao vaza tempo.
     *
     * Comparacao de string sai no primeiro byte diferente, e o tempo de
     * resposta entrega quantos bytes o atacante acertou. O teste le a LINHA que
     * compara: procurar "===" no arquivo inteiro reprovaria a checagem legitima
     * de campo vazio - foi o que aconteceu ao escrever este teste.
     *
     * @return void
     */
    public function test_a_comparacao_do_hmac_usa_hash_equals(): void {
        $fonte = file_get_contents(__DIR__ . '/../classes/webhook_signature.php');
        $linhas = array_filter(
            explode("\n", $fonte),
            static fn(string $linha): bool => str_contains($linha, 'hash_hmac')
                && !str_contains(trim($linha), '*')
        );

        $this->assertNotEmpty($linhas, 'a classe precisa calcular o HMAC');

        foreach ($linhas as $linha) {
            $this->assertStringContainsString(
                'hash_equals',
                $linha,
                'o HMAC calculado tem que ser comparado com hash_equals, na mesma expressao'
            );
        }
    }
}
