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
 * Mapa do site, para os buscadores.
 *
 * Pagina PUBLICA por definicao: um sitemap que exige login nao e um sitemap.
 *
 * Lista APENAS as paginas publicas de captacao. NAO lista curso, categoria nem
 * perfil - conteudo de dentro do LMS depende de matricula, e anunciar endereco
 * que devolve tela de login gasta o orcamento de rastreamento do buscador com
 * porta fechada.
 *
 * O endereco bonito e /sitemap.xml, servido por um Alias no vhost. Este arquivo
 * continua acessivel pelo caminho proprio, e os dois devolvem o mesmo XML.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- Sitemap e publico por definicao, ver o docblock.
require(__DIR__ . '/../../config.php');

use local_partners\landing;
use local_partners\seo;

// Quem monta as entradas e a classe seo, para ter teste: script de saida direta
// so se prova abrindo o navegador. Cada pagina entra pelo endereco CANONICO de
// cada idioma, e nao pelas duas URLs que servem o mesmo conteudo - anunciar a
// raiz e a URL propria ao mesmo tempo e pedir ao buscador que escolha, e ele
// escolhe sozinho, normalmente a errada.
$paginas = landing::is_enabled() ? seo::sitemap_entries() : [];

// Cabecalho antes de qualquer saida. O sitemap muda quando o site muda, e nao a
// cada requisicao - meia hora de cache poupa o servidor sem atrasar nada que
// importe.
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=1800');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

// CADA idioma tem a propria entrada, e todas repetem o cluster inteiro. O
// formato antigo listava uma entrada so com os alternates dentro, o que parece
// economico e esta errado: sem link de retorno em cada URL anunciada, o
// buscador descarta o cluster e as versoes por idioma nao sao indexadas.
foreach ($paginas as $pagina) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($pagina['url'], ENT_XML1) . "</loc>\n";

    foreach ($pagina['alternates'] as $hreflang => $alternativa) {
        echo '    <xhtml:link rel="alternate" hreflang="'
            . htmlspecialchars($hreflang, ENT_XML1)
            . '" href="' . htmlspecialchars($alternativa, ENT_XML1) . '"/>' . "\n";
    }

    echo '    <changefreq>' . $pagina['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $pagina['priority'] . "</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
