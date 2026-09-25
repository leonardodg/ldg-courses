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

use local_marketplace\api;
use local_marketplace\library_account;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * config::for_course() e a fronteira do multi-tenant: cada curso resolve na
 * library da PROPRIA empresa, nunca numa credencial global.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_bunnystream\config::class)]
final class config_test extends \advanced_testcase {
    /**
     * Cria uma empresa com library e um curso na categoria dela.
     *
     * @return array [company, course]
     */
    private function company_with_course(): array {
        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa Video',
            'shortname' => 'vid' . random_int(1000, 9999),
        ], (int) $owner->id);

        $suffix = $company->get('shortname');
        $library = new library_account();
        $library->set('companyid', (int) $company->get('id'));
        $library->set('bunnylibraryid', random_int(100000, 999999));
        $library->set('apikey', library_account::encrypt('chave-da-library-' . $suffix));
        $library->set('securitykey', library_account::encrypt('chave-de-seguranca-' . $suffix));
        $library->set('cdnhostname', 'vz-' . $suffix . '.b-cdn.net');
        $library->create();

        $course = $this->getDataGenerator()->create_course(['category' => $company->get('categoryid')]);

        return [$company, $course];
    }

    /**
     * Um curso de empresa com library configurada resolve as credenciais
     * daquela empresa.
     *
     * @return void
     */
    public function test_for_course_resolves_the_owning_companys_library(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        [$company, $course] = $this->company_with_course();
        $suffix = $company->get('shortname');

        $cfg = config::for_course((int) $course->id);

        $this->assertSame('chave-da-library-' . $suffix, $cfg->api_key);
        $this->assertSame('chave-de-seguranca-' . $suffix, $cfg->security_key);
        $this->assertSame('vz-' . $suffix . '.b-cdn.net', $cfg->cdn_hostname);
    }

    /**
     * Curso fora de qualquer empresa do marketplace (ex.: curso de teste na
     * raiz) nao tem credencial nenhuma - nao ha para onde cair.
     *
     * @return void
     */
    public function test_course_outside_any_company_is_not_configured(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->expectException(not_configured_exception::class);
        config::for_course((int) $course->id);
    }

    /**
     * Empresa sem library ainda (provisionamento em espera) tambem cai em
     * "nao configurado" - o professor nao pode enviar video antes disso.
     *
     * @return void
     */
    public function test_company_without_library_is_not_configured(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        $company = api::create_company((object) [
            'name' => 'Empresa Sem Library',
            'shortname' => 'nolib' . random_int(1000, 9999),
        ], (int) $owner->id);
        $course = $this->getDataGenerator()->create_course(['category' => $company->get('categoryid')]);

        $this->expectException(not_configured_exception::class);
        config::for_course((int) $course->id);
    }

    /**
     * Duas empresas, dois cursos, duas libraries diferentes - a credencial
     * de uma NUNCA vaza para a outra.
     *
     * @return void
     */
    public function test_two_companies_never_share_credentials(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        [, $coursea] = $this->company_with_course();
        [, $courseb] = $this->company_with_course();

        $cfga = config::for_course((int) $coursea->id);
        $cfgb = config::for_course((int) $courseb->id);

        $this->assertNotSame($cfga->api_key, $cfgb->api_key);
        $this->assertNotSame($cfga->library_id, $cfgb->library_id);
    }
}
