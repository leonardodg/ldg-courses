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

use curl;
use moodle_exception;

/**
 * Unico ponto de contato com a API de CONTA da Bunny (nao a API de library).
 *
 * So sabe fazer uma coisa: criar uma library nova dentro da conta unica da
 * plataforma. Qualquer chamada por library (upload, video, embed) e do
 * plugin de player (mod_bunnystream, proximo sub-passo), com a chave DA
 * LIBRARY - nunca com a chave de conta que este cliente usa.
 *
 * A costura de transporte - make_curl() - existe de proposito, no mesmo
 * padrao do asaas_client deste projeto: um teste estende a classe, devolve
 * um transporte falso e exercita o corpo da chamada e o mapeamento de erro
 * sem tocar na rede.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bunny_platform_client {
    /** @var string Base da API de conta da Bunny. */
    public const BASE_URL = 'https://api.bunny.net';

    /** @var int Segundos de espera por resposta. */
    public const TIMEOUT = 20;

    /** @var int Segundos de espera pela conexao. */
    public const CONNECT_TIMEOUT = 10;

    /** @var string Chave de API de CONTA da plataforma. */
    protected string $accountapikey;

    /**
     * Construtor.
     *
     * @param string $accountapikey Chave de conta, em claro.
     */
    public function __construct(string $accountapikey) {
        $this->accountapikey = $accountapikey;
    }

    /** @var string[] Escada de resolucao da Bunny, da menor para a maior. */
    public const RESOLUTION_LADDER = ['240p', '360p', '480p', '720p', '1080p', '1440p', '2160p'];

    /** @var array<string,string> Nome do teto no vocabulario do plano -> nome na Bunny. '4k' e '2160p' na Bunny. */
    public const CAP_TO_BUNNY_RESOLUTION = [
        '720p' => '720p',
        '1080p' => '1080p',
        '1440p' => '1440p',
        '4k' => '2160p',
    ];

    /**
     * Cria uma library nova, para uma empresa, ja com o teto de resolucao do
     * plano dela.
     *
     * Devolve so bunnylibraryid e apikey - e so o que a Bunny devolve na
     * criacao, verificado ao vivo em 25/09/2026 contra a API real. Chave de
     * autenticacao por token (securitykey) e o hostname da pull zone
     * (cdnhostname) exigem passos a mais - habilitar
     * PlayerTokenAuthenticationEnabled e resolver o PullZoneId por outra
     * chamada -, que ficam para quando o player tiver consumidor de verdade
     * para eles.
     *
     * `EnabledResolutions` no corpo da criacao funciona - verificado ao vivo
     * em 25/09/2026: nao precisa de uma segunda chamada de update depois.
     *
     * @param string $name Nome de exibicao da library no painel da Bunny.
     * @param string $enabledresolutions CSV no vocabulario da Bunny, ex.: "240p,360p,480p,720p".
     * @return array bunnylibraryid, apikey
     */
    public function create_library(string $name, string $enabledresolutions): array {
        $response = $this->request('POST', '/videolibrary', [
            'Name' => $name,
            'EnabledResolutions' => $enabledresolutions,
        ]);

        $id = (int) ($response['Id'] ?? 0);
        if ($id <= 0) {
            throw new moodle_exception('errorbunnyapi', 'local_marketplace', '', 'resposta sem Id de library');
        }

        return [
            'bunnylibraryid' => $id,
            'apikey' => (string) ($response['ApiKey'] ?? ''),
        ];
    }

    /**
     * Atualiza o teto de resolucao de uma library existente - usado quando o
     * plano da empresa muda depois da library ja criada.
     *
     * @param int $bunnylibraryid
     * @param string $enabledresolutions CSV no vocabulario da Bunny.
     * @return void
     */
    public function update_library_resolutions(int $bunnylibraryid, string $enabledresolutions): void {
        $this->request('POST', "/videolibrary/{$bunnylibraryid}", [
            'EnabledResolutions' => $enabledresolutions,
        ]);
    }

    /**
     * Traduz o teto do plano (vocabulario de local_marketplace\plan_tier)
     * para o CSV que a Bunny entende, sempre incluindo as faixas baixas
     * (240p/360p/480p) - elas nao sao o que o degrau comercial protege, sao
     * o que faz o streaming adaptativo funcionar em conexao fraca em
     * qualquer plano.
     *
     * @param string|null $cap 720p|1080p|1440p|4k, ou nulo para nao travar
     *                         (BYOS - a plataforma nao paga a banda).
     * @return string
     */
    public static function enabled_resolutions_for_cap(?string $cap): string {
        if ($cap === null) {
            return implode(',', self::RESOLUTION_LADDER);
        }

        $bunnycap = self::CAP_TO_BUNNY_RESOLUTION[$cap] ?? end(self::RESOLUTION_LADDER);
        $index = array_search($bunnycap, self::RESOLUTION_LADDER, true);

        return implode(',', array_slice(self::RESOLUTION_LADDER, 0, $index + 1));
    }

    /**
     * Chamada HTTP.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @return array
     */
    protected function request(string $method, string $path, ?array $body = null): array {
        $url = self::BASE_URL . $path;

        $curl = $this->make_curl();
        $curl->setHeader([
            'AccessKey: ' . $this->accountapikey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $options = [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT,
            'CURLOPT_RETURNTRANSFER' => 1,
        ];

        if ($method === 'GET') {
            $response = $curl->get($url, [], $options);
        } else {
            $response = $curl->post($url, json_encode($body ?? []), $options);
        }

        return $this->decode($curl, $response);
    }

    /**
     * Instancia o transporte.
     *
     * A costura de teste. Nao ha logica aqui de proposito: quem estende so
     * precisa devolver algo que se comporte como \curl.
     *
     * @return curl
     */
    protected function make_curl(): curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        return new curl();
    }

    /**
     * Interpreta a resposta.
     *
     * @param curl $curl
     * @param string|bool $response
     * @return array
     */
    protected function decode(curl $curl, $response): array {
        if ($curl->get_errno()) {
            throw new moodle_exception('errorcurl', 'local_marketplace', '', $curl->error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new moodle_exception('errorinvalidresponse', 'local_marketplace');
        }

        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['Message'] ?? $decoded['message'] ?? "HTTP {$status}");
            throw new moodle_exception('errorbunnyapi', 'local_marketplace', '', $message);
        }

        return $decoded;
    }
}
