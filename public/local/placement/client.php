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
 * A client's record: profile (only permitted fields), assignment, resumes, applications and internal notes.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\audit;
use local_placement\local\clients;
use local_placement\local\notify;
use local_placement\local\perm;
use local_placement\local\resumes;
use local_placement\local\ui;

$id = required_param('id', PARAM_INT);
$client = clients::get($id);
$url = new moodle_url('/local/placement/client.php', ['id' => $id]);
ui::setup($url, fullname($client->user), 'clients');
if ((int) $client->userid === (int) $USER->id) {
    redirect(new moodle_url('/local/placement/profile.php'));
}
clients::require_access($client);
audit::log('client_view', 'client', $id, $id);

$action = optional_param('action', '', PARAM_ALPHA);
if ($action && confirm_sesskey()) {
    if ($action === 'assign') {
        perm::require($client->employeeid ? 'act_reassign' : 'act_assign');
        clients::assign($client, required_param('employeeid', PARAM_INT));
    } else if ($action === 'notes') {
        if (!perm::is_admin() && !perm::can(['field_notes', 'act_edit'])) {
            throw new moodle_exception('nopermission', 'local_placement');
        }
        $DB->set_field('local_placement_client', 'adminnotes', optional_param('adminnotes', '', PARAM_TEXT), ['id' => $id]);
        audit::log('client_notes_edit', 'client', $id, $id);
    } else if ($action === 'requestupdate') {
        perm::require('act_edit');
        notify::send('profile', (int) $client->userid, get_string('msg_profile_subject', 'local_placement'),
            get_string('msg_profile_body', 'local_placement', optional_param('message', '', PARAM_TEXT)),
            new moodle_url('/local/placement/profile.php'));
        audit::log('client_update_requested', 'client', $id, $id);
    }
    redirect($url, get_string('saved', 'local_placement'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo ui::header();
$form = fn(string $action, string $inner, string $class = 'lp-inlineform') => html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) . $inner,
    ['method' => 'post', 'action' => $url->out(false), 'class' => $class]);

// Title bar with actions.
$buttons = [];
if (perm::can(['feat_client_edit', 'act_edit'])) {
    $buttons[] = html_writer::link(new moodle_url('/local/placement/clientedit.php', ['id' => $id]), get_string('editprofile', 'local_placement'),
        ['class' => 'btn btn-outline-secondary']);
}
if (perm::can(['act_add', 'feat_app_create'])) {
    $buttons[] = html_writer::link(new moodle_url('/local/placement/appedit.php', ['clientid' => $id]), get_string('newapplication', 'local_placement'),
        ['class' => 'btn btn-primary']);
}
echo html_writer::div(html_writer::div(($client->status ? '' : html_writer::span(get_string('inactive', 'local_placement'), 'lp-status lp-st-grey') . ' ') .
    html_writer::span(s($client->data['currenttitle'] ?? ''), 'lp-muted'), 'lp-headline') .
    html_writer::div(implode(' ', $buttons), 'lp-actions'), 'lp-titlebar');

echo html_writer::start_div('lp-grid');
echo html_writer::start_div('lp-main');

echo ui::profile_html($client);

// Applications.
$apps = $DB->get_records('local_placement_app', ['clientid' => $id], 'timecreated DESC', '*', 0, 10);
echo html_writer::start_div('lp-card');
echo html_writer::div(html_writer::tag('h4', get_string('tab_applications', 'local_placement'), ['class' => 'lp-card-title']) .
    html_writer::link(new moodle_url('/local/placement/applications.php', ['clientid' => $id]), get_string('viewall', 'local_placement')),
    'lp-card-head');
if ($apps) {
    $items = '';
    foreach ($apps as $a) {
        $items .= html_writer::tag('li', html_writer::link(new moodle_url('/local/placement/application.php', ['id' => $a->id]),
            html_writer::tag('strong', s($a->company)) . ' · ' . s($a->jobtitle)) . ' ' . ui::status($a->status) .
            html_writer::span(ui::date((int) ($a->dateapplied ?: $a->timecreated)), 'lp-muted small'));
    }
    echo html_writer::tag('ul', $items, ['class' => 'lp-list']);
} else {
    echo html_writer::div(get_string('noapplications', 'local_placement'), 'lp-muted');
}
echo html_writer::end_div();
echo html_writer::end_div();

// Side column: assignment, resume, notes, request update.
echo html_writer::start_div('lp-side');
echo html_writer::start_div('lp-card');
echo html_writer::tag('h4', get_string('assignedemployee', 'local_placement'), ['class' => 'lp-card-title']);
$employees = clients::employees(false);
echo html_writer::div(s($employees[$client->employeeid] ?? get_string('notassigned', 'local_placement')), 'mb-2');
if (perm::can($client->employeeid ? 'act_reassign' : 'act_assign')) {
    $opts = html_writer::tag('option', get_string('notassigned', 'local_placement'), ['value' => 0]);
    foreach (clients::employees() as $eid => $name) {
        $opts .= html_writer::tag('option', s($name), ['value' => $eid] + ($eid == $client->employeeid ? ['selected' => 'selected'] : []));
    }
    echo $form('assign', html_writer::tag('select', $opts, ['name' => 'employeeid', 'class' => 'form-select', 'aria-label' => get_string('employee', 'local_placement')]) .
        html_writer::tag('button', get_string($client->employeeid ? 'reassign' : 'assign', 'local_placement'), ['class' => 'btn btn-sm btn-outline-primary']));
}
echo html_writer::end_div();

if (clients::can_see($client, 'resume')) {
    $latest = resumes::latest($id);
    echo html_writer::start_div('lp-card');
    echo html_writer::div(html_writer::tag('h4', get_string('resume', 'local_placement'), ['class' => 'lp-card-title']) .
        html_writer::link(new moodle_url('/local/placement/resume.php', ['clientid' => $id]), get_string('allversions', 'local_placement')), 'lp-card-head');
    if ($latest) {
        echo html_writer::div(s($latest->filename) . ' · v' . $latest->version . ' ' . ui::status($latest->status), 'mb-2');
        $links = [];
        if (resumes::can_open($latest, false)) {
            $links[] = html_writer::link(new moodle_url('/local/placement/file.php', ['id' => $latest->id]), get_string('preview', 'local_placement'),
                ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']);
        }
        if (resumes::can_open($latest, true)) {
            $links[] = html_writer::link(new moodle_url('/local/placement/file.php', ['id' => $latest->id, 'download' => 1]), get_string('download'),
                ['class' => 'btn btn-sm btn-outline-secondary']);
        }
        echo html_writer::div(implode(' ', $links), 'lp-actions');
    } else {
        echo html_writer::div(get_string('noresume', 'local_placement'), 'lp-muted');
    }
    echo html_writer::end_div();
}

if (perm::is_admin() || perm::can('field_notes')) {
    echo html_writer::start_div('lp-card');
    echo html_writer::tag('h4', get_string('internalnotes', 'local_placement'), ['class' => 'lp-card-title']);
    if (perm::is_admin() || perm::can(['field_notes', 'act_edit'])) {
        echo $form('notes', html_writer::tag('textarea', s($client->adminnotes ?? ''), ['name' => 'adminnotes', 'rows' => 4, 'class' => 'form-control mb-2',
            'aria-label' => get_string('internalnotes', 'local_placement')]) .
            html_writer::tag('button', get_string('save', 'admin'), ['class' => 'btn btn-sm btn-outline-primary']), '');
    } else {
        echo html_writer::div(nl2br(s($client->adminnotes ?: '–')));
    }
    echo html_writer::end_div();
}

if (perm::can('act_edit')) {
    echo html_writer::start_div('lp-card');
    echo html_writer::tag('h4', get_string('requestupdate', 'local_placement'), ['class' => 'lp-card-title']);
    echo $form('requestupdate', html_writer::tag('textarea', '', ['name' => 'message', 'rows' => 2, 'class' => 'form-control mb-2',
        'placeholder' => get_string('requestupdate_ph', 'local_placement'), 'aria-label' => get_string('requestupdate', 'local_placement')]) .
        html_writer::tag('button', get_string('send', 'message'), ['class' => 'btn btn-sm btn-outline-primary']), '');
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
