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
 * My progress: Career Readiness, Skill Passport, certificates, attendance, quiz scores, assignments and weak topics.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\attendance;
use local_itskills\local\certificates;
use local_itskills\local\insights;

require_login(null, false);
$userid = optional_param('userid', $USER->id, PARAM_INT);
if ($userid != $USER->id) {
    require_capability('moodle/site:config', context_system::instance());
}
$user = core_user::get_user($userid, '*', MUST_EXIST);

$url = new moodle_url('/local/itskills/progress.php', $userid != $USER->id ? ['userid' => $userid] : []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myprogress', 'local_itskills'));
$PAGE->set_heading(get_string('myprogress', 'local_itskills'));

$courses = [];
foreach (enrol_get_all_users_courses($userid, true, ['enablecompletion']) as $course) {
    $context = context_course::instance($course->id);
    if (has_capability('moodle/course:manageactivities', $context, $userid)) {
        continue;
    }
    $att = attendance::summary($userid, $course->id);
    $quiz = insights::quiz_average($userid, $course->id);
    $courses[] = [
        'name' => format_string($course->fullname, true, ['escape' => false]),
        'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'progress' => (int) round(\core_completion\progress::get_course_progress_percentage($course, $userid) ?? 0),
        'attendance' => $att['percent'] === null ? '–' : $att['percent'] . '%',
        'quiz' => $quiz === null ? '–' : $quiz . '%',
        'feedbackurl' => (new moodle_url('/local/itskills/feedback.php', ['courseid' => $course->id]))->out(false),
    ];
}

$readiness = insights::readiness($userid);
$assign = insights::assignments($userid);
$att = attendance::summary($userid);
$quizavg = insights::quiz_average($userid);
$skills = insights::skills($userid);
$certs = certificates::for_user($userid);
$weak = insights::weak_topics($userid);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/progress', [
    'readiness' => $readiness['score'],
    'hasreadiness' => $readiness['score'] !== null,
    'factors' => $readiness['factors'],
    'suggestions' => $readiness['suggestions'],
    'hassuggestions' => $readiness['hassuggestions'],
    'quizavg' => $quizavg === null ? '–' : $quizavg . '%',
    'attendance' => $att['percent'] === null ? '–' : $att['percent'] . '%',
    'assignpending' => $assign['pending'],
    'assignsubmitted' => $assign['submitted'],
    'assignreviewed' => $assign['reviewed'],
    'assignments' => $assign['items'],
    'hasassignments' => !empty($assign['items']),
    'skills' => $skills,
    'hasskills' => !empty($skills),
    'certificates' => $certs,
    'hascertificates' => !empty($certs),
    'weak' => $weak,
    'hasweak' => !empty($weak),
    'courses' => $courses,
    'hascourses' => !empty($courses),
]);
echo $OUTPUT->footer();
