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
 * Live classes of a course: upcoming and past sessions, join links, attendance.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\attendance;
use local_itskills\local\sessions;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
$canmanage = has_capability('local/itskills:managesessions', $context);

$url = new moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('liveclasses', 'local_itskills') . ' | ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('itskills_sessions');

if ($canmanage && ($cancel = optional_param('cancel', 0, PARAM_INT))) {
    require_sesskey();
    $session = $DB->get_record('local_itskills_session', ['id' => $cancel, 'courseid' => $course->id], '*', MUST_EXIST);
    sessions::cancel($session->id);
    redirect($url, get_string('sessioncancelled', 'local_itskills'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$now = time();
$rows = ['upcoming' => [], 'past' => []];
$myattendance = [];
if (!$canmanage) {
    foreach ($DB->get_records_sql("SELECT a.sessionid, a.status FROM {local_itskills_attendance} a
                                     JOIN {local_itskills_session} s ON s.id = a.sessionid
                                    WHERE a.userid = ? AND s.courseid = ?", [$USER->id, $course->id]) as $a) {
        $myattendance[$a->sessionid] = $a->status;
    }
}
foreach (sessions::for_course($course->id, $USER->id) as $s) {
    $end = $s->timestart + $s->duration * MINSECS;
    $group = $s->groupid ? groups_get_group($s->groupid) : null;
    $marked = $canmanage ? $DB->count_records('local_itskills_attendance', ['sessionid' => $s->id]) : 0;
    $row = [
        'id' => $s->id,
        'title' => $s->title,
        'description' => $s->description,
        'date' => userdate($s->timestart, get_string('strftimedaydate', 'langconfig')),
        'time' => userdate($s->timestart, get_string('strftimetime', 'langconfig')) . ' – '
            . userdate($end, get_string('strftimetime', 'langconfig')),
        'batch' => $group ? format_string($group->name, true, ['escape' => false]) : get_string('allbatches', 'local_itskills'),
        'cancelled' => $s->status == sessions::CANCELLED,
        'live' => $s->status == sessions::SCHEDULED && $s->timestart - 15 * MINSECS <= $now && $end >= $now,
        'link' => $s->status == sessions::SCHEDULED && $end >= $now ? sessions::link($s) : '',
        'editurl' => (new moodle_url('/local/itskills/session_edit.php', ['courseid' => $course->id, 'id' => $s->id]))->out(false),
        'cancelurl' => (new moodle_url($url, ['cancel' => $s->id, 'sesskey' => sesskey()]))->out(false),
        'attendanceurl' => (new moodle_url('/local/itskills/attendance.php', ['sessionid' => $s->id]))->out(false),
        'marked' => $marked,
        'mystatus' => isset($myattendance[$s->id]) ? get_string('att_' . $myattendance[$s->id], 'local_itskills') : '',
        'mystatuscode' => $myattendance[$s->id] ?? '',
        'confirmcancel' => get_string('confirmcancel', 'local_itskills', $s->title),
    ];
    $rows[$end >= $now && $s->status == sessions::SCHEDULED ? 'upcoming' : 'past'][] = $row;
}
$rows['upcoming'] = array_reverse($rows['upcoming']);

$summary = $canmanage ? null : attendance::summary($USER->id, $course->id);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/sessions', [
    'canmanage' => $canmanage,
    'addurl' => (new moodle_url('/local/itskills/session_edit.php', ['courseid' => $course->id]))->out(false),
    'upcoming' => $rows['upcoming'],
    'hasupcoming' => !empty($rows['upcoming']),
    'past' => $rows['past'],
    'haspast' => !empty($rows['past']),
    'summary' => $summary,
    'hassummary' => $summary && $summary['total'] > 0,
]);
echo $OUTPUT->footer();
