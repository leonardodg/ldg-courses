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

/**
 * Passos de teste da atividade de video.
 *
 * @package    mod_ldgvideo
 * @category   test
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Mink\Exception\ExpectationException;

/**
 * O que so o navegador consegue provar sobre esta atividade: o TAMANHO.
 *
 * A razao de este arquivo existir e que a promessa central do plugin - "a
 * largura vem da coluna e a altura sai da proporcao" - nao aparece em teste
 * nenhum de servidor. O HTML sai igual em qualquer largura de tela; o que muda
 * e o que o navegador calcula a partir do CSS.
 *
 * O core_media_manager emite width e height em PIXEL FIXO no iframe, e a
 * unica coisa que os anula e o aspect-ratio do styles.css. Um dia alguem mexe
 * naquele arquivo, o atributo do core volta a valer, e o video encolhe para
 * 560x315 no meio da coluna - sem erro, sem log, e sem nenhum teste falhando.
 * E este passo que falha.
 *
 * @package    mod_ldgvideo
 * @category   test
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_ldgvideo extends behat_base {
    /**
     * O quadro do video guarda a proporcao pedida.
     *
     * @Then /^the video frame should keep the "(?P<ratio_string>(?:[^"]|\\")*)" ratio$/
     *
     * @param string $ratio Proporcao no formato "16:9".
     * @return void
     */
    public function the_video_frame_should_keep_the_ratio(string $ratio): void {
        $this->require_javascript();

        [$width, $height] = array_map('intval', explode(':', $ratio));

        if (!$width || !$height) {
            throw new PendingException("Proporcao '{$ratio}' nao entendida; use o formato 16:9.");
        }

        $measured = $this->measure_frame();
        $nominal = $width / $height;
        $obtained = $measured['width'] / $measured['height'];

        // A folga cobre o arredondamento de subpixel do navegador, e nada mais:
        // um quadro que virou 560x315 fixo numa coluna de 900px erra por muito
        // mais do que isto.
        if (abs($obtained - $nominal) / $nominal > 0.02) {
            throw new ExpectationException(
                sprintf(
                    'O quadro mede %dx%d, proporcao %.3f, e deveria ser %s (%.3f).',
                    $measured['width'],
                    $measured['height'],
                    $obtained,
                    $ratio,
                    $nominal
                ),
                $this->getSession()
            );
        }
    }

    /**
     * O quadro ocupa a largura que a coluna deu a ele, e nao um numero fixo.
     *
     * @Then /^the video frame should fill its column$/
     *
     * @return void
     */
    public function the_video_frame_should_fill_its_column(): void {
        $this->require_javascript();

        $measured = $this->measure_frame();

        // Um quadro que caiu no 560 do atributo do core, ou perto disso, dentro
        // de uma coluna bem mais larga, e exatamente o defeito que se persegue.
        if ($measured['width'] < $measured['parent'] - 2) {
            throw new ExpectationException(
                sprintf(
                    'O quadro mede %dpx numa coluna de %dpx - ele deveria acompanhar a coluna.',
                    $measured['width'],
                    $measured['parent']
                ),
                $this->getSession()
            );
        }
    }

    /**
     * Mede o quadro do video na tela.
     *
     * @return array{width: float, height: float, parent: float}
     */
    protected function measure_frame(): array {
        // ENVOLVIDO NUMA FUNCAO de proposito: o evaluate_script do Moodle avalia
        // uma EXPRESSAO, e nao um bloco - declarar const ou usar return solto ali
        // devolve "Unexpected token 'const'" vindo do Chrome, longe da causa.
        $measured = $this->evaluate_script(
            '(function() {'
            . 'var el = document.querySelector(".ldgvideo__frame");'
            . 'if (!el) { return null; }'
            . 'var r = el.getBoundingClientRect();'
            . 'var parent = el.parentElement;'
            . 'var s = window.getComputedStyle(parent);'
            // A largura da CAIXA DE CONTEUDO do pai, e nao a de borda: um
            // width:100% preenche o conteudo, e o padding do pai fica de fora.
            // Comparar com a caixa de borda acusaria falha por 30px de padding
            // com o CSS perfeitamente correto - foi o que aconteceu aqui.
            . 'var available = parent.clientWidth'
            . ' - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight);'
            . 'return {width: r.width, height: r.height, parent: available};'
            . '})()'
        );

        if (!$measured) {
            throw new ExpectationException(
                'Nao ha nenhum .ldgvideo__frame nesta pagina.',
                $this->getSession()
            );
        }

        return $measured;
    }
}
