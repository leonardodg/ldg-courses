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
 * Instalacao do modulo de video.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Faz a atividade nova nascer com "marcar manualmente como feito".
 *
 * A CONCLUSAO PADRAO NAO E CONFIGURACAO DE PLUGIN, e essa e a parte que engana.
 * O settings.php nao alcanca isso: quem decide e
 * \core_completion\manager::get_default_completion(), que le a tabela
 * course_completion_defaults e, sem linha la, devolve COMPLETION_TRACKING_NONE.
 * Quem aplica e o course/modlib.php, ao montar o formulario.
 *
 * Entao a unica forma de entregar o padrao pedido e semear a linha para o curso
 * do SITE, que e de onde o core cai quando o curso nao tem padrao proprio.
 *
 * A ORDEM E SEGURA: o upgrade_plugins_modules() insere o registro em {modules}
 * ANTES de chamar este gancho - o comentario do core na linha do insert diz
 * literalmente "may be needed in install.php already".
 *
 * Continua sendo DEFAULT, e nao trava: o admin muda em Padroes de conclusao, e
 * o professor muda em cada atividade.
 *
 * @return void
 */
function xmldb_ldgvideo_install() {
    global $DB;

    $moduleid = $DB->get_field('modules', 'id', ['name' => 'ldgvideo'], MUST_EXIST);

    // Idempotente: uma instalacao repetida nao pode duplicar o padrao.
    if ($DB->record_exists('course_completion_defaults', ['course' => SITEID, 'module' => $moduleid])) {
        return;
    }

    $DB->insert_record('course_completion_defaults', (object) [
        'course' => SITEID,
        'module' => $moduleid,
        'completion' => COMPLETION_TRACKING_MANUAL,
    ]);
}
