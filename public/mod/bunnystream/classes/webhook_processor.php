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

use local_marketplace\library_account;
/**
 * Recebe o webhook de status da Bunny e atualiza o video.
 *
 * O plugin de referencia identificava a origem por um segredo SINGLETON (uma
 * instalacao, uma library). Aqui cada library tem o proprio segredo
 * (local_marketplace_library.webhooksecret) - o token na URL e como o
 * webhook diz de qual EMPRESA ele fala, sem depender de dominio ou IP.
 *
 * Tres guardas, na mesma ordem do plugin de referencia:
 *   1. Segredo bate com alguma library (comparacao em tempo constante).
 *   2. VideoLibraryId do payload bate com o bunnylibraryid daquela library.
 *   3. Estado terminal (ready/failed) nao regride.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_processor {
    /**
     * Processa um POST de webhook.
     *
     * @param string $token
     * @param string $rawbody
     * @return void
     */
    public static function process(string $token, string $rawbody): void {
        global $DB;

        $library = library_account::get_by_webhook_secret($token);
        if (!$library || !hash_equals((string) $library->get('webhooksecret'), $token)) {
            self::reject('unknown_token', 401, ['token_prefix' => substr($token, 0, 4)]);

            return;
        }

        $body = json_decode($rawbody, true);
        if (!is_array($body)) {
            self::reject('invalid_json', 400);

            return;
        }

        $guid = trim((string) ($body['VideoGuid'] ?? ''));
        $eventlib = $body['VideoLibraryId'] ?? null;
        $statuscode = $body['Status'] ?? null;

        if ($guid === '' || $eventlib === null || !is_int($statuscode)) {
            self::reject('malformed_payload', 400, [
                'has_guid' => $guid !== '',
                'has_lib' => $eventlib !== null,
                'status_type' => gettype($statuscode),
            ]);

            return;
        }

        $eventlibstr = (string) $eventlib;
        $librarybunnyid = (string) $library->get('bunnylibraryid');

        if (!hash_equals($librarybunnyid, $eventlibstr)) {
            self::reject('library_mismatch', 403, [
                'event_library_id' => $eventlibstr,
                'configured' => $librarybunnyid,
            ]);

            return;
        }

        $video = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
        if (!$video) {
            // Confirma para a Bunny parar de tentar de novo. Fica registrado
            // para operacao notar orfaos.
            self::ok('unknown_video');

            return;
        }

        if ($video->library_id !== $eventlibstr) {
            self::reject('tenant_mismatch', 403, [
                'event_library_id' => $eventlibstr,
                'row_library_id' => $video->library_id,
            ]);

            return;
        }

        $newstatus = bunny_client::map_status($statuscode);

        // Regressao de estado terminal: ready/failed nao volta atras.
        if (bunny_client::is_terminal_status($video->status) && $newstatus !== $video->status) {
            self::reject('terminal_state_regression', 200, [
                'guid' => $guid, 'current' => $video->status, 'incoming' => $newstatus,
            ]);

            return;
        }

        // Em transicao relevante, atualiza duracao e miniatura a partir da Bunny.
        if (
            $newstatus !== $video->status &&
            ($newstatus === bunny_client::STATUS_READY || $newstatus === bunny_client::STATUS_ENCODING)
        ) {
            try {
                $cfg = config::from_library($library);
                $client = new bunny_client($cfg);
                $meta = $client->get_video($guid);
                if ($meta) {
                    $thumb = bunny_client::thumbnail_url($cfg->cdn_hostname, $guid, $meta['thumbnailFileName'] ?? null);
                    if ($thumb) {
                        $video->thumbnail_url = $thumb;
                    }
                    $length = $meta['length'] ?? null;
                    if (is_numeric($length) && $length > 0) {
                        $video->duration_sec = (int) round($length);
                    }
                }
            } catch (\Throwable $e) {
                // Melhor esforco - o webhook continua com os metadados que
                // ja tem. A troca de status e o que importa de verdade.
                debugging('[bunny:webhook] meta_refresh_failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $video->status = $newstatus;
        $video->timemodified = time();
        $DB->update_record('bunnystream_videos', $video);

        // Espelha em qualquer atividade que aponte para este guid.
        $activities = $DB->get_records('bunnystream', ['guid' => $guid]);
        foreach ($activities as $a) {
            $a->status = $newstatus;
            if (!empty($video->duration_sec)) {
                $a->duration_sec = $video->duration_sec;
            }
            if (!empty($video->thumbnail_url)) {
                $a->thumbnail_url = $video->thumbnail_url;
            }
            $a->timemodified = time();
            $DB->update_record('bunnystream', $a);
        }

        self::ok();
    }

    /**
     * Recusa o webhook, com o motivo registrado para depuracao.
     *
     * @param string $reason
     * @param int $code
     * @param array $ctx
     * @return void
     */
    private static function reject(string $reason, int $code, array $ctx = []): void {
        debugging("[bunny:webhook] reject:{$reason} " . json_encode($ctx), DEBUG_DEVELOPER);
        if (!headers_sent()) {
            http_response_code($code);
        }
        echo json_encode(['error' => $reason]);
    }

    /**
     * Confirma o webhook.
     *
     * @param string|null $note
     * @return void
     */
    private static function ok(?string $note = null): void {
        if (!headers_sent()) {
            http_response_code(200);
        }
        $body = ['ok' => true];
        if ($note) {
            $body['note'] = $note;
        }
        echo json_encode($body);
    }
}
