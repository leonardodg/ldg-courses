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

use local_partners\output\landing_page;
use moodle_url;

/**
 * Superficie publica da landing, consumida pelo tema.
 *
 * E o unico ponto de contato entre o local_partners e o theme_ldg, e existe
 * para que nenhum dos dois conheca as tabelas do outro. O tema testa
 * class_exists() e chama estes tres metodos; nao le config, nao consulta o
 * banco, e nao declara dependencia deste plugin - o moodle-plugin-ci instala o
 * tema sem ele, e um fatal ali reprovaria o CI do tema por causa de um plugin
 * que nao esta la.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class landing {
    /**
     * A landing deve ser exibida?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('local_partners', 'enablelanding');
    }

    /**
     * A landing deve tomar o lugar da pagina inicial do site?
     *
     * Sao DUAS condicoes, e as duas precisam valer:
     *
     *   enablelanding    a landing existe como pagina
     *   frontpagemode    o administrador escolheu que ela e a home
     *
     * A segunda mora na tela de Configuracoes da pagina inicial, junto das
     * opcoes do Moodle, porque e ali que o administrador vai procurar - ver o
     * comentario em settings.php sobre por que ela nao pode entrar na lista
     * nativa de conteudo da home.
     *
     * Isto NAO decide se o visitante e anonimo nem se o dominio e de vendedor:
     * quem sabe disso e o layout do tema, que e quem tem a requisicao na mao.
     *
     * @return bool
     */
    public static function replaces_frontpage(): bool {
        return self::is_enabled() && get_config('local_partners', 'frontpagemode') === 'landing';
    }

    /**
     * O miolo da landing, ja renderizado.
     *
     * Devolve so o conteudo. O cabecalho, o rodape e o resto do chrome sao do
     * tema - quem manda no chrome e quem desenha o site.
     *
     * @param \renderer_base $output O renderer da pagina.
     * @return string
     */
    public static function render(\renderer_base $output): string {
        return $output->render_from_template(
            'local_partners/landing',
            (new landing_page())->export_for_template($output)
        );
    }

    /**
     * O que o rodape do SITE precisa saber para falar a mesma lingua da landing.
     *
     * Faz parte da superficie que o tema consome, junto de is_enabled(),
     * replaces_frontpage(), render() e head_html(). O tema chama por
     * class_exists e cai no que ele mesmo tem quando este plugin nao existe -
     * a dependencia continua sendo do tema para ca, e continua opcional.
     *
     * Devolve so o que e comum as duas superficies: a marca, a frase que explica
     * o produto, os links legais que tem destino, a razao social, o CNPJ e o
     * credito de quem assina. Ancoras de secao ficam de fora de proposito -
     * elas so existem na landing.
     *
     * @return array
     */
    public static function site_footer(): array {
        $criador = seo::creator();
        $legais = landing_page::legal_links();

        return [
            'legal' => $legais,
            'haslegal' => !empty($legais),
            // A marca e a frase saem daqui e nao do tema: sao os mesmos textos
            // da landing, ja traduzidos neste plugin. Duplica-los no tema
            // significaria dois lugares para corrigir a mesma frase, e um deles
            // ficaria para tras.
            'brand' => landing_page::brand(),
            'tagline' => get_string('footertagline', 'local_partners'),
            'legalname' => seo::legal_name() !== '' ? seo::legal_name() : false,
            'taxid' => seo::tax_id() !== '' ? seo::tax_id() : false,
            'creatorname' => $criador['name'] !== '' ? $criador['name'] : false,
            'creatorurl' => $criador['url'],
            'landingurl' => (new moodle_url('/local/partners/index.php'))->out(false),
            'landingenabled' => self::is_enabled(),
        ];
    }

    /**
     * O que a landing acrescenta ao <head>.
     *
     * Delega para a classe seo, que trata indexacao, compartilhamento, idioma e
     * dados estruturados juntos - sao a mesma decisao vista de angulos
     * diferentes, e separa-los faria o canonical de um lado divergir do @id do
     * outro.
     *
     * @param string $superficie
     * @return string
     */
    public static function head_html(string $superficie = seo::SURFACE_LANDING): string {
        return seo::head_html($superficie);
    }
}
