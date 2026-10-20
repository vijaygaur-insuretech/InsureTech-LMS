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
 * My assignments: everything to submit across my courses and, for trainers, what is waiting to be reviewed.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\insights;

require_login(null, false);

$PAGE->set_url(new moodle_url('/local/itskills/myassignments.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('nav_assignments', 'theme_insuretech'));
$PAGE->set_heading(get_string('nav_assignments', 'theme_insuretech'));

$items = [];
$review = [];
foreach (enrol_get_my_courses(['id'], 'fullname ASC') as $course) {
    $context = context_course::instance($course->id);
    if (has_capability('mod/assign:grade', $context)) {
        // Courses I teach: submissions waiting for a grade.
        $sql = "SELECT a.id, a.name, a.duedate,
                       (SELECT COUNT(1)
                          FROM {assign_submission} s
                     LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                               AND g.attemptnumber = s.attemptnumber
                         WHERE s.assignment = a.id AND s.latest = 1 AND s.status = 'submitted'
                               AND (g.id IS NULL OR g.grade IS NULL OR g.grade < 0 OR g.timemodified < s.timemodified)
                       ) AS waiting
                  FROM {assign} a
                 WHERE a.course = :courseid
              ORDER BY a.duedate";
        foreach ($DB->get_records_sql($sql, ['courseid' => $course->id]) as $row) {
            $cm = get_coursemodule_from_instance('assign', $row->id, $course->id);
            if (!$cm) {
                continue;
            }
            $review[] = [
                'name' => format_string($row->name, true, ['escape' => false]),
                'course' => format_string($course->fullname, true, ['escape' => false]),
                'due' => $row->duedate ? userdate($row->duedate, get_string('strftimedatetimeshort', 'langconfig')) : '',
                'waiting' => (int) $row->waiting,
                'haswaiting' => $row->waiting > 0,
                'url' => (new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']))->out(false),
            ];
        }
    } else if (has_capability('mod/assign:submit', $context)) {
        $items = array_merge($items, insights::assignments($USER->id, $course->id)['items']);
    }
}
// To do first (overdue, then by due date), then submitted, then reviewed.
$order = ['pending' => 0, 'submitted' => 1, 'reviewed' => 2];
usort($items, fn($a, $b) => [$order[$a['status']], $a['duedate'] ?: PHP_INT_MAX] <=> [$order[$b['status']], $b['duedate'] ?: PHP_INT_MAX]);
$counts = array_count_values(array_column($items, 'status')) + ['pending' => 0, 'submitted' => 0, 'reviewed' => 0];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/myassignments', [
    'items' => $items,
    'hasitems' => !empty($items),
    'total' => count($items),
    'pending' => $counts['pending'],
    'submitted' => $counts['submitted'],
    'reviewed' => $counts['reviewed'],
    'review' => $review,
    'hasreview' => !empty($review),
]);
echo $OUTPUT->footer();
