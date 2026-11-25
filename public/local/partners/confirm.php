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
 * Confirmacao do e-mail de uma candidatura.
 *
 * Pagina PUBLICA, e tem que ser: quem clica no link ainda nao tem conta. O
 * token e a unica credencial, e por isso ele e de uso unico e some assim que
 * cumpre a funcao.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- Pagina publica, ver o docblock acima.
require(__DIR__ . '/../../config.php');

use local_partners\api;
use local_partners\application;

$token = required_param('token', PARAM_ALPHANUM);

$PAGE->set_context(\core\context\system::instance());
$PAGE->set_url(new moodle_url('/local/partners/confirm.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('confirmtitle', 'local_partners'));
$PAGE->set_heading('');

$application = application::get_by_token($token);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('confirmtitle', 'local_partners'));

if (!$application) {
    // Token invalido e token ja usado dao a MESMA resposta, de proposito:
    // distinguir os dois diria a quem esta tentando adivinhar se acertou o
    // formato. E quem confirmou duas vezes ve uma mensagem que faz sentido.
    echo $OUTPUT->notification(get_string('confirminvalid', 'local_partners'), 'error');
} else {
    api::confirm($application);
    echo $OUTPUT->notification(get_string('confirmdone', 'local_partners'), 'success');
}

echo html_writer::link(
    new moodle_url('/'),
    get_string('backtohome', 'local_partners'),
    ['class' => 'btn btn-secondary']
);

echo $OUTPUT->footer();
