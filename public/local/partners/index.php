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
 * Landing de captacao de empresas parceiras.
 *
 * Pagina PUBLICA: sem require_login de proposito. O visitante que ela quer
 * atingir e justamente quem ainda nao tem conta.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- Pagina publica, ver o docblock acima.
require(__DIR__ . '/../../config.php');

use local_partners\landing;

$PAGE->set_context(\core\context\system::instance());
$PAGE->set_url(new moodle_url('/local/partners/index.php'));
// A barra de secoes e o UNICO menu desta pagina.
//
// O layout 'embedded' nao traz navbar, drawer nem rodape - e definido
// pelo theme_boost e herdado pelo ldg e pelo moove, entao as tres
// renderizam igual. O preco e ter o proprio rodape, que esta em
// templates/footer.mustache, e o proprio atalho para o conteudo.
$PAGE->set_pagelayout('embedded');
// A classe libera a largura no styles.css. O layout 'standard' limita a
// .main-inner a largura de leitura, o que corta o hero pela metade - medido em
// 720px dentro de um viewport de 1440.
$PAGE->add_body_class('ldgp-page');
// O title da aba NAO e o texto do H1. O H1 fala com quem ja esta na
// pagina; o title fala com quem esta lendo uma lista de resultados de
// busca e ainda nao clicou. O nome do site e anexado pelo Moodle.
// O false e obrigatorio: a marca ja vem na string de idioma, e deixar o core
// anexar o nome do site poria uma segunda marca no mesmo titulo.
$PAGE->set_title(\local_partners\seo::page_title(), false);
$PAGE->set_heading('');

if (!landing::is_enabled()) {
    throw new moodle_exception('landingdisabled', 'local_partners');
}

echo $OUTPUT->header();
echo landing::render($OUTPUT);
echo $OUTPUT->footer();
