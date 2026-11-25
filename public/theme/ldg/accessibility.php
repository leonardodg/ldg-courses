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
 * Preferencias de acessibilidade do usuario.
 *
 * A barra de acessibilidade e OPT-IN: ela nao aparece para ninguem ate ser
 * ligada aqui. Barra permanente ocupa altura util em toda pagina e desmonta a
 * hierarquia do topo; quem precisa dela liga uma vez.
 *
 * Pagina simples de proposito. O tema de onde a barra veio resolvia isto com um
 * modal, dois modulos AMD e quatro web services proprios; um moodleform na
 * pagina de preferencias entrega o mesmo resultado usando so o que o Moodle ja
 * tem, e aparece no lugar onde o usuario ja procura preferencia.
 *
 * @package    theme_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_ldg\form\accessibility_form;

require_login();

// Guest nao tem onde guardar preferencia: a escolha se perderia no proximo
// carregamento, e uma tela que nao guarda nada e pior que tela nenhuma.
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$url = new moodle_url('/theme/ldg/accessibility.php');
$returnurl = new moodle_url('/user/preferences.php');

$PAGE->set_url($url);
$PAGE->set_context(\core\context\user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('accessibility', 'theme_ldg'));
$PAGE->set_heading(get_string('accessibility', 'theme_ldg'));
$PAGE->navbar->add(get_string('preferences'), $returnurl);
$PAGE->navbar->add(get_string('accessibility', 'theme_ldg'), $url);

$form = new accessibility_form($url);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    set_user_preference('theme_ldg-accessibilitybar', !empty($data->accessibilitybar) ? 1 : 0);

    // Desligar a barra NAO apaga o tamanho de fonte nem o contraste ja
    // escolhidos: quem desliga costuma estar tirando a barra da tela, e nao
    // desfazendo o ajuste que fez. Religar traz tudo de volta como estava.
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form->set_data((object) [
    'accessibilitybar' => (int) get_user_preferences('theme_ldg-accessibilitybar', 0),
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('accessibility', 'theme_ldg'));
$form->display();
echo $OUTPUT->footer();
