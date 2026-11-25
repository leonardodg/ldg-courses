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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fake_bunny_curl.php');

/**
 * Cliente da Bunny que fala com um transporte falso, para os testes.
 *
 * Entra pela costura make_curl(), no mesmo padrao do fake_asaas_client deste
 * projeto.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_bunny_platform_client extends bunny_platform_client {
    /** @var array Resposta que a proxima chamada devolve. */
    public array $nextresponse = [];

    /** @var int Codigo HTTP da proxima resposta. */
    public int $nextstatus = 200;

    /** @var int Erro de curl da proxima chamada. 0 = sem erro. */
    public int $nexterrno = 0;

    /** @var array Corpo do ultimo POST. */
    public array $lastbody = [];

    /** @var array Uma entrada por chamada: [metodo, caminho]. */
    public array $calls = [];

    /**
     * Construtor sem exigir chave real.
     */
    public function __construct() {
        parent::__construct('chave-de-teste');
    }

    /**
     * Devolve o transporte falso.
     *
     * @return \curl
     */
    protected function make_curl(): \curl {
        return new fake_bunny_curl($this);
    }

    /**
     * Registra a chamada e guarda o corpo.
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
