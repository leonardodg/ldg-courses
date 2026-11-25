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
 * Confirmacao de candidatura enviada.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- Pagina publica, ver o docblock acima.
require(__DIR__ . '/../../config.php');

$PAGE->set_context(\core\context\system::instance());
$PAGE->set_url(new moodle_url('/local/partners/thanks.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('thankstitle', 'local_partners'));
$PAGE->set_heading('');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('thankstitle', 'local_partners'));
// A mensagem muda conforme o passo que falta. Dizer "recebemos" a quem ainda
// precisa clicar no link faria a pessoa esperar por uma resposta que nao vem.
$pending = optional_param('confirm', 0, PARAM_BOOL);

echo $OUTPUT->notification(
    get_string($pending ? 'thanksbodyunconfirmed' : 'thanksbody', 'local_partners'),
    $pending ? 'info' : 'success'
);
echo html_writer::link(
    new moodle_url('/'),
    get_string('backtohome', 'local_partners'),
    ['class' => 'btn btn-secondary']
);
echo $OUTPUT->footer();
