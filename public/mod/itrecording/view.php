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
 * Watch a recording.
 *
 * @package    mod_itrecording
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_itrecording\video;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'itrecording');
$recording = $DB->get_record('itrecording', ['id' => $cm->instance], '*', MUST_EXIST);

// require_login() also enforces enrolment, access end dates (subscription) and activity availability rules.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/itrecording/view.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . format_string($recording->name, true, ['context' => $context]));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_description('');

if (!video::can_view($context, $course->id)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nopermissiontoview', 'itrecording'), \core\output\notification::NOTIFY_INFO, false);
    echo $OUTPUT->footer();
    exit;
}

itrecording_view($recording, $course, $cm, $context);

$embed = video::embed($recording->videourl);
$watermark = '';
if (get_config('itrecording', 'watermark')) {
    $watermark = fullname($USER) . ($USER->email ? ' · ' . $USER->email : '');
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_itrecording/player', [
    'name' => format_string($recording->name, true, ['context' => $context, 'escape' => false]),
    'intro' => format_module_intro('itrecording', $recording, $cm->id),
    'hasintro' => trim(strip_tags($recording->intro ?? '')) !== '',
    'duration' => $recording->duration,
    'embed' => $embed,
    'invalid' => !$embed,
    'isdrive' => $embed && $embed['provider'] === 'drive',
    // Without allow-popups, Drive's pop-out button cannot open the file in a new tab.
    'sandbox' => (bool) get_config('itrecording', 'blockpopout'),
    'watermark' => $watermark,
    'canmanage' => has_capability('mod/itrecording:manage', $context),
]);
echo $OUTPUT->footer();
