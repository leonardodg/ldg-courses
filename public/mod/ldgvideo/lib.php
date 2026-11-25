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
 * Biblioteca do modulo de video.
 *
 * Nasceu de uma copia do mod_page em 04/09/2026, e a poda e o que interessa:
 * SEM area de arquivos, SEM revisao de cache, SEM popup e SEM drag-and-drop.
 * Todas essas pecas existiam por causa do campo de conteudo HTML do mod_page,
 * e aqui o conteudo e um endereco de video.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * O que o modulo suporta.
 *
 * MOD_ARCHETYPE_RESOURCE porque, para o professor, um video e material - ele
 * aparece junto da pagina e do arquivo no seletor de atividades, e nao junto do
 * questionario.
 *
 * SEM NOTA, de proposito. O que se mede aqui e ter assistido, e "assistiu" nao
 * e nota - o YouTube nao nos conta quanto foi visto, entao qualquer nota seria
 * inventada.
 *
 * @param string $feature Constante FEATURE_xx.
 * @return mixed Verdadeiro se suporta, nulo se nao sabe.
 */
function ldgvideo_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_RESOURCE,
        FEATURE_GROUPS => false,
        FEATURE_GROUPINGS => false,
        FEATURE_MOD_INTRO => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_CONTENT,
        default => null,
    };
}

/**
 * Limpeza de dados do usuario ao reiniciar o curso.
 *
 * O modulo nao guarda nada por usuario - so o registro de visualizacao, que e
 * do core - entao nao ha o que apagar.
 *
 * @param object $data
 * @return array
 */
function ldgvideo_reset_userdata($data) {
    return [];
}

/**
 * As acoes de log que contam como "visualizar".
 *
 * @return array
 */
function ldgvideo_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * As acoes de log que contam como "publicar".
 *
 * @return array
 */
function ldgvideo_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Cria a instancia.
 *
 * @param object $data Dados do formulario.
 * @param object|null $mform
 * @return int
 */
function ldgvideo_add_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->displayoptions = serialize(['printintro' => (int) $data->printintro]);

    $data->id = $DB->insert_record('ldgvideo', $data);

    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $data->coursemodule]);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'ldgvideo',
        $data->id,
        $completiontimeexpected
    );

    return $data->id;
}

/**
 * Atualiza a instancia.
 *
 * @param object $data Dados do formulario.
 * @param object $mform
 * @return bool
 */
function ldgvideo_update_instance($data, $mform) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    $data->displayoptions = serialize(['printintro' => (int) $data->printintro]);

    $DB->update_record('ldgvideo', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'ldgvideo',
        $data->id,
        $completiontimeexpected
    );

    return true;
}

/**
 * Apaga a instancia.
 *
 * @param int $id
 * @return bool
 */
function ldgvideo_delete_instance($id) {
    global $DB;

    if (!$video = $DB->get_record('ldgvideo', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('ldgvideo', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'ldgvideo', $id, null);

    $DB->delete_records('ldgvideo', ['id' => $video->id]);

    return true;
}

/**
 * Informacao extra para a listagem do curso.
 *
 * O mod_page devolvia um onclick aqui quando o display era popup. Nao ha popup
 * neste modulo: video de tamanho fixo numa janela nova e o oposto do desenho, e
 * o portal do aluno embute a view.php num quadro.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 */
function ldgvideo_get_coursemodule_info($coursemodule) {
    global $DB;

    $video = $DB->get_record(
        'ldgvideo',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat'
    );

    if (!$video) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $video->name;

    if ($coursemodule->showdescription) {
        // A versao em cache NAO passa por filtro: os filtros rodam na hora de
        // mostrar, e cachear o resultado deles congelaria coisas que mudam.
        $info->content = format_module_intro('ldgvideo', $video, $coursemodule->id, false);
    }

    return $info;
}

/**
 * Os tipos de pagina para o bloco de navegacao.
 *
 * CUIDADO COM O NOME: page_type_list e nome de gancho do core, e a parte "page"
 * dele nao vira "ldgvideo". Uma renomeacao cega produziria
 * ldgvideo_ldgvideo_type_list, que o Moodle nunca chamaria - e o sintoma seria
 * silencio, nao erro.
 *
 * @param string $pagetype
 * @param stdClass $parentcontext
 * @param stdClass $currentcontext
 * @return array
 */
function ldgvideo_page_type_list($pagetype, $parentcontext, $currentcontext) {
    return ['mod-ldgvideo-*' => get_string('page-mod-ldgvideo-x', 'ldgvideo')];
}

/**
 * O conteudo da atividade para o aplicativo movel.
 *
 * Devolve o ENDERECO do video, e nao arquivo: nao ha arquivo. E a mesma forma
 * que o mod_url usa.
 *
 * @param stdClass $cm
 * @param string $baseurl
 * @return array
 */
function ldgvideo_export_contents($cm, $baseurl) {
    global $DB;

    $video = $DB->get_record('ldgvideo', ['id' => $cm->instance], '*', MUST_EXIST);

    return [[
        'type' => 'url',
        'filename' => clean_param(format_string($video->name), PARAM_FILE),
        'filepath' => null,
        'filesize' => 0,
        'fileurl' => $video->videourl,
        'timecreated' => null,
        'timemodified' => $video->timemodified,
        'sortorder' => null,
        'userid' => null,
        'author' => null,
        'license' => null,
    ]];
}

/**
 * Marca a visita: dispara o evento e conta para a conclusao.
 *
 * @param stdClass $video
 * @param stdClass $course
 * @param stdClass $cm
 * @param context_module $context
 * @return void
 */
function ldgvideo_view($video, $course, $cm, $context) {
    $event = \mod_ldgvideo\event\course_module_viewed::create([
        'context' => $context,
        'objectid' => $video->id,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('ldgvideo', $video);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Se houve mudanca desde um instante.
 *
 * A lista de areas de arquivo vai VAZIA, e nao com 'content': o modulo nao tem
 * area de arquivo nenhuma, e pedir por uma que nao existe faria o core procurar
 * o que nunca vai achar.
 *
 * @param cm_info $cm
 * @param int $from
 * @param array $filter
 * @return stdClass
 */
function ldgvideo_check_updates_since(cm_info $cm, $from, $filter = []) {
    return course_check_module_updates_since($cm, $from, [], $filter);
}

/**
 * A acao que o bloco "Visao geral" mostra para um evento deste modulo.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_ldgvideo_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    $userid = 0
) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['ldgvideo'][$event->instance];

    $completion = new \completion_info($cm->get_course());
    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/ldgvideo/view.php', ['id' => $cm->id]),
        1,
        true
    );
}
