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
 * Traducoes de status e utilitarios puros do bunny_client - sem rede.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_bunnystream\bunny_client::class)]
final class bunny_client_test extends \advanced_testcase {
    /**
     * Todo codigo numerico da Bunny mapeia para um status conhecido.
     *
     * @return void
     */
    public function test_map_status_handles_all_bunny_codes(): void {
        $this->assertSame('pending', bunny_client::map_status(0));
        $this->assertSame('uploaded', bunny_client::map_status(1));
        $this->assertSame('encoding', bunny_client::map_status(2));
        $this->assertSame('encoding', bunny_client::map_status(3));
        $this->assertSame('ready', bunny_client::map_status(4));
        $this->assertSame('failed', bunny_client::map_status(5));
        $this->assertSame('failed', bunny_client::map_status(6));
        $this->assertSame('encoding', bunny_client::map_status(7));
        $this->assertSame('ready', bunny_client::map_status(8));
        $this->assertSame('pending', bunny_client::map_status(null));
        $this->assertSame('pending', bunny_client::map_status(999));
    }

    /**
     * So ready e failed sao terminais.
     *
     * @return void
     */
    public function test_is_terminal_status(): void {
        $this->assertTrue(bunny_client::is_terminal_status('ready'));
        $this->assertTrue(bunny_client::is_terminal_status('failed'));
        $this->assertFalse(bunny_client::is_terminal_status('encoding'));
        $this->assertFalse(bunny_client::is_terminal_status('pending'));
        $this->assertFalse(bunny_client::is_terminal_status(''));
    }

    /**
     * A URL da miniatura tira o protocolo do hostname antes de montar.
     *
     * @return void
     */
    public function test_thumbnail_url_strips_protocol(): void {
        $url = bunny_client::thumbnail_url('https://vz-abc.b-cdn.net/', 'guid-1', null);
        $this->assertSame('https://vz-abc.b-cdn.net/guid-1/thumbnail.jpg', $url);
    }

    /**
     * Nome de arquivo explicito e usado quando fornecido.
     *
     * @return void
     */
    public function test_thumbnail_url_uses_provided_filename(): void {
        $url = bunny_client::thumbnail_url('vz-abc.b-cdn.net', 'guid-1', 'custom.jpg');
        $this->assertSame('https://vz-abc.b-cdn.net/guid-1/custom.jpg', $url);
    }

    /**
     * Sem hostname nao ha URL de miniatura.
     *
     * @return void
     */
    public function test_thumbnail_url_returns_null_without_host(): void {
        $this->assertNull(bunny_client::thumbnail_url(null, 'guid-1', null));
        $this->assertNull(bunny_client::thumbnail_url('', 'guid-1', null));
    }
}
