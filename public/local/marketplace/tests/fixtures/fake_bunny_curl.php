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

/**
 * Transporte falso para os testes do bunny_platform_client.
 *
 * Mesmo padrao do fake_curl do paygw_asaas: nao sobrescreve setHeader, o
 * comportamento do pai serve.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_bunny_curl extends \curl {
    /** @var fake_bunny_platform_client Quem guarda o roteiro da resposta. */
    protected fake_bunny_platform_client $owner;

    /**
     * Construtor.
     *
     * @param fake_bunny_platform_client $owner
     */
    public function __construct(fake_bunny_platform_client $owner) {
        parent::__construct();
        $this->owner = $owner;
        $this->error = 'erro simulado';
    }

    /**
     * GET simulado.
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return string
     */
    public function get($url, $params = [], $options = []) {
        return $this->body();
    }

    /**
     * POST simulado.
     *
     * @param string $url
     * @param string|array $params
     * @param array $options
     * @return string
     */
    public function post($url, $params = '', $options = []) {
        return $this->body();
    }

    /**
     * Erro de transporte simulado.
     *
     * @return int
     */
    public function get_errno() {
        return $this->owner->nexterrno;
    }

    /**
     * Informacoes da resposta.
     *
     * @return array
     */
    public function get_info() {
        return ['http_code' => $this->owner->nextstatus];
    }

    /**
     * Corpo da resposta.
     *
     * @return string
     */
    protected function body(): string {
        return json_encode($this->owner->nextresponse);
    }
}
