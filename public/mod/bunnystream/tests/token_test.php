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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Assinatura de upload TUS e de embed - formulas exatas ditadas pela Bunny.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_bunnystream\token::class)]
final class token_test extends \advanced_testcase {
    /**
     * O hash do TUS bate com a formula documentada da Bunny.
     *
     * @return void
     */
    public function test_sign_tus_produces_expected_hash(): void {
        $libraryid = '12345';
        $apikey = 'fake-api-key';
        $videoguid = 'abc-def-123';
        $result = token::sign_tus($libraryid, $apikey, $videoguid, 3600);
        $this->assertSame($libraryid, $result['library_id']);
        $this->assertIsInt($result['expires']);
        $this->assertGreaterThan(time(), $result['expires']);
        $expected = hash('sha256', $libraryid . $apikey . $result['expires'] . $videoguid);
        $this->assertSame($expected, $result['signature']);
    }

    /**
     * A URL de embed assinada carrega token e expires corretos.
     *
     * @return void
     */
    public function test_sign_embed_produces_expected_url(): void {
        $libraryid = '12345';
        $guid = 'abc-def-123';
        $securitykey = 'fake-security-key';
        $url = token::sign_embed($libraryid, $guid, $securitykey, 21600, ['autoplay' => 'true']);
        $this->assertStringStartsWith('https://iframe.mediadelivery.net/embed/12345/abc-def-123?', $url);
        $this->assertStringContainsString('autoplay=true', $url);
        $this->assertStringContainsString('token=', $url);
        $this->assertStringContainsString('expires=', $url);

        $parts = parse_url($url);
        parse_str($parts['query'], $qs);
        $expected = hash('sha256', $securitykey . $guid . $qs['expires']);
        $this->assertSame($expected, $qs['token']);
    }

    /**
     * A URL sem assinatura nao carrega token.
     *
     * @return void
     */
    public function test_unsigned_embed_omits_token(): void {
        $url = token::unsigned_embed('12345', 'abc-def-123', ['responsive' => 'true']);
        $this->assertStringNotContainsString('token=', $url);
        $this->assertStringContainsString('responsive=true', $url);
    }

    /**
     * Sem chave de seguranca, cai para a URL sem assinatura.
     *
     * @return void
     */
    public function test_embed_url_for_falls_back_to_unsigned_when_no_security_key(): void {
        $url = token::embed_url_for('12345', 'abc-def-123', null);
        $this->assertStringNotContainsString('token=', $url);
    }
}
