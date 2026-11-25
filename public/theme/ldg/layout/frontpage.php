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
 * Layout da pagina inicial do tema LDG.
 *
 * A URL continua sendo a raiz: uma requisicao, HTTP 200, canonical certa. Nao
 * ha redirecionamento para /local/partners/index.php - um 302 na URL canonica
 * do dominio, em toda visita anonima, e ruim de SEO e move a home da marca para
 * um endereco interno que depois nao pode mudar.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');

// O layout do Moove ja monta todo o contexto da frontpage; reaproveitamos o
// arquivo dele em vez de copiar cem linhas que teriam que ser mantidas em
// paralelo. Ele termina com um render_from_template, e o que fazemos aqui e
// decidir ANTES qual template ele vai usar - por isso a inclusao vem por
// ultimo, depois de definirmos $ldglanding.
$ldglanding = '';

if (!isloggedin() || isguestuser()) {
    // A landing e da PLATAFORMA. Num dominio de vendedor ela seria o pitch
    // errado para o publico errado: quem chega em meuscursos.joao.com veio
    // pelo curso do Joao, e nao para virar parceiro da LDG.
    //
    // $CFG->marketplacecompany e gravada pelo config.php ao casar o Host com o
    // mapa de dominios, e e a mesma marca que o
    // local_marketplace\hook_callbacks::after_config usa.
    $ldgisplatform = empty($CFG->marketplacecompany);

    // Dependencia OPCIONAL, por class_exists e nunca por get_config ou $DB: o
    // moodle-plugin-ci instala este tema SEM o local_partners, e um fatal aqui
    // reprovaria o CI do tema por causa de um plugin que nao esta la.
    $ldghaslanding = class_exists('\local_partners\landing')
        && \local_partners\landing::replaces_frontpage();

    if ($ldgisplatform && $ldghaslanding) {
        $ldglanding = \local_partners\landing::render($OUTPUT);
    }
}

if ($ldglanding === '') {
    // Sem landing, cai no layout padrao do Moodle - o drawers do Boost, que e
    // o mesmo que este tema ja usa em todas as outras paginas.
    //
    // Apontava para o frontpage do Moove, e aquilo deixou de ser um fallback
    // quando o Moove saiu das dependencias: a linha que existe para o site
    // nunca ficar sem home era justamente a que quebraria com o Moove ausente.
    require(__DIR__ . '/drawers.php');
    return;
}

// Com landing, o contexto e minimo de proposito: ela e a pagina INTEIRA.
//
// Nem navbar nem rodape do tema entram aqui. A landing ja traz a propria barra
// de secoes e o proprio rodape - e enquanto este layout tambem montava os do
// tema, quem abria a raiz do dominio via duas barras e dois rodapes, um dentro
// do outro. A pagina servida na raiz e a mesma de /local/partners/index.php, e
// tem que ser identica a ela.
$templatecontext = [
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(['ldg-has-landing']),
    'landing' => $ldglanding,
];

echo $OUTPUT->render_from_template('theme_ldg/landing', $templatecontext);
