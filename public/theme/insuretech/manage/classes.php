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
 * Classes & batches: every course's live class link and timing, its batches and their own links, in one place.
 *
 * Admins see every course. Trainers see the courses they can edit, so they can keep their own links up to date.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/group/lib.php');

use theme_insuretech\local\live_class_fields;

$search = trim(optional_param('search', '', PARAM_NOTAGS));
$action = optional_param('action', '', PARAM_ALPHA);

require_login();
$syscontext = context_system::instance();
$isadmin = has_capability('moodle/site:config', $syscontext);

$url = new moodle_url('/theme/insuretech/manage/classes.php', $search !== '' ? ['search' => $search] : []);
$PAGE->set_url($url);
$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('manageclasses', 'theme_insuretech'));
$PAGE->set_heading(get_string('manageclasses', 'theme_insuretech'));
$PAGE->navbar->add(get_string('manageclasses', 'theme_insuretech'), $url);

if ($action) {
    require_sesskey();
    $courseid = required_param('courseid', PARAM_INT);
    $coursecontext = context_course::instance($courseid);
    $anchor = 'course-' . $courseid;
    $type = \core\output\notification::NOTIFY_SUCCESS;

    switch ($action) {
        case 'savecourse':
            require_capability('moodle/course:update', $coursecontext);
            $ok = live_class_fields::save_course($courseid, optional_param('link', '', PARAM_RAW_TRIMMED),
                optional_param('schedule', '', PARAM_TEXT));
            $message = $ok ? 'linksaved' : 'invalidlink';
            break;
        case 'savebatch':
            require_capability('moodle/course:managegroups', $coursecontext);
            $group = groups_get_group(required_param('groupid', PARAM_INT), '*', MUST_EXIST);
            if ((int) $group->courseid !== $courseid) {
                throw new moodle_exception('invalidgroupid');
            }
            $ok = live_class_fields::save_group($group->id, optional_param('link', '', PARAM_RAW_TRIMMED),
                optional_param('schedule', '', PARAM_TEXT));
            $message = $ok ? 'linksaved' : 'invalidlink';
            break;
        case 'addbatch':
            require_capability('moodle/course:managegroups', $coursecontext);
            $name = trim(required_param('name', PARAM_TEXT));
            if ($name === '') {
                $ok = false;
                $message = 'batchnamerequired';
            } else {
                groups_create_group((object) ['courseid' => $courseid, 'name' => $name]);
                $ok = true;
                $message = 'batchadded';
            }
            break;
        default:
            throw new moodle_exception('invalidaction', 'theme_insuretech');
    }
    if (!$ok) {
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect(new moodle_url($url, [], $anchor), get_string($message, 'theme_insuretech'), null, $type);
}

// Courses this person may manage links for.
if ($isadmin) {
    $courses = get_courses('all', 'c.fullname ASC', 'c.id, c.fullname, c.shortname, c.visible');
    unset($courses[SITEID]);
} else {
    $courses = enrol_get_my_courses(['shortname', 'visible'], 'fullname ASC');
}
$courses = array_filter($courses, fn($c) => has_capability('moodle/course:update', context_course::instance($c->id)));
if (!$courses && !$isadmin) {
    throw new required_capability_exception($syscontext, 'moodle/course:update', 'nopermissions', '');
}
if ($search !== '') {
    $courses = array_filter($courses, fn($c) => core_text::strpos(core_text::strtolower($c->fullname . ' ' . $c->shortname),
        core_text::strtolower($search)) !== false);
}

$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$trainerroles = array_map(fn($r) => $r->id, array_merge(get_archetype_roles('editingteacher'), get_archetype_roles('teacher')));
$withlink = 0;
$cards = [];
foreach ($courses as $course) {
    $context = context_course::instance($course->id);
    $courselive = live_class_fields::get_course($course->id);
    if ($courselive['link'] !== '') {
        $withlink++;
    }
    $trainers = [];
    foreach ($trainerroles as $roleid) {
        foreach (get_role_users($roleid, $context, false, 'u.id, ' . $namefields, 'u.lastname, u.firstname') as $u) {
            $trainers[$u->id] = fullname($u);
        }
    }
    $canbatch = has_capability('moodle/course:managegroups', $context);
    $learners = count_enrolled_users($context, 'moodle/course:isincompletionreports', 0, true);
    $batches = [];
    foreach (groups_get_all_groups($course->id) as $group) {
        $live = live_class_fields::get_group($group->id);
        $batches[] = [
            'id' => $group->id,
            'courseid' => $course->id,
            'name' => format_string($group->name, true, ['context' => $context, 'escape' => false]),
            'members' => count(groups_get_members($group->id, 'u.id')),
            'link' => $live['link'],
            'schedule' => $live['schedule'],
            'membersurl' => (new moodle_url('/group/members.php', ['group' => $group->id]))->out(false),
        ];
    }
    $cards[] = [
        'id' => $course->id,
        'name' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
        'hidden' => empty($course->visible),
        'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'trainers' => implode(', ', $trainers),
        'hastrainers' => !empty($trainers),
        'learnerstext' => get_string($learners == 1 ? 'learnercount' : 'learnerscount', 'theme_insuretech', $learners),
        'link' => $courselive['link'],
        'schedule' => $courselive['schedule'],
        'haslink' => $courselive['link'] !== '',
        'batches' => $batches,
        'hasbatches' => !empty($batches),
        'canbatch' => $canbatch,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_insuretech/manage_classes', [
    'isadmin' => $isadmin,
    'search' => $search,
    'formurl' => (new moodle_url('/theme/insuretech/manage/classes.php'))->out(false),
    'peopleurl' => (new moodle_url('/theme/insuretech/manage/people.php'))->out(false),
    'sesskey' => sesskey(),
    'courses' => $cards,
    'hascourses' => !empty($cards),
    'summary' => get_string('classessummary', 'theme_insuretech', (object) ['total' => count($cards), 'withlink' => $withlink]),
]);
echo $OUTPUT->footer();
