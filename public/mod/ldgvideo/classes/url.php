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
 * O que o professor cola vira endereco.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ldgvideo;

/**
 * Extrai o endereco do video daquilo que o professor colou.
 *
 * EXTRAIR, E NAO AVISAR. O botao Compartilhar -> Incorporar de toda plataforma
 * de video entrega um trecho de HTML inteiro, com width e height em pixel:
 *
 *   <iframe width="560" height="315" src="https://..." ...></iframe>
 *
 * Avisar "nao cole assim" transfere ao professor um trabalho que a maquina faz
 * melhor - e, se ele colar mesmo assim, o aviso nao impediu nada. Entao o campo
 * aceita, pega o src, aproveita o width/height so para adivinhar a proporcao, e
 * JOGA FORA OS PIXELS.
 *
 * ESTA CLASSE NAO RECONHECE PLATAFORMA, e essa e a decisao central. Dizer se um
 * endereco e video que da para embutir e trabalho do core_media_manager, que ja
 * traz o regex de cada player. Duplicar aquilo aqui criaria a segunda fonte de
 * verdade do mesmo padrao - e o plugin deixaria de ganhar de graca toda
 * plataforma que o site aprender a embutir depois.
 *
 * O QUE ELA CONHECE, e vale ser exato: uma tabela de quatro reescritas de
 * caminho, em canonicalize(). Ela existe porque os regex do core cobrem o
 * endereco que se copia da BARRA DE ENDERECOS, e nao o que vai no src do trecho
 * de incorporacao - descoberto testando, em 04/09/2026. Reescrever /embed/ID
 * para watch?v=ID nao decide se aquilo e video; so escreve a mesma midia na
 * forma que o core sabe ler.
 *
 * O QUE ESTA CLASSE GARANTE, e o formulario depende: o HTML colado nunca
 * atravessa. So o endereco sobrevive, e e por isso que colar um <iframe> com
 * onload=... nao vira XSS entre inquilinos.
 *
 * @package    mod_ldgvideo
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class url {
    /** @var string Proporcao padrao: o video comum de todas as plataformas. */
    public const RATIO_LANDSCAPE = '16:9';

    /** @var string Vertical, do Shorts e do Reels. */
    public const RATIO_PORTRAIT = '9:16';

    /** @var string Material antigo, de antes do widescreen. */
    public const RATIO_CLASSIC = '4:3';

    /**
     * @var string[] Parametros que so servem para rastrear, e nao entram.
     *
     * O 'list' NAO esta aqui de proposito: playlist muda o que o aluno assiste,
     * entao e conteudo, e nao rastreio.
     */
    protected const TRACKING = ['si', 'pp', 'feature', 'ab_channel', 'utm_source',
        'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * @var float Quanto a proporcao medida pode desviar da nominal.
     *
     * Sem uma folga, 561x315 nao seria reconhecido como 16:9. Com folga demais,
     * um quadrado de 400x400 viraria 4:3 - que e por que a tolerancia e
     * apertada e o caso duvidoso devolve nulo, deixando o padrao decidir.
     */
    protected const TOLERANCE = 0.06;

    /**
     * As proporcoes que o formulario oferece.
     *
     * A ORDEM E A DA TELA: o comum primeiro, o vertical por ultimo. Devolve a
     * chave da string de idioma, e nao o texto, para esta classe continuar
     * sendo codigo puro - o url_test roda sem banco.
     *
     * @return array<string, string> proporcao => chave de idioma.
     */
    public static function ratios(): array {
        return [
            self::RATIO_LANDSCAPE => 'ratiolandscape',
            self::RATIO_CLASSIC => 'ratioclassic',
            self::RATIO_PORTRAIT => 'ratioportrait',
        ];
    }

    /**
     * Transforma o que foi colado num endereco, e adivinha a proporcao.
     *
     * @param string $input O que veio do formulario: trecho de iframe ou URL.
     * @return array{url: \moodle_url, ratio: ?string}|null Nulo se nao da para aproveitar.
     */
    public static function normalize(string $input): ?array {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        $ratio = null;

        if (stripos($input, '<iframe') !== false) {
            $raw = self::attribute($input, 'src');

            if ($raw === null) {
                return null;
            }

            $ratio = self::ratio_from_size(
                self::attribute($input, 'width'),
                self::attribute($input, 'height')
            );
        } else {
            $raw = $input;
        }

        $parts = self::clean($raw);

        if ($parts === null) {
            return null;
        }

        // A LEITURA DO CAMINHO VEM ANTES DA CANONICALIZACAO, e a ordem importa:
        // canonicalizar troca /shorts/ID por /watch?v=ID, e depois disso o
        // endereco nao diz mais que era vertical.
        $ratio = $ratio ?? self::ratio_from_path($parts['path']);

        return [
            'url' => new \moodle_url(self::assemble(self::canonicalize($parts))),
            'ratio' => $ratio,
        ];
    }

    /**
     * O que ha de errado com o que foi colado, se houver.
     *
     * A REGRA MORA AQUI, e nao no mod_form, para poder ser testada de verdade.
     * Um teste que refizesse a mesma sequencia de ifs estaria conferindo a
     * copia, e nao a regra - e as duas versoes divergiriam no primeiro ajuste.
     *
     * Sao dois portoes, nesta ordem:
     *
     *   1. Nao e endereco de video que o site saiba embutir. Quem responde isso
     *      e o core_media_manager, com o regex de cada player HABILITADO - por
     *      isso nao ha lista de plataformas neste plugin, e instalar um player
     *      novo passa a valer aqui sem uma linha de codigo.
     *
     *   2. E video, mas hospedado neste site. Regra de negocio, e nao paranoia:
     *      o professor pode subir um .mp4 num rotulo do curso, copiar o link do
     *      pluginfile.php e colar aqui - video servido pela NOSSA banda, no
     *      plano que existe para custar zero de infraestrutura.
     *
     * O portao do papel de vendedor, que nem deixa o arquivo existir, e a outra
     * metade disto e vive no local_marketplace. Este protege contra o engano;
     * aquele protege contra a intencao.
     *
     * @param string $input
     * @return string|null Chave da string de erro, ou nulo se esta bom.
     */
    public static function problem(string $input): ?string {
        $video = self::normalize($input);

        if ($video === null) {
            return 'erroraddressnotvideo';
        }

        if (self::is_self_hosted($video['url'])) {
            return 'errorselfhosted';
        }

        if (!\core_media_manager::instance()->can_embed_url($video['url'])) {
            // DUAS COISAS DIFERENTES DAVAM A MESMA MENSAGEM, e uma delas mandava
            // o professor para o lado errado: "isto nao parece um endereco de
            // video" saia tanto para lixo colado quanto para um Vimeo legitimo
            // cujo player esta DESLIGADO no site. No segundo caso o endereco
            // esta perfeito, e conferi-lo mil vezes nao resolve - quem tem que
            // agir e o administrador.
            return self::any_installed_player_recognizes($video['url'])
                ? 'errorplayerdisabled'
                : 'erroraddressnotvideo';
        }

        return null;
    }

    /**
     * O endereco e do PROPRIO site (ou subdominio dele)?
     *
     * Compara POR HOST, com fronteira de dominio: str_starts_with contra o
     * wwwroot marcaria como proprio um host externo so porque a string comeca
     * igual - ex. wwwroot https://ldg.example.com e video em
     * https://ldg.example.com.cdn-video.net/..., que o Moodle nao serve.
     *
     * @param \moodle_url $url
     * @return bool
     */
    protected static function is_self_hosted(\moodle_url $url): bool {
        global $CFG;

        $site = strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST));
        $host = strtolower((string) $url->get_host());

        if ($site === '' || $host === '') {
            return false;
        }

        return $host === $site || str_ends_with($host, '.' . $site);
    }

    /**
     * Ha algum player INSTALADO que embutiria este endereco, mesmo desligado?
     *
     * So e chamado depois de o can_embed_url() ter dito nao - ou seja, quando
     * nenhum player HABILITADO reconheceu. Se um instalado reconhece, o problema
     * e configuracao do site, e nao o endereco.
     *
     * Isto NAO devolve conhecimento de plataforma para esta classe: quem
     * responde continua sendo o regex de cada player, e a lista de players sai
     * do core_plugin_manager. So se pergunta ao conjunto maior.
     *
     * @param \moodle_url $url
     * @return bool
     */
    protected static function any_installed_player_recognizes(\moodle_url $url): bool {
        foreach (array_keys(\core_plugin_manager::instance()->get_plugins_of_type('media')) as $name) {
            $class = "media_{$name}_plugin";

            if (!class_exists($class)) {
                continue;
            }

            try {
                $player = new $class();

                if ($player->list_supported_urls([$url])) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Player que nao instancia nao pode derrubar a validacao de um
                // formulario: o pior que pode acontecer aqui e a mensagem sair
                // menos precisa.
                continue;
            }
        }

        return false;
    }

    /**
     * Qual proporcao vale: a lida do trecho colado, ou a que o professor marcou.
     *
     * A DEDUCAO SO VALE SE O PROFESSOR NAO ESCOLHEU. Se o campo ainda esta no
     * padrao do site, o formato lido do width/height do trecho entra - e um
     * vertical colado ja chega vertical, sem ninguem precisar reparar nisso. Se
     * ele mexeu no campo, a escolha dele vence: sobrescrever sempre faria quem
     * escolheu 4:3 de proposito perder a escolha ao salvar.
     *
     * @param string|null $detected Proporcao deduzida do que foi colado, se houve.
     * @param string $chosen O que esta no campo do formulario.
     * @param string $default O padrao do site.
     * @return string
     */
    public static function choose_ratio(?string $detected, string $chosen, string $default): string {
        if ($detected !== null && $chosen === $default) {
            return $detected;
        }

        return $chosen;
    }

    /**
     * O valor de um atributo do trecho colado.
     *
     * Aceita aspas duplas e simples: o que se cola de um e-mail ou de um editor
     * de texto vem dos dois jeitos.
     *
     * @param string $html
     * @param string $name
     * @return string|null
     */
    protected static function attribute(string $html, string $name): ?string {
        $pattern = '/\b' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';

        if (!preg_match($pattern, $html, $match)) {
            return null;
        }

        $value = trim($match[1] !== '' ? $match[1] : ($match[2] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * Valida o endereco, tira o rastreio, e devolve as pecas dele.
     *
     * SO http e https. O parse_url sozinho nao basta: 'javascript:alert(1)'
     * tem esquema e passaria por qualquer checagem que so olhe se ha um.
     *
     * @param string $raw
     * @return array{scheme: string, host: string, port: string, path: string, query: string[], fragment: string}|null
     */
    protected static function clean(string $raw): ?array {
        $raw = html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $parts = parse_url($raw);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        if (clean_param($raw, PARAM_URL) === '') {
            return null;
        }

        // A query fica como LISTA DE PARES, preservando a ordem. Passar por
        // parse_str e http_build_query reordenaria os parametros, e o endereco
        // guardado deixaria de ser reconhecivel para quem colou.
        $kept = [];

        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            if ($pair === '') {
                continue;
            }

            if (!in_array(strtolower(explode('=', $pair, 2)[0]), self::TRACKING, true)) {
                $kept[] = $pair;
            }
        }

        return [
            'scheme' => strtolower($parts['scheme']),
            'host' => strtolower($parts['host']),
            // A PORTA NAO PODE SUMIR. Ela veio separada do host no parse_url, e
            // a primeira versao disto simplesmente nao a guardava: um endereco
            // com porta - qualquer site que nao esteja no 80 ou no 443 - saia
            // remontado sem ela, apontando para outro lugar. Pior: como o
            // endereco remontado deixava de comecar pelo wwwroot, o portao do
            // "video hospedado aqui" parava de reconhecer o proprio site.
            // Encontrado pelo Behat em 04/09/2026, que roda em :8000.
            'port' => isset($parts['port']) ? ':' . $parts['port'] : '',
            'path' => $parts['path'] ?? '',
            'query' => $kept,
            'fragment' => isset($parts['fragment']) ? '#' . $parts['fragment'] : '',
        ];
    }

    /**
     * Leva o endereco de incorporacao para a forma que o core reconhece.
     *
     * ESTA E A UNICA PLATAFORMA QUE ESTA CLASSE CONHECE, e ela existe por uma
     * descoberta desagradavel: o regex de cada player do core cobre o endereco
     * que se COPIA DA BARRA DE ENDERECOS, e nao o que vai no atributo src do
     * trecho de incorporacao. O media_youtube casa watch?v=, v/ e youtu.be/ -
     * e NAO casa /embed/ nem /shorts/. O media_vimeo casa vimeo.com/123 e nao
     * casa player.vimeo.com/video/123.
     *
     * Ou seja: justamente a forma que o professor mais cola e a que o core
     * recusaria. Sem esta tabela, o caso principal do plugin nao funciona.
     *
     * O QUE ISTO NAO E: reconhecimento. Nao ha aqui nenhuma decisao sobre o
     * endereco ser ou nao um video - quem responde isso continua sendo o
     * can_embed_url(). Isto e canonicalizacao: a mesma midia, escrita na forma
     * que o core sabe ler. Uma plataforma que nao esteja nesta tabela continua
     * funcionando pelo endereco normal dela.
     *
     * @param array{scheme: string, host: string, port: string, path: string, query: string[], fragment: string} $parts
     * @return array{scheme: string, host: string, port: string, path: string, query: string[], fragment: string}
     */
    protected static function canonicalize(array $parts): array {
        // Host SEM www/ m., o mesmo que o ramo do YouTube ja usava. O Vimeo
        // checava o host cru: https://www.player.vimeo.com/video/123 nao
        // casava, a URL passava intacta e o professor via erroraddressnotvideo
        // num video legitimo.
        $host = preg_replace('/^(www|m)\./', '', $parts['host']);

        // O segmento 'videoseries' e o embed de PLAYLIST do YouTube
        // (/embed/videoseries?list=PL...), nao um id de video. Casar ele
        // reescrevia para watch?v=videoseries, apontando para video inexistente.
        if (
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)
                && preg_match('~^/(?:embed|shorts|v)/([A-Za-z0-9_-]+)~', $parts['path'], $match)
                && $match[1] !== 'videoseries'
        ) {
            $parts['path'] = '/watch';
            array_unshift($parts['query'], 'v=' . $match[1]);

            return $parts;
        }

        if (
            $host === 'player.vimeo.com'
                && preg_match('~^/video/(\d+)~', $parts['path'], $match)
        ) {
            $parts['host'] = 'vimeo.com';
            $parts['path'] = '/' . $match[1];

            return $parts;
        }

        return $parts;
    }

    /**
     * Junta as pecas de volta num endereco.
     *
     * @param array{scheme: string, host: string, port: string, path: string, query: string[], fragment: string} $parts
     * @return string
     */
    protected static function assemble(array $parts): string {
        $address = $parts['scheme'] . '://' . $parts['host'] . $parts['port'] . $parts['path'];

        if ($parts['query']) {
            $address .= '?' . implode('&', $parts['query']);
        }

        return $address . $parts['fragment'];
    }

    /**
     * A proporcao que o width e o height do trecho colado indicam.
     *
     * E conveniencia, e nao regra: o campo do formulario continua editavel e
     * vence. Um vertical colado como 315x560 ja chega com 9:16 selecionado, e o
     * professor so confere.
     *
     * @param string|null $width
     * @param string|null $height
     * @return string|null
     */
    protected static function ratio_from_size(?string $width, ?string $height): ?string {
        $w = (int) $width;
        $h = (int) $height;

        if ($w <= 0 || $h <= 0) {
            return null;
        }

        $measured = $w / $h;

        $candidates = [
            self::RATIO_LANDSCAPE => 16 / 9,
            self::RATIO_CLASSIC => 4 / 3,
            self::RATIO_PORTRAIT => 9 / 16,
        ];

        foreach ($candidates as $name => $nominal) {
            if (abs($measured - $nominal) / $nominal <= self::TOLERANCE) {
                return $name;
            }
        }

        // Proporcao que nao e nenhuma das tres nao vira palpite: devolver a
        // "mais proxima" faria um quadrado virar 4:3, e o professor levaria
        // tarja preta sem entender de onde veio.
        return null;
    }

    /**
     * A proporcao que o proprio caminho anuncia.
     *
     * O /shorts/ nao e conhecimento de YouTube: e uma convencao de caminho que
     * varias plataformas usam para o formato vertical. Se nao bater, nao ha
     * palpite - o padrao do site decide.
     *
     * @param string $address
     * @return string|null
     */
    protected static function ratio_from_path(string $address): ?string {
        $path = strtolower((string) parse_url($address, PHP_URL_PATH));

        if (str_contains($path, '/shorts/') || str_contains($path, '/reels/')) {
            return self::RATIO_PORTRAIT;
        }

        return null;
    }
}
