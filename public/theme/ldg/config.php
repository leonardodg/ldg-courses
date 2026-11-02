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
 * Configuracao do tema LDG.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

// Tem que ser igual ao nome do diretorio: o find_theme_config acha o tema pelo
// diretorio, mas o layout_file() usa este nome para montar o caminho.
$THEME->name = 'ldg';

// Lista PLANA, do mais especifico para o mais generico.
//
// O theme_config NAO expande os pais dos pais (lib/classes/output/theme_config.php,
// no laco que monta parent_configs). Declarar uma lista vazia faria o
// mustache_template_finder e o layout_file() pararem de enxergar theme/boost, e
// todo override que o Boost faz de template core/* sumiria sem erro nenhum -
// o site continuaria no ar, so que renderizando o template do core.
$THEME->parents = ['boost'];

$THEME->sheets = [];
$THEME->editor_sheets = [];
// Sem editor_scss de proposito: sem ele o core sobe a hierarquia e usa
// o scss/editor.scss do Boost. Este tema nao tem esse arquivo, e declarar reprova
// a inicializacao do behat desde o MDL-90110 (ver tests/editor_scss_test.php).
$THEME->usefallback = true;
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = [
    'notitle' => true,
];

// Sem isto o \theme_ldg\output\core_renderer NUNCA e instanciado. Nao ha erro:
// o site simplesmente serve o logo e o Google Analytics do Moove - settings que
// nunca configuramos, entao o sintoma parece "sem logo", e nao "logo errado".
$THEME->rendererfactory = 'theme_overridden_renderer_factory';

$THEME->scss = function ($theme) {
    return theme_ldg_get_main_scss_content($theme);
};

// ATENCAO: os callbacks do PAI tambem rodam, e antes destes, recebendo a config
// do FILHO. Ver o comentario de cada funcao em lib.php.
$THEME->prescsscallback = 'theme_ldg_get_pre_scss';
$THEME->extrascsscallback = 'theme_ldg_get_extra_scss';
$THEME->precompiledcsscallback = 'theme_ldg_get_precompiled_css';

// Os layouts CASCATEIAM: base, depois os pais em ordem reversa, depois o
// proprio. Herdamos tudo do Moove e do Boost e so declaramos o que
// sobrescrevemos. Na pratica bastaria existir o arquivo layout/frontpage.php,
// porque o layout_file() procura em [ldg, boost] - mas declarar aqui
// deixa a intencao explicita para quem ler.
$THEME->layouts = [
    'frontpage' => [
        'file' => 'frontpage.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['nonavbar' => true],
    ],

    // O portal do aluno, pedido pelo format_ldg. Sem navbar e sem drawers: o
    // chrome inteiro e do portal, e e esse o ponto - a pagina do curso deixa de
    // parecer uma pagina do Moodle.
    //
    // Quem TROCA para este layout e o \format_ldg\hook_callbacks, no
    // before_http_headers, e ele so troca se encontrar esta chave aqui. Ou
    // seja: o nome 'ldgportal' e o contrato entre os dois plugins, e renomear
    // aqui desliga o portal em silencio.
    //
    // Sem regioes de bloco de proposito. Bloco no portal seria o chrome do
    // Moodle voltando pela janela.
    'ldgportal' => [
        'file' => 'ldgportal.php',
        'regions' => [],
        'options' => ['nonavbar' => true, 'nocourseheaderfooter' => true],
    ],
];
