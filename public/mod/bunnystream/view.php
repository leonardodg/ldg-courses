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
 * Player para o aluno - iframe assinado com a chave da EMPRESA dona do curso.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$n  = optional_param('n', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('bunnystream', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $activity = $DB->get_record('bunnystream', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $activity = $DB->get_record('bunnystream', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $activity->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('bunnystream', $activity->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingparameter');
}

require_course_login($course, true, $cm);
require_capability('mod/bunnystream:view', context_module::instance($cm->id));

$PAGE->set_url('/mod/bunnystream/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context(context_module::instance($cm->id));

$event = \mod_bunnystream\event\course_module_viewed::create([
    'objectid' => $activity->id,
    'context'  => context_module::instance($cm->id),
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('bunnystream', $activity);
$event->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));
if (!empty($activity->intro)) {
    echo $OUTPUT->box(format_module_intro('bunnystream', $activity, $cm->id), 'generalbox');
}

if (empty($activity->guid) || empty($activity->library_id)) {
    echo $OUTPUT->notification(get_string('error_no_video', 'mod_bunnystream'), 'warning');
} else {
    try {
        $cfg = \mod_bunnystream\config::for_course((int) $course->id);
        $embedurl = \mod_bunnystream\token::embed_url_for(
            $activity->library_id,
            $activity->guid,
            $cfg->security_key,
            ['autoplay' => 'false', 'preload' => 'true', 'responsive' => 'true']
        );
    } catch (\mod_bunnystream\not_configured_exception $e) {
        // Sem chave decifravel, cai para a URL sem assinatura - so funciona
        // se a library nao exigir token, mas e melhor UX que pagina em branco.
        $embedurl = \mod_bunnystream\token::unsigned_embed(
            $activity->library_id,
            $activity->guid,
            ['autoplay' => 'false', 'preload' => 'true', 'responsive' => 'true']
        );
    }
    $renderer = $PAGE->get_renderer('mod_bunnystream');
    echo $renderer->render_player($activity, $embedurl);

    $PAGE->requires->js_call_amd('mod_bunnystream/player', 'init', [[
        'instance'           => (int) $activity->id,
        'cmid'               => (int) $cm->id,
        'completionPercent'  => (int) $activity->completion_percent,
        'sesskey'            => sesskey(),
    ]]);
}

echo $OUTPUT->footer();
