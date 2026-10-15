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
 * Rate a course and its trainer.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\feedback;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
if (!is_enrolled($context, null, '', true)) {
    throw new required_capability_exception($context, 'moodle/course:view', 'nopermissions', '');
}

$url = new moodle_url('/local/itskills/feedback.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('ratecourse', 'local_itskills'));
$PAGE->set_heading(format_string($course->fullname));

$saved = false;
if (data_submitted() && confirm_sesskey()) {
    feedback::save($course->id, $USER->id, required_param('rating', PARAM_INT), optional_param('trainerrating', 0, PARAM_INT),
        optional_param('comment', '', PARAM_TEXT));
    $saved = true;
}
$existing = $DB->get_record('local_itskills_feedback', ['courseid' => $course->id, 'userid' => $USER->id]);
$stars = function(string $name, int $value) {
    $out = [];
    for ($i = 5; $i >= 1; $i--) {
        $out[] = ['value' => $i, 'checked' => $i === $value, 'name' => $name];
    }
    return $out;
};

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/feedback', [
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
    'saved' => $saved,
    'moderated' => (bool) get_config('local_itskills', 'feedbackmoderation'),
    'course' => format_string($course->fullname, true, ['escape' => false]),
    'ratingstars' => $stars('rating', $existing ? (int) $existing->rating : 0),
    'trainerstars' => $stars('trainerrating', $existing ? (int) $existing->trainerrating : 0),
    'comment' => $existing ? $existing->comment : '',
    'googleurl' => clean_param(get_config('local_itskills', 'googlereviewurl'), PARAM_URL),
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
]);
echo $OUTPUT->footer();
