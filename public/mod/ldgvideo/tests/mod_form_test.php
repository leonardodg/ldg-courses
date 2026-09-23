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

namespace mod_ldgvideo;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Os dois portoes do campo de video.
 *
 * @package    mod_ldgvideo
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_ldgvideo\url::class)]
final class mod_form_test extends \advanced_testcase {
    /**
     * O que o professor cola de verdade passa.
     *
     * @return void
     */
    public function test_o_trecho_colado_passa(): void {
        $this->resetAfterTest();

        $trecho = '<iframe width="560" height="315" '
            . 'src="https://www.youtube.com/embed/d2bq9QW7fZg?si=_yAd63h_wkBsRARU" '
            . 'title="YouTube video player" allowfullscreen></iframe>';

        $this->assertNull(url::problem($trecho));
        $this->assertNull(url::problem('https://youtu.be/d2bq9QW7fZg'));
    }

    /**
     * As plataformas aceitas sao as dos players HABILITADOS no site.
     *
     * ESTE TESTE JA FOI UM ENGANO UTIL. A primeira versao afirmava que um
     * endereco do Vimeo passava - e falhou, porque o media_vimeo vem
     * DESLIGADO neste site: so videojs e youtube estao habilitados. O teste
     * estava afirmando configuracao, e nao codigo.
     *
     * Como esta agora, ele prova o que interessa: o conjunto de plataformas
     * aceitas segue a tela de Players de midia do core, sem uma linha de codigo
     * neste plugin. Ligar o Vimeo passa a valer aqui na hora.
     *
     * @return void
     */
    public function test_a_plataforma_aceita_segue_o_player_habilitado(): void {
        $this->resetAfterTest();

        $vimeo = 'https://vimeo.com/226053498';

        \core\plugininfo\media::set_enabled_plugins('videojs,youtube');
        \core_media_manager::reset_caches();
        $this->assertSame('errorplayerdisabled', url::problem($vimeo));

        \core\plugininfo\media::set_enabled_plugins('videojs,youtube,vimeo');
        \core_media_manager::reset_caches();
        $this->assertNull(url::problem($vimeo));

        // E o endereco de incorporacao do Vimeo tambem, que so passa porque a
        // canonicalizacao o levou para a forma que o core reconhece.
        $this->assertNull(url::problem('https://player.vimeo.com/video/226053498'));
    }

    /**
     * "Nao e video" e "o player esta desligado" sao coisas diferentes.
     *
     * ATE 06/09/2026 AS DUAS DAVAM A MESMA MENSAGEM, e uma delas mandava o
     * professor para o lado errado: com o media_vimeo desligado, um endereco
     * perfeito do Vimeo era recusado com "isto nao parece um endereco de
     * video". Conferir o link mil vezes nao resolveria - quem tinha que agir
     * era o administrador, e nada na tela dizia isso.
     *
     * @return void
     */
    public function test_player_desligado_nao_e_confundido_com_endereco_ruim(): void {
        $this->resetAfterTest();

        \core\plugininfo\media::set_enabled_plugins('youtube');
        \core_media_manager::reset_caches();

        // Endereco legitimo, player instalado e desligado: e configuracao.
        $this->assertSame('errorplayerdisabled', url::problem('https://vimeo.com/226053498'));

        // Endereco que player nenhum reconheceria, ligado ou desligado.
        $this->assertSame('erroraddressnotvideo', url::problem('https://exemplo.com.br/sobre'));
        $this->assertSame('erroraddressnotvideo', url::problem('a aula de hoje'));

        // E a fronteira do plano Free continua vindo ANTES das duas: um video
        // do proprio site e recusado por ser nosso, e nao por player nenhum.
        global $CFG;
        $this->assertSame(
            'errorselfhosted',
            url::problem($CFG->wwwroot . '/pluginfile.php/1/mod_resource/content/1/aula.mp4')
        );
    }

    /**
     * O primeiro portao: o que nao e video nao entra.
     *
     * @return void
     */
    public function test_recusa_o_que_nao_e_video(): void {
        $this->resetAfterTest();

        $this->assertSame('erroraddressnotvideo', url::problem(''));
        $this->assertSame('erroraddressnotvideo', url::problem('<script>alert(1)</script>'));
        $this->assertSame('erroraddressnotvideo', url::problem('a aula de hoje'));

        // Endereco valido, mas que nenhum player de midia reconhece.
        $this->assertSame('erroraddressnotvideo', url::problem('https://exemplo.com.br/sobre'));
    }

    /**
     * O segundo portao: video do proprio site nao entra.
     *
     * NAO E PARANOIA, e regra de negocio. O professor pode subir um .mp4 num
     * rotulo do curso, copiar o link do pluginfile.php e colar aqui - video
     * servido pela NOSSA banda, no plano que existe para custar zero de
     * infraestrutura.
     *
     * @return void
     */
    public function test_recusa_video_hospedado_aqui(): void {
        global $CFG;

        $this->resetAfterTest();

        $nosso = $CFG->wwwroot . '/pluginfile.php/123/mod_resource/content/1/aula.mp4';

        $this->assertSame('errorselfhosted', url::problem($nosso));
    }

    /**
     * O proprio host e recusado; um host que so COMECA igual, nao.
     *
     * str_starts_with contra o wwwroot marcaria como proprio um host externo
     * so porque a string comeca igual - ex. wwwroot https://ldg.example.com e
     * video em https://ldg.example.com.cdn-video.net/..., que o Moodle nao
     * serve.
     *
     * @return void
     */
    public function test_fronteira_de_dominio_na_autohospedagem(): void {
        global $CFG;

        $this->resetAfterTest();

        $original = $CFG->wwwroot;
        $CFG->wwwroot = 'https://ldg.example.com';

        try {
            // O proprio site, e o subdominio dele: fronteira de dominio casa.
            $this->assertSame(
                'errorselfhosted',
                url::problem('https://ldg.example.com/pluginfile.php/1/aula.mp4')
            );
            $this->assertSame(
                'errorselfhosted',
                url::problem('https://cdn.ldg.example.com/pluginfile.php/1/aula.mp4')
            );

            // Prefixo textual SEM ponto de fronteira: host externo de verdade.
            \core\plugininfo\media::set_enabled_plugins('videojs,youtube');
            \core_media_manager::reset_caches();
            $this->assertNotSame(
                'errorselfhosted',
                url::problem('https://ldg.example.com.cdn-video.net/embed/videoseries?list=PL1')
            );
        } finally {
            $CFG->wwwroot = $original;
        }
    }

    /**
     * O padrao do site cede a proporcao lida do trecho; a escolha do professor
     * nao.
     *
     * @return void
     */
    public function test_a_proporcao_lida_so_vale_se_o_professor_nao_escolheu(): void {
        $this->resetAfterTest();

        $vertical = '<iframe width="315" height="560" '
            . 'src="https://www.youtube.com/embed/d2bq9QW7fZg"></iframe>';

        $detected = url::normalize($vertical)['ratio'];
        $this->assertSame(url::RATIO_PORTRAIT, $detected);

        $default = url::RATIO_LANDSCAPE;

        // O professor nao mexeu no campo: a leitura entra.
        $this->assertSame(
            url::RATIO_PORTRAIT,
            url::choose_ratio($detected, $default, $default)
        );

        // O professor escolheu 4:3: a escolha dele vence.
        $this->assertSame(
            url::RATIO_CLASSIC,
            url::choose_ratio($detected, url::RATIO_CLASSIC, $default)
        );

        // Nao houve leitura: o campo manda, seja o padrao ou nao.
        $this->assertSame(
            url::RATIO_LANDSCAPE,
            url::choose_ratio(null, $default, $default)
        );
        $this->assertSame(
            url::RATIO_PORTRAIT,
            url::choose_ratio(null, url::RATIO_PORTRAIT, $default)
        );
    }
}
