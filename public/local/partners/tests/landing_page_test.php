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

namespace local_partners\output;

use PHPUnit\Framework\Attributes\CoversClass;
use local_marketplace\plan;
use local_marketplace\plan_tier;

/**
 * A pagina de captacao, do lado do servidor.
 *
 * O que estes testes protegem: a landing e a unica pagina PUBLICA do projeto, e
 * tudo que ela mostra sobre preco vem do banco. Um numero errado aqui e uma
 * oferta errada na cara de quem nunca entrou no site - nao ha login entre o
 * defeito e o visitante.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_partners\output\landing_page::class)]
final class landing_page_test extends \advanced_testcase {
    /**
     * O contexto que o template recebe.
     *
     * O RENDERER_TARGET_GENERAL nao e enfeite: sem ele o Moodle entrega o
     * core_renderer_cli quando o PHPUnit roda em linha de comando, e os testes
     * de negacao passariam verificando nada.
     *
     * @return array
     */
    private function contexto(): array {
        global $PAGE;

        return (new landing_page())->export_for_template(
            $PAGE->get_renderer('local_partners', null, RENDERER_TARGET_GENERAL)
        );
    }

    /**
     * O HTML que a landing realmente produz.
     *
     * @return string
     */
    private function html(): string {
        global $PAGE;

        $output = $PAGE->get_renderer('local_partners', null, RENDERER_TARGET_GENERAL);

        return $output->render_from_template('local_partners/landing', $this->contexto());
    }

    /**
     * Sem plano publico, a secao de precos nao e anunciada.
     *
     * O portao existe porque uma secao "Planos" vazia numa pagina publica
     * parece defeito, e nao ausencia de oferta.
     *
     * @return void
     */
    public function test_sem_plano_publico_a_secao_de_planos_nao_e_anunciada(): void {
        global $DB;

        $this->resetAfterTest();
        $DB->set_field('local_marketplace_plan', 'ispublic', 0, []);

        $contexto = $this->contexto();

        $this->assertFalse($contexto['hasplans']);
        $this->assertEmpty($contexto['plans']);
    }

    /**
     * O preco vem do banco, e mensalidade zero vira palavra.
     *
     * "R$ 0,00" nao comunica o argumento central do plano de entrada. Quem le
     * um zero formatado pensa em erro de cadastro.
     *
     * @return void
     */
    public function test_o_preco_vem_do_banco_e_nao_do_template(): void {
        $this->resetAfterTest();

        $pornome = [];
        foreach ($this->contexto()['plans'] as $plano) {
            $pornome[$plano['name']] = $plano;
        }

        $startfree = plan::get_record_by_shortname('start_free');
        $pro = plan::get_record_by_shortname('pro');
        $this->assertNotFalse($startfree, 'o seed da instalacao deveria ter criado o plano start_free');

        $this->assertTrue($pornome[$startfree->get('name')]['isfree']);
        $this->assertFalse($pornome[$pro->get('name')]['isfree']);
        // A comissao sai do registro, com duas casas.
        $this->assertSame('10.00', $pornome[$startfree->get('name')]['commissionpct']);
    }

    /**
     * A faixa final e descrita pelo teto da ANTERIOR.
     *
     * Dizer "sem limite de preco" nao ajudaria quem esta comparando planos, e
     * este e o ramo mais sutil da classe - o unico que depende do estado do
     * laco anterior.
     *
     * @return void
     */
    public function test_a_faixa_final_e_descrita_pelo_teto_anterior(): void {
        $this->resetAfterTest();

        // Os planos do seed (start_free/start_50/start_100/pro, desde
        // 17/09/2026) tem uma faixa SO cada - o teste da faixa MULTIPLA
        // precisa do proprio plano, criado aqui, e nao mais do seed.
        $plano = new plan(0, (object) [
            'shortname' => 'multiplasfaixas' . random_int(100000, 999999),
            'name' => 'Plano de tres faixas',
            'monthlyfee' => 0,
            'commissionpct' => 10,
        ]);
        $plano->create();

        $faixas = [
            ['maxprice' => 49.90, 'maxresolution' => '720p', 'sortorder' => 10],
            ['maxprice' => 200.00, 'maxresolution' => '1080p', 'sortorder' => 20],
            ['maxprice' => null, 'maxresolution' => '4k', 'sortorder' => 30],
        ];
        foreach ($faixas as $dados) {
            $dados['planid'] = (int) $plano->get('id');
            (new plan_tier(0, (object) $dados))->create();
        }

        $tiers = [];
        foreach ($this->contexto()['plans'] as $item) {
            if ($item['name'] === $plano->get('name')) {
                $tiers = $item['tiers'];
            }
        }

        $this->assertCount(3, $tiers);

        $ultima = end($tiers);

        $this->assertSame('4k', $ultima['resolution']);
        // O teto da faixa do meio e 200,00, entao a ultima e "acima de" esse
        // valor - e nao "acima de" o teto dela propria, que e nulo.
        $this->assertStringContainsString('200', $ultima['label']);
    }

    /**
     * Quatro passos e quatro perguntas, com texto de verdade.
     *
     * Uma chave de idioma faltando renderiza [[step5title]] numa pagina
     * publica, em silencio: o Moodle nao lanca, so imprime a chave.
     *
     * @return void
     */
    public function test_quatro_passos_e_quatro_perguntas(): void {
        $this->resetAfterTest();

        $contexto = $this->contexto();

        $this->assertCount(4, $contexto['steps']);
        $this->assertCount(4, $contexto['faq']);

        foreach ($contexto['steps'] as $passo) {
            $this->assertStringNotContainsString('[[', $passo['title']);
            $this->assertStringNotContainsString('[[', $passo['text']);
        }

        foreach ($contexto['faq'] as $pergunta) {
            $this->assertStringNotContainsString('[[', $pergunta['question']);
            $this->assertStringNotContainsString('[[', $pergunta['answer']);
        }
    }

    /**
     * As ancoras da barra apontam para secoes que existem na pagina.
     *
     * A lista de secoes e exportada UMA vez e alimenta os dois lados: os links
     * da barra e os id= das secoes. Escrever a lista duas vezes e exatamente
     * como uma ancora passa a apontar para lugar nenhum sem ninguem notar.
     *
     * @return void
     */
    public function test_as_ancoras_saem_da_mesma_fonte_que_a_barra(): void {
        $this->resetAfterTest();

        $secoes = $this->contexto()['sections'];
        $html = $this->html();

        $this->assertNotEmpty($secoes);

        $ids = array_column($secoes, 'id');
        $this->assertSame($ids, array_unique($ids), 'id de secao repetido faz a ancora pular para a primeira');

        foreach ($secoes as $secao) {
            $this->assertStringNotContainsString('[[', $secao['label']);
            // A secao existe na pagina, esteja ela na barra ou nao.
            $this->assertStringContainsString('id="' . $secao['id'] . '"', $html);

            if (empty($secao['inbar'])) {
                continue;
            }

            // E quem esta na barra tem um link que aponta para ela.
            $this->assertStringContainsString('href="#' . $secao['id'] . '"', $html);
        }
    }

    /**
     * O "Apply" nao aparece duas vezes na barra.
     *
     * Ele e o BOTAO primario da barra, e enquanto tambem era ancora a mesma
     * acao aparecia duas vezes a dois centimetros de distancia - uma como link
     * de texto no meio das secoes, outra como botao azul na ponta.
     *
     * O `inbar` e o que separa os dois usos, e este teste segura os DOIS lados:
     * a ancora sai da barra, e a secao continua existindo na pagina para o
     * botao ter para onde levar.
     *
     * @return void
     */
    public function test_a_ancora_de_candidatura_nao_repete_o_botao_da_barra(): void {
        $this->resetAfterTest();

        $secoes = $this->contexto()['sections'];
        $apply = null;

        foreach ($secoes as $secao) {
            if ($secao['id'] === 'ldgp-apply') {
                $apply = $secao;
            }
        }

        $this->assertNotNull($apply, 'a secao de candidatura sumiu da lista');
        $this->assertFalse($apply['inbar'], 'a candidatura voltou a ser ancora da barra');

        $html = $this->html();
        $barra = substr($html, strpos($html, 'ldgp-bar-list'));
        $barra = substr($barra, 0, strpos($barra, '</ul>'));

        $this->assertStringNotContainsString('href="#ldgp-apply"', $barra);
        $this->assertStringContainsString('id="ldgp-apply"', $html);
    }

    /**
     * Quem nunca escolheu ve a pagina escura.
     *
     * @return void
     */
    public function test_modo_escuro_e_o_padrao_para_quem_nunca_escolheu(): void {
        $this->resetAfterTest();

        $this->assertSame('dark', $this->contexto()['colormode']);
    }

    /**
     * Quem escolheu claro ve claro desde o servidor.
     *
     * O estado inicial precisa sair pronto do servidor. Deixar o JavaScript
     * corrigir depois produz um pisca de escuro para claro a cada carregamento.
     *
     * @return void
     */
    public function test_preferencia_do_usuario_manda_no_estado_inicial(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        set_user_preference('dark-mode-on', 0, $user);
        $this->assertSame('light', $this->contexto()['colormode']);

        set_user_preference('dark-mode-on', 1, $user);
        $this->assertSame('dark', $this->contexto()['colormode']);
    }

    /**
     * O template renderiza sem lancar.
     *
     * Teste de contexto nao ve erro de mustache: uma chave errada, um bloco nao
     * fechado ou um parcial inexistente so aparecem quando alguem renderiza.
     *
     * @return void
     */
    public function test_o_template_renderiza(): void {
        $this->resetAfterTest();

        $html = $this->html();

        $this->assertStringContainsString('ldgp', $html);
        $this->assertStringNotContainsString('[[', $html);
    }

    /**
     * O seletor lista exatamente os idiomas que o site tem.
     *
     * A fonte e o proprio Moodle. Uma lista escrita no plugin divergiria dela
     * no dia em que alguem instalasse um idioma - e o pior caso e oferecer um
     * idioma que nao existe, que devolve a pagina em ingles sem explicar por
     * que.
     *
     * @return void
     */
    public function test_o_seletor_lista_o_que_o_site_tem(): void {
        $this->resetAfterTest();

        $instalados = array_keys(get_string_manager()->get_list_of_translations());
        $oferecidos = array_column(landing_page::languages(), 'code');

        if (count($instalados) < 2) {
            // Com um idioma so NAO ha seletor, e isso e a regra e nao uma
            // limitacao: um seletor de uma opcao so ocupa espaco e nao decide
            // nada. E o caso do ambiente do phpunit, que instala so o ingles.
            $this->assertSame([], $oferecidos);

            return;
        }

        sort($instalados);
        sort($oferecidos);

        $this->assertSame($instalados, $oferecidos);
    }

    /**
     * O hreflang de cada link sai em BCP 47.
     *
     * O Moodle diz 'pt_br' e o padrao quer 'pt-br'. O atributo do link precisa
     * concordar com o do cabecalho, senao o buscador ve dois sinais diferentes
     * para a mesma pagina.
     *
     * @return void
     */
    public function test_o_hreflang_do_link_nao_usa_sublinhado(): void {
        $this->resetAfterTest();

        $idiomas = landing_page::languages();

        if (!$idiomas) {
            $this->markTestSkipped('o site de teste tem um idioma so, e ai nao ha seletor');
        }

        foreach ($idiomas as $idioma) {
            $this->assertStringNotContainsString('_', $idioma['hreflang']);
            // A URL, ao contrario, usa o codigo do Moodle - e ele que o site le.
            $this->assertStringContainsString('lang=' . $idioma['code'], $idioma['url']);
        }
    }

    /**
     * Menu de idiomas desligado no site esconde o seletor.
     *
     * A landing nao contraria a configuracao do Moodle: se o administrador
     * desligou a troca de idioma, ela nao reaparece por uma porta lateral.
     *
     * @return void
     */
    public function test_menu_de_idiomas_desligado_esconde_o_seletor(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->langmenu = 0;

        $this->assertSame([], landing_page::languages());
        $this->assertFalse($this->contexto()['haslanguages']);
    }

    /**
     * O docblock do template nao vaza para a tela.
     *
     * Um comentario de mustache termina no PRIMEIRO fecha-chaves duplo. Citar
     * uma tag dentro do comentario - escrever o nome de um bloco entre chaves
     * para explicar de onde vem o dado - encerra o comentario ali, e todo o
     * resto do texto vai para a pagina publica como paragrafo.
     *
     * Aconteceu, e nenhum teste pegou: o contexto estava certo, o phpunit
     * passava, e o defeito so apareceu na captura de tela. Este teste e a rede.
     *
     * @return void
     */
    public function test_o_comentario_do_template_nao_vaza_para_a_tela(): void {
        $this->resetAfterTest();

        $html = $this->html();

        foreach (['@template', 'Context variables', 'Example context'] as $marca) {
            $this->assertStringNotContainsString($marca, $html, 'o docblock do template escapou para a pagina');
        }
    }
    /**
     * A saida da sessao existe na barra, e leva a chave de sessao junto.
     *
     * O logout.php do Moodle recusa chamada sem sesskey, e com razao: uma
     * <img src="/login/logout.php"> numa pagina de terceiro derrubaria a sessao
     * de quem passasse por la. Um botao "Sair" que devolve erro e pior que
     * nenhum botao.
     *
     * @return void
     */
    public function test_o_botao_de_sair_leva_a_chave_de_sessao(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $url = landing_page::logout_url();

        $this->assertStringContainsString('/login/logout.php', $url);
        $this->assertStringContainsString('sesskey=', $url);
        $this->assertStringContainsString($url, $this->html());
    }

    /**
     * Visitante anonimo nao recebe endereco de sair.
     *
     * Nao e detalhe de aparencia: sesskey() FABRICA uma sessao quando nao ha
     * nenhuma, e o publico desta pagina e justamente quem ainda nao entrou.
     *
     * @return void
     */
    public function test_visitante_anonimo_nao_recebe_endereco_de_sair(): void {
        $this->resetAfterTest();
        $this->setUser(null);

        $this->assertSame('', landing_page::logout_url());
    }

    /**
     * Visitante anonimo, ou logado sem empresa nenhuma, ve o botao "Apply"
     * levar para a candidatura - o comportamento de sempre.
     *
     * @return void
     */
    public function test_botao_leva_a_candidatura_sem_empresa(): void {
        $this->resetAfterTest();

        $this->setUser(null);
        $this->assertStringContainsString('/local/partners/apply.php', $this->contexto()['applyurl']);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertStringContainsString('/local/partners/apply.php', $this->contexto()['applyurl']);
    }

    /**
     * Gerente de UMA empresa so, logado, ve o botao "Apply" levar para a
     * PROPRIA pagina de gerenciamento - ele ja e parceiro, e mandar para a
     * candidatura de novo seria um segundo cadastro que ninguem pediu.
     *
     * @return void
     */
    public function test_botao_leva_ao_painel_de_quem_ja_gerencia_uma_empresa(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        $company = \local_marketplace\api::create_company((object) [
            'name' => 'Empresa da landing',
            'shortname' => 'landingtest' . random_int(100000, 999999),
        ], (int) $owner->id);

        $this->setUser($owner);
        $url = $this->contexto()['applyurl'];

        $this->assertStringContainsString('/local/marketplace/company.php', $url);
        $this->assertStringContainsString((string) $company->get('shortname'), $url);
    }

    /**
     * Gerente de DUAS empresas cai no caso padrao (candidatura) - a landing
     * nao tem como perguntar qual das duas, e chutar uma erraria a metade
     * das vezes.
     *
     * @return void
     */
    public function test_botao_nao_escolhe_entre_duas_empresas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        \local_marketplace\api::create_company((object) [
            'name' => 'Primeira empresa',
            'shortname' => 'landingdupla1' . random_int(100000, 999999),
        ], (int) $owner->id);
        \local_marketplace\api::create_company((object) [
            'name' => 'Segunda empresa',
            'shortname' => 'landingdupla2' . random_int(100000, 999999),
        ], (int) $owner->id);

        $this->setUser($owner);

        $this->assertStringContainsString('/local/partners/apply.php', $this->contexto()['applyurl']);
    }

    /**
     * A marca acha a logo mesmo quando o renderer recebido nao tem nenhuma.
     *
     * A pagina de cadastro renderiza pelo renderer DO PLUGIN, que nao expoe
     * get_logo; a landing renderiza pelo do tema, que expoe. Com a mesma
     * chamada, uma mostrava a imagem e a outra caia para a palavra - e o
     * sintoma era "a marca some no cadastro".
     *
     * @return void
     */
    public function test_a_marca_cai_para_o_renderer_do_tema(): void {
        global $PAGE;

        $this->resetAfterTest();

        $doplugin = $PAGE->get_renderer('local_partners');
        $marca = landing_page::brand($doplugin);

        // Sem logo configurada no site de teste, o que se prova e que a busca
        // percorre os dois renderers e devolve a estrutura completa - e nao que
        // ha imagem, que depende de configuracao.
        $this->assertArrayHasKey('haslogo', $marca);
        $this->assertArrayHasKey('logolight', $marca);
        $this->assertNotEmpty($marca['name']);
    }

    /**
     * O rodape do SITE recebe a marca e a frase, e nao so os links legais.
     *
     * E a superficie que o theme_ldg consome. Sem estes dois campos o rodape do
     * tema teria que escrever a propria frase, e as duas versoes divergiriam na
     * primeira correcao de texto.
     *
     * @return void
     */
    public function test_o_rodape_do_site_leva_marca_e_frase(): void {
        $this->resetAfterTest();

        $rodape = \local_partners\landing::site_footer();

        $this->assertArrayHasKey('brand', $rodape);
        $this->assertArrayHasKey('haslogo', $rodape['brand']);
        $this->assertNotEmpty($rodape['tagline']);
        $this->assertStringNotContainsString('[[', $rodape['tagline']);
    }
}
