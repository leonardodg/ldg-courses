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
 * Strings for mod_ldgvideo.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aspectratio'] = 'Aspect ratio';
$string['aspectratio_help'] = 'The shape of the video frame. The width always follows the column the video sits in, so only the shape is chosen here.

If you pasted the embed snippet, the shape was read from it and is already selected.';
$string['configaspectratio'] = 'The aspect ratio proposed for new video activities.';
$string['erroraddressnotvideo'] = 'This does not look like a video address. Paste the link to the video, or the whole embed snippet the video service gave you.';
$string['errornopermissions'] = 'Sorry, but you do not have permission to view this video activity.';
$string['errorplayerdisabled'] = 'This video service is recognised, but its media player is turned off on this site. Ask an administrator to enable it in Site administration > Plugins > Media players.';
$string['errorselfhosted'] = 'Videos are hosted outside the platform. Paste an address from YouTube, Vimeo or another video service - not a file from this site.';
$string['ldgvideo:addinstance'] = 'Add a new video activity';
$string['ldgvideo:view'] = 'View a video activity';
$string['modulename'] = 'Video';
$string['modulename_help'] = 'The video activity shows one lesson video, hosted on an external service such as YouTube or Vimeo.

Paste the address of the video, or the whole embed snippet the service gives you: the address is extracted and the fixed pixel size is discarded, so the video fits whatever space it is given - on a desktop, on a phone, and with the course sidebars open or hidden.

The video is never uploaded to this site.';
$string['modulename_link'] = 'mod/ldgvideo/view';
$string['modulenameplural'] = 'Videos';
$string['page-mod-ldgvideo-x'] = 'Any video activity module page';
$string['pluginadministration'] = 'Video administration';
$string['pluginname'] = 'Video';
$string['printintro'] = 'Display video description';
$string['printintroexplain'] = 'Display the description above the video.';
$string['privacy:metadata'] = 'The Video activity does not store any personal data. The address of the video is course content, not personal data - but note that playing an embedded video sends the learner IP address to the video service.';
$string['ratioclassic'] = '4:3';
$string['ratiolandscape'] = '16:9 (widescreen)';
$string['ratioportrait'] = '9:16 (vertical)';
$string['search:activity'] = 'Video';
$string['videoheader'] = 'Video';
$string['videourl'] = 'Video address';
$string['videourl_help'] = 'Paste any of these:

* the link from the address bar, such as "https://www.youtube.com/watch?v=xxxxxxxxxxx"
* the short share link, such as "https://youtu.be/xxxxxxxxxxx"
* the whole embed snippet, starting with "<iframe"

Whatever you paste, only the address is stored. Tracking parameters and the fixed "width" and "height" are discarded.';
