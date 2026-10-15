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
 * Schedule or edit a live class session.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\form\session_form;
use local_itskills\local\sessions;

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/itskills:managesessions', $context);

$url = new moodle_url('/local/itskills/session_edit.php', ['courseid' => $course->id, 'id' => $id]);
$returnurl = new moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string($id ? 'editsession' : 'addsession', 'local_itskills'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('itskills_sessions');

$batches = [];
foreach (groups_get_all_groups($course->id) as $g) {
    $batches[$g->id] = format_string($g->name, true, ['escape' => false]);
}
$form = new session_form($url, ['courseid' => $course->id, 'batches' => $batches]);
if ($id) {
    $session = $DB->get_record('local_itskills_session', ['id' => $id, 'courseid' => $course->id], '*', MUST_EXIST);
    $form->set_data($session);
} else {
    $form->set_data(['timestart' => make_timestamp(date('Y'), date('n'), date('j') + 1, 19, 0)]);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $data->courseid = $course->id;
    if (!$data->id) {
        unset($data->id);
    }
    if ($data->groupid && !isset($batches[$data->groupid])) {
        throw new moodle_exception('invalidgroupid');
    }
    sessions::save($data);
    redirect($returnurl, get_string('sessionsaved', 'local_itskills'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($id ? 'editsession' : 'addsession', 'local_itskills'));
echo html_writer::div(get_string('sessionemailnote', 'local_itskills'), 'alert alert-info');
$form->display();
echo $OUTPUT->footer();
