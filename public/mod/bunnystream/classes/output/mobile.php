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

use mod_bunnystream\config;
use mod_bunnystream\not_configured_exception;
use mod_bunnystream\token;
/**
 * Integracao com o Moodle Mobile App - iframe assinado dentro do webview.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Inicializacao do componente mobile.
     *
     * @return array
     */
    public static function mobile_init() {
        return [
            'javascript' => '',
        ];
    }

    /**
     * Template Ionic com o iframe assinado.
     *
     * @param array $args
     * @return array
     */
    public static function mobile_view(array $args): array {
        global $DB;

        $cmid = (int) ($args['cmid'] ?? 0);
        $cm = get_coursemodule_from_id('bunnystream', $cmid, 0, false, MUST_EXIST);
        $activity = $DB->get_record('bunnystream', ['id' => $cm->instance], '*', MUST_EXIST);

        $embedurl = '';
        if (!empty($activity->guid) && !empty($activity->library_id)) {
            try {
                $cfg = config::for_course((int) $cm->course);
                $embedurl = token::embed_url_for(
                    $activity->library_id,
                    $activity->guid,
                    $cfg->security_key,
                    ['autoplay' => 'false', 'preload' => 'true', 'responsive' => 'true']
                );
            } catch (not_configured_exception $e) {
                $embedurl = token::unsigned_embed(
                    $activity->library_id,
                    $activity->guid
                );
            }
        }

        $template = '
<core-loading [hideUntil]="loaded">
  <ion-card>
    <ion-card-header><ion-card-title>{{ activity.name }}</ion-card-title></ion-card-header>
    <ion-card-content>
      <div *ngIf="!embedUrl"><p>{{ "plugin.mod_bunnystream.error_no_video" | translate }}</p></div>
      <div *ngIf="embedUrl" style="position:relative;padding-top:56.25%;">
        <iframe [src]="embedUrl | safeURL"
                style="position:absolute;inset:0;width:100%;height:100%;border:0"
                allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture"
                allowfullscreen></iframe>
      </div>
    </ion-card-content>
  </ion-card>
</core-loading>';

        return [
            'templates' => [
                ['id' => 'main', 'html' => $template],
            ],
            'javascript' => '',
            'otherdata'  => [
                'activity' => (object) ['id' => $activity->id, 'name' => $activity->name],
                'embedUrl' => $embedurl,
                'loaded'   => true,
            ],
        ];
    }
}
