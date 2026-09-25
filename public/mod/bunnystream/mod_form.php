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
 * Formulario da atividade - upload inline do video.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Formulario da atividade.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_bunnystream_mod_form extends moodleform_mod {
    /**
     * Define os campos do formulario.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('name', 'mod_bunnystream'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', null, 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'videoheader', get_string('video', 'mod_bunnystream'));
        $mform->setExpanded('videoheader', true);

        $courseid = (int) $this->get_course()->id;

        // Aviso cedo, antes do professor tentar enviar um video: sem library
        // provisionada para a empresa deste curso, o upload nao tem para
        // onde ir. company::for_course() devolve nulo em curso fora de
        // marketplace (ex.: curso de teste na raiz) - tratado igual.
        try {
            \mod_bunnystream\config::for_course($courseid);
        } catch (\mod_bunnystream\not_configured_exception $e) {
            $mform->addElement('html', $this->output_not_configured_notice());
        }

        $instance = !empty($this->_instance) ? (int) $this->_instance : 0;
        $activity = $instance ? $this->db_record_for_instance($instance) : null;

        $renderer = $this->get_renderer();
        $authorhtml = $renderer->render_author($activity);
        $mform->addElement('html', $authorhtml);

        foreach (['guid', 'library_id', 'title', 'thumbnail_url', 'status'] as $f) {
            $mform->addElement('hidden', $f, '');
            $mform->setType($f, $f === 'thumbnail_url' ? PARAM_URL : PARAM_RAW);
        }
        $mform->addElement('hidden', 'duration_sec', 0);
        $mform->setType('duration_sec', PARAM_INT);

        $styles = [
            'default' => get_string('style_default', 'mod_bunnystream'),
            'rounded' => get_string('style_rounded', 'mod_bunnystream'),
            'padded'  => get_string('style_padded', 'mod_bunnystream'),
            'cinema'  => get_string('style_cinema', 'mod_bunnystream'),
            'compact' => get_string('style_compact', 'mod_bunnystream'),
        ];
        $mform->addElement('select', 'video_style', get_string('video_style', 'mod_bunnystream'), $styles);
        $mform->setDefault('video_style', 'default');

        $mform->addElement('text', 'completion_percent', get_string('completion_percent', 'mod_bunnystream'), ['size' => 4]);
        $mform->setType('completion_percent', PARAM_INT);
        $mform->setDefault('completion_percent', (int) (get_config('mod_bunnystream', 'completion_percent') ?: 90));
        $mform->addHelpButton('completion_percent', 'completion_percent', 'mod_bunnystream');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

        global $PAGE;
        $PAGE->requires->js_call_amd('mod_bunnystream/author', 'init', [[
            'instance'   => $instance,
            'courseid'   => $courseid,
            'guid'       => $activity->guid ?? '',
            'libraryId'  => $activity->library_id ?? '',
            'title'      => $activity->title ?? '',
            'status'     => $activity->status ?? '',
            'durationSec' => $activity->duration_sec ?? 0,
            'thumbnailUrl' => $activity->thumbnail_url ?? '',
            'sesskey'    => sesskey(),
        ]]);
    }

    /**
     * O renderer do plugin.
     *
     * @return \mod_bunnystream\output\renderer
     */
    private function get_renderer(): \mod_bunnystream\output\renderer {
        global $PAGE;

        return $PAGE->get_renderer('mod_bunnystream');
    }

    /**
     * Aviso de que a empresa deste curso ainda nao tem library provisionada.
     *
     * @return string
     */
    private function output_not_configured_notice(): string {
        global $OUTPUT;

        return $OUTPUT->notification(get_string('error_not_configured', 'mod_bunnystream'), 'warning');
    }

    /**
     * A linha da atividade, para preencher o estado inicial do formulario.
     *
     * @param int $id
     * @return stdClass|null
     */
    private function db_record_for_instance(int $id): ?stdClass {
        global $DB;

        return $DB->get_record('bunnystream', ['id' => $id]) ?: null;
    }
}
