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
 * Configuracao do modulo de video.
 *
 * NAO HA CONFIGURACAO DE PLATAFORMA AQUI, e essa e a decisao. Quem liga e
 * desliga YouTube, Vimeo e os outros e a tela do core, em Plugins > Players de
 * midia: o reconhecimento do endereco e do core_media_manager, entao duplicar
 * a lista aqui criaria uma segunda chave para a mesma porta.
 *
 * NAO HA POPUP, tambem. O mod_page tinha display, popupwidth e popupheight; um
 * video de tamanho fixo numa janela nova e o oposto do desenho.
 *
 * E NAO HA "marcar manualmente como feito": aquilo nao e configuracao de
 * plugin, e sim linha em course_completion_defaults - ver o db/install.php.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'ldgvideomodeditdefaults',
        get_string('modeditdefaults', 'admin'),
        get_string('condifmodeditdefaults', 'admin')
    ));

    // Marcado por padrao, ao contrario do mod_page. Numa aula em video a
    // descricao e o que diz do que a aula trata antes de o aluno apertar play.
    $settings->add(new admin_setting_configcheckbox(
        'ldgvideo/printintro',
        get_string('printintro', 'ldgvideo'),
        get_string('printintroexplain', 'ldgvideo'),
        1
    ));

    $ratios = [];
    foreach (\mod_ldgvideo\url::ratios() as $value => $key) {
        $ratios[$value] = get_string($key, 'ldgvideo');
    }

    $settings->add(new admin_setting_configselect(
        'ldgvideo/aspectratio',
        get_string('aspectratio', 'ldgvideo'),
        get_string('configaspectratio', 'ldgvideo'),
        \mod_ldgvideo\url::RATIO_LANDSCAPE,
        $ratios
    ));
}
