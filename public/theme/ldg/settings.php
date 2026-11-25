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
 * Configuracoes do tema LDG.
 *
 * O filho declara SO o que ele mesmo le. Slider, caixas de marketing, numeros da
 * frontpage e FAQ ficaram de fora de proposito: a frontpage vira a landing de
 * captacao, e esses settings seriam telas de configuracao para conteudo que
 * nunca aparece.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingldg', get_string('configtitle', 'theme_ldg'));

    // Aba Marca.
    $page = new admin_settingpage('theme_ldg_brand', get_string('brandsettings', 'theme_ldg'));

    $name = 'theme_ldg/logo';
    $title = get_string('logo', 'theme_ldg');
    $description = get_string('logodesc', 'theme_ldg');
    $opts = ['accepted_types' => ['.png', '.jpg', '.gif', '.webp', '.svg'], 'maxfiles' => 1];
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo', 0, $opts);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ldg/logodark';
    $title = get_string('logodark', 'theme_ldg');
    $description = get_string('logodarkdesc', 'theme_ldg');
    $opts = ['accepted_types' => ['.png', '.jpg', '.gif', '.webp', '.svg'], 'maxfiles' => 1];
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logodark', 0, $opts);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ldg/favicon';
    $title = get_string('favicon', 'theme_ldg');
    $description = get_string('favicondesc', 'theme_ldg');
    $opts = ['accepted_types' => ['.ico', '.png', '.svg'], 'maxfiles' => 1];
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'favicon', 0, $opts);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // O nome NAO e 'loginbgimg'. Ver o comentario de theme_ldg_get_extra_scss:
    // com o nome do Moove, o callback dele emitiria o mesmo CSS de novo.
    $name = 'theme_ldg/loginbg';
    $title = get_string('loginbg', 'theme_ldg');
    $description = get_string('loginbgdesc', 'theme_ldg');
    $opts = ['accepted_types' => ['.png', '.jpg', '.webp', '.svg'], 'maxfiles' => 1];
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'loginbg', 0, $opts);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ldg/brandcolor';
    $title = get_string('brandcolor', 'theme_ldg');
    $description = get_string('brandcolordesc', 'theme_ldg');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#007AFF');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ldg/secondarymenucolor';
    $title = get_string('secondarymenucolor', 'theme_ldg');
    $description = get_string('secondarymenucolordesc', 'theme_ldg');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#1E1E1E');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ldg/fontsite';
    $title = get_string('fontsite', 'theme_ldg');
    $description = get_string('fontsitedesc', 'theme_ldg');
    $choices = [
        'Moodle' => 'Moodle',
        'Inter' => 'Inter',
        'Manrope' => 'Manrope',
        'Montserrat' => 'Montserrat',
        'Poppins' => 'Poppins',
        'Roboto' => 'Roboto',
        'Sora' => 'Sora',
        'Work Sans' => 'Work Sans',
    ];
    $setting = new admin_setting_configselect($name, $title, $description, 'Inter', $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Qual modo o site usa para quem ainda nao escolheu - inclusive o visitante
    // anonimo, que nao tem preferencia de usuario nenhuma. Nao desliga o modo
    // claro: so diz por onde se comeca.
    $name = 'theme_ldg/defaultcolormode';
    $title = get_string('defaultcolormode', 'theme_ldg');
    $description = get_string('defaultcolormodedesc', 'theme_ldg');
    $setting = new admin_setting_configselect($name, $title, $description, 'dark', [
        'dark' => get_string('colormodedark', 'theme_ldg'),
        'light' => get_string('colormodelight', 'theme_ldg'),
    ]);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Checkbox, e nao select. Com um select de Nao/Sim, "Nao" e a primeira
    // opcao: qualquer render em que o valor gravado nao case exatamente com uma
    // chave faz o formulario nascer em "Nao", e o proximo save da aba desliga o
    // botao sem ninguem pedir. Aconteceu aqui - o config_log registra o
    // enablecolormodetoggle indo de 1 para 0 no mesmo POST em que os logos
    // foram enviados.
    $name = 'theme_ldg/enablecolormodetoggle';
    $title = get_string('enablecolormodetoggle', 'theme_ldg');
    $description = get_string('enablecolormodetoggledesc', 'theme_ldg');
    $setting = new admin_setting_configcheckbox($name, $title, $description, 1);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // Aba Avancado.
    $page = new admin_settingpage('theme_ldg_advanced', get_string('advancedsettings', 'theme_ldg'));

    $name = 'theme_ldg/preset';
    $title = get_string('preset', 'theme_ldg');
    $description = get_string('presetdesc', 'theme_ldg');

    $context = \core\context\system::instance();
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'theme_ldg', 'preset', 0, 'itemid, filepath, filename', false);

    $choices = [];
    foreach ($files as $file) {
        $choices[$file->get_filename()] = $file->get_filename();
    }
    $choices['default.scss'] = 'default.scss';
    $choices['plain.scss'] = 'plain.scss';

    $setting = new admin_setting_configselect($name, $title, $description, 'default.scss', $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ldg/presetfiles';
    $title = get_string('presetfiles', 'theme_ldg');
    $description = get_string('presetfilesdesc', 'theme_ldg');
    $setting = new admin_setting_configstoredfile(
        $name,
        $title,
        $description,
        'preset',
        0,
        ['maxfiles' => 10, 'accepted_types' => ['.scss']]
    );
    $page->add($setting);

    $name = 'theme_ldg/scsspre';
    $title = get_string('rawscsspre', 'theme_ldg');
    $description = get_string('rawscsspredesc', 'theme_ldg');
    $setting = new admin_setting_scsscode($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ldg/scss';
    $title = get_string('rawscss', 'theme_ldg');
    $description = get_string('rawscssdesc', 'theme_ldg');
    $setting = new admin_setting_scsscode($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ldg/googleanalytics';
    $title = get_string('googleanalytics', 'theme_ldg');
    $description = get_string('googleanalyticsdesc', 'theme_ldg');
    $setting = new admin_setting_configtext($name, $title, $description, '', PARAM_RAW);
    $page->add($setting);

    $settings->add($page);

    // Aba Rodape.
    $page = new admin_settingpage('theme_ldg_footer', get_string('footersettings', 'theme_ldg'));

    // Todos nascem VAZIOS, e e proposital: link de rede social que vem
    // preenchido por padrao acaba publicado sem ninguem ter escolhido, e no
    // rodape de uma plataforma comercial isso vira marca de terceiro.
    $footersettings = [
        'website' => PARAM_URL,
        'mobile' => PARAM_TEXT,
        'mail' => PARAM_TEXT,
        'facebook' => PARAM_URL,
        'instagram' => PARAM_URL,
        'linkedin' => PARAM_URL,
        'youtube' => PARAM_URL,
        'tiktok' => PARAM_URL,
        'twitter' => PARAM_URL,
        'pinterest' => PARAM_URL,
        'whatsapp' => PARAM_TEXT,
        'telegram' => PARAM_TEXT,
    ];

    foreach ($footersettings as $key => $type) {
        $name = 'theme_ldg/' . $key;
        $title = get_string($key, 'theme_ldg');
        $description = get_string($key . 'desc', 'theme_ldg');
        $setting = new admin_setting_configtext($name, $title, $description, '', $type);
        $page->add($setting);
    }

    $settings->add($page);
}
