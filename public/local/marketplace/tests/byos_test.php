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
use PHPUnit\Framework\Attributes\CoversMethod;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_bunny_platform_client.php');

/**
 * Frente A - BYOS: o produtor conecta a PROPRIA conta Bunny, e a plataforma
 * nunca cria nem mexe na library dele.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\library_account::class)]
#[CoversMethod(\local_marketplace\api::class, 'connect_byos_library')]
#[CoversMethod(\local_marketplace\api::class, 'create_video_library')]
#[CoversMethod(\local_marketplace\api::class, 'sync_video_library_resolution')]
final class byos_test extends \advanced_testcase {
    /**
     * Restaura o cliente real depois de cada teste.
     *
     * @return void
     */
    protected function tearDown(): void {
        api::override_bunny_client(null);
        parent::tearDown();
    }

    /**
     * Cria um plano BYOS de teste.
     *
     * @return plan
     */
    private function make_byos_plan(): plan {
        $plan = new plan(0, (object) [
            'shortname' => 'byos' . random_int(1000, 9999),
            'name' => 'Plano BYOS de teste',
            'hostingmodel' => plan::HOSTING_BYOS,
        ]);
        $plan->create();

        return $plan;
    }

    /**
     * Cria uma empresa ja no plano BYOS.
     *
     * @param plan $plan
     * @return company
     */
    private function make_byos_company(plan $plan): company {
        $owner = $this->getDataGenerator()->create_user();

        return api::create_company((object) [
            'name' => 'Empresa BYOS',
            'shortname' => 'com' . random_int(1000, 9999),
            'planid' => (int) $plan->get('id'),
        ], (int) $owner->id);
    }

    /**
     * Empresa BYOS nao ganha library provisionada automaticamente - a
     * plataforma nao pode criar recurso numa conta que nao e dela.
     *
     * @return void
     */
    public function test_company_creation_never_provisions_a_platform_library_for_byos(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $fake = new fake_bunny_platform_client();
        $fake->nextresponse = ['Id' => 1, 'ApiKey' => 'nao-deveria-ser-usada'];
        api::override_bunny_client($fake);

        $plan = $this->make_byos_plan();
        $company = $this->make_byos_company($plan);

        $this->assertNull(library_account::get_for((int) $company->get('id')));
        // Nem uma chamada de API - o provisionamento automatico e so pra
        // plano nativo (Frente B).
        $this->assertCount(0, $fake->calls);
    }

    /**
     * O produtor conecta a propria library, e a chave fica cifrada.
     *
     * @return void
     */
    public function test_connect_byos_library_stores_encrypted_credentials(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $plan = $this->make_byos_plan();
        $company = $this->make_byos_company($plan);

        $library = api::connect_byos_library($company, 998877, 'chave-do-produtor', 'chave-de-token', 'vz-produtor.b-cdn.net');

        $this->assertSame(998877, $library->get('bunnylibraryid'));
        $this->assertSame('chave-do-produtor', $library->get_api_key());
        $this->assertSame('chave-de-token', $library->get_security_key());
        $this->assertSame('vz-produtor.b-cdn.net', $library->get('cdnhostname'));
        // BYOS nao tem teto - o produtor controla a propria banda.
        $this->assertNull($library->get('maxresolution'));

        $raw = $DB->get_field('local_marketplace_library', 'apikey', ['id' => $library->get('id')]);
        $this->assertStringNotContainsString('chave-do-produtor', $raw);
    }

    /**
     * Duas empresas nao podem conectar o mesmo bunnylibraryid - o indice
     * unico da tabela ja recusaria, mas sem validate_bunnylibraryid() o erro
     * sairia como dml_write_exception em vez de mensagem legivel. Achado do
     * code review de 25/09/2026.
     *
     * @return void
     */
    public function test_connecting_a_library_id_already_taken_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $plan = $this->make_byos_plan();
        $first = $this->make_byos_company($plan);
        $second = $this->make_byos_company($plan);

        api::connect_byos_library($first, 555444, 'chave-da-primeira');

        $this->expectException(\core\invalid_persistent_exception::class);
        api::connect_byos_library($second, 555444, 'chave-da-segunda');
    }

    /**
     * Empresa conecta a propria library (BYOS) e depois volta pro plano
     * nativo - sync_video_library_resolution() continua sem mexer na
     * library, porque a linha ainda pertence a conta do produtor
     * (origin = byos), mesmo com o plano atual dizendo nativo. Achado do
     * code review de 25/09/2026: sem o campo `origin`, esta chamada batia
     * na conta do produtor com a chave da PLATAFORMA.
     *
     * @return void
     */
    public function test_reverting_to_native_plan_still_protects_the_byos_library(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $byosplan = $this->make_byos_plan();
        $company = $this->make_byos_company($byosplan);
        api::connect_byos_library($company, 1, 'chave-do-produtor');

        $nativeplan = new plan(0, (object) [
            'shortname' => 'nativo' . random_int(1000, 9999),
            'name' => 'Plano nativo de teste',
            'hostingmodel' => plan::HOSTING_NATIVE,
        ]);
        $nativeplan->create();
        $company->set('planid', (int) $nativeplan->get('id'));
        $company->update();

        $fake = new fake_bunny_platform_client();
        api::override_bunny_client($fake);

        api::sync_video_library_resolution($company);

        $this->assertCount(0, $fake->calls);
        $this->assertSame(
            library_account::ORIGIN_BYOS,
            library_account::get_for((int) $company->get('id'))->get('origin')
        );
    }

    /**
     * Conectar de novo ATUALIZA a mesma linha, e nao cria uma segunda
     * library para a mesma empresa.
     *
     * @return void
     */
    public function test_connecting_again_updates_the_same_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $plan = $this->make_byos_plan();
        $company = $this->make_byos_company($plan);

        $first = api::connect_byos_library($company, 1, 'chave-1');
        $second = api::connect_byos_library($company, 2, 'chave-2');

        $this->assertEquals($first->get('id'), $second->get('id'));
        $this->assertSame(2, $second->get('bunnylibraryid'));
        $this->assertSame('chave-2', $second->get_api_key());
        $this->assertCount(1, library_account::get_records(['companyid' => (int) $company->get('id')]));
    }

    /**
     * Empresa fora do plano BYOS nao pode conectar library de produtor - a
     * plataforma teria autoridade zero sobre um recurso alheio marcado como
     * se fosse dela.
     *
     * @return void
     */
    public function test_connecting_without_byos_plan_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa Nativa',
            'shortname' => 'com' . random_int(1000, 9999),
        ], (int) $owner->id);

        $this->expectException(\moodle_exception::class);
        api::connect_byos_library($company, 1, 'chave-qualquer');
    }

    /**
     * Trocar o plano da empresa para BYOS nunca chama a Bunny para
     * sincronizar resolucao - a library, se existir, e do produtor.
     *
     * @return void
     */
    public function test_sync_resolution_is_a_no_op_for_byos(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $plan = $this->make_byos_plan();
        $company = $this->make_byos_company($plan);
        api::connect_byos_library($company, 1, 'chave-do-produtor');

        $fake = new fake_bunny_platform_client();
        api::override_bunny_client($fake);

        api::sync_video_library_resolution($company);

        $this->assertCount(0, $fake->calls);
    }
}
