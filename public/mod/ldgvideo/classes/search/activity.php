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
 * Area de busca do modulo de video.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ldgvideo\search;

/**
 * O que a busca do site enxerga desta atividade.
 *
 * A classe herda tudo e NAO sobrescreve nada, e isso e a mudanca: no mod_page
 * havia um get_document() proprio porque o conteudo HTML da pagina era o corpo
 * do documento, e a descricao ficava em segundo plano.
 *
 * Aqui nao ha corpo. O que se pode indexar de um video hospedado fora e o nome
 * e a descricao - e isso e exatamente o que a base_activity ja faz. Indexar a
 * URL nao ajudaria ninguem: ninguem procura aula por "youtube.com".
 *
 * Sem indexacao de arquivo, tambem: nao ha arquivo.
 *
 * @package    mod_ldgvideo
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity extends \core_search\base_activity {
}
