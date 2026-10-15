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
 * Add a student or trainer: account, optional enrolment, and a welcome email with a temporary password.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use theme_insuretech\form\person_form;
use theme_insuretech\local\people;

$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);

$url = new moodle_url('/theme/insuretech/manage/addperson.php');
$listurl = new moodle_url('/theme/insuretech/manage/people.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('addperson', 'theme_insuretech'));
$PAGE->set_heading(get_string('addperson', 'theme_insuretech'));
$PAGE->navbar->add(get_string('managepeople', 'theme_insuretech'), $listurl);
$PAGE->navbar->add(get_string('addperson', 'theme_insuretech'), $url);

$courses = people::course_options();
$form = new person_form($url, ['courses' => $courses]);

if ($form->is_cancelled()) {
    redirect($listurl);
} else if ($data = $form->get_data()) {
    $password = generate_password(12);
    $user = (object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => core_text::strtolower(trim($data->username) !== '' ? trim($data->username) : trim($data->email)),
        'password' => $password,
        'firstname' => trim($data->firstname),
        'lastname' => trim($data->lastname),
        'email' => trim($data->email),
    ];
    $user->id = \core\user::create_user($user);
    // They choose their own password the first time they log in.
    set_user_preference('auth_forcepasswordchange', 1, $user->id);

    $courseline = '';
    [$courseid, $groupid] = array_map('intval', explode(':', $data->coursebatch));
    if ($courseid && isset($courses[$courseid])) {
        $haslocal = class_exists('\\local_itskills\\local\\mailer');
        if ($haslocal) {
            // The welcome email already mentions the course; skip the separate enrolment email.
            \local_itskills\local\mailer::$suppressenrolmentemail = true;
        }
        people::assign($user->id, $courseid, $data->type, isset($courses[$courseid]['batches'][$groupid]) ? $groupid : 0,
            time(), $data->type === people::STUDENT ? (int) ($data->timeend ?? 0) : 0);
        if ($haslocal) {
            \local_itskills\local\mailer::$suppressenrolmentemail = false;
        }
        $courseline = get_string('welcomecourseline', 'theme_insuretech', (object) [
            'course' => $courses[$courseid]['name'],
            'url' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
        ]);
    }

    $message = 'personcreated';
    if ($data->sendwelcome && class_exists('\\local_itskills\\local\\mailer')) {
        $record = core_user::get_user($user->id);
        \local_itskills\local\mailer::email($record, 'welcome', [
            'username' => $record->username,
            'password' => $password,
            'course' => $courseline,
            'courseurl' => $courseid ? (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false) : '',
        ]);
        $message = 'personcreatedemailed';
    }
    redirect(new moodle_url('/theme/insuretech/manage/person.php', ['id' => $user->id]),
        get_string($message, 'theme_insuretech', $user->email), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo html_writer::div(get_string('addperson_intro', 'theme_insuretech'), 'alert alert-info');
$form->display();
echo $OUTPUT->footer();
