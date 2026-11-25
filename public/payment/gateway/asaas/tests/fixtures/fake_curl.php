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
 * Transporte falso para os testes do paygw_asaas.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_asaas;

/**
 * Um \curl que devolve o que o teste mandar, sem tocar na rede.
 *
 * Nao sobrescreve setHeader: o comportamento do pai serve, e sobrescrever
 * exigiria um nome em camelCase que o phpcs do Moodle recusa.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_curl extends \curl {
    /** @var fake_asaas_client Quem guarda o roteiro da resposta. */
    protected fake_asaas_client $owner;

    /**
     * Construtor.
     *
     * @param fake_asaas_client $owner
     */
    public function __construct(fake_asaas_client $owner) {
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
        if ($this->owner->rawresponse !== null) {
            return $this->owner->rawresponse;
        }

        return json_encode($this->owner->nextresponse);
    }
}
