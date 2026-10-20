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
 * My live classes: upcoming sessions across my courses with Join buttons, and each course's class link and timing.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\sessions;

require_login(null, false);

$PAGE->set_url(new moodle_url('/local/itskills/mylive.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('nav_liveclasses', 'theme_insuretech'));
$PAGE->set_heading(get_string('nav_liveclasses', 'theme_insuretech'));

$hasfields = class_exists('\\theme_insuretech\\local\\live_class_fields');
$now = time();
$upcoming = [];
$links = [];
foreach (enrol_get_my_courses(['id'], 'fullname ASC') as $course) {
    $context = context_course::instance($course->id);
    $coursename = format_string($course->fullname, true, ['escape' => false]);
    $canmanage = has_capability('local/itskills:managesessions', $context);

    foreach (sessions::for_course($course->id, $USER->id, true) as $session) {
        $end = $session->timestart + $session->duration * MINSECS;
        $live = $now >= $session->timestart && $now <= $end;
        $upcoming[] = [
            'title' => format_string($session->title, true, ['escape' => false]),
            'course' => $coursename,
            'timestart' => (int) $session->timestart,
            'day' => userdate($session->timestart, '%d'),
            'month' => userdate($session->timestart, '%b'),
            'when' => userdate($session->timestart, get_string('strftimedaydatetime', 'langconfig')),
            'duration' => get_string('numminutes', 'moodle', (int) $session->duration),
            'live' => $live,
            'today' => !$live && userdate($session->timestart, '%Y%m%d') === userdate($now, '%Y%m%d'),
            // The Join button opens 15 minutes before the start.
            'canjoin' => $now >= $session->timestart - 15 * MINSECS && $now <= $end,
            'joinurl' => sessions::link($session),
            'manageurl' => $canmanage ? (new moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]))->out(false) : '',
        ];
    }

    if ($hasfields) {
        // The batch link wins over the course link for learners in a batch.
        $fields = \theme_insuretech\local\live_class_fields::get_course($course->id);
        foreach (groups_get_all_groups($course->id, $USER->id) as $group) {
            $batch = \theme_insuretech\local\live_class_fields::get_group($group->id);
            if ($batch['link']) {
                $fields = ['link' => $batch['link'], 'schedule' => $batch['schedule'] ?: $fields['schedule']];
                break;
            }
        }
        if ($fields['link'] || $fields['schedule']) {
            $links[] = [
                'course' => $coursename,
                'schedule' => $fields['schedule'],
                'link' => $fields['link'],
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'manageurl' => $canmanage ? (new moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]))->out(false) : '',
            ];
        }
    }
}
usort($upcoming, fn($a, $b) => $a['timestart'] <=> $b['timestart']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/mylive', [
    'upcoming' => $upcoming,
    'hasupcoming' => !empty($upcoming),
    'links' => $links,
    'haslinks' => !empty($links),
]);
echo $OUTPUT->footer();
