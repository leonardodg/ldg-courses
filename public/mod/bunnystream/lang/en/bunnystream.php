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
 * Strings for mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['bunnystream:addinstance'] = 'Add a new Bunny Stream video';
$string['bunnystream:manage'] = 'Manage Bunny Stream videos (upload, edit, delete)';
$string['bunnystream:view'] = 'View Bunny Stream videos';
$string['cancel_upload'] = 'Cancel upload';
$string['caption_add'] = 'Upload .vtt';
$string['caption_remove'] = 'Remove';
$string['caption_transcribe'] = 'Auto-transcribe (en)';
$string['captions_empty'] = 'No subtitle tracks yet.';
$string['captions_section'] = 'Subtitles';
$string['chapter_add'] = 'Add chapter';
$string['chapter_save'] = 'Save chapters';
$string['chapters_section'] = 'Chapters';
$string['choose_video'] = 'Choose video';
$string['completion_percent'] = 'Completion threshold (%)';
$string['completion_percent_help'] = 'Mark the activity complete once the learner has watched at least this percentage of the video.';
$string['delete_confirm_body'] = 'The video will be removed from Bunny.net and from this activity. This cannot be undone.';
$string['delete_confirm_no'] = 'Cancel';
$string['delete_confirm_title'] = 'Delete this video?';
$string['delete_confirm_yes'] = 'Yes, delete';
$string['delete_video'] = 'Delete video';
$string['drop_video_here'] = 'Drop a video file here, or';
$string['error'] = '{$a}';
$string['error_failed_upload'] = 'Upload failed. Check your connection and try again.';
$string['error_no_video'] = 'No video uploaded yet.';
$string['error_not_configured'] = 'This company does not have a Bunny Stream library yet. Ask the platform to finish provisioning it before uploading video.';
$string['error_too_many_deletes'] = 'Too many delete requests. Try again in a minute.';
$string['error_too_many_uploads'] = 'Too many uploads. Try again in a minute.';
$string['failed_label'] = 'Something went wrong with this video.';
$string['modulename'] = 'Bunny Stream';
$string['modulename_help'] = 'The Bunny Stream activity lets a seller upload a video straight into their company\'s own Bunny.net Stream library and embed it in a course with a signed, hot-link-protected player. Each company has its own library — one seller never reaches another company\'s video.';
$string['modulenameplural'] = 'Bunny Stream videos';
$string['name'] = 'Activity name';
$string['pluginadministration'] = 'Bunny Stream administration';
$string['pluginname'] = 'Bunny Stream';
$string['privacy:metadata:bunny_net'] = 'Bunny.net is the external video streaming provider. Embedding a video causes the learner\'s browser to make a request to iframe.mediadelivery.net and to the company\'s CDN hostname; no Moodle user identifier is forwarded.';
$string['privacy:metadata:bunny_net:guid'] = 'The Bunny video GUID requested for playback.';
$string['privacy:metadata:bunnystream_progress'] = 'Stores the highest watched percentage per user per video so completion + grading can be computed.';
$string['privacy:metadata:bunnystream_progress:bunnystreamid'] = 'The Bunny Stream activity ID.';
$string['privacy:metadata:bunnystream_progress:max_percent'] = 'Highest percentage of the video the user has watched.';
$string['privacy:metadata:bunnystream_progress:timemodified'] = 'When the progress was last updated.';
$string['privacy:metadata:bunnystream_progress:userid'] = 'The Moodle user ID.';
$string['processing_elapsed'] = 'Elapsed';
$string['processing_label'] = 'Bunny is processing your video — this can take a few minutes for longer files.';
$string['replace_video'] = 'Replace video';
$string['setting_completion_percent'] = 'Default completion threshold (%)';
$string['setting_completion_percent_desc'] = 'Default percentage of the video a learner must watch for the activity to be marked complete. Can be overridden per activity.';
$string['settings_heading'] = 'Bunny Stream';
$string['settings_heading_desc'] = 'Each company provisions its own Bunny.net Stream library automatically when it is created — there is no library/API key to paste here. The platform account key that creates new libraries lives in Site administration → Plugins → Local plugins → Marketplace.';
$string['style_cinema'] = 'Cinema';
$string['style_compact'] = 'Compact';
$string['style_default'] = 'Default';
$string['style_padded'] = 'Padded';
$string['style_rounded'] = 'Rounded corners';
$string['thumbnail_replace'] = 'Replace thumbnail';
$string['thumbnail_section'] = 'Thumbnail';
$string['uploading_label'] = 'Uploading';
$string['video'] = 'Video';
$string['video_duration'] = 'Duration';
$string['video_id'] = 'Bunny GUID';
$string['video_style'] = 'Video display style';
$string['video_title'] = 'Video title';
