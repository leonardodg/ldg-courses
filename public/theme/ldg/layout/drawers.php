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
 * Layout de drawers do tema LDG.
 *
 * E o layout de quase todas as paginas: o config.php do Boost aponta base,
 * standard, course, incourse, admin e companhia para este arquivo, e o
 * layout_file() procura em [ldg, boost] - entao este substitui o do
 * Moove para todas elas de uma vez.
 *
 * O que muda em relacao ao do Moove: o drawer ESQUERDO deixa de existir so
 * dentro de curso. Ele passa a conter o menu de navegacao da plataforma e, se
 * houver, o course index empilhado abaixo. Ver o override de
 * templates/drawers.mustache.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}

$themesettings = new \theme_ldg\util\settings();

$courseindex = $themesettings->course_index_enabled() ? core_course_drawer() : '';

// O menu lateral e so para quem esta autenticado: ele e feito de destinos
// pessoais (painel, meus cursos, preferencias, sair) e do customusermenuitems,
// que nao existem para visitante anonimo.
$navmenu = '';
if (isloggedin() && !isguestuser()) {
    $navmenu = $OUTPUT->render_from_template(
        'theme_ldg/ldg/navmenu',
        (new \theme_ldg\output\navmenu($PAGE, $courseindex))->export_for_template($OUTPUT)
    );
}

// O drawer esquerdo existe se tiver ALGUMA coisa dentro. Sem esta checagem, a
// tela de login e a landing ganhariam um drawer vazio e um botao que abre nada.
$hasleftdrawer = !empty($navmenu) || !empty($courseindex);

if (!$hasleftdrawer) {
    $courseindexopen = false;
}

$navmenucollapsed = (bool) get_user_preferences('theme_ldg-navmenu-collapsed', 0);

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}
if ($hasleftdrawer) {
    $extraclasses[] = 'has-ldg-navmenu';
}
if ($navmenucollapsed) {
    $extraclasses[] = 'ldg-navmenu-is-collapsed';
}

$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';

// O mesmo helper do layout do portal: uma copia do more-menu em cada arquivo
// e como as duas superficies deixariam de andar juntas numa correcao futura.
if (\theme_ldg\util\layouthead::has_secondary_children()) {
    $secondarynavigation = \theme_ldg\util\layouthead::secondary_more_menu();
    $extraclasses[] = 'has-secondarynavigation';
}

if ($PAGE->has_secondary_navigation()) {
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$templatecontext = [
    'sitename' => \theme_ldg\util\layouthead::sitename(),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'ldgnavmenu' => $navmenu,
    'hasleftdrawer' => $hasleftdrawer,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => \theme_ldg\util\langmenu::shorten($primarymenu['lang']),
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
];

$templatecontext = array_merge($templatecontext, $themesettings->footer());

echo $OUTPUT->render_from_template('theme_ldg/drawers', $templatecontext);
