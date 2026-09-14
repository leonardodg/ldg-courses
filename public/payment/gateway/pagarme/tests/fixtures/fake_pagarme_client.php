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

namespace paygw_pagarme;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fake_curl.php');

/**
 * Cliente com o transporte trocado.
 *
 * O request() aqui e um gravador que AINDA chama o parent: montagem de corpo,
 * cabecalho e decode() continuam executando de verdade. So o transporte e
 * falso - se o dublê substituisse request() inteiro, o teste passaria a medir
 * o dublê.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_pagarme_client extends pagarme_client {
    /** @var array Corpo decodificado que a proxima chamada devolve. */
    public array $nextresponse = [];

    /** @var string|null Corpo cru, para o caso de resposta que nao e JSON. */
    public ?string $rawresponse = null;

    /** @var int Codigo HTTP da proxima resposta. */
    public int $nextstatus = 200;

    /** @var int errno do curl. Zero e sem erro. */
    public int $nexterrno = 0;

    /** @var array Corpo do ultimo POST/PATCH. */
    public array $lastbody = [];

    /** @var array Uma entrada [metodo, caminho] por chamada. */
    public array $calls = [];

    /**
     * Devolve o curl falso.
     *
     * @return \curl
     */
    protected function make_curl(): \curl {
        return new fake_curl($this);
    }

    /**
     * Grava a chamada e segue para o metodo de verdade.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @return array
     */
    protected function request(string $method, string $path, ?array $body = null): array {
        $this->calls[] = [$method, $path];
        if ($body !== null) {
            $this->lastbody = $body;
        }

        return parent::request($method, $path, $body);
    }
}
