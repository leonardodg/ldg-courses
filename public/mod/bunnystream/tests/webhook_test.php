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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Webhook de status da Bunny - identificado por library, nunca por um
 * segredo unico de instalacao (diferenca do plugin de referencia).
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_bunnystream\webhook_processor::class)]
final class webhook_test extends \advanced_testcase {
    /**
     * Prepara o ambiente.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }
    }

    /**
     * Cria uma library de teste, com bunnylibraryid conhecido.
     *
     * @param int $bunnylibraryid
     * @return library_account
     */
    private function make_library(int $bunnylibraryid): library_account {
        $library = new library_account();
        $library->set('companyid', random_int(1, 999999));
        $library->set('bunnylibraryid', $bunnylibraryid);
        $library->set('apikey', library_account::encrypt('chave'));
        $library->create();

        return $library;
    }

    /**
     * Captura o JSON e o codigo HTTP devolvidos por webhook_processor::process().
     *
     * @param string $token
     * @param string $body
     * @return array
     */
    private function call_process(string $token, string $body): array {
        ob_start();
        try {
            webhook_processor::process($token, $body);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $output = ob_get_clean();

        return json_decode($output ?: '{}', true) ?: [];
    }

    /**
     * Token que nao bate com nenhuma library e recusado.
     *
     * @return void
     */
    public function test_unknown_token_rejected(): void {
        $result = $this->call_process('aaaaaaaaaaaaaaaaaaaaaa', '{}');
        $this->assertSame('unknown_token', $result['error']);
        $this->assertDebuggingCalled();
    }

    /**
     * Token conhecido, mas corpo mal formado, e recusado no corpo - nao no
     * token.
     *
     * @return void
     */
    public function test_known_token_with_malformed_payload_rejected(): void {
        $library = $this->make_library(12345);
        $token = (string) $library->get('webhooksecret');

        $result = $this->call_process($token, 'not-json');
        $this->assertSame('invalid_json', $result['error']);
        $this->assertDebuggingCalled();

        $result2 = $this->call_process($token, json_encode(['VideoGuid' => 'g']));
        $this->assertSame('malformed_payload', $result2['error']);
        $this->assertDebuggingCalled();
    }

    /**
     * O VideoLibraryId do payload tem que bater com a library dona do token.
     *
     * @return void
     */
    public function test_library_mismatch_rejected(): void {
        $library = $this->make_library(12345);
        $token = (string) $library->get('webhooksecret');

        $result = $this->call_process($token, json_encode([
            'VideoGuid' => 'guid-1', 'VideoLibraryId' => 99999, 'Status' => 4,
        ]));
        $this->assertSame('library_mismatch', $result['error']);
        $this->assertDebuggingCalled();
    }

    /**
     * Estado terminal (ready/failed) nao regride, mesmo vindo da library
     * certa.
     *
     * @return void
     */
    public function test_terminal_state_regression_rejected(): void {
        global $DB;

        $library = $this->make_library(12345);
        $token = (string) $library->get('webhooksecret');

        $DB->insert_record('bunnystream_videos', (object) [
            'guid' => 'guid-1', 'library_id' => '12345', 'title' => '',
            'status' => 'ready', 'created_by' => null,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $result = $this->call_process($token, json_encode([
            'VideoGuid' => 'guid-1', 'VideoLibraryId' => 12345, 'Status' => 2,
        ]));
        $this->assertSame('terminal_state_regression', $result['error']);
        $this->assertDebuggingCalled();
    }

    /**
     * Video de uma library confirma, mesmo com o token de OUTRA library
     * valido - a segunda guarda (tenant_mismatch) pega o cruzamento.
     *
     * @return void
     */
    public function test_video_from_another_library_is_rejected_as_tenant_mismatch(): void {
        global $DB;

        $ownerlibrary = $this->make_library(11111);
        $otherlibrary = $this->make_library(22222);

        // O video pertence a OUTRALIBRARY, mas o token usado e o de OWNERLIBRARY -
        // so aconteceria com um bug de configuracao cruzada, e tem que ser pego.
        $DB->insert_record('bunnystream_videos', (object) [
            'guid' => 'guid-cruzado', 'library_id' => (string) $otherlibrary->get('bunnylibraryid'),
            'title' => '', 'status' => 'encoding', 'created_by' => null,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $result = $this->call_process((string) $ownerlibrary->get('webhooksecret'), json_encode([
            'VideoGuid' => 'guid-cruzado',
            'VideoLibraryId' => (int) $ownerlibrary->get('bunnylibraryid'),
            'Status' => 4,
        ]));

        $this->assertSame('tenant_mismatch', $result['error']);
        $this->assertDebuggingCalled();
    }

    /**
     * Video desconhecido confirma (200) para a Bunny parar de tentar de
     * novo, sem estourar.
     *
     * @return void
     */
    public function test_unknown_video_is_acknowledged(): void {
        $library = $this->make_library(33333);

        $result = $this->call_process((string) $library->get('webhooksecret'), json_encode([
            'VideoGuid' => 'guid-nunca-existiu', 'VideoLibraryId' => 33333, 'Status' => 4,
        ]));

        $this->assertTrue($result['ok']);
        $this->assertSame('unknown_video', $result['note']);
    }
}
