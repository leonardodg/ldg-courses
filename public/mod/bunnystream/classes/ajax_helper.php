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
 * Bootstrap compartilhado dos endpoints AJAX de /mod/bunnystream/ajax/.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ajax_helper {
    /** @var array|null Cache do corpo JSON lido, para nao consumir o stream duas vezes. */
    private static ?array $jsonbodycache = null;

    /**
     * Devolve JSON e encerra.
     *
     * @param mixed $payload
     * @param int $code
     * @return void
     */
    public static function json($payload, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    /**
     * Devolve um erro em JSON e encerra.
     *
     * @param string $message
     * @param int $code
     * @return void
     */
    public static function fail(string $message, int $code = 400): void {
        self::json(['error' => $message], $code);
    }

    /**
     * Le o corpo JSON de php://input. Vazio se ausente.
     *
     * @return array
     */
    public static function read_json_body(): array {
        if (self::$jsonbodycache !== null) {
            return self::$jsonbodycache;
        }
        $raw = file_get_contents('php://input');
        if (!$raw) {
            self::$jsonbodycache = [];

            return [];
        }
        $decoded = json_decode($raw, true);
        self::$jsonbodycache = is_array($decoded) ? $decoded : [];

        return self::$jsonbodycache;
    }

    /**
     * O `courseid` mandado pelo cliente, do corpo JSON ou da query string.
     *
     * Todo endpoint que mexe em video precisa saber de qual CURSO ele fala -
     * e do curso que se chega na empresa (company::for_course()) e dai na
     * library. Sem isto nao haveria como saber de qual empresa e a chamada.
     *
     * @return int
     */
    public static function require_courseid(): int {
        $courseid = optional_param('courseid', 0, PARAM_INT);
        if (!$courseid) {
            $body = self::read_json_body();
            $courseid = (int) ($body['courseid'] ?? 0);
        }
        if (!$courseid) {
            self::fail('missing_courseid', 400);
        }

        return $courseid;
    }

    /**
     * Confirma sessao valida e devolve as credenciais da empresa dona do
     * curso. Usado pelos endpoints de LEITURA (sem exigir capacidade de
     * gerenciar).
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function require_view(int $courseid): \stdClass {
        require_login($courseid, false);

        return self::resolve_cfg($courseid);
    }

    /**
     * Confirma sessao, sesskey e capacidade de gerenciar video no curso, e
     * devolve as credenciais da empresa dona dele.
     *
     * A capacidade e checada no CONTEXTO DO CURSO porque um video pode ser
     * criado (mint) antes de a atividade existir - nao ha course_module
     * ainda para checar no nivel certo. `mod/bunnystream:addinstance` ja e
     * CONTEXT_COURSE por desenho, e quem tem o papel de vendedor/dono da
     * empresa (atribuido na CATEGORIA, que e pai do curso) herda a
     * capacidade por cima na arvore de contexto - sem logica de tenant
     * duplicada aqui.
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function require_manage(int $courseid): \stdClass {
        require_login($courseid, false);

        $sesskey = optional_param('sesskey', '', PARAM_RAW);
        if ($sesskey === '') {
            $body = self::read_json_body();
            $sesskey = $body['sesskey'] ?? '';
        }
        if (!confirm_sesskey($sesskey ?: null)) {
            self::fail('invalid_sesskey', 403);
        }

        require_capability('mod/bunnystream:addinstance', \context_course::instance($courseid));

        return self::resolve_cfg($courseid);
    }

    /**
     * Resolve as credenciais, traduzindo "sem library ainda" num JSON de
     * erro em vez de deixar a excecao subir crua para o cliente AJAX.
     *
     * @param int $courseid
     * @return \stdClass
     */
    private static function resolve_cfg(int $courseid): \stdClass {
        try {
            return config::for_course($courseid);
        } catch (not_configured_exception $e) {
            self::fail($e->getMessage(), 503);
        }

        return new \stdClass();
    }

    /**
     * Limitador de taxa por processo, apoiado em MUC.
     *
     * @param string $bucket
     * @param int $maxperwindow
     * @param int $windowseconds
     * @return bool
     */
    public static function rate_ok(string $bucket, int $maxperwindow, int $windowseconds = 60): bool {
        $cache = \cache::make('mod_bunnystream', 'ratelimit');
        $key = 'rl_' . md5($bucket);
        $now = time();
        $row = $cache->get($key);
        if (!$row || $row['reset_at'] <= $now) {
            $cache->set($key, ['count' => 1, 'reset_at' => $now + $windowseconds]);

            return true;
        }
        if ($row['count'] >= $maxperwindow) {
            return false;
        }
        $row['count']++;
        $cache->set($key, $row);

        return true;
    }

    /**
     * Serializa uma linha de video para o formato que o AMD espera.
     *
     * @param \stdClass $row
     * @return array
     */
    public static function serialize_video(\stdClass $row): array {
        return [
            'guid'          => $row->guid,
            'library_id'    => $row->library_id,
            'title'         => $row->title,
            'status'        => $row->status,
            'duration_sec'  => (int) $row->duration_sec,
            'thumbnail_url' => $row->thumbnail_url ?: null,
        ];
    }
}
