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
 * Trainer handover: module and topic status, learner overview and private notes for a batch.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\attendance;
use local_itskills\local\handover;
use local_itskills\local\insights;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/itskills:handover', $context);

$groups = groups_get_all_groups($course->id);
$groupid = optional_param('groupid', $groups ? (int) array_key_first($groups) : 0, PARAM_INT);
if ($groupid && !isset($groups[$groupid])) {
    throw new moodle_exception('invalidgroupid');
}
$url = new moodle_url('/local/itskills/handover.php', ['courseid' => $course->id, 'groupid' => $groupid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('handover', 'local_itskills') . ' | ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('itskills_handover');

$action = optional_param('action', '', PARAM_ALPHA);
if ($action) {
    require_sesskey();
    if ($action === 'status') {
        $statuses = [];
        foreach ((array) data_submitted() as $name => $value) {
            if (preg_match('/^st_(section|cm)_(\d+)$/', $name, $m)) {
                $statuses[$m[1] . ':' . $m[2]] = clean_param($value, PARAM_INT);
            }
        }
        handover::save($course->id, $groupid, $statuses, optional_param('current', '', PARAM_RAW_TRIMMED));
        $message = 'handoversaved';
    } else if ($action === 'note') {
        handover::add_note($course->id, $groupid, optional_param('learner', 0, PARAM_INT), required_param('note', PARAM_TEXT));
        $message = 'notesaved';
    } else if ($action === 'deletenote') {
        handover::delete_note(required_param('noteid', PARAM_INT));
        $message = 'notedeleted';
    } else {
        throw new moodle_exception('invalidaction', 'local_itskills');
    }
    redirect($url, get_string($message, 'local_itskills'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$modules = handover::items($course, $groupid);
$current = '';
$completed = 0;
foreach ($modules as $m) {
    $completed += $m['status'] === 2 ? 1 : 0;
    foreach (array_merge([$m], $m['topics']) as $item) {
        if ($item['iscurrent']) {
            $current = $item['name'];
        }
    }
}

$learners = [];
foreach (get_enrolled_users($context, 'mod/assign:submit', $groupid, 'u.*', 'u.lastname, u.firstname', 0, 0, true) as $u) {
    $att = attendance::summary($u->id, $course->id);
    $quiz = insights::quiz_average($u->id, $course->id);
    $learners[] = [
        'id' => $u->id,
        'name' => fullname($u),
        'progress' => (int) round(\core_completion\progress::get_course_progress_percentage($course, $u->id) ?? 0),
        'attendance' => $att['percent'] === null ? '–' : $att['percent'] . '%',
        'quiz' => $quiz === null ? '–' : $quiz . '%',
        'lastaccess' => $u->lastaccess ? format_time(time() - $u->lastaccess) : get_string('never'),
    ];
}

$batches = [['id' => 0, 'name' => get_string('allbatches', 'local_itskills'), 'selected' => $groupid == 0]];
foreach ($groups as $g) {
    $batches[] = ['id' => $g->id, 'name' => format_string($g->name, true, ['escape' => false]), 'selected' => $g->id == $groupid];
}

$notes = handover::notes($course->id, $groupid);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/handover', [
    'url' => $url->out(false),
    'baseurl' => (new moodle_url('/local/itskills/handover.php'))->out(false),
    'courseid' => $course->id,
    'groupid' => $groupid,
    'batches' => $batches,
    'sesskey' => sesskey(),
    'modules' => $modules,
    'hasmodules' => !empty($modules),
    'current' => $current,
    'completed' => $completed,
    'total' => count($modules),
    'learners' => $learners,
    'haslearners' => !empty($learners),
    'notes' => $notes,
    'hasnotes' => !empty($notes),
    'notecount' => count($notes),
]);
echo $OUTPUT->footer();
