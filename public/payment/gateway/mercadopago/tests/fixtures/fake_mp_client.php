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

/**
 * Cliente do Mercado Pago com o transporte substituido, para os testes.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_mercadopago;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fake_curl.php');

/**
 * Cliente que fala com um transporte falso.
 *
 * Entra pela costura make_curl(). O roteiro e ESTATICO, e nao de instancia,
 * porque metade da superficie do mp_client e estatica: exchange_code() e
 * refresh_token() nao tem instancia para carregar o roteiro. Um roteiro de
 * instancia cobriria a preferencia e deixaria o OAuth de fora, que e justamente
 * o fluxo que autoriza o split.
 *
 * Chame reset() no setUp(): estado estatico atravessa teste.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_mp_client extends mp_client {
    /** @var array Resposta que a proxima chamada devolve. */
    public static array $nextresponse = [];

    /** @var array Fila de respostas, uma por chamada, para fluxos com mais de
     * uma requisicao - guess_payment_method() faz duas (busca por BIN,
     * emissor). Vazia, cai em $nextresponse para todas as chamadas. */
    public static array $responsequeue = [];

    /** @var string|null Resposta crua, quando o teste quer algo que nao e JSON. */
    public static ?string $rawresponse = null;

    /** @var int Codigo HTTP da proxima resposta. */
    public static int $nextstatus = 200;

    /** @var int[] Fila de codigos HTTP, um por chamada - anda junto de
     * $responsequeue. Vazia, cai em $nextstatus para todas as chamadas. */
    public static array $statusqueue = [];

    /** @var int Erro de curl da proxima chamada. 0 = sem erro. */
    public static int $nexterrno = 0;

    /** @var array Corpo do ultimo POST, ja decodificado. */
    public static array $lastbody = [];

    /** @var string[] Cabecalhos da ultima chamada. */
    public static array $lastheaders = [];

    /** @var array Uma entrada por chamada: [metodo, url]. */
    public static array $calls = [];

    /**
     * Devolve o roteiro ao estado inicial.
     *
     * @return void
     */
    public static function reset(): void {
        self::$nextresponse = [];
        self::$responsequeue = [];
        self::$rawresponse = null;
        self::$nextstatus = 200;
        self::$statusqueue = [];
        self::$nexterrno = 0;
        self::$lastbody = [];
        self::$lastheaders = [];
        self::$calls = [];
    }

    /**
     * Devolve o transporte falso.
     *
     * @return \curl
     */
    protected static function make_curl(): \curl {
        return new fake_curl();
    }
}
