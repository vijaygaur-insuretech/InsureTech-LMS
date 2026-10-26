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
 * Resume and cover letter versions of a client: upload, preview, download and review.
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

$clientid = optional_param('clientid', 0, PARAM_INT);
require_login(null, false);
if (!$clientid) {
    $own = clients::for_user((int) $USER->id);
    if (!$own) {
        throw new moodle_exception('notaclient', 'local_placement');
    }
    $clientid = (int) $own->id;
}
$client = clients::get($clientid);
$self = (int) $client->userid === (int) $USER->id;
$url = new moodle_url('/local/placement/resume.php', $self ? [] : ['clientid' => $clientid]);
ui::setup($url, get_string($self ? 'tab_myresumes' : 'resumes', 'local_placement'), $self ? 'myresumes' : 'clients');
clients::require_access($client);
if (!$self && !clients::can_see($client, 'resume')) {
    throw new moodle_exception('nopermission', 'local_placement');
}
$canupload = $self || perm::is_admin() || perm::can(['feat_client_edit', 'act_add']);

// Review actions.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action && confirm_sesskey()) {
    if (!resumes::can_review() || !in_array($action, ['approved', 'rejected', 'replace'])) {
        throw new moodle_exception('nopermission', 'local_placement');
    }
    $resume = $DB->get_record('local_placement_resume', ['id' => required_param('resumeid', PARAM_INT), 'clientid' => $clientid],
        '*', MUST_EXIST);
    resumes::review($resume, $action, optional_param('note', '', PARAM_TEXT));
    redirect($url, get_string('resumereviewed', 'local_placement'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form = null;
$uploaded = null;
if ($canupload) {
    $form = new \local_placement\form\resume_form($url, ['clientid' => $clientid]);
    if ($data = $form->get_data()) {
        $uploaded = resumes::upload($client, (int) $data->file, $data->kind);
        $form = new \local_placement\form\resume_form($url, ['clientid' => $clientid]);
    }
}

echo ui::header();
if (!$self) {
    echo html_writer::tag('h3', html_writer::link(new moodle_url('/local/placement/client.php', ['id' => $clientid]),
        s(fullname($client->user))), ['class' => 'lp-h3']);
}
if ($uploaded) {
    echo html_writer::div(get_string('uploadconfirm', 'local_placement', (object) [
        'file' => s($uploaded->filename), 'time' => ui::date((int) $uploaded->timecreated, true),
        'version' => $uploaded->version, 'kind' => get_string('kind_' . $uploaded->kind, 'local_placement'),
    ]), 'alert alert-success');
}

$rows = resumes::for_client($clientid);
if ($rows) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable lp-table';
    $table->head = [get_string('doctype', 'local_placement'), get_string('version', 'local_placement'), get_string('file'),
        get_string('uploaded', 'local_placement'), get_string('status'), ''];
    foreach ($rows as $r) {
        $actions = [];
        if (resumes::can_open($r, false)) {
            $actions[] = html_writer::link(new moodle_url('/local/placement/file.php', ['id' => $r->id]),
                get_string('preview', 'local_placement'), ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']);
        }
        if (resumes::can_open($r, true)) {
            $actions[] = html_writer::link(new moodle_url('/local/placement/file.php', ['id' => $r->id, 'download' => 1]),
                get_string('download'), ['class' => 'btn btn-sm btn-outline-secondary']);
        }
        if (resumes::can_review()) {
            foreach (['approved' => 'btn-success', 'replace' => 'btn-warning', 'rejected' => 'btn-outline-danger'] as $st => $cls) {
                $actions[] = html_writer::tag('form',
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resumeid', 'value' => $r->id]) .
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $st]) .
                    ($st === 'approved' ? '' : html_writer::empty_tag('input', ['type' => 'text', 'name' => 'note',
                        'class' => 'form-control form-control-sm', 'placeholder' => get_string('reviewnote', 'local_placement')])) .
                    html_writer::tag('button', get_string('review_' . $st, 'local_placement'), ['class' => 'btn btn-sm ' . $cls]),
                    ['method' => 'post', 'action' => $url->out(false), 'class' => 'lp-inlineform']);
            }
        }
        $status = ui::status($r->status) . ($r->reviewnote ? html_writer::div(s($r->reviewnote), 'lp-muted small') : '');
        $table->data[] = [get_string('kind_' . $r->kind, 'local_placement'), 'v' . $r->version, s($r->filename),
            ui::date((int) $r->timecreated, true) . html_writer::div(s(fullname(core_user::get_user($r->uploadedby))), 'lp-muted small'),
            $status, html_writer::div(implode(' ', $actions), 'lp-actions')];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::div(get_string('noresumes', 'local_placement'), 'alert alert-info');
}

if ($form) {
    echo html_writer::tag('h4', get_string('uploadnew', 'local_placement'), ['class' => 'lp-h4']);
    $form->display();
}
echo $OUTPUT->footer();
