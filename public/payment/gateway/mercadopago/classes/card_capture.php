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

/**
 * Onde o aluno digita o cartao - e o que cada escolha custa.
 *
 * A pagina de pagamento e NOSSA nos dois modos, com o nosso layout e a nossa
 * marca. O que muda e quem hospeda os tres campos do cartao, e essa diferenca
 * nao e de aparencia:
 *
 *   BRICK  - os campos sao iframes do Mercado Pago dentro da nossa pagina. O
 *            numero do cartao nao entra no nosso DOM nem no nosso servidor.
 *            O projeto fica FORA de escopo PCI (SAQ A).
 *
 *   NATIVE - o formulario e nosso, e o numero do cartao passa pelo nosso
 *            backend a caminho do Mercado Pago. Tecnicamente funciona - medido
 *            em 16/09/2026, POST /v1/card_tokens?public_key=... tokeniza a
 *            partir do servidor. O custo e entrar em PCI DSS SAQ D.
 *
 * O QUE O CODIGO GARANTE, E O QUE NAO GARANTE. Metade do SAQ D nao cabe num
 * plugin: varredura trimestral por scanner aprovado, teste de intrusao anual,
 * politicas formais e a atestacao entregue ao adquirente sao processo da
 * empresa, e nenhuma linha daqui os substitui. O que este arquivo entrega e a
 * parte que vive na aplicacao - e ela esta listada nos guardas abaixo.
 *
 * A PROPRIEDADE QUE TORNA A ESCOLHA ACEITAVEL e que o modo inseguro nao
 * depende de o administrador acertar a configuracao. Sem HTTPS ele nao vale, e
 * o codigo cai no modo seguro SOZINHO - porque "seguro desde que a
 * configuracao esteja certa" e o desenho que este projeto recusa.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_capture {
    /** @var string Campos do Mercado Pago na nossa pagina. O PAN nao nos toca. */
    const MODE_BRICK = 'brick';

    /** @var string Formulario nosso. O PAN transita pelo nosso backend. */
    const MODE_NATIVE = 'native';

    /** @var string[] Os modos que existem. */
    const MODES = [self::MODE_BRICK, self::MODE_NATIVE];

    /**
     * O modo que vale AGORA, nesta requisicao.
     *
     * Nao devolve o que esta configurado: devolve o que e seguro executar. A
     * diferenca aparece sem HTTPS, e e deliberada - uma caixa marcada por
     * engano mandaria numero de cartao em texto claro pela rede, e o sintoma
     * seria invisivel ate o vazamento.
     *
     * @return string Um dos MODES
     */
    public static function current(): string {
        $configurado = (string) get_config('paygw_mercadopago', 'cardcapture');

        if ($configurado !== self::MODE_NATIVE) {
            // Inclui o valor vazio e o desconhecido. Qualquer duvida cai no
            // modo que nao toca no cartao.
            return self::MODE_BRICK;
        }

        return self::native_is_allowed() ? self::MODE_NATIVE : self::MODE_BRICK;
    }

    /**
     * O modo nativo pode ser executado neste site?
     *
     * Existe separada de current() para que a TELA consiga dizer por que a
     * escolha do administrador nao esta valendo. Recusar em silencio seria
     * trocar um risco por uma confusao.
     *
     * @return bool
     */
    public static function native_is_allowed(): bool {
        global $CFG;

        require_once($CFG->libdir . '/weblib.php');

        // Sem TLS, o numero do cartao viajaria em texto claro entre o navegador
        // e o nosso servidor. Nao ha configuracao que compense isso.
        return is_https();
    }

    /**
     * O modo esta configurado mas nao pode valer?
     *
     * @return bool
     */
    public static function native_is_blocked(): bool {
        return (string) get_config('paygw_mercadopago', 'cardcapture') === self::MODE_NATIVE
            && !self::native_is_allowed();
    }
}
