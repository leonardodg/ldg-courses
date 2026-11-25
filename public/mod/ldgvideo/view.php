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
 * A tela da aula em video.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/ldgvideo/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT);
$v = optional_param('v', 0, PARAM_INT);

if ($v) {
    $video = $DB->get_record('ldgvideo', ['id' => $v], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('ldgvideo', $video->id, $video->course, false, MUST_EXIST);
} else {
    $cm = get_coursemodule_from_id('ldgvideo', $id, 0, false, MUST_EXIST);
    $video = $DB->get_record('ldgvideo', ['id' => $cm->instance], '*', MUST_EXIST);
}

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ldgvideo:view', $context);

ldgvideo_view($video, $course, $cm, $context);

$PAGE->set_url('/mod/ldgvideo/view.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . $video->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($video);

$options = empty($video->displayoptions) ? [] : (array) unserialize_array($video->displayoptions);
$showdescription = !isset($options['printintro']) || !empty($options['printintro']);

$header = ['hidecompletion' => false];
if (!$showdescription) {
    $header['description'] = '';
}
if (!$PAGE->activityheader->is_title_allowed()) {
    $header['title'] = '';
}
$PAGE->activityheader->set_attrs($header);

// NADA de limitedwidth aqui, ao contrario do mod_page. Aquela classe estreita a
// coluna para largura de leitura, que e o certo para texto e o errado para
// video: o quadro encolheria no meio da tela sem motivo visivel.

echo $OUTPUT->header();

// QUEM DESENHA O PLAYER E O CORE. O core_media_manager conhece o regex e o
// embed de cada plataforma - YouTube, Vimeo, e o que mais o site tiver
// habilitado -, e ja trata coisas como o dominio sem cookie do YouTube.
//
// O que ele NAO faz e o nosso problema: o template dele emite width e height em
// PIXEL FIXO (media/player/youtube/templates/embed.mustache). Os numeros
// passados aqui existem so para o core ter o que escrever no atributo; quem
// manda de verdade e o aspect-ratio do styles.css, e atributo de HTML perde
// para CSS sem precisar de !important.
$player = core_media_manager::instance()->embed_url(
    new moodle_url($video->videourl),
    format_string($video->name),
    0,
    0,
    ['nolink' => true]
);

$class = 'ldgvideo__frame ldgvideo--' . str_replace(':', '-', $video->aspectratio);

echo html_writer::div($player, $class);

echo $OUTPUT->footer();
