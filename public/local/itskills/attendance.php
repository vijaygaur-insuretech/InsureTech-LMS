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
 * Take attendance for a session.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\attendance;
use local_itskills\local\sessions;

$sessionid = required_param('sessionid', PARAM_INT);
$session = $DB->get_record('local_itskills_session', ['id' => $sessionid], '*', MUST_EXIST);
$course = get_course($session->courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/itskills:managesessions', $context);

$url = new moodle_url('/local/itskills/attendance.php', ['sessionid' => $session->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('takeattendance', 'local_itskills'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('itskills_sessions');

$learners = sessions::learners($session);
if (data_submitted() && confirm_sesskey()) {
    $statuses = [];
    foreach ($learners as $learner) {
        $statuses[$learner->id] = optional_param('status_' . $learner->id, 'P', PARAM_ALPHA);
    }
    attendance::save($session->id, $statuses);
    redirect(new moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]),
        get_string('attendancesaved', 'local_itskills'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$existing = $DB->get_records_menu('local_itskills_attendance', ['sessionid' => $session->id], '', 'userid, status');
$rows = [];
foreach ($learners as $learner) {
    $status = $existing[$learner->id] ?? 'P';
    $rows[] = [
        'id' => $learner->id,
        'name' => fullname($learner),
        'picture' => $OUTPUT->user_picture($learner, ['size' => 32, 'link' => false]),
        'options' => array_map(fn($s) => ['value' => $s, 'label' => get_string('att_' . $s, 'local_itskills'),
            'checked' => $s === $status], attendance::STATUSES),
        'percent' => attendance::summary($learner->id, $course->id)['percent'],
        'haspercent' => attendance::summary($learner->id, $course->id)['percent'] !== null,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/attendance', [
    'title' => $session->title,
    'date' => userdate($session->timestart, get_string('strftimedaydatetime', 'langconfig')),
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'backurl' => (new moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]))->out(false),
]);
echo $OUTPUT->footer();
