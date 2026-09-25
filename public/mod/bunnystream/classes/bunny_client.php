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

namespace mod_bunnystream;

/**
 * Unico ponto de contato com a API de VIDEO da Bunny (por library).
 *
 * Fala com a library de UMA empresa por vez - a chave vem de fora, no $cfg do
 * construtor (local_marketplace\library_account::get_api_key(), via
 * config::for_course()). Este cliente nao sabe o que e uma empresa; quem
 * resolve o tenant e a camada acima (config.php).
 *
 * Fork de amirtds/moodle-mod_bunnystream, adaptado so na resolucao de
 * credenciais - o CRUD de video contra a API da Bunny e o mesmo.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bunny_client {
    /** @var string Base da API de video da Bunny. */
    public const BUNNY_BASE = 'https://video.bunnycdn.com';

    /** @var string Base do embed assinado. */
    public const BUNNY_EMBED_BASE = 'https://iframe.mediadelivery.net/embed';

    /** @var string Video enviado, ainda sem processar. */
    public const STATUS_PENDING  = 'pending';

    /** @var string Upload concluido, aguardando encoding. */
    public const STATUS_UPLOADED = 'uploaded';

    /** @var string Bunny esta codificando as trilhas. */
    public const STATUS_ENCODING = 'encoding';

    /** @var string Pronto para reproducao. */
    public const STATUS_READY    = 'ready';

    /** @var string Encoding falhou. */
    public const STATUS_FAILED   = 'failed';

    /** @var \stdClass Credenciais em claro, da library desta chamada. */
    private $cfg;

    /**
     * Construtor.
     *
     * @param \stdClass $cfg library_id, api_key, cdn_hostname, security_key
     */
    public function __construct(\stdClass $cfg) {
        $this->cfg = $cfg;
    }

    /**
     * Chamada HTTP contra a API de video.
     *
     * @param string $method
     * @param string $path
     * @param array|null $jsonbody
     * @param string|null $rawbody
     * @param string|null $rawcontenttype
     * @return array code, body, info
     */
    private function request(
        string $method,
        string $path,
        ?array $jsonbody = null,
        ?string $rawbody = null,
        ?string $rawcontenttype = null
    ): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $url = self::BUNNY_BASE . $path;
        $curl = $this->make_curl();
        $headers = [
            'AccessKey: ' . $this->cfg->api_key,
            'Accept: application/json',
        ];
        $options = ['CURLOPT_TIMEOUT' => 30, 'CURLOPT_CONNECTTIMEOUT' => 10];
        $body = null;
        if ($jsonbody !== null) {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($jsonbody);
        } else if ($rawbody !== null) {
            $headers[] = 'Content-Type: ' . ($rawcontenttype ?: 'application/octet-stream');
            $body = $rawbody;
        }
        $curl->setHeader($headers);
        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url, [], $options);
                break;
            case 'POST':
                $response = $curl->post($url, $body, $options);
                break;
            case 'PUT':
                $curl->setopt(['CURLOPT_CUSTOMREQUEST' => 'PUT']);
                $response = $curl->post($url, $body, $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, [], $options);
                break;
            default:
                throw new bunny_api_error(500, 'Unsupported method: ' . $method);
        }
        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);

        return ['code' => $code, 'body' => (string) $response, 'info' => $info];
    }

    /**
     * Instancia o transporte.
     *
     * A costura de teste, no mesmo padrao do bunny_platform_client do
     * local_marketplace: quem estende so precisa devolver algo que se
     * comporte como \curl.
     *
     * @return \curl
     */
    protected function make_curl(): \curl {
        return new \curl();
    }

    /**
     * Cria um video vazio na library, pronto para receber o upload TUS.
     *
     * @param string $title
     * @return string guid
     */
    public function create_video(string $title): string {
        $res = $this->request('POST', "/library/{$this->cfg->library_id}/videos", ['title' => $title]);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny createVideo failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
        $data = json_decode($res['body'], true);
        $guid = $data['guid'] ?? '';
        if (!$guid) {
            throw new bunny_api_error(502, 'Bunny createVideo returned no guid');
        }

        return $guid;
    }

    /**
     * Metadados de um video.
     *
     * @param string $guid
     * @return array|null Nulo quando nao existe (mais) na Bunny.
     */
    public function get_video(string $guid): ?array {
        $res = $this->request('GET', "/library/{$this->cfg->library_id}/videos/{$guid}");
        if ($res['code'] === 404) {
            return null;
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny getVideo failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }

        return json_decode($res['body'], true);
    }

    /**
     * Atualiza titulo e/ou capitulos.
     *
     * @param string $guid
     * @param string|null $title
     * @param array|null $chapters
     * @return void
     */
    public function update_video(string $guid, ?string $title = null, ?array $chapters = null): void {
        $payload = [];
        if ($title !== null) {
            $payload['title'] = $title;
        }
        if ($chapters !== null) {
            $payload['chapters'] = $chapters;
        }
        if (empty($payload)) {
            return;
        }
        $res = $this->request('POST', "/library/{$this->cfg->library_id}/videos/{$guid}", $payload);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny updateVideo failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    /**
     * Apaga um video na Bunny.
     *
     * @param string $guid
     * @return void
     */
    public function delete_video(string $guid): void {
        $res = $this->request('DELETE', "/library/{$this->cfg->library_id}/videos/{$guid}");
        if (($res['code'] < 200 || $res['code'] >= 300) && $res['code'] !== 404) {
            throw new bunny_api_error($res['code'], "Bunny deleteVideo failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    /**
     * Legendas cadastradas num video.
     *
     * @param string $guid
     * @return array
     */
    public function list_captions(string $guid): array {
        $meta = $this->get_video($guid);
        if (!$meta) {
            return [];
        }
        $captions = $meta['captions'] ?? [];
        $out = [];
        foreach ($captions as $c) {
            if (!is_array($c)) {
                continue;
            }
            $out[] = [
                'srclang' => $c['srclang'] ?? $c['Srclang'] ?? '',
                'label'   => $c['label'] ?? $c['Label'] ?? '',
            ];
        }

        return $out;
    }

    /**
     * Envia um arquivo .vtt de legenda.
     *
     * @param string $guid
     * @param string $srclang
     * @param string $label
     * @param string $vttbytes
     * @return void
     */
    public function upload_caption(string $guid, string $srclang, string $label, string $vttbytes): void {
        $payload = [
            'srclang'      => $srclang,
            'label'        => $label ?: strtoupper($srclang),
            'captionsFile' => base64_encode($vttbytes),
        ];
        $res = $this->request('POST', "/library/{$this->cfg->library_id}/videos/{$guid}/captions/{$srclang}", $payload);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny uploadCaption failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    /**
     * Remove uma legenda.
     *
     * @param string $guid
     * @param string $srclang
     * @return void
     */
    public function delete_caption(string $guid, string $srclang): void {
        $res = $this->request('DELETE', "/library/{$this->cfg->library_id}/videos/{$guid}/captions/{$srclang}");
        if (($res['code'] < 200 || $res['code'] >= 300) && $res['code'] !== 404) {
            throw new bunny_api_error($res['code'], "Bunny deleteCaption failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    /**
     * Pede transcricao automatica de audio para a Bunny.
     *
     * @param string $guid
     * @param string $language
     * @param bool $force
     * @return void
     */
    public function transcribe(string $guid, string $language = 'en', bool $force = false): void {
        $qs = http_build_query(['language' => $language, 'force' => $force ? 'true' : 'false']);
        $res = $this->request('POST', "/library/{$this->cfg->library_id}/videos/{$guid}/transcribe?{$qs}");
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny transcribe failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    /**
     * Capitulos de um video.
     *
     * @param string $guid
     * @return array
     */
    public function get_chapters(string $guid): array {
        $meta = $this->get_video($guid);
        if (!$meta) {
            return [];
        }
        $chapters = $meta['chapters'] ?? [];
        $out = [];
        foreach ($chapters as $ch) {
            if (!is_array($ch)) {
                continue;
            }
            $out[] = [
                'title' => trim((string) ($ch['title'] ?? $ch['Title'] ?? '')),
                'start' => (int) ($ch['start'] ?? $ch['Start'] ?? 0),
                'end'   => (int) ($ch['end'] ?? $ch['End'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Substitui a lista de capitulos.
     *
     * @param string $guid
     * @param array $chapters
     * @return void
     */
    public function set_chapters(string $guid, array $chapters): void {
        $this->update_video($guid, null, $chapters);
    }

    /**
     * Envia uma miniatura customizada.
     *
     * @param string $guid
     * @param string $imagebytes
     * @param string $contenttype
     * @return void
     */
    public function set_thumbnail(string $guid, string $imagebytes, string $contenttype): void {
        $res = $this->request(
            'POST',
            "/library/{$this->cfg->library_id}/videos/{$guid}/thumbnail",
            null,
            $imagebytes,
            $contenttype ?: 'application/octet-stream'
        );
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny setThumbnail failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    /**
     * Traduz o codigo numerico da Bunny para o status interno do plugin.
     *
     * @param mixed $code
     * @return string
     */
    public static function map_status($code): string {
        if ($code === 0) {
            return self::STATUS_PENDING;
        }
        if ($code === 1) {
            return self::STATUS_UPLOADED;
        }
        if (in_array($code, [2, 3, 7], true)) {
            return self::STATUS_ENCODING;
        }
        if (in_array($code, [4, 8], true)) {
            return self::STATUS_READY;
        }
        if (in_array($code, [5, 6], true)) {
            return self::STATUS_FAILED;
        }

        return self::STATUS_PENDING;
    }

    /**
     * O status e final (nao regride)?
     *
     * @param string $status
     * @return bool
     */
    public static function is_terminal_status(string $status): bool {
        return $status === self::STATUS_READY || $status === self::STATUS_FAILED;
    }

    /**
     * Monta a URL da miniatura a partir do hostname da CDN.
     *
     * @param string|null $cdnhostname
     * @param string $guid
     * @param string|null $filename
     * @return string|null
     */
    public static function thumbnail_url(?string $cdnhostname, string $guid, ?string $filename = null): ?string {
        if (!$cdnhostname) {
            return null;
        }
        $host = rtrim(preg_replace('#^https?://#', '', $cdnhostname), '/');
        $name = $filename ?: 'thumbnail.jpg';

        return "https://{$host}/{$guid}/{$name}";
    }
}
