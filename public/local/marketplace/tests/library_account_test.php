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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Library da Bunny de uma empresa, dentro da conta unica da plataforma.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\library_account::class)]
final class library_account_test extends \advanced_testcase {
    /**
     * Garante a chave de cifragem, criando se o ambiente de teste nao tiver.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }
    }

    /**
     * A chave e a chave de seguranca sobrevivem a uma ida e volta ao banco,
     * cifradas.
     *
     * @return void
     */
    public function test_keys_survive_a_round_trip_encrypted(): void {
        global $DB;

        $library = new library_account();
        $library->set('companyid', 1);
        $library->set('bunnylibraryid', 42);
        $library->set('apikey', library_account::encrypt('chave-de-verdade'));
        $library->set('securitykey', library_account::encrypt('token-de-verdade'));
        $library->set('cdnhostname', 'vz-1234-abc.b-cdn.net');
        $library->create();

        // O que fica gravado no banco NAO e a chave em claro.
        $raw = $DB->get_field('local_marketplace_library', 'apikey', ['id' => $library->get('id')]);
        $this->assertStringNotContainsString('chave-de-verdade', $raw);

        $read = new library_account($library->get('id'));
        $this->assertSame('chave-de-verdade', $read->get_api_key());
        $this->assertSame('token-de-verdade', $read->get_security_key());
    }

    /**
     * Nao pode haver duas libraries para a mesma empresa.
     *
     * @return void
     */
    public function test_company_cannot_have_two_libraries(): void {
        $first = new library_account();
        $first->set('companyid', 5);
        $first->set('bunnylibraryid', 1);
        $first->set('apikey', library_account::encrypt('a'));
        $first->set('securitykey', library_account::encrypt('b'));
        $first->set('cdnhostname', 'vz-1.b-cdn.net');
        $first->create();

        $duplicate = new library_account();
        $duplicate->set('companyid', 5);
        $duplicate->set('bunnylibraryid', 2);
        $duplicate->set('apikey', library_account::encrypt('c'));
        $duplicate->set('securitykey', library_account::encrypt('d'));
        $duplicate->set('cdnhostname', 'vz-2.b-cdn.net');

        $this->assertNotTrue($duplicate->validate());
        $this->assertArrayHasKey('companyid', $duplicate->get_errors());
    }

    /**
     * Resolucao fora da lista de plan_tier::RESOLUTIONS e recusada.
     *
     * @return void
     */
    public function test_unsupported_resolution_is_rejected(): void {
        $library = new library_account();
        $library->set('companyid', 9);
        $library->set('bunnylibraryid', 1);
        $library->set('apikey', library_account::encrypt('a'));
        $library->set('securitykey', library_account::encrypt('b'));
        $library->set('cdnhostname', 'vz-9.b-cdn.net');
        $library->set('maxresolution', '8k');

        $this->assertNotTrue($library->validate());
        $this->assertArrayHasKey('maxresolution', $library->get_errors());
    }

    /**
     * Empresa sem library devolve nulo, e nao a de outra empresa.
     *
     * @return void
     */
    public function test_company_without_library_returns_null(): void {
        $this->assertNull(library_account::get_for(999999));
    }

    /**
     * O segredo do webhook nasce sozinho, e cada library recebe um diferente
     * - nunca um singleton compartilhado.
     *
     * @return void
     */
    public function test_webhook_secret_is_generated_and_unique_per_library(): void {
        $first = new library_account();
        $first->set('companyid', 20);
        $first->set('bunnylibraryid', 100);
        $first->set('apikey', library_account::encrypt('a'));
        $first->create();

        $second = new library_account();
        $second->set('companyid', 21);
        $second->set('bunnylibraryid', 101);
        $second->set('apikey', library_account::encrypt('b'));
        $second->create();

        $this->assertNotEmpty($first->get('webhooksecret'));
        $this->assertNotSame($first->get('webhooksecret'), $second->get('webhooksecret'));

        $this->assertEquals($first->get('id'), library_account::get_by_webhook_secret($first->get('webhooksecret'))->get('id'));
        $this->assertEquals($first->get('id'), library_account::get_by_bunnylibraryid(100)->get('id'));
        $this->assertNull(library_account::get_by_webhook_secret('nao-existe'));
        $this->assertNull(library_account::get_by_bunnylibraryid(999999));
    }

    /**
     * securitykey e cdnhostname nascem nulos, e o getter da chave devolve
     * vazio em vez de estourar - a Bunny nao devolve nenhum dos dois na
     * criacao da library (verificado ao vivo em 25/09/2026), so apikey.
     *
     * @return void
     */
    public function test_security_key_and_cdn_hostname_can_be_null(): void {
        $library = new library_account();
        $library->set('companyid', 12);
        $library->set('bunnylibraryid', 1);
        $library->set('apikey', library_account::encrypt('a'));
        $library->create();

        $read = new library_account($library->get('id'));

        $this->assertSame('', $read->get_security_key());
        $this->assertNull($read->get('cdnhostname'));
    }
}
