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
 * Transporte falso para os testes do paygw_mercadopago.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_mercadopago;

/**
 * Um \curl que devolve o que o teste mandar, sem tocar na rede.
 *
 * O registro da chamada acontece AQUI, e nao no cliente, porque o mp_client
 * tem duas portas de saida: o request() de instancia e o post_json() estatico
 * do fluxo OAuth. Anotando no transporte, as duas caem no mesmo lugar e nao ha
 * como um caminho passar sem ser visto.
 *
 * Nao sobrescreve setHeader: o comportamento do pai serve, e sobrescrever
 * exigiria um nome em camelCase que o phpcs do Moodle recusa.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_curl extends \curl {
    /**
     * Construtor.
     */
    public function __construct() {
        parent::__construct();
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
        fake_mp_client::$calls[] = ['GET', $url];

        return $this->body();
    }

    /**
     * POST simulado.
     *
     * O corpo chega serializado, como o mp_client o manda. Guardamos decodificado
     * porque e sobre ele que o teste afirma - o marketplace_fee, o test_token, o
     * code_verifier.
     *
     * @param string $url
     * @param string|array $params
     * @param array $options
     * @return string
     */
    public function post($url, $params = '', $options = []) {
        fake_mp_client::$calls[] = ['POST', $url];
        fake_mp_client::$lastbody = is_string($params)
            ? (array) json_decode($params, true)
            : (array) $params;

        return $this->body();
    }

    /**
     * PUT simulado.
     *
     * Existe separado do post() para que o teste consiga AFIRMAR o verbo. Nao e
     * detalhe: alterar assinatura e PUT /preapproval/{id}, e um POST no mesmo
     * caminho CRIA outra assinatura - o aluno passaria a ser cobrado duas
     * vezes, sem erro nenhum aparecendo.
     *
     * @param string $url
     * @param string|array $params
     * @param array $options
     * @return string
     */
    public function put($url, $params = [], $options = []) {
        fake_mp_client::$calls[] = ['PUT', $url];
        fake_mp_client::$lastbody = is_string($params)
            ? (array) json_decode($params, true)
            : (array) $params;

        return $this->body();
    }

    /**
     * Erro de transporte simulado.
     *
     * @return int
     */
    public function get_errno() {
        return fake_mp_client::$nexterrno;
    }

    /**
     * Informacoes da resposta.
     *
     * @return array
     */
    public function get_info() {
        return ['http_code' => fake_mp_client::$nextstatus];
    }

    /**
     * Corpo da resposta.
     *
     * @return string
     */
    protected function body(): string {
        if (fake_mp_client::$rawresponse !== null) {
            return fake_mp_client::$rawresponse;
        }

        if (fake_mp_client::$responsequeue) {
            return json_encode(array_shift(fake_mp_client::$responsequeue));
        }

        return json_encode(fake_mp_client::$nextresponse);
    }
}
