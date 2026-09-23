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

namespace local_partners;

use PHPUnit\Framework\Attributes\CoversClass;
use local_marketplace\plan;

/**
 * Descoberta da landing por buscador.
 *
 * O que estes testes protegem: dado estruturado errado e pior que dado
 * estruturado ausente. Um preco desatualizado no schema aparece no resultado de
 * busca depois de ja ter mudado na pagina, e quem clica chega achando que foi
 * enganado; uma pergunta no schema que nao esta na tela e recusada pelos
 * validadores.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_partners\seo::class)]
final class seo_test extends \advanced_testcase {
    /**
     * O grafo JSON-LD que a pagina publica.
     *
     * @return array
     */
    private function graph(): array {
        $html = seo::head_html();

        $found = preg_match(
            '~<script type="application/ld\+json">(.*?)</script>~s',
            $html,
            $capture
        );

        $this->assertSame(1, $found, 'a pagina deveria publicar um bloco JSON-LD');

        $decoded = json_decode($capture[1], true);

        $this->assertNotNull($decoded, 'o bloco JSON-LD precisa ser JSON valido: ' . json_last_error_msg());

        return $decoded;
    }

    /**
     * Um no do grafo, pelo tipo.
     *
     * @param string $type
     * @return array|null
     */
    private function node(string $type): ?array {
        foreach ($this->graph()['@graph'] as $item) {
            if (($item['@type'] ?? '') === $type) {
                return $item;
            }
        }

        return null;
    }

    /**
     * O bloco JSON-LD e JSON valido, com contexto e grafo.
     *
     * @return void
     */
    public function test_o_json_ld_e_valido(): void {
        $this->resetAfterTest();

        $graph = $this->graph();

        $this->assertSame('https://schema.org', $graph['@context']);
        $this->assertNotEmpty($graph['@graph']);
    }

    /**
     * As barras saem escapadas, e isso nao e preciosismo.
     *
     * Com as barras escapadas, um "</script>" que venha de dado nao consegue
     * fechar o bloco e virar HTML. E o mesmo motivo pelo qual nao se concatena
     * dado dentro de script.
     *
     * @return void
     */
    public function test_o_bloco_nao_pode_ser_fechado_por_dado(): void {
        $this->resetAfterTest();

        $html = seo::head_html();
        $start = strpos($html, '<script type="application/ld+json">');
        $end = strpos($html, '</script>', $start);
        $body = substr($html, $start, $end - $start);

        $this->assertStringNotContainsString('</', $body);
    }

    /**
     * O FAQ do schema e o MESMO que a pagina mostra.
     *
     * @return void
     */
    public function test_o_faq_do_schema_e_o_mesmo_da_tela(): void {
        $this->resetAfterTest();

        $onscreen = array_column(\local_partners\output\landing_page::faq_items(), 'question');
        $inschema = array_column($this->node('FAQPage')['mainEntity'], 'name');

        $this->assertSame($onscreen, $inschema);
        $this->assertCount(4, $inschema);
    }

    /**
     * O preco do schema vem do banco, com a moeda do plano.
     *
     * @return void
     */
    public function test_o_preco_do_schema_vem_do_banco(): void {
        $this->resetAfterTest();

        $pro = plan::get_record_by_shortname('pro');
        $this->assertNotFalse($pro, 'o seed da instalacao deveria ter criado o plano pro');

        $offers = [];
        foreach ($this->node('Service')['offers'] as $offer) {
            $offers[$offer['name']] = $offer;
        }

        $expected = number_format((float) $pro->get('monthlyfee'), 2, '.', '');

        $this->assertSame($expected, $offers[format_string($pro->get('name'))]['price']);
        $this->assertSame($pro->get('currency'), $offers[format_string($pro->get('name'))]['priceCurrency']);
    }

    /**
     * Sem plano publico nao ha oferta nenhuma no schema.
     *
     * Anunciar servico sem preco e o tipo de dado que o validador aceita e o
     * leitor humano nao perdoa.
     *
     * @return void
     */
    public function test_sem_plano_publico_nao_ha_oferta(): void {
        global $DB;

        $this->resetAfterTest();
        $DB->set_field('local_marketplace_plan', 'ispublic', 0, []);

        $this->assertNull($this->node('Service'));
    }

    /**
     * Campo de marca vazio NAO entra no schema.
     *
     * E a regra que separa dado estruturado de alegacao com carimbo: o que
     * ninguem preencheu simplesmente nao e publicado.
     *
     * @return void
     */
    public function test_campo_de_marca_vazio_nao_entra(): void {
        $this->resetAfterTest();

        $org = $this->node('Organization');

        // Vazios em seo::brand(): endereco (o do contrato social e residencial),
        // telefone e e-mail (ainda nao ha os de empresa).
        $this->assertArrayNotHasKey('address', $org);
        $this->assertArrayNotHasKey('telephone', $org);
        $this->assertArrayNotHasKey('email', $org);

        // O que esta preenchido sai.
        $this->assertNotEmpty($org['name']);
        $this->assertNotEmpty($org['url']);
        $this->assertNotEmpty($org['legalName']);
        $this->assertNotEmpty($org['taxID']);
    }

    /**
     * O CPF do socio nao e publicado, em lugar nenhum.
     *
     * O CNPJ e dado da pessoa JURIDICA - sai na nota fiscal e no cadastro da
     * Receita. O CPF e de pessoa natural, e uma vez num dado estruturado
     * publico ele fica indexado, permanente e fora do nosso controle. Este
     * teste existe para o dia em que alguem colar "os dados da empresa" inteiros
     * dentro do brand().
     *
     * @return void
     */
    public function test_o_cpf_do_socio_nunca_e_publicado(): void {
        $this->resetAfterTest();

        $html = seo::head_html();

        // Um CPF em qualquer das grafias usuais.
        $this->assertDoesNotMatchRegularExpression(
            '~\b\d{3}\.\d{3}\.\d{3}-\d{2}\b~',
            $html,
            'ha algo com formato de CPF na saida publica'
        );
    }

    /**
     * O hreflang sai em BCP 47, e nao no formato do Moodle.
     *
     * O Moodle diz 'pt_br'; o padrao quer 'pt-BR'. Publicar o formato do Moodle
     * faz o buscador ignorar a tag inteira, sem avisar ninguem.
     *
     * @return void
     */
    public function test_hreflang_sai_em_bcp47(): void {
        $this->resetAfterTest();

        $html = seo::head_html();

        $this->assertStringContainsString('hreflang="x-default"', $html);
        $this->assertStringNotContainsString('hreflang="pt_br"', $html);
    }

    /**
     * O canonical acompanha onde a landing realmente mora.
     *
     * Duas URLs com o mesmo conteudo e sem canonical fazem o buscador escolher
     * uma por conta propria, e normalmente a errada.
     *
     * @return void
     */
    public function test_o_canonical_acompanha_a_home(): void {
        $this->resetAfterTest();

        set_config('enablelanding', 1, 'local_partners');

        set_config('frontpagemode', 'default', 'local_partners');
        $this->assertStringContainsString('/local/partners/index.php', seo::canonical_url());

        set_config('frontpagemode', 'landing', 'local_partners');
        $this->assertStringNotContainsString('/local/partners/index.php', seo::canonical_url());
    }

    /**
     * A pagina se declara indexavel.
     *
     * @return void
     */
    public function test_a_pagina_pede_para_ser_indexada(): void {
        $this->resetAfterTest();

        $this->assertStringContainsString('content="index, follow', seo::head_html());
    }

    /**
     * Com a indexacao desligada no site, NAO pedimos indexacao.
     *
     * O core ja emite noindex quando o administrador escolhe "em lugar nenhum".
     * Emitir "index, follow" ao lado deixaria as duas na pagina, e em conflito
     * vale a mais restritiva - a nossa seria derrotada em silencio, e a pagina
     * pareceria pedir indexacao sem nunca consegui-la.
     *
     * @return void
     */
    public function test_indexacao_desligada_no_site_e_respeitada(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->allowindexing = 2;

        $html = seo::head_html();

        $this->assertStringNotContainsString('content="index, follow', $html);
        // O resto do cabecalho continua: canonical e schema servem para quem
        // recebe o link por outro caminho.
        $this->assertStringContainsString('rel="canonical"', $html);
    }

    /**
     * A meta de verificacao so sai quando ha valor.
     *
     * Verificacao com valor errado e pior que ausente: o Search Console passa a
     * reportar "falhou" em vez de "faltando", e quem investiga procura no lugar
     * errado.
     *
     * @return void
     */
    public function test_a_verificacao_so_sai_com_valor(): void {
        $this->resetAfterTest();

        $this->assertSame('', seo::verification_html());

        set_config('searchconsoletoken', 'abc123XYZ', 'local_partners');

        $this->assertStringContainsString('name="google-site-verification"', seo::verification_html());
        $this->assertStringContainsString('abc123XYZ', seo::verification_html());
    }

    /**
     * Id de analytics malformado nao carrega script de terceiro.
     *
     * Um id errado nao mede nada, e mesmo assim traz o script do Google para
     * dentro de uma pagina publica - custo de rede e de privacidade sem
     * contrapartida nenhuma.
     *
     * @return void
     */
    public function test_id_de_analytics_malformado_nao_carrega_nada(): void {
        $this->resetAfterTest();

        $this->assertSame('', seo::analytics_html());

        set_config('analyticsid', 'nao-e-um-id', 'local_partners');
        $this->assertSame('', seo::analytics_html());

        set_config('analyticsid', 'G-ABC123XYZ', 'local_partners');
        $output = seo::analytics_html();

        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-ABC123XYZ', $output);
        $this->assertStringContainsString('anonymize_ip', $output);
    }

    /**
     * Cada versao de idioma aponta a canonica para ELA MESMA.
     *
     * E a regra que sustenta o hreflang inteiro. Quando /?lang=pt_br declara
     * canonica para /, as duas afirmacoes se contradizem e a canonica vence: o
     * buscador consolida tudo na versao sem parametro e DESCARTA o cluster, de
     * modo que a versao em portugues nunca e indexada.
     *
     * @return void
     */
    public function test_cada_idioma_se_autocanonicaliza(): void {
        $this->resetAfterTest();

        set_config('enablelanding', 1, 'local_partners');
        set_config('frontpagemode', 'landing', 'local_partners');

        // Sem idioma na requisicao, a canonica e a URL limpa: e o x-default.
        $this->assertStringNotContainsString('lang=', seo::canonical_url());

        // Com idioma, a canonica carrega o idioma - e nao a URL limpa.
        $this->assertStringContainsString('lang=pt_br', seo::canonical_url(seo::SURFACE_LANDING, 'pt_br'));
    }

    /**
     * O alternate de cada idioma e exatamente a canonica daquele idioma.
     *
     * Se as duas divergirem em um caractere, o cluster nao fecha: o buscador
     * procura o link de retorno na URL anunciada e nao acha.
     *
     * @return void
     */
    public function test_o_alternate_aponta_para_a_canonica_daquele_idioma(): void {
        $this->resetAfterTest();

        $html = seo::head_html();

        foreach (array_keys(get_string_manager()->get_list_of_translations()) as $lang) {
            $this->assertStringContainsString(
                'href="' . s(seo::canonical_url(seo::SURFACE_LANDING, $lang)) . '"',
                $html,
                "o alternate de {$lang} precisa ser a canonica daquele idioma"
            );
        }
    }

    /**
     * O x-default e a URL SEM parametro de idioma.
     *
     * @return void
     */
    public function test_o_x_default_e_a_url_sem_idioma(): void {
        $this->resetAfterTest();

        $this->assertStringContainsString(
            'hreflang="x-default" href="' . s(seo::base_url()) . '"',
            seo::head_html()
        );
    }

    /**
     * O titulo traz a marca, e o Moodle nao anexa o nome do site por cima.
     *
     * A marca entra na string de idioma para poder acompanhar o idioma. Deixar
     * o core anexar o nome curto amarraria a marca a um valor unico no banco, e
     * o titulo em portugues sairia com o sufixo em ingles.
     *
     * @return void
     */
    public function test_o_titulo_traz_a_marca_do_idioma(): void {
        $this->resetAfterTest();

        // O titulo servido e o do idioma corrente.
        $this->assertStringContainsString(
            get_string('brandname', 'local_partners'),
            seo::page_title()
        );

        // A marca precisa existir em CADA pacote, e diferente em cada um: e o
        // motivo de ela vir da string de idioma em vez do nome do site, que e
        // um valor unico no banco.
        //
        // Lemos o arquivo do pacote, e nao o get_string: o site de teste so tem
        // o ingles instalado como TRADUCAO, entao tanto o force_current_language
        // quanto o get_string com idioma explicito caem no ingles - e o teste
        // passaria sem provar nada.
        $brands = [];

        foreach (['en', 'pt_br', 'es'] as $lang) {
            $string = [];
            include(__DIR__ . "/../lang/{$lang}/local_partners.php");

            $this->assertArrayHasKey('brandname', $string, "o pacote {$lang} precisa definir a marca");
            $brands[$lang] = $string['brandname'];
        }

        $this->assertSame('LDG Technology', $brands['en']);
        $this->assertSame('LDG Tecnologia', $brands['pt_br']);
        $this->assertSame('LDG Tecnología', $brands['es']);
    }

    /**
     * A pagina de candidatura tem canonica propria, e nao a da landing.
     *
     * Ela esta no sitemap e no llms.txt como pagina principal: sem canonica
     * propria, ou some do indice ou compete com a landing.
     *
     * @return void
     */
    public function test_o_apply_tem_canonica_propria(): void {
        $this->resetAfterTest();

        set_config('enablelanding', 1, 'local_partners');
        set_config('frontpagemode', 'landing', 'local_partners');

        $this->assertStringContainsString('/local/partners/apply.php', seo::canonical_url(seo::SURFACE_APPLY));
        $this->assertStringNotContainsString('/local/partners/apply.php', seo::canonical_url());
    }

    /**
     * A candidatura declara os proprios metadados, e nao os da landing.
     *
     * @return void
     */
    public function test_o_apply_declara_os_proprios_metadados(): void {
        $this->resetAfterTest();

        $html = seo::head_html(seo::SURFACE_APPLY);

        $this->assertStringContainsString(
            '<link rel="canonical" href="' . s(seo::canonical_url(seo::SURFACE_APPLY)) . '">',
            $html
        );
        $this->assertStringContainsString('<meta name="description"', $html);
        $this->assertStringContainsString('hreflang="x-default"', $html);
    }

    /**
     * O og:title e o titulo da pagina, e nao o nome do site.
     *
     * O nome do site continua no og:site_name, que e o campo dele. O og:title e
     * o que o WhatsApp, o LinkedIn e o assistente de IA mostram como manchete:
     * repetir a marca ali gasta a manchete sem dizer o que a pagina resolve.
     *
     * @return void
     */
    public function test_o_og_title_e_o_titulo_da_pagina(): void {
        $this->resetAfterTest();

        $this->assertStringContainsString(
            '<meta property="og:title" content="' . s(seo::page_title()) . '">',
            seo::head_html()
        );
    }

    /**
     * Nome com "&" no schema sai como "&", e nao como entidade HTML.
     *
     * Dentro de <script type="application/ld+json"> o parser NAO decodifica
     * entidade: um format_string() escapado publica o nome da empresa
     * corrompido, e nenhum validador reclama.
     *
     * @return void
     */
    public function test_o_nome_no_schema_nao_carrega_entidade_html(): void {
        global $SITE, $DB;

        $this->resetAfterTest();

        $DB->set_field('course', 'fullname', 'LDG Courses & Technology', ['id' => $SITE->id]);
        $SITE = $DB->get_record('course', ['id' => $SITE->id]);

        $organization = $this->node('Organization');

        $this->assertSame('LDG Courses & Technology', $organization['name']);

        // E no atributo o escape sai UMA vez, e nao duas. Escapado nos dois
        // lugares, o "&" vira "&amp;amp;" e o compartilhamento mostra a
        // entidade em vez do nome.
        $html = seo::head_html();

        $this->assertStringContainsString('content="LDG Courses &amp; Technology"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * O og:locale nao inventa territorio para idioma que nao tem.
     *
     * O Open Graph pede lingua_TERRITORIO, e e tentador completar: "en_US" para
     * satisfazer o formato, ou o locale do langconfig, que devolve en_AU. Os
     * dois declaram um territorio que ninguem configurou - o pacote base do
     * Moodle e ingles GLOBAL, e o site em ingles nao e americano nem
     * australiano.
     *
     * @return void
     */
    public function test_o_og_locale_nao_inventa_territorio(): void {
        $this->resetAfterTest();

        $html = seo::head_html();

        // O idioma corrente do site de teste e o ingles global: sai 'en', seco.
        $this->assertStringContainsString('<meta property="og:locale" content="en">', $html);

        // E o territorio aparece quando ele existe no CODIGO do idioma.
        $this->assertSame('pt_BR', self::visible_locale('pt_br'));
        $this->assertSame('es_AR', self::visible_locale('es_ar'));
        $this->assertSame('es', self::visible_locale('es'));
    }

    /**
     * O locale que a classe publicaria para um idioma.
     *
     * @param string $lang
     * @return string
     */
    private static function visible_locale(string $lang): string {
        $method = new \ReflectionMethod(seo::class, 'locale_for');

        return $method->invoke(null, $lang);
    }

    /**
     * A landing pede o hero antes, e a candidatura nao pede.
     *
     * Imagem de fundo o preload scanner nao enxerga: ela so baixa depois do
     * CSSOM, e e a candidata a maior elemento visivel. Na candidatura o hero
     * nao existe, e preload de recurso que a pagina nao usa e banda jogada
     * fora, com aviso no console.
     *
     * @return void
     */
    public function test_so_a_landing_faz_preload_do_hero(): void {
        $this->resetAfterTest();

        $this->assertStringContainsString(
            '<link rel="preload" as="image"',
            seo::head_html()
        );
        $this->assertStringNotContainsString(
            '<link rel="preload" as="image"',
            seo::head_html(seo::SURFACE_APPLY)
        );
    }

    /**
     * O sitemap tem uma entrada por idioma, e nao uma entrada so.
     *
     * O protocolo exige que CADA URL do cluster tenha a propria entrada, com o
     * conjunto completo de alternates - inclusive a que aponta para ela mesma.
     * Uma entrada so nao tem link de retorno, e o cluster e descartado.
     *
     * @return void
     */
    public function test_o_sitemap_tem_uma_entrada_por_idioma(): void {
        $this->resetAfterTest();

        set_config('enablelanding', 1, 'local_partners');

        $languages = count(get_string_manager()->get_list_of_translations());
        $entries = seo::sitemap_entries();

        // Duas paginas publicas, cada uma com a URL limpa mais uma por idioma.
        $this->assertCount(2 * ($languages + 1), $entries);

        // Toda entrada carrega o cluster inteiro, para servir de link de retorno.
        foreach ($entries as $entry) {
            $this->assertCount($languages + 1, $entry['alternates']);
        }
    }
}
