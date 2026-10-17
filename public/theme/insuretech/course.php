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
 * Public course details: what a visitor sees before logging in (summary, curriculum outline, trainers).
 *
 * Only the outline is shown: module and lesson names, never their content. Opening the course itself still goes
 * through Moodle's login and enrolment checks. When the site forces login, this page does too.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

use theme_insuretech\local\landing;
use theme_insuretech\local\live_class_fields;

$id = required_param('id', PARAM_INT);

if (!empty($CFG->forcelogin)) {
    require_login();
}
if ($id == SITEID) {
    redirect(new moodle_url('/'));
}

$course = get_course($id);
if (!core_course_category::can_view_course_info($course)) {
    throw new moodle_exception('coursehidden');
}
$context = context_course::instance($course->id);
$url = new moodle_url('/theme/insuretech/course.php', ['id' => $course->id]);
$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

$PAGE->set_course($course);
$PAGE->set_url($url);
$PAGE->set_pagelayout('insuretechpublic');
$coursename = format_string($course->fullname, true, ['context' => $context, 'escape' => false]);
$PAGE->set_title($coursename . ' | ' . landing::setting('brandname'));

$loggedin = isloggedin() && !isguestuser();
if (!$loggedin) {
    // After logging in, take the visitor straight to the course they were looking at.
    $SESSION->wantsurl = $courseurl->out(false);
}
$enrolled = $loggedin && (is_enrolled($context, null, '', true) || is_viewing($context));

// Curriculum outline: visible modules (sections) and the names of their visible lessons (activities).
$modinfo = get_fast_modinfo($course);
$modules = [];
$lessoncount = 0;
foreach ($modinfo->get_section_info_all() as $section) {
    if (!$section->visible || $section->is_delegated()) {
        continue;
    }
    $lessons = [];
    foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
        $cm = $modinfo->get_cm($cmid);
        if (!$cm->visible || $cm->deletioninprogress || !$cm->has_view()) {
            continue;
        }
        $lessons[] = [
            'name' => $cm->get_formatted_name(['escape' => false]),
            'icon' => $cm->get_icon_url()->out(false),
            'type' => get_string('modulename', $cm->modname),
        ];
    }
    if ($section->section == 0 && !$lessons) {
        continue;
    }
    $lessoncount += count($lessons);
    $modules[] = [
        'name' => html_entity_decode(get_section_name($course, $section), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'lessons' => $lessons,
        'count' => count($lessons),
        'counttext' => get_string(count($lessons) == 1 ? 'lessoncount' : 'lessonscount', 'theme_insuretech', count($lessons)),
        'open' => count($modules) === 0,
    ];
}

$listelement = new core_course_list_element($course);
$trainers = [];
foreach ($listelement->get_course_contacts() as $contact) {
    $trainers[] = ['name' => $contact['username'], 'role' => $contact['rolename']];
}

$summaryhtml = format_text(
    file_rewrite_pluginfile_urls($course->summary, 'pluginfile.php', $context->id, 'course', 'summary', null),
    $course->summaryformat, ['context' => $context]
);
$category = core_course_category::get($course->category, IGNORE_MISSING);
$image = landing::course_image($course);
$live = live_class_fields::get_course($course->id);
$islive = \theme_insuretech\local\landing::delivery($course->id, $live) === 'live';
$schedule = $islive ? $live['schedule'] : '';
$chrome = landing::chrome();
$description = landing::excerpt($summaryhtml, 160) ?: $coursename;

landing::set_meta($coursename . ' | ' . landing::setting('brandname'), $description, $url->out(false),
    str_starts_with($image, 'data:') ? $OUTPUT->image_url('logo', 'theme_insuretech')->out(false) : $image, [[
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => $coursename,
        'description' => $description,
        'url' => $url->out(false),
        'provider' => ['@type' => 'Organization', 'name' => landing::setting('brandname'), 'sameAs' => $CFG->wwwroot],
        'hasCourseInstance' => [
            '@type' => 'CourseInstance',
            'courseMode' => 'Online',
            'courseSchedule' => $schedule ? ['@type' => 'Schedule', 'description' => $schedule] : null,
        ],
    ]]);

$extra = ['itlevel' => '', 'itoutcomes' => '', 'itprereq' => ''];
$reviews = ['average' => null, 'count' => 0, 'items' => [], 'hasitems' => false, 'stars' => ''];
if (class_exists('\\local_itskills\\local\\fields')) {
    $extra = \local_itskills\local\fields::course($course->id);
    $reviews = \local_itskills\local\feedback::published($course->id);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_insuretech/public_course', [
    'name' => $coursename,
    'category' => $category ? $category->get_formatted_name(['escape' => false]) : '',
    'summary' => $summaryhtml,
    'image' => $image,
    'modules' => $modules,
    'hasmodules' => !empty($modules),
    'modulecount' => count(array_filter($modules, fn($m) => $m['count'] > 0)),
    'lessoncount' => $lessoncount,
    'trainers' => $trainers,
    'hastrainers' => !empty($trainers),
    'schedule' => $schedule,
    'islive' => $islive,
    'loggedin' => $loggedin,
    'enrolled' => $enrolled,
    'courseurl' => $courseurl->out(false),
    'loginurl' => get_login_url(),
    'email' => $chrome['email'],
    'mailsubject' => rawurlencode($coursename),
    'phone' => $chrome['phone'],
    'phonelink' => $chrome['phonelink'],
    'homeurl' => $chrome['homeurl'],
    'level' => $extra['itlevel'],
    'outcomes' => $extra['itoutcomes'],
    'prereq' => $extra['itprereq'],
    'reviews' => $reviews,
    'hasreviews' => $reviews['count'] > 0,
]);
echo $OUTPUT->footer();
