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

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_marketplace\company;
use local_marketplace\plan;
use local_marketplace\plan_tier;
use local_partners\seo;
use moodle_url;

/**
 * A pagina de captacao de empresas parceiras.
 *
 * A comparacao de planos NAO e escrita no template: vem de
 * local_marketplace_plan, a mesma tabela que resolve a comissao de verdade.
 * Uma tabela de precos escrita a mao na landing envelheceria no primeiro
 * reajuste, e o visitante veria um numero que o sistema nao pratica.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class landing_page implements renderable, templatable {
    /**
     * Contexto para o template.
     *
     * @param renderer_base $output O renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'applyurl' => self::cta_url(),
            'heroimage' => (new moodle_url('/local/partners/pix/hero.jpg'))->out(false),
            'plans' => $this->plans(),
            'hasplans' => !empty($this->plans()),
            'steps' => $this->steps(),
            'faq' => $this->faq(),
            'sections' => self::sections(),
            'colormode' => self::color_mode(),
            'languages' => self::languages(),
            'haslanguages' => count(self::languages()) > 1,
            'currentlanguage' => self::current_language_label(),
            'footer' => self::footer(),
            'brand' => self::brand($output),
            'loginurl' => (new moodle_url('/login/index.php'))->out(false),
            'logouturl' => self::logout_url(),
            // Decide ONDE o alternador guarda a escolha: quem esta autenticado
            // grava a preferencia do perfil, que vale no site inteiro; o
            // visitante anonimo grava no navegador, porque setUserPreference
            // exige sessao - e o publico desta pagina e justamente ele.
            'isloggedin' => isloggedin() && !isguestuser(),
        ];
    }

    /**
     * Para onde os botoes "Apply" da landing mandam o visitante.
     *
     * DUAS respostas, dependendo de QUEM esta vendo a mesma pagina publica:
     *
     *   visitante anonimo, ou logado sem empresa - candidatura (apply.php),
     *   como sempre foi. E o publico que a landing quer atingir.
     *
     *   gerente de UMA empresa so, logado - a propria pagina de gerenciamento
     *   (local_marketplace/company.php), onde a secao de Plano fica. Ele ja
     *   e parceiro; mandar para o formulario de candidatura de novo seria um
     *   segundo cadastro que ninguem pediu.
     *
     * Gerente de DUAS OU MAIS empresas cai no caso anonimo de proposito: a
     * landing nao tem como perguntar qual empresa, e chutar uma erraria
     * metade das vezes.
     *
     * @return string
     */
    protected static function cta_url(): string {
        $apply = (new moodle_url('/local/partners/apply.php'))->out(false);

        if (!isloggedin() || isguestuser()) {
            return $apply;
        }

        global $USER;
        $companies = company::get_by_member((int) $USER->id);

        if (count($companies) !== 1) {
            return $apply;
        }

        return (new moodle_url('/local/marketplace/company.php', [
            'company' => reset($companies)->get('shortname'),
        ]))->out(false);
    }

    /**
     * As secoes da pagina, na ordem em que aparecem.
     *
     * Esta lista alimenta OS DOIS lados: os links da barra de secoes e os id=
     * das proprias secoes no template. Escrever a lista duas vezes e como uma
     * ancora passa a apontar para lugar nenhum sem ninguem notar - por isso ela
     * e exportada, e nao repetida no mustache.
     *
     * O `inbar` marca quem aparece na BARRA. So o "Apply" fica de fora, e por um
     * motivo: ele ja e o botao primario ao lado do de entrar, e a ancora repetia
     * a mesma acao a dois centimetros dela. Na barra o "Apply" e um botao; no
     * rodape, onde nao ha botao, continua sendo um link como os outros.
     *
     * @return array
     */
    public static function sections(): array {
        $out = [];

        foreach (['features', 'pricing', 'how', 'faq', 'apply'] as $id) {
            $out[] = [
                'id' => 'ldgp-' . $id,
                'label' => get_string('section' . $id, 'local_partners'),
                'inbar' => $id !== 'apply',
            ];
        }

        return $out;
    }

    /**
     * O endereco de sair, com a chave de sessao.
     *
     * O `logout.php` do Moodle EXIGE sesskey - sem ela ele recusa, e por um bom
     * motivo: um <img src="/login/logout.php"> numa pagina de terceiro derrubaria
     * a sessao de quem passasse por la.
     *
     * Devolve vazio para quem nao esta autenticado. Chamar sesskey() sem sessao
     * fabrica uma, e a landing e justamente a pagina do visitante anonimo.
     *
     * @return string
     */
    public static function logout_url(): string {
        if (!isloggedin() || isguestuser()) {
            return '';
        }

        return (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false);
    }

    /**
     * A marca, para a barra e para o rodape.
     *
     * Ela sumiu quando a navbar do tema saiu destas paginas, e nao pode sumir:
     * pagina de captacao sem marca e pagina de ninguem.
     *
     * A busca vai do mais especifico para o mais geral, e cada degrau tem uma
     * razao:
     *
     *  1. o logo do TEMA em uso, quando o renderer dele expuser um. E soft, por
     *     method_exists - o plugin nao pode declarar dependencia de tema nenhum,
     *     e sob Boost ou Moove o metodo simplesmente nao existe;
     *  2. o logo do SITE, que e do core e vale em qualquer tema;
     *  3. o nome curto do site, desenhado como marca-palavra. Sem imagem
     *     nenhuma, ainda ha marca - e nao um buraco.
     *
     * SAO DOIS RENDERERS, e nao um. A pagina de cadastro renderiza pelo renderer
     * DO PLUGIN (`$PAGE->get_renderer('local_partners')`), que nao tem get_logo
     * nenhum - e a marca caia para a palavra ali enquanto na landing aparecia a
     * imagem, com a mesma chamada. Por isso o renderer recebido e apenas o
     * primeiro candidato: o $OUTPUT do tema vem logo atras.
     *
     * @param renderer_base|null $output
     * @return array
     */
    public static function brand(?renderer_base $output = null): array {
        global $SITE, $OUTPUT;

        $claro = false;
        $escuro = false;

        foreach ([$output, $OUTPUT] as $renderer) {
            if ($renderer === null) {
                continue;
            }

            if (method_exists($renderer, 'get_logo')) {
                $claro = $renderer->get_logo();
                $escuro = method_exists($renderer, 'get_logo_dark') ? $renderer->get_logo_dark() : $claro;
            } else if (method_exists($renderer, 'get_logo_url')) {
                $url = $renderer->get_logo_url();
                $claro = $url ? $url->out(false) : false;
                $escuro = $claro;
            }

            if ($claro) {
                break;
            }
        }

        return [
            'name' => format_string($SITE->shortname),
            'homeurl' => (new moodle_url('/local/partners/index.php'))->out(false),
            'logolight' => $claro ?: false,
            'logodark' => $escuro ?: false,
            'haslogo' => (bool) $claro,
        ];
    }

    /**
     * O contexto do rodape, comum as duas paginas publicas.
     *
     * Existe porque estas paginas usam o layout 'embedded' e nao recebem chrome
     * nenhum do tema - a barra de secoes e o unico menu, e o rodape e nosso.
     *
     * @return array
     */
    public static function footer(bool $simples = false): array {
        global $SITE;

        $marca = seo::legal_name();
        $criador = seo::creator();
        $legais = self::legal_links();

        return [
            'sitename' => format_string($SITE->shortname),
            'landingurl' => (new moodle_url('/local/partners/index.php'))->out(false),
            'applyurl' => (new moodle_url('/local/partners/apply.php'))->out(false),
            'loginurl' => (new moodle_url('/login/index.php'))->out(false),
            'logouturl' => self::logout_url(),
            'isloggedin' => isloggedin() && !isguestuser(),
            // A mesma marca da barra: no rodape ela era so a palavra, e a
            // pagina terminava com um nome de site onde comecou com uma logo.
            'brand' => self::brand(),
            'sections' => self::sections(),
            // Vazia vira false para o mustache poder cair no nome do site, em
            // vez de imprimir uma empresa que ninguem declarou.
            'legalname' => $marca !== '' ? $marca : false,
            'taxid' => seo::tax_id() !== '' ? seo::tax_id() : false,
            'creatorname' => $criador['name'] !== '' ? $criador['name'] : false,
            'creatorurl' => $criador['url'],
            'legal' => $legais,
            'haslegal' => !empty($legais),
            'year' => userdate(time(), '%Y'),
            // A variante simples e uma linha so, como no mockup do cadastro. A
            // completa e a da landing, com colunas.
            'issimple' => $simples,
        ];
    }

    /**
     * Termos, privacidade e cookies - so os que tem destino.
     *
     * Link legal que nao leva a lugar nenhum e pior que link ausente: ele
     * PROMETE um documento. Cada um so aparece quando alguem configurou a URL,
     * e o padrao do de termos e a politica do site, que o Moodle ja tem.
     *
     * @return array
     */
    public static function legal_links(): array {
        global $CFG;

        $politica = !empty($CFG->sitepolicy) ? $CFG->sitepolicy : ($CFG->sitepolicyguest ?? '');

        $mapa = [
            'termsurl' => ['chave' => 'footerterms', 'padrao' => $politica],
            'privacyurl' => ['chave' => 'footerprivacy', 'padrao' => ''],
            'cookiesurl' => ['chave' => 'footercookies', 'padrao' => ''],
        ];

        $saida = [];

        foreach ($mapa as $config => $dados) {
            $url = trim((string) get_config('local_partners', $config));

            if ($url === '') {
                $url = (string) $dados['padrao'];
            }

            if ($url === '') {
                continue;
            }

            $saida[] = [
                'label' => get_string($dados['chave'], 'local_partners'),
                'url' => $url,
            ];
        }

        return $saida;
    }

    /**
     * Os idiomas oferecidos ao visitante.
     *
     * A lista sai de get_list_of_translations() SEM o parametro de "todos": ela
     * ja devolve o que esta instalado E habilitado, respeitando o $CFG->langlist
     * do administrador. Uma lista escrita no plugin divergiria dela no dia em
     * que alguem instalasse um idioma novo - e o pior caso e oferecer um idioma
     * que o site nao tem, que devolve a pagina em ingles sem explicar por que.
     *
     * Devolve vazio quando o menu de idiomas esta desligado no site: a landing
     * nao contraria a configuracao do Moodle.
     *
     * @param moodle_url|null $atual A pagina em que o visitante esta.
     * @return array
     */
    public static function languages(?moodle_url $atual = null): array {
        global $CFG, $PAGE;

        if (empty($CFG->langmenu)) {
            return [];
        }

        $traducoes = get_string_manager()->get_list_of_translations();

        if (count($traducoes) < 2) {
            return [];
        }

        $base = $atual ?? ($PAGE->has_set_url() ? $PAGE->url : new moodle_url('/local/partners/index.php'));
        $corrente = current_language();
        $saida = [];

        foreach ($traducoes as $codigo => $nome) {
            $url = new moodle_url($base, ['lang' => $codigo]);

            $saida[] = [
                'code' => $codigo,
                // O hreflang do link precisa do formato BCP 47, e nao do formato
                // do Moodle - a mesma conversao que a classe seo faz.
                'hreflang' => str_replace('_', '-', $codigo),
                // O nome vem do proprio pacote de idioma, entao cada opcao
                // aparece no idioma dela: quem procura portugues reconhece
                // "Portugues", e nao "Portuguese".
                'label' => $nome,
                'short' => self::language_short($codigo),
                'url' => $url->out(false),
                'iscurrent' => $codigo === $corrente,
            ];
        }

        return $saida;
    }

    /**
     * O codigo do Moodle reduzido a sigla de exibicao (pt_br -> PT).
     *
     * Unica implementacao da regra, compartilhada com o menu de idiomas do
     * theme_ldg por class_exists - nao declara dependencia do tema.
     *
     * @param string $lang
     * @return string
     */
    public static function language_short(string $lang): string {
        return strtoupper(explode('_', str_replace('-', '_', $lang))[0]);
    }

    /**
     * O rotulo curto do idioma em uso, para o botao do seletor.
     *
     * @return string
     */
    public static function current_language_label(): string {
        foreach (self::languages() as $idioma) {
            if ($idioma['iscurrent']) {
                return $idioma['short'];
            }
        }

        return self::language_short(current_language());
    }

    /**
     * O modo de cor com que a pagina nasce.
     *
     * Escuro e o padrao: e o que o desenho pede, e e o que o visitante anonimo
     * ve. O estado sai PRONTO do servidor - deixar o JavaScript corrigir depois
     * produz um pisca de escuro para claro a cada carregamento.
     *
     * A chave e a mesma do theme_ldg e do theme_moove ('dark-mode-on'), de
     * proposito: assim a escolha feita na landing continua valendo no resto do
     * site, e a do resto do site vale aqui. Nao lemos classe nenhuma dos temas -
     * so a preferencia, que e do core.
     *
     * @return string 'dark' ou 'light'.
     */
    public static function color_mode(): string {
        $preference = get_user_preferences('dark-mode-on', null);

        if ($preference === null) {
            return 'dark';
        }

        return $preference ? 'dark' : 'light';
    }

    /**
     * Planos publicos, prontos para a comparacao.
     *
     * @return array
     */
    protected function plans(): array {
        $out = [];

        foreach (plan::get_public_plans() as $plan) {
            $fee = (float) $plan->get('monthlyfee');

            $out[] = [
                'name' => format_string($plan->get('name')),
                'description' => format_string((string) $plan->get('description')),
                // Mensalidade zero vira palavra, e nao "R$ 0,00": e o argumento
                // central do Starter, e um zero formatado nao o comunica.
                'isfree' => $fee <= 0,
                'monthlyfee' => self::money($fee, $plan->get('currency')),
                'commissionpct' => format_float((float) $plan->get('commissionpct'), 2),
                'isbyos' => $plan->get('hostingmodel') === plan::HOSTING_BYOS,
                'hosting' => $plan->get('hostingmodel') === plan::HOSTING_BYOS
                    ? get_string('planhostingbyos', 'local_partners')
                    : get_string('planhostingnative', 'local_partners'),
                'tiers' => $this->tiers($plan),
                'hastiers' => !empty($this->tiers($plan)),
            ];
        }

        return $out;
    }

    /**
     * Faixas de resolucao de um plano, em texto.
     *
     * @param plan $plan
     * @return array
     */
    protected function tiers(plan $plan): array {
        $out = [];
        $previous = null;

        /** @var plan_tier $tier */
        foreach ($plan->get_tiers() as $tier) {
            $max = $tier->get('maxprice');
            $currency = $plan->get('currency');

            if ($max === null) {
                // A faixa final e descrita pelo teto da ANTERIOR: "acima de X".
                // Dizer "sem limite de preco" nao ajudaria quem esta comparando.
                $label = $previous === null
                    ? get_string('tierany', 'local_partners')
                    : get_string('tierabove', 'local_partners', self::money($previous, $currency));
            } else {
                $label = get_string('tierupto', 'local_partners', self::money((float) $max, $currency));
                $previous = (float) $max;
            }

            $out[] = [
                'label' => $label,
                'resolution' => $tier->get('maxresolution'),
            ];
        }

        return $out;
    }

    /**
     * Como funciona, em passos.
     *
     * @return array
     */
    protected function steps(): array {
        $steps = [];

        for ($i = 1; $i <= 4; $i++) {
            $steps[] = [
                'number' => $i,
                'title' => get_string('step' . $i . 'title', 'local_partners'),
                'text' => get_string('step' . $i . 'text', 'local_partners'),
            ];
        }

        return $steps;
    }

    /**
     * As perguntas frequentes, para quem precisa delas fora do template.
     *
     * O bloco JSON-LD da pagina precisa das MESMAS perguntas que aparecem na
     * tela: schema com pergunta que o visitante nao encontra e recusado pelos
     * validadores, e com razao. Uma lista so, dois consumidores.
     *
     * @return array
     */
    public static function faq_items(): array {
        return (new self())->faq();
    }

    /**
     * Perguntas frequentes.
     *
     * @return array
     */
    protected function faq(): array {
        $faq = [];

        for ($i = 1; $i <= 4; $i++) {
            $faq[] = [
                'id' => 'ldgfaq' . $i,
                'question' => get_string('faq' . $i . 'question', 'local_partners'),
                'answer' => get_string('faq' . $i . 'answer', 'local_partners'),
            ];
        }

        return $faq;
    }

    /**
     * Formata um valor com a moeda do plano.
     *
     * @param float $amount
     * @param string $currency Codigo ISO da moeda.
     * @return string
     */
    protected static function money(float $amount, string $currency): string {
        // O core sabe formatar moeda pelo idioma da pagina - e o mesmo helper
        // que o checkout usa, entao o visitante ve o valor no formato que vai
        // reencontrar na hora de pagar.
        return \core_payment\helper::get_cost_as_string($amount, $currency);
    }
}
