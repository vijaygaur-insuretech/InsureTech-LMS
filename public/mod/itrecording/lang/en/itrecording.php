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
 * Recording activity strings.
 *
 * @package    mod_itrecording
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['blockpopout'] = 'Block the "open in new window" button';
$string['blockpopout_desc'] = 'Runs the player in a sandbox so Google Drive\'s pop-out button cannot open the file in a new tab (where it could be downloaded). Turn off only if a video does not play.';
$string['downloadnote'] = 'This recording is for your personal learning only. Please do not record or share it.';
$string['duration'] = 'Length (optional)';
$string['editrecording'] = 'Edit recording';
$string['invalidvideourl'] = 'Paste a Google Drive file link (https://drive.google.com/file/d/…), a YouTube link or a Vimeo link.';
$string['itrecording:addinstance'] = 'Add a recording';
$string['itrecording:manage'] = 'Edit recordings and see their video links';
$string['itrecording:view'] = 'Watch recordings';
$string['modulename'] = 'Recording';
$string['modulename_help'] = 'A class recording or lesson video, played inside the course from a Google Drive, YouTube or Vimeo link. Students and admins can watch it; trainers cannot open it or see its link.';
$string['modulenameplural'] = 'Recordings';
$string['nopermissiontoview'] = 'This recording is available to students only.';
$string['pluginadministration'] = 'Recording administration';
$string['pluginname'] = 'Recording';
$string['privacy:metadata'] = 'The Recording activity does not store personal data. Views are recorded in the standard logs.';
$string['settings_intro'] = 'Recordings are videos the admin adds to course sections from a Google Drive, YouTube or Vimeo link. Who can add, edit and watch them is also controlled by the capabilities mod/itrecording:addinstance, :manage and :view (Site administration > Users > Define roles). For Google Drive, share each file as "Anyone with the link" (Viewer) and, in the file\'s share settings, turn off download, print and copy for viewers.';
$string['trainerscanview'] = 'Trainers can watch recordings';
$string['trainerscanview_desc'] = 'When on, trainers (anyone who can edit the course) can also watch recordings. They still cannot see or edit the video link.';
$string['videourl'] = 'Video link';
$string['videourl_help'] = 'The share link of the video: a Google Drive file (Share > Anyone with the link > Copy link), a YouTube link (unlisted is fine) or a Vimeo link. Only admins see this link; students only see the player.';
$string['watermark'] = 'Show a name watermark on the video';
$string['watermark_desc'] = 'Shows the viewer\'s name and email faintly over the player, to discourage screen recording and sharing.';
