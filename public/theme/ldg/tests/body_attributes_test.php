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

namespace theme_ldg;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * As classes do `<body>`, que decidem como a página aparece.
 *
 * O caso que dá mais trabalho de redescobrir é o `ldg-embedded`: o `format_ldg`
 * embute a atividade num iframe, e o tema esconde cabeçalho, menu e rodapé
 * quando detecta que o destino é um quadro. Um teste de servidor não prova a
 * aparência — mas prova a **decisão**, que é onde o erro mora.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\theme_ldg\output\core_renderer::class)]
final class body_attributes_test extends \advanced_testcase {
    /**
     * Deixa o ambiente no tema LDG, e limpa o que a requisição anterior deixou.
     *
     * @return void
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        unset($_SERVER['HTTP_SEC_FETCH_DEST']);
        unset($_GET['ldgembed']);

        $PAGE->set_url('/');
        $PAGE->force_theme('ldg');
    }

    /**
     * Limpa o cabeçalho e o parâmetro, que são superglobais e vazam entre testes.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_SERVER['HTTP_SEC_FETCH_DEST']);
        unset($_GET['ldgembed']);

        parent::tearDown();
    }

    /**
     * O renderer do tema.
     *
     * O `RENDERER_TARGET_GENERAL` é obrigatório aqui, e custou uma rodada
     * vermelha para aparecer: o PHPUnit roda em CLI, e sem o alvo explícito o
     * Moodle entrega o `core\output\core_renderer_cli` — que não é o do tema e
     * não tem nada disto. Pior, os testes de negação passariam assim mesmo,
     * verificando nada.
     *
     * @return \theme_ldg\output\core_renderer
     */
    protected function renderer(): \theme_ldg\output\core_renderer {
        global $PAGE;

        return $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
    }

    /**
     * Os atributos do `<body>` como o tema os monta.
     *
     * @return string
     */
    protected function attributes(): string {
        return $this->renderer()->body_attributes();
    }

    /**
     * Navegação normal NÃO esconde o chrome.
     *
     * É a metade que se esquece de testar. O `Sec-Fetch-Dest` vem em toda
     * navegação, com valores diferentes; se a comparação fosse frouxa — um
     * `!empty()`, por exemplo — o site inteiro perderia o cabeçalho.
     *
     * @return void
     */
    public function test_navegacao_normal_mantem_o_chrome(): void {
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';

        $this->assertStringNotContainsString('ldg-embedded', $this->attributes());
    }

    /**
     * Sem o cabeçalho nenhum, também não esconde.
     *
     * @return void
     */
    public function test_sem_cabecalho_mantem_o_chrome(): void {
        $this->assertStringNotContainsString('ldg-embedded', $this->attributes());
    }

    /**
     * Destino iframe esconde o chrome.
     *
     * @return void
     */
    public function test_iframe_esconde_o_chrome(): void {
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';

        $this->assertStringContainsString('ldg-embedded', $this->attributes());
    }

    /**
     * O parâmetro de URL é a reserva para navegador sem Fetch Metadata.
     *
     * @return void
     */
    public function test_parametro_de_url_e_a_reserva(): void {
        $_GET['ldgembed'] = 1;

        $this->assertStringContainsString('ldg-embedded', $this->attributes());
    }

    /**
     * O modo de cor sai na classe E no `data-bs-theme`.
     *
     * São duas saídas do mesmo valor, e quem lê cada uma é diferente: o SCSS do
     * tema usa a classe, e o Bootstrap 5 usa o atributo. Divergirem significa
     * componente claro dentro de página escura.
     *
     * @return void
     */
    public function test_modo_de_cor_sai_nos_dois_lugares(): void {
        set_user_preference('dark-mode-on', 1);
        $atributos = $this->attributes();

        $this->assertStringContainsString('ldg-dark', $atributos);
        $this->assertStringContainsString("data-bs-theme='dark'", $atributos);

        set_user_preference('dark-mode-on', 0);
        $atributos = $this->attributes();

        $this->assertStringContainsString('ldg-light', $atributos);
        $this->assertStringContainsString("data-bs-theme='light'", $atributos);
    }

    /**
     * A barra de acessibilidade é opt-in.
     *
     * Ela já foi sempre visível, e o resultado era uma faixa fixa acima da
     * navbar para todo mundo. Sem preferência gravada, não aparece.
     *
     * @return void
     */
    public function test_barra_de_acessibilidade_e_opt_in(): void {
        $this->assertStringNotContainsString('hasaccessibilitybar', $this->attributes());

        set_user_preference('theme_ldg-accessibilitybar', 1);

        $this->assertStringContainsString('hasaccessibilitybar', $this->attributes());
    }

    /**
     * As classes de acessibilidade escolhidas pelo usuário entram no body.
     *
     * @return void
     */
    public function test_classes_de_acessibilidade_entram(): void {
        set_user_preference('accessibilitystyles_fontsizeclass', 'fontsize-120');
        set_user_preference('accessibilitystyles_sitecolorclass', 'sitecolor-bw');

        $atributos = $this->attributes();

        $this->assertStringContainsString('fontsize-120', $atributos);
        $this->assertStringContainsString('sitecolor-bw', $atributos);
    }

    /**
     * Classe passada por quem chama continua chegando ao body.
     *
     * @return void
     */
    public function test_classe_recebida_e_preservada(): void {
        $atributos = $this->renderer()->body_attributes(['minha-classe']);

        $this->assertStringContainsString('minha-classe', $atributos);
    }

    /**
     * O renderer sob teste é mesmo o do tema.
     *
     * Guarda contra o modo de falha que este arquivo já teve: com o renderer
     * errado, todo `assertStringNotContainsString` daqui passa sem provar nada.
     *
     * @return void
     */
    public function test_o_renderer_e_o_do_tema(): void {
        $this->assertInstanceOf(\theme_ldg\output\core_renderer::class, $this->renderer());
    }
}
