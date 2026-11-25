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
 * Formulario de candidatura a parceria.
 *
 * Pagina PUBLICA. Ver as tres camadas de anti-spam em
 * classes/form/application_form.php.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- Pagina publica, ver o docblock acima.
require(__DIR__ . '/../../config.php');

use local_partners\api;
use local_partners\form\application_form;
use local_partners\landing;

$PAGE->set_context(\core\context\system::instance());
$PAGE->set_url(new moodle_url('/local/partners/apply.php'));
// Mesmo layout da landing: a barra e o unico menu. Ver o comentario
// em index.php.
$PAGE->set_pagelayout('embedded');
// Ver o comentario em index.php: o layout 'standard' limita a largura, e o
// cadastro e uma tela de duas colunas.
$PAGE->add_body_class('ldgp-page');
// Ver o comentario em index.php sobre o segundo parametro.
$PAGE->set_title(\local_partners\seo::page_title(\local_partners\seo::SURFACE_APPLY), false);
$PAGE->set_heading('');

if (!landing::is_enabled()) {
    throw new moodle_exception('landingdisabled', 'local_partners');
}

$form = new application_form($PAGE->url);
$thanksurl = new moodle_url('/local/partners/thanks.php');

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/partners/index.php'));
}

if ($data = $form->get_data()) {
    // Honeypot preenchido: responde como se tivesse dado certo e nao grava
    // nada. Devolver erro ensinaria o robo a nao preencher o campo da proxima
    // vez, e a proxima vez seria bem-sucedida.
    if (!empty($data->fax)) {
        redirect($thanksurl);
    }

    $application = api::submit($data);

    if ($application->get('status') === \local_partners\application::STATUS_UNCONFIRMED) {
        $thanksurl->param('confirm', 1);
    }

    redirect($thanksurl);
}

// O formulario e renderizado DENTRO do template, e nao por display(): a pagina
// e um split de duas colunas, e o cartao do formulario e uma delas. Quem desenha
// o formulario continua sendo o Moodle - ver o docblock do apply_page.
$renderer = $PAGE->get_renderer('local_partners');

echo $OUTPUT->header();
echo $renderer->render(new \local_partners\output\apply_page($form));
echo $OUTPUT->footer();
