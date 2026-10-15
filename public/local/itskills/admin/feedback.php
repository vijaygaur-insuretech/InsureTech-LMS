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
 * Moderate course and trainer feedback.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_itskills\local\feedback;

admin_externalpage_setup('local_itskills_feedback');

$url = new moodle_url('/local/itskills/admin/feedback.php');
if ($id = optional_param('id', 0, PARAM_INT)) {
    require_sesskey();
    $status = required_param('status', PARAM_INT);
    if (in_array($status, [feedback::PUBLISHED, feedback::HIDDEN, feedback::PENDING])) {
        $DB->set_field('local_itskills_feedback', 'status', $status, ['id' => $id]);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$rows = [];
foreach ($DB->get_records('local_itskills_feedback', null, 'status ASC, timemodified DESC') as $row) {
    $user = core_user::get_user($row->userid);
    $course = $DB->get_record('course', ['id' => $row->courseid]);
    $rows[] = [
        'id' => $row->id,
        'name' => $user ? fullname($user) : '-',
        'course' => $course ? format_string($course->fullname, true, ['escape' => false]) : '-',
        'stars' => feedback::stars((int) $row->rating),
        'trainerstars' => $row->trainerrating ? feedback::stars((int) $row->trainerrating) : '',
        'comment' => $row->comment,
        'date' => userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'status' => $row->status,
        'statuslabel' => get_string('fbstatus_' . $row->status, 'local_itskills'),
        'publishurl' => (new moodle_url($url, ['id' => $row->id, 'status' => feedback::PUBLISHED, 'sesskey' => sesskey()]))->out(false),
        'hideurl' => (new moodle_url($url, ['id' => $row->id, 'status' => feedback::HIDDEN, 'sesskey' => sesskey()]))->out(false),
        'ispublished' => $row->status == feedback::PUBLISHED,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/admin_feedback', ['rows' => $rows, 'hasrows' => !empty($rows)]);
echo $OUTPUT->footer();
