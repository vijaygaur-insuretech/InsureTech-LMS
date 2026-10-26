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
 * Employees (admins only): accounts, status, workload and links to their permissions and reports.
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

$url = new moodle_url('/local/placement/employees.php');
ui::setup($url, get_string('tab_employees', 'local_placement'), 'employees');
if (!perm::is_admin()) {
    throw new moodle_exception('nopermission', 'local_placement');
}

if (($userid = optional_param('suspend', 0, PARAM_INT)) && confirm_sesskey()) {
    $user = core_user::get_user($userid, '*', MUST_EXIST);
    if (!has_capability('local/placement:employee', context_system::instance(), $userid) || is_siteadmin($userid)) {
        throw new moodle_exception('nopermission', 'local_placement');
    }
    $user->suspended = $user->suspended ? 0 : 1;
    user_update_user($user, false);
    if ($user->suspended) {
        \core\session\manager::destroy_user_sessions($userid);
    }
    audit::log($user->suspended ? 'employee_suspend' : 'employee_reactivate', 'user', $userid);
    redirect($url);
}
if (($userid = optional_param('remove', 0, PARAM_INT)) && confirm_sesskey()) {
    // Deactivate as an employee: remove the role; assigned clients become unassigned.
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'placementemployee'], MUST_EXIST);
    role_unassign($roleid, $userid, context_system::instance()->id);
    foreach ($DB->get_fieldset_select('local_placement_client', 'id', 'employeeid = ?', [$userid]) as $cid) {
        clients::assign(clients::get((int) $cid), 0);
    }
    audit::log('employee_deactivate', 'user', $userid);
    redirect($url, get_string('employeeremoved', 'local_placement'));
}

echo ui::header();
echo html_writer::div(html_writer::span(get_string('employees_intro', 'local_placement'), 'lp-muted') .
    html_writer::div(html_writer::link(new moodle_url('/local/placement/addperson.php', ['type' => 'employee']),
        get_string('addemployee', 'local_placement'), ['class' => 'btn btn-primary']) . ' ' .
        html_writer::link(new moodle_url('/local/placement/permissions.php'), get_string('tab_permissions', 'local_placement'),
        ['class' => 'btn btn-outline-secondary']), 'lp-actions'), 'lp-titlebar');

$employees = clients::employees(false);
if (!$employees) {
    echo html_writer::div(get_string('noemployees', 'local_placement'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}
$today = usergetmidnight(time());
$t = new html_table();
$t->attributes['class'] = 'generaltable lp-table';
$t->head = [get_string('employee', 'local_placement'), get_string('status'), get_string('assignedclients', 'local_placement'),
    get_string('appstoday', 'local_placement'), get_string('apps7days', 'local_placement'), get_string('followupsdue', 'local_placement'),
    get_string('lastreport', 'local_placement'), ''];
foreach ($employees as $id => $name) {
    $user = core_user::get_user($id);
    $count = fn(string $where, array $params) => $DB->count_records_select('local_placement_app', $where, $params);
    [$notin, $p] = $DB->get_in_or_equal(\local_placement\local\apps::FINAL, SQL_PARAMS_NAMED, 'f', false);
    $due = $DB->count_records_select('local_placement_app', "employeeid = :e AND followupdate > 0 AND followupdate < :u AND status $notin",
        ['e' => $id, 'u' => $today + DAYSECS] + $p);
    $last = $DB->get_field_sql('SELECT MAX(reportdate) FROM {local_placement_report} WHERE employeeid = ?', [$id]);
    $t->data[] = [
        html_writer::tag('strong', s($name)) . html_writer::div(s($user->email), 'lp-muted small'),
        $user->suspended ? html_writer::span(get_string('suspended', 'local_placement'), 'lp-status lp-st-red')
            : html_writer::span(get_string('active', 'local_placement'), 'lp-status lp-st-green'),
        $DB->count_records('local_placement_client', ['employeeid' => $id, 'status' => 1]),
        $count('employeeid = ? AND timecreated >= ?', [$id, $today]),
        $count('employeeid = ? AND timecreated >= ?', [$id, $today - 6 * DAYSECS]),
        $due ? html_writer::span($due, 'lp-overdue') : 0,
        ui::date((int) $last),
        html_writer::div(
            html_writer::link(new moodle_url('/local/placement/permissions.php', ['userid' => $id]), get_string('permissions', 'role'),
                ['class' => 'btn btn-sm btn-outline-secondary']) . ' ' .
            html_writer::link(new moodle_url('/local/placement/dailyreport.php', ['employeeid' => $id]), get_string('reports', 'local_placement'),
                ['class' => 'btn btn-sm btn-outline-secondary']) . ' ' .
            html_writer::link(new moodle_url($url, ['suspend' => $id, 'sesskey' => sesskey()]),
                get_string($user->suspended ? 'reactivate' : 'suspend', 'local_placement'), ['class' => 'btn btn-sm btn-link']) .
            html_writer::link(new moodle_url($url, ['remove' => $id, 'sesskey' => sesskey()]), get_string('deactivate', 'local_placement'),
                ['class' => 'btn btn-sm btn-link text-danger', 'data-lp-confirm' => get_string('confirmremoveemployee', 'local_placement')]),
            'lp-actions'),
    ];
}
echo html_writer::table($t);
$PAGE->requires->js_amd_inline("document.querySelectorAll('[data-lp-confirm]').forEach(function(a){a.addEventListener('click',function(e){if(!confirm(a.getAttribute('data-lp-confirm'))){e.preventDefault();}});});");
echo $OUTPUT->footer();
