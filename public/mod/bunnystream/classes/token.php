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
 * Upload TUS e assinatura de embed da Bunny.
 *
 * Formulas ditadas pela Bunny - nao reordene os campos nem troque separador.
 * Sem mudanca de tenant aqui: a chave e sempre a da library de UM curso,
 * passada pelo chamador (config::for_course()).
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token {
    /** @var int URL de embed valida por 6h - recomendacao da propria Bunny. */
    public const EMBED_TTL_SECONDS = 21600;

    /** @var int Assinatura de upload TUS valida por 1h - sobra para qualquer upload. */
    public const TUS_TTL_SECONDS = 3600;

    /**
     * Assina um upload TUS.
     *
     * AuthorizationSignature = sha256(libraryId + apiKey + expires + videoId)
     *
     * @param string $libraryid
     * @param string $apikey
     * @param string $videoguid
     * @param int $ttl
     * @return array library_id, expires, signature
     */
    public static function sign_tus(string $libraryid, string $apikey, string $videoguid, int $ttl = self::TUS_TTL_SECONDS): array {
        $expires = time() + $ttl;
        $raw = $libraryid . $apikey . $expires . $videoguid;

        return [
            'library_id' => $libraryid,
            'expires'    => $expires,
            'signature'  => hash('sha256', $raw),
        ];
    }

    /**
     * Assina uma URL de embed do player da Bunny.
     *
     * token = sha256(securityKey + videoId + expirationUnix)
     *
     * @param string $libraryid
     * @param string $guid
     * @param string $securitykey
     * @param int $ttl
     * @param array $extra
     * @return string
     */
    public static function sign_embed(
        string $libraryid,
        string $guid,
        string $securitykey,
        int $ttl = self::EMBED_TTL_SECONDS,
        array $extra = []
    ): string {
        $expires = time() + $ttl;
        $signedtoken = hash('sha256', $securitykey . $guid . $expires);
        $params = array_merge(['token' => $signedtoken, 'expires' => (string) $expires], $extra);

        return bunny_client::BUNNY_EMBED_BASE . "/{$libraryid}/{$guid}?" . http_build_query($params);
    }

    /**
     * URL de embed sem assinatura - so funciona se a library nao exigir token.
     *
     * @param string $libraryid
     * @param string $guid
     * @param array $extra
     * @return string
     */
    public static function unsigned_embed(string $libraryid, string $guid, array $extra = []): string {
        $base = bunny_client::BUNNY_EMBED_BASE . "/{$libraryid}/{$guid}";
        if (empty($extra)) {
            return $base;
        }

        return $base . '?' . http_build_query($extra);
    }

    /**
     * Escolhe entre embed assinado e sem assinatura, conforme a library tenha
     * chave de seguranca configurada.
     *
     * @param string $libraryid
     * @param string $guid
     * @param string|null $securitykey
     * @param array $extra
     * @return string
     */
    public static function embed_url_for(string $libraryid, string $guid, ?string $securitykey, array $extra = []): string {
        if ($securitykey) {
            return self::sign_embed($libraryid, $guid, $securitykey, self::EMBED_TTL_SECONDS, $extra);
        }

        return self::unsigned_embed($libraryid, $guid, $extra);
    }
}
