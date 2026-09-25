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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_bunny_platform_client.php');

/**
 * Cliente da API de CONTA da Bunny - so cria library, com transporte falso.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\bunny_platform_client::class)]
final class bunny_platform_client_test extends \advanced_testcase {
    /**
     * Uma criacao bem sucedida devolve os quatro campos que a library
     * precisa, e chama o endpoint certo com o nome pedido.
     *
     * @return void
     */
    public function test_create_library_returns_the_fields_the_link_needs(): void {
        // O formato exato de resposta que a API real da Bunny devolve na
        // criacao - verificado ao vivo em 25/09/2026. So Id e ApiKey; o
        // resto do payload real e configuracao da library que este metodo
        // nao usa.
        $client = new fake_bunny_platform_client();
        $client->nextresponse = [
            'Id' => 4242,
            'ApiKey' => 'abc123',
            'EnabledResolutions' => '240p,360p,480p,720p,1080p',
        ];

        $created = $client->create_library('Empresa Teste (com1234)', '240p,360p,480p,720p');

        $this->assertSame(4242, $created['bunnylibraryid']);
        $this->assertSame('abc123', $created['apikey']);

        $this->assertSame([['POST', '/videolibrary']], $client->calls);
        $this->assertSame('Empresa Teste (com1234)', $client->lastbody['Name']);
        $this->assertSame('240p,360p,480p,720p', $client->lastbody['EnabledResolutions']);
    }

    /**
     * Resposta sem Id e um contrato quebrado, e nao uma library valida com
     * id zero.
     *
     * @return void
     */
    public function test_response_without_id_is_rejected(): void {
        $client = new fake_bunny_platform_client();
        $client->nextresponse = ['ApiKey' => 'abc123'];

        $this->expectException(\moodle_exception::class);
        $client->create_library('Empresa Teste', '720p');
    }

    /**
     * Status HTTP de erro estoura, com a mensagem da Bunny.
     *
     * @return void
     */
    public function test_http_error_status_is_reported(): void {
        $client = new fake_bunny_platform_client();
        $client->nextstatus = 401;
        $client->nextresponse = ['Message' => 'Invalid AccessKey'];

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Invalid AccessKey/');
        $client->create_library('Empresa Teste', '720p');
    }

    /**
     * Erro de transporte (sem rede, timeout) estoura como erro proprio, e
     * nao devolve dado nenhum como se tivesse funcionado.
     *
     * @return void
     */
    public function test_transport_error_is_reported(): void {
        $client = new fake_bunny_platform_client();
        $client->nexterrno = 28;

        $this->expectException(\moodle_exception::class);
        $client->create_library('Empresa Teste', '720p');
    }

    /**
     * A escada de resolucao inclui sempre as faixas baixas, e para exatamente
     * no teto do plano.
     *
     * @return void
     */
    public function test_enabled_resolutions_for_cap_stops_at_the_plan_ceiling(): void {
        $this->assertSame('240p,360p,480p,720p', bunny_platform_client::enabled_resolutions_for_cap('720p'));
        $this->assertSame('240p,360p,480p,720p,1080p', bunny_platform_client::enabled_resolutions_for_cap('1080p'));
        $this->assertSame('240p,360p,480p,720p,1080p,1440p', bunny_platform_client::enabled_resolutions_for_cap('1440p'));
        // No vocabulario do plano e "4k"; na Bunny e "2160p".
        $this->assertSame(
            '240p,360p,480p,720p,1080p,1440p,2160p',
            bunny_platform_client::enabled_resolutions_for_cap('4k')
        );
    }

    /**
     * Sem teto (BYOS, plano sem faixa) libera a escada inteira - a
     * plataforma nao paga a banda desse degrau.
     *
     * @return void
     */
    public function test_enabled_resolutions_for_cap_null_means_everything(): void {
        $this->assertSame(
            implode(',', bunny_platform_client::RESOLUTION_LADDER),
            bunny_platform_client::enabled_resolutions_for_cap(null)
        );
    }

    /**
     * Atualizar a resolucao de uma library existente chama o endpoint certo,
     * com o id na URL e a nova escada no corpo.
     *
     * @return void
     */
    public function test_update_library_resolutions_calls_the_right_endpoint(): void {
        $client = new fake_bunny_platform_client();
        $client->nextresponse = ['Id' => 777, 'EnabledResolutions' => '240p,360p,480p,720p'];

        $client->update_library_resolutions(777, '240p,360p,480p,720p');

        $this->assertSame([['POST', '/videolibrary/777']], $client->calls);
        $this->assertSame('240p,360p,480p,720p', $client->lastbody['EnabledResolutions']);
    }
}
