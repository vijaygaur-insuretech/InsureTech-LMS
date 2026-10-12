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
 * Manage one student or trainer: their courses, batches and access dates.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use theme_insuretech\form\assign_form;
use theme_insuretech\local\people;

$userid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);

$user = core_user::get_user($userid, '*', MUST_EXIST);
if ($user->deleted || isguestuser($user)) {
    throw new moodle_exception('invaliduser');
}

$url = new moodle_url('/theme/insuretech/manage/person.php', ['id' => $userid]);
$listurl = new moodle_url('/theme/insuretech/manage/people.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(fullname($user));
$PAGE->set_heading(fullname($user));
$PAGE->navbar->add(get_string('managepeople', 'theme_insuretech'), $listurl);
$PAGE->navbar->add(fullname($user), $url);

$assignments = people::assignments($userid);
$istrainer = (bool) array_filter($assignments, fn($a) => $a['type'] === people::TRAINER);
$courses = people::course_options();

// Quick actions on an existing assignment (POST + sesskey, then back to this page).
if ($action && $action !== 'assign') {
    require_sesskey();
    $courseid = required_param('courseid', PARAM_INT);
    $message = 'saved';
    switch ($action) {
        case 'renew':
            $date = required_param('timeend', PARAM_TEXT);
            $timeend = 0;
            if ($date !== '') {
                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
                    throw new moodle_exception('invaliddate', 'theme_insuretech');
                }
                // End of that day in the admin's timezone.
                $timeend = make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 23, 59, 59);
            }
            people::update_access($userid, $courseid, null, $timeend, ENROL_USER_ACTIVE);
            $message = 'accessupdated';
            break;
        case 'suspend':
            people::update_access($userid, $courseid, null, null, ENROL_USER_SUSPENDED);
            $message = 'accesssuspended';
            break;
        case 'activate':
            people::update_access($userid, $courseid, null, null, ENROL_USER_ACTIVE);
            $message = 'accessactivated';
            break;
        case 'remove':
            people::remove($userid, $courseid);
            $message = 'removedfromcourse';
            break;
        case 'addbatch':
            people::add_to_batch($userid, $courseid, required_param('groupid', PARAM_INT));
            $message = 'addedtobatch';
            break;
        case 'removebatch':
            people::remove_from_batch($userid, $courseid, required_param('groupid', PARAM_INT));
            $message = 'removedfrombatch';
            break;
        default:
            throw new moodle_exception('invalidaction', 'theme_insuretech');
    }
    redirect($url, get_string($message, 'theme_insuretech'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form = new assign_form($url, [
    'userid' => $userid,
    'courses' => $courses,
    'type' => $istrainer ? people::TRAINER : people::STUDENT,
]);
if ($data = $form->get_data()) {
    [$courseid, $groupid] = array_map('intval', explode(':', $data->coursebatch));
    if (!isset($courses[$courseid]) || ($groupid && !isset($courses[$courseid]['batches'][$groupid]))) {
        throw new moodle_exception('invalidcourseid');
    }
    people::assign($userid, $courseid, $data->type, $groupid, (int) ($data->timestart ?? 0), (int) ($data->timeend ?? 0));
    redirect($url, get_string('assigned', 'theme_insuretech'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$rows = [];
foreach ($assignments as $a) {
    $inbatch = array_column($a['batches'], 'id');
    $available = [];
    foreach ($courses[$a['courseid']]['batches'] ?? [] as $groupid => $name) {
        if (!in_array($groupid, $inbatch)) {
            $available[] = ['id' => $groupid, 'name' => $name];
        }
    }
    $isstudent = $a['type'] === people::STUDENT;
    $rows[] = $a + [
        'isstudent' => $isstudent,
        'typelabel' => get_string($a['type'], 'theme_insuretech'),
        'courseurl' => (new moodle_url('/course/view.php', ['id' => $a['courseid']]))->out(false),
        'start' => $a['timestart'] ? userdate($a['timestart'], get_string('strftimedate', 'langconfig')) : '—',
        'end' => $a['timeend'] ? userdate($a['timeend'], get_string('strftimedate', 'langconfig'))
            : get_string('noenddate', 'theme_insuretech'),
        'enddate' => $a['timeend'] ? userdate($a['timeend'], '%Y-%m-%d', 99, false) : '',
        'statuslabel' => get_string('status' . $a['status'], 'theme_insuretech'),
        'suspended' => $a['status'] === 'suspended',
        'available' => $available,
        'hasavailable' => !empty($available),
        'hasbatches' => !empty($a['batches']),
        'confirmremove' => get_string('confirmremove', 'theme_insuretech', (object) [
            'name' => fullname($user), 'course' => $a['course'],
        ]),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_insuretech/manage_person', [
    'picture' => $OUTPUT->user_picture($user, ['size' => 64, 'link' => false]),
    'name' => fullname($user),
    'email' => $user->email,
    'suspended' => !empty($user->suspended),
    'typelabel' => get_string($istrainer ? 'trainer' : ($assignments ? 'student' : 'notassigned'), 'theme_insuretech'),
    'editurl' => (new moodle_url('/user/editadvanced.php', ['id' => $userid, 'course' => SITEID]))->out(false),
    'profileurl' => (new moodle_url('/user/profile.php', ['id' => $userid]))->out(false),
    'listurl' => $listurl->out(false),
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'form' => $form->render(),
]);
echo $OUTPUT->footer();
