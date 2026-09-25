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
 * Provisionamento automatico da library da Bunny no create_company()
 * (Frente B, ADR-0014).
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\library_account::class)]
#[CoversMethod(\local_marketplace\api::class, 'create_video_library')]
#[CoversMethod(\local_marketplace\api::class, 'create_company')]
final class video_library_provisioning_test extends \advanced_testcase {
    /**
     * Restaura o cliente real depois de cada teste - um override esquecido
     * vazaria para o proximo teste da suite inteira.
     *
     * @return void
     */
    protected function tearDown(): void {
        api::override_bunny_client(null);
        parent::tearDown();
    }

    /**
     * Sem chave de conta configurada e sem cliente de teste, a empresa nasce
     * normalmente e a library fica em espera - nulo, e nao excecao.
     *
     * @return void
     */
    public function test_company_is_created_without_library_when_bunny_is_not_configured(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa Sem Bunny',
            'shortname' => 'com' . random_int(1000, 9999),
        ], (int) $owner->id);

        $this->assertNotEmpty($company->get('id'));
        $this->assertNull(library_account::get_for((int) $company->get('id')));
    }

    /**
     * Com o cliente configurado, a empresa nasce com a library ja vinculada,
     * na mesma chamada que cria a categoria e a conta de pagamento.
     *
     * @return void
     */
    public function test_company_is_created_with_a_library_when_bunny_is_configured(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        // Formato real da resposta de criacao, verificado ao vivo em
        // 25/09/2026: so Id e ApiKey. securitykey/cdnhostname nascem nulos -
        // sao do proximo sub-passo (player).
        $fake = new fake_bunny_platform_client();
        $fake->nextresponse = [
            'Id' => 777,
            'ApiKey' => 'chave-da-library',
        ];
        api::override_bunny_client($fake);

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa Com Bunny',
            'shortname' => 'com' . random_int(1000, 9999),
        ], (int) $owner->id);

        $library = library_account::get_for((int) $company->get('id'));

        $this->assertNotNull($library);
        $this->assertSame(777, $library->get('bunnylibraryid'));
        $this->assertSame('chave-da-library', $library->get_api_key());
        $this->assertSame('', $library->get_security_key());
        $this->assertNull($library->get('cdnhostname'));

        // Sem plano ainda, o teto e o mais conservador (720p) - nunca "sem
        // teto" por omissao. A library ja nasce criada com esse teto no
        // corpo da chamada (verificado ao vivo: EnabledResolutions funciona
        // na criacao, sem precisar de update depois).
        $this->assertSame('720p', $library->get('maxresolution'));
        $this->assertSame('240p,360p,480p,720p', $fake->lastbody['EnabledResolutions']);
    }

    /**
     * Empresa que ja nasce com plano usa o teto DAQUELE plano, nao o
     * conservador de "sem plano ainda".
     *
     * @return void
     */
    public function test_company_created_with_a_plan_uses_the_plans_resolution_cap(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $plan = new plan(0, (object) [
            'shortname' => 'planocomteto' . random_int(1000, 9999),
            'name' => 'Plano com teto',
        ]);
        $plan->create();
        (new plan_tier(0, (object) [
            'planid' => (int) $plan->get('id'),
            'maxprice' => null,
            'maxresolution' => '1080p',
        ]))->create();

        $fake = new fake_bunny_platform_client();
        $fake->nextresponse = ['Id' => 42, 'ApiKey' => 'a'];
        api::override_bunny_client($fake);

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa Com Plano',
            'shortname' => 'com' . random_int(1000, 9999),
            'planid' => (int) $plan->get('id'),
        ], (int) $owner->id);

        $library = library_account::get_for((int) $company->get('id'));

        $this->assertSame('1080p', $library->get('maxresolution'));
        $this->assertSame('240p,360p,480p,720p,1080p', $fake->lastbody['EnabledResolutions']);
    }

    /**
     * Pedir a library duas vezes para a mesma empresa devolve a mesma, sem
     * chamar a API de novo.
     *
     * @return void
     */
    public function test_creating_twice_returns_the_same_library_without_a_second_api_call(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $fake = new fake_bunny_platform_client();
        $fake->nextresponse = [
            'Id' => 1,
            'ApiKey' => 'a',
        ];
        api::override_bunny_client($fake);

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa Teste',
            'shortname' => 'com' . random_int(1000, 9999),
        ], (int) $owner->id);

        $first = library_account::get_for((int) $company->get('id'));
        $second = api::create_video_library($company);

        $this->assertEquals($first->get('id'), $second->get('id'));
        // A chamada dentro de create_company() ja contou uma vez - so uma
        // chamada de API no total, e nao duas.
        $this->assertCount(1, $fake->calls);
    }

    /**
     * Falha na chamada da Bunny desfaz a empresa inteira - library e
     * provisionamento automatico, e uma empresa sem library, num ambiente que
     * JA tem conta Bunny configurada, e um estado que ninguem saberia
     * destravar depois.
     *
     * @return void
     */
    public function test_bunny_failure_rolls_back_the_whole_company(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $fake = new fake_bunny_platform_client();
        $fake->nextstatus = 500;
        $fake->nextresponse = ['Message' => 'falha simulada'];
        api::override_bunny_client($fake);

        $owner = $this->getDataGenerator()->create_user();
        $shortname = 'com' . random_int(1000, 9999);

        try {
            api::create_company((object) [
                'name' => 'Empresa Que Nao Deveria Existir',
                'shortname' => $shortname,
            ], (int) $owner->id);
            $this->fail('esperava excecao da chamada da Bunny');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('falha simulada', $e->getMessage());
        }

        $this->assertFalse($DB->record_exists('local_marketplace_company', ['shortname' => $shortname]));
    }

    /**
     * Cria uma empresa com library ja provisionada.
     *
     * @param fake_bunny_platform_client $fake
     * @return company
     */
    private function company_with_library(fake_bunny_platform_client $fake): company {
        api::override_bunny_client($fake);
        $owner = $this->getDataGenerator()->create_user();

        return api::create_company((object) [
            'name' => 'Empresa Sync',
            'shortname' => 'sync' . random_int(1000, 9999),
        ], (int) $owner->id);
    }

    /**
     * Trocar de plano atualiza o teto na Bunny e no cache local.
     *
     * @return void
     */
    public function test_sync_updates_resolution_when_plan_changes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $fake = new fake_bunny_platform_client();
        $fake->nextresponse = ['Id' => 5, 'ApiKey' => 'a'];
        $company = $this->company_with_library($fake);
        // Nasce sem plano -> teto conservador 720p (ja testado acima).

        $plan = new plan(0, (object) ['shortname' => 'upgrade' . random_int(1000, 9999), 'name' => 'Upgrade']);
        $plan->create();
        (new plan_tier(0, (object) ['planid' => (int) $plan->get('id'), 'maxresolution' => '4k']))->create();

        $company->set('planid', (int) $plan->get('id'));
        $company->update();

        api::sync_video_library_resolution($company);

        $library = library_account::get_for((int) $company->get('id'));
        $this->assertSame('4k', $library->get('maxresolution'));
        $this->assertSame('/videolibrary/5', $fake->calls[array_key_last($fake->calls)][1]);
        $this->assertSame('240p,360p,480p,720p,1080p,1440p,2160p', $fake->lastbody['EnabledResolutions']);
    }

    /**
     * Teto que nao mudou nao rechama a API - o cache em maxresolution existe
     * exatamente para isto.
     *
     * @return void
     */
    public function test_sync_is_idempotent_when_cap_did_not_change(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $fake = new fake_bunny_platform_client();
        $fake->nextresponse = ['Id' => 6, 'ApiKey' => 'a'];
        $company = $this->company_with_library($fake);

        $calls = count($fake->calls);
        // Empresa continua sem plano - o teto e 720p antes e depois, sem
        // mudanca nenhuma para sincronizar.
        api::sync_video_library_resolution($company);

        $this->assertCount($calls, $fake->calls);
    }

    /**
     * Falha da Bunny ao sincronizar resolucao nao estoura - e best-effort,
     * ao contrario da criacao da library.
     *
     * @return void
     */
    public function test_sync_failure_does_not_throw(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $fake = new fake_bunny_platform_client();
        $fake->nextresponse = ['Id' => 7, 'ApiKey' => 'a'];
        $company = $this->company_with_library($fake);

        $plan = new plan(0, (object) ['shortname' => 'falha' . random_int(1000, 9999), 'name' => 'Falha']);
        $plan->create();
        (new plan_tier(0, (object) ['planid' => (int) $plan->get('id'), 'maxresolution' => '1440p']))->create();
        $company->set('planid', (int) $plan->get('id'));
        $company->update();

        $fake->nextstatus = 500;
        $fake->nextresponse = ['Message' => 'falha simulada'];

        api::sync_video_library_resolution($company);
        $this->assertDebuggingCalled();

        // O cache NAO avancou - a proxima tentativa vai rechamar a API, e
        // nao vai achar que ja sincronizou algo que falhou.
        $library = library_account::get_for((int) $company->get('id'));
        $this->assertSame('720p', $library->get('maxresolution'));
    }
}
