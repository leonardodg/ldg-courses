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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * O que o professor cola, e o que a plataforma guarda.
 *
 * @package    mod_ldgvideo
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_ldgvideo\url::class)]
final class url_test extends \basic_testcase {
    /**
     * O que o professor cola, e o que sai disso.
     *
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function entradas_aceitas(): array {
        return [
            'o trecho inteiro que o YouTube exporta' => [
                '<iframe width="560" height="315" '
                . 'src="https://www.youtube.com/embed/d2bq9QW7fZg?si=_yAd63h_wkBsRARU" '
                . 'title="YouTube video player" frameborder="0" '
                . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media" '
                . 'referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
                'https://www.youtube.com/watch?v=d2bq9QW7fZg',
                '16:9',
            ],
            'trecho com aspas simples' => [
                "<iframe src='https://vimeo.com/226053498' width='640' height='360'></iframe>",
                'https://vimeo.com/226053498',
                '16:9',
            ],
            'trecho de um vertical: a proporcao vem do proprio tamanho' => [
                '<iframe width="315" height="560" src="https://www.youtube.com/embed/abc12345678"></iframe>',
                'https://www.youtube.com/watch?v=abc12345678',
                '9:16',
            ],
            'trecho antigo, 4 por 3' => [
                '<iframe width="640" height="480" src="https://www.youtube.com/embed/abc12345678"></iframe>',
                'https://www.youtube.com/watch?v=abc12345678',
                '4:3',
            ],
            'o link curto de compartilhar, com o rastreio junto' => [
                'https://youtu.be/d2bq9QW7fZg?si=_yAd63h_wkBsRARU',
                'https://youtu.be/d2bq9QW7fZg',
                null,
            ],
            'a barra de enderecos do navegador' => [
                'https://www.youtube.com/watch?v=d2bq9QW7fZg',
                'https://www.youtube.com/watch?v=d2bq9QW7fZg',
                null,
            ],
            'a playlist NAO e rastreio, entao fica' => [
                'https://www.youtube.com/watch?v=d2bq9QW7fZg&list=PL1234567890',
                'https://www.youtube.com/watch?v=d2bq9QW7fZg&list=PL1234567890',
                null,
            ],
            'o vertical se anuncia no caminho, e o caminho e lido ANTES de sumir' => [
                'https://www.youtube.com/shorts/d2bq9QW7fZg',
                'https://www.youtube.com/watch?v=d2bq9QW7fZg',
                '9:16',
            ],
            'o endereco de incorporacao do Vimeo tambem e canonicalizado' => [
                'https://player.vimeo.com/video/226053498',
                'https://vimeo.com/226053498',
                null,
            ],
            'a canonicalizacao preserva o resto da query' => [
                'https://www.youtube.com/embed/d2bq9QW7fZg?si=abc&start=30',
                'https://www.youtube.com/watch?v=d2bq9QW7fZg&start=30',
                null,
            ],
            'Vimeo, que atravessa sem que esta classe decida nada sobre ele' => [
                'https://vimeo.com/226053498',
                'https://vimeo.com/226053498',
                null,
            ],
            'a PORTA nao pode sumir na remontagem' => [
                'https://video.exemplo.com.br:8443/watch?v=abc',
                'https://video.exemplo.com.br:8443/watch?v=abc',
                null,
            ],
            'o fragmento tambem sobrevive' => [
                'https://vimeo.com/226053498#t=30s',
                'https://vimeo.com/226053498#t=30s',
                null,
            ],
            'espaco em volta, que colar de um PDF produz' => [
                "  https://vimeo.com/226053498  \n",
                'https://vimeo.com/226053498',
                null,
            ],
            'www no player do Vimeo tambem e canonicalizado' => [
                'https://www.player.vimeo.com/video/226053498',
                'https://vimeo.com/226053498',
                null,
            ],
            'videoseries de playlist NAO vira watch de video inexistente' => [
                'https://www.youtube.com/embed/videoseries?list=PL1234567890',
                'https://www.youtube.com/embed/videoseries?list=PL1234567890',
                null,
            ],
        ];
    }

    /**
     * A entrada vira endereco limpo, e a proporcao sai junto quando da.
     *
     * @param string $input
     * @param string $expected
     * @param string|null $ratio
     * @return void
     */
    #[DataProvider('entradas_aceitas')]
    public function test_normalize_aceita(string $input, string $expected, ?string $ratio): void {
        $output = url::normalize($input);

        $this->assertNotNull($output, 'a entrada devia ter sido aceita');
        $this->assertSame($expected, $output['url']->out(false));
        $this->assertSame($ratio, $output['ratio']);
    }

    /**
     * O que nao pode entrar.
     *
     * @return array<string, array{0: string}>
     */
    public static function entradas_recusadas(): array {
        return [
            'vazio' => [''],
            'so espaco' => ["   \n\t "],
            'HTML que nao e iframe - o portao do XSS entre inquilinos' => [
                '<script>alert(1)</script>',
            ],
            'iframe sem src' => ['<iframe width="560" height="315"></iframe>'],
            'iframe apontando para script' => [
                '<iframe src="javascript:alert(1)"></iframe>',
            ],
            'endereco de script direto' => ['javascript:alert(1)'],
            'texto solto' => ['a aula de hoje'],
            'caminho sem esquema' => ['/mod/ldgvideo/view.php?id=1'],
            'ftp nao e video' => ['ftp://exemplo.com/aula.mp4'],
        ];
    }

    /**
     * Entrada invalida devolve nulo, e nao uma URL torta.
     *
     * Devolver algo "quase certo" aqui seria pior que recusar: o formulario
     * salvaria, e o erro apareceria na tela do aluno.
     *
     * @param string $input
     * @return void
     */
    #[DataProvider('entradas_recusadas')]
    public function test_normalize_recusa(string $input): void {
        $this->assertNull(url::normalize($input));
    }

    /**
     * O HTML colado NUNCA sobrevive.
     *
     * E o portao que fecha o XSS entre inquilinos: HTML do vendedor A rodando
     * no navegador do aluno da empresa B. So o endereco atravessa.
     *
     * @return void
     */
    public function test_o_html_colado_nao_sobrevive(): void {
        $input = '<iframe src="https://vimeo.com/226053498" '
            . 'onload="alert(1)" width="560" height="315"></iframe>';

        $output = url::normalize($input);

        $this->assertNotNull($output);
        $stored = $output['url']->out(false);

        $this->assertStringNotContainsString('<', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('iframe', $stored);
        $this->assertSame('https://vimeo.com/226053498', $stored);
    }

    /**
     * As proporcoes aceitas sao tres, e a lista e a mesma do formulario.
     *
     * @return void
     */
    public function test_as_proporcoes_conhecidas(): void {
        $this->assertSame(
            ['16:9', '4:3', '9:16'],
            array_keys(url::ratios())
        );
    }
}
