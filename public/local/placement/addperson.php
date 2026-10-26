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
 * Add a placement client or employee.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\audit;
use local_placement\local\clients;
use local_placement\local\perm;
use local_placement\local\ui;

$type = required_param('type', PARAM_ALPHA) === 'employee' ? 'employee' : 'client';
$url = new moodle_url('/local/placement/addperson.php', ['type' => $type]);
ui::setup($url, get_string('add' . $type, 'local_placement'), $type === 'employee' ? 'employees' : 'clients');
// Only admins create accounts; employees may add clients only when allowed to add and assign.
if (!perm::is_admin() && !($type === 'client' && perm::can(['act_add', 'act_assign']))) {
    throw new moodle_exception('nopermission', 'local_placement');
}
$back = new moodle_url('/local/placement/' . ($type === 'employee' ? 'employees.php' : 'clients.php'));

$form = new \local_placement\form\person_form($url, ['type' => $type, 'employees' => clients::employees()]);
if ($form->is_cancelled()) {
    redirect($back);
} else if ($data = $form->get_data()) {
    $email = trim($data->email);
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0], '*', IGNORE_MULTIPLE);
    $password = null;
    if (!$user) {
        $password = generate_password(12);
        $userid = \core\user::create_user((object) [
            'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
            'username' => core_text::strtolower($email), 'password' => $password,
            'firstname' => trim($data->firstname), 'lastname' => trim($data->lastname), 'email' => $email,
        ]);
        set_user_preference('auth_forcepasswordchange', 1, $userid);
        $user = core_user::get_user($userid);
    }
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'placement' . $type], MUST_EXIST);
    role_assign($roleid, $user->id, context_system::instance());

    if ($type === 'client') {
        $clientid = $DB->insert_record('local_placement_client', [
            'userid' => $user->id, 'status' => clients::ACTIVE, 'employeeid' => 0,
            'profile' => json_encode(['legalname' => fullname($user)]), 'createdby' => $USER->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        audit::log('client_create', 'client', $clientid, $clientid, $password ? 'new account' : 'existing account');
        $employeeid = perm::is_admin() ? (int) ($data->employeeid ?? 0) : (int) $USER->id;
        if ($employeeid) {
            clients::assign(clients::get($clientid), $employeeid);
        }
        $next = new moodle_url('/local/placement/client.php', ['id' => $clientid]);
    } else {
        audit::log('employee_create', 'user', $user->id, 0, $password ? 'new account' : 'existing account');
        $next = $back;
    }

    if ($data->sendwelcome && class_exists('\\local_itskills\\local\\mailer')) {
        \local_itskills\local\mailer::email($user, 'welcome', [
            'username' => $user->username,
            'password' => $password ?? get_string('existingpassword', 'local_placement'),
            'course' => get_string('welcomeportal', 'local_placement'),
            'courseurl' => (new moodle_url('/local/placement/index.php'))->out(false),
        ]);
    }
    redirect($next, get_string($password ? 'personcreated' : 'personlinked', 'local_placement', $email), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo ui::header();
$form->display();
echo $OUTPUT->footer();
