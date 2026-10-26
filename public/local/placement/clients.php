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
 * Clients: admins see all; employees see the clients assigned to them.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\clients;
use local_placement\local\perm;
use local_placement\local\resumes;
use local_placement\local\ui;

$q = optional_param('q', '', PARAM_TEXT);
$employeeid = optional_param('employeeid', -1, PARAM_INT);
$status = optional_param('status', 1, PARAM_INT);
$url = new moodle_url('/local/placement/clients.php', ['q' => $q, 'employeeid' => $employeeid, 'status' => $status]);
ui::setup($url, get_string('tab_clients', 'local_placement'), 'clients');
if (perm::is_client()) {
    redirect(new moodle_url('/local/placement/profile.php'));
}

// Bulk assign (admins, or employees allowed to bulk-assign).
if (optional_param('bulkassign', 0, PARAM_BOOL) && confirm_sesskey()) {
    perm::require(['feat_bulk', 'act_reassign']);
    $target = required_param('target', PARAM_INT);
    $ids = optional_param_array('ids', [], PARAM_INT);
    foreach ($ids as $id) {
        $client = clients::get($id);
        clients::require_access($client);
        clients::assign($client, $target);
    }
    redirect($url, get_string('assigned', 'local_placement', count($ids)), null, \core\output\notification::NOTIFY_SUCCESS);
}
// Activate / deactivate (admins).
if (($toggle = optional_param('toggle', 0, PARAM_INT)) && confirm_sesskey()) {
    if (!perm::is_admin()) {
        throw new moodle_exception('nopermission', 'local_placement');
    }
    $client = clients::get($toggle);
    $new = $client->status ? clients::INACTIVE : clients::ACTIVE;
    $DB->set_field('local_placement_client', 'status', $new, ['id' => $toggle]);
    \local_placement\local\audit::log($new ? 'client_activate' : 'client_deactivate', 'client', $toggle, $toggle);
    redirect($url);
}

$visible = clients::visible_ids();
$where = ['c.status = :status'];
$params = ['status' => $status ? 1 : 0];
if ($visible !== null) {
    $where[] = $visible ? 'c.id IN (' . implode(',', $visible) . ')' : '1 = 0';
}
if ($q !== '') {
    $where[] = '(' . $DB->sql_like($DB->sql_concat('u.firstname', "' '", 'u.lastname'), ':q1', false) . ' OR ' .
        $DB->sql_like('u.email', ':q2', false) . ')';
    $params['q1'] = $params['q2'] = '%' . $DB->sql_like_escape($q) . '%';
}
if ($employeeid >= 0 && perm::is_admin()) {
    $where[] = 'c.employeeid = :emp';
    $params['emp'] = $employeeid;
}
$rows = $DB->get_records_sql("SELECT c.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename,
                                     u.alternatename, u.email,
                                     (SELECT COUNT(1) FROM {local_placement_app} a WHERE a.clientid = c.id) AS apps
                                FROM {local_placement_client} c JOIN {user} u ON u.id = c.userid
                               WHERE " . implode(' AND ', $where) . " ORDER BY u.firstname, u.lastname", $params);
$employees = clients::employees(false);

echo ui::header();
$opts = '';
if (perm::is_admin()) {
    $opts = html_writer::tag('option', get_string('allemployees', 'local_placement'), ['value' => -1]) .
        html_writer::tag('option', get_string('notassigned', 'local_placement'), ['value' => 0] + ($employeeid === 0 ? ['selected' => 'selected'] : []));
    foreach ($employees as $id => $name) {
        $opts .= html_writer::tag('option', s($name), ['value' => $id] + ($employeeid === $id ? ['selected' => 'selected'] : []));
    }
    $opts = html_writer::tag('select', $opts, ['name' => 'employeeid', 'class' => 'form-select']);
}
echo html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $q, 'class' => 'form-control',
        'placeholder' => get_string('searchclients', 'local_placement')]) . $opts .
    html_writer::tag('select', html_writer::tag('option', get_string('active', 'local_placement'), ['value' => 1]) .
        html_writer::tag('option', get_string('inactive', 'local_placement'), ['value' => 0] + ($status ? [] : ['selected' => 'selected'])),
        ['name' => 'status', 'class' => 'form-select']) .
    html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-primary']),
    ['method' => 'get', 'class' => 'lp-filters']);

$buttons = [];
if (perm::is_admin() || perm::can(['act_add', 'act_assign'])) {
    $buttons[] = html_writer::link(new moodle_url('/local/placement/addperson.php', ['type' => 'client']),
        get_string('addclient', 'local_placement'), ['class' => 'btn btn-primary']);
}
echo html_writer::div(html_writer::span(get_string('resultcount', 'local_placement', count($rows)), 'lp-muted') .
    html_writer::div(implode(' ', $buttons), 'lp-actions'), 'lp-titlebar');

if (!$rows) {
    echo html_writer::div(get_string('noclients', 'local_placement'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}
$bulk = perm::can(['feat_bulk', 'act_reassign']);
$t = new html_table();
$t->attributes['class'] = 'generaltable lp-table';
$t->head = array_merge($bulk ? [''] : [], [get_string('client', 'local_placement')],
    perm::field('email') ? [get_string('email')] : [], [get_string('employee', 'local_placement'),
    get_string('resume', 'local_placement'), get_string('tab_applications', 'local_placement'), '']);
foreach ($rows as $r) {
    $latest = resumes::latest((int) $r->id);
    $row = $bulk ? [html_writer::checkbox('ids[]', $r->id, false, '', ['form' => 'lp-bulk', 'class' => 'lp-select'])] : [];
    $row[] = html_writer::link(new moodle_url('/local/placement/client.php', ['id' => $r->id]), html_writer::tag('strong', s(fullname($r))));
    if (perm::field('email')) {
        $row[] = s($r->email);
    }
    $actions = [html_writer::link(new moodle_url('/local/placement/client.php', ['id' => $r->id]), get_string('open', 'local_placement'),
        ['class' => 'btn btn-sm btn-outline-secondary'])];
    if (perm::is_admin()) {
        $actions[] = html_writer::link(new moodle_url($url, ['toggle' => $r->id, 'sesskey' => sesskey()]),
            get_string($r->status ? 'deactivate' : 'activate', 'local_placement'), ['class' => 'btn btn-sm btn-link']);
    }
    array_push($row, s($employees[$r->employeeid] ?? get_string('notassigned', 'local_placement')),
        $latest ? ui::status($latest->status) . ' ' . html_writer::span('v' . $latest->version, 'lp-muted small')
            : html_writer::span(get_string('noresume', 'local_placement'), 'lp-muted'),
        (int) $r->apps, html_writer::div(implode(' ', $actions), 'lp-actions'));
    $t->data[] = $row;
}
echo html_writer::table($t);

if ($bulk) {
    $opts = html_writer::tag('option', get_string('notassigned', 'local_placement'), ['value' => 0]);
    foreach (clients::employees() as $id => $name) {
        $opts .= html_writer::tag('option', s($name), ['value' => $id]);
    }
    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bulkassign', 'value' => 1]) .
        html_writer::span(get_string('bulkassign', 'local_placement'), 'lp-muted') .
        html_writer::tag('select', $opts, ['name' => 'target', 'class' => 'form-select', 'aria-label' => get_string('employee', 'local_placement')]) .
        html_writer::tag('button', get_string('assign', 'local_placement'), ['class' => 'btn btn-outline-primary']),
        ['method' => 'post', 'id' => 'lp-bulk', 'action' => $url->out(false), 'class' => 'lp-filters']);
}
echo $OUTPUT->footer();
