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
 * My certificates: earned certificates to view, download and share, and courses on the way to one.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\certificates;

require_login(null, false);

$PAGE->set_url(new moodle_url('/local/itskills/mycertificates.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('nav_certificates', 'theme_insuretech'));
$PAGE->set_heading(get_string('nav_certificates', 'theme_insuretech'));

$earned = certificates::for_user($USER->id);
$earnedcourses = $DB->get_fieldset_select('local_itskills_cert', 'courseid', 'userid = ?', [$USER->id]);

$inprogress = [];
foreach (enrol_get_my_courses(['enablecompletion'], 'fullname ASC') as $course) {
    $context = context_course::instance($course->id);
    if (in_array($course->id, $earnedcourses) || !has_capability('mod/assign:submit', $context) ||
            !certificates::enabled_for($course)) {
        continue;
    }
    $percent = (int) round(\core_completion\progress::get_course_progress_percentage($course, $USER->id) ?? 0);
    $inprogress[] = [
        'course' => format_string($course->fullname, true, ['escape' => false]),
        'percent' => $percent,
        'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/mycertificates', [
    'earned' => $earned,
    'hasearned' => !empty($earned),
    'inprogress' => $inprogress,
    'hasinprogress' => !empty($inprogress),
]);
echo $OUTPUT->footer();
