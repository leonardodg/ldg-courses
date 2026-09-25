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

namespace mod_bunnystream\output;

/**
 * Renderer do mod_bunnystream: painel de autoria e player.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Renderiza o painel inline de autoria (upload TUS de 5 estados).
     *
     * @param \stdClass|null $activity
     * @return string
     */
    public function render_author(?\stdClass $activity): string {
        $ctx = [
            'has_video'  => !empty($activity->guid),
            'state'      => $this->compute_state($activity),
            'title'      => $activity->title ?? '',
            'thumbnail'  => $activity->thumbnail_url ?? '',
            'duration'   => $activity->duration_sec ?? 0,
            'guid_short' => !empty($activity->guid) ? substr($activity->guid, 0, 8) . '…' : '',
        ];

        return $this->render_from_template('mod_bunnystream/author', $ctx);
    }

    /**
     * Renderiza o player para o aluno.
     *
     * @param \stdClass $activity
     * @param string $embedurl
     * @return string
     */
    public function render_player(\stdClass $activity, string $embedurl): string {
        return $this->render_from_template('mod_bunnystream/player', [
            'embed_url' => $embedurl,
            'title'     => $activity->title ?: $activity->name,
            'style'     => $activity->video_style ?: 'default',
            'instance'  => $activity->id,
            'completion_percent' => (int) $activity->completion_percent,
        ]);
    }

    /**
     * Em qual dos 5 paineis o autor esta.
     *
     * @param \stdClass|null $activity
     * @return string
     */
    private function compute_state(?\stdClass $activity): string {
        if (!$activity || empty($activity->guid)) {
            return 'empty';
        }
        if ($activity->status === 'ready') {
            return 'ready';
        }
        if ($activity->status === 'failed') {
            return 'failed';
        }

        return 'processing';
    }
}
