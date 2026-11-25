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

/**
 * curl que nao sai da maquina.
 *
 * Sobrescreve TODOS os verbos que o pagarme_client usa. O fake_curl do
 * paygw_asaas nao cobre delete(), e por isso o teste de cancelamento de
 * assinatura de la chega a tentar uma requisicao real - passa por acidente,
 * porque so inspeciona a lista de chamadas.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_curl extends \curl {
    /** @var fake_pagarme_client Dono, de onde sai a resposta combinada. */
    protected fake_pagarme_client $owner;

    /**
     * Guarda o dono, de onde sai a resposta combinada.
     *
     * @param fake_pagarme_client $owner
     */
    public function __construct(fake_pagarme_client $owner) {
        parent::__construct();
        $this->owner = $owner;
        $this->error = 'erro simulado';
    }

    /**
     * Corpo que a chamada devolve.
     *
     * @return string
     */
    protected function body(): string {
        if ($this->owner->rawresponse !== null) {
            return $this->owner->rawresponse;
        }

        return (string) json_encode($this->owner->nextresponse);
    }

    /**
     * Devolve o corpo combinado, sem sair da maquina.
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
     * Devolve o corpo combinado, sem sair da maquina.
     *
     * @param string $url
     * @param string $params
     * @param array $options
     * @return string
     */
    public function post($url, $params = '', $options = []) {
        return $this->body();
    }

    /**
     * Devolve o corpo combinado, sem sair da maquina.
     *
     * @param string $url
     * @param string $params
     * @param array $options
     * @return string
     */
    public function patch($url, $params = '', $options = []) {
        return $this->body();
    }

    /**
     * Devolve o corpo combinado, sem sair da maquina.
     *
     * @param string $url
     * @param array|string $param
     * @param array $options
     * @return string
     */
    public function delete($url, $param = [], $options = []) {
        return $this->body();
    }

    /**
     * Erro de transporte combinado para a proxima chamada.
     *
     * @return int
     */
    public function get_errno() {
        return $this->owner->nexterrno;
    }

    /**
     * Codigo HTTP combinado para a proxima chamada.
     *
     * @return array
     */
    public function get_info() {
        return ['http_code' => $this->owner->nextstatus];
    }
}
