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
 * One job application: details, screening answers, resume version used, and status history.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\apps;
use local_placement\local\audit;
use local_placement\local\clients;
use local_placement\local\perm;
use local_placement\local\resumes;
use local_placement\local\ui;

$id = required_param('id', PARAM_INT);
$url = new moodle_url('/local/placement/application.php', ['id' => $id]);
require_login(null, false);
[$app, $client] = apps::get_checked($id);
$self = (int) $client->userid === (int) $USER->id;
ui::setup($url, s($app->company) . ' – ' . s($app->jobtitle), $self ? 'myapplications' : 'applications');

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'status' && confirm_sesskey()) {
    perm::require(['feat_app_status', 'act_edit']);
    $status = required_param('status', PARAM_TEXT);
    if (!in_array($status, ui::lines('statuses'))) {
        throw new moodle_exception('invalidstatus', 'local_placement');
    }
    apps::set_status($app, $status, optional_param('note', '', PARAM_TEXT));
    if (perm::can('feat_followups') && optional_param('followup', '', PARAM_TEXT) !== '') {
        $follow = strtotime(optional_param('followup', '', PARAM_TEXT));
        $DB->set_field('local_placement_app', 'followupdate', $follow ?: 0, ['id' => $id]);
    }
    redirect($url, get_string('statusupdated', 'local_placement'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'delete' && confirm_sesskey()) {
    perm::require(['act_delete']);
    $DB->delete_records('local_placement_answer', ['appid' => $id]);
    $DB->delete_records('local_placement_statuslog', ['appid' => $id]);
    $DB->delete_records('local_placement_app', ['id' => $id]);
    audit::log('application_delete', 'application', $id, (int) $client->id, "{$app->company} - {$app->jobtitle}");
    redirect(new moodle_url('/local/placement/applications.php'), get_string('applicationdeleted', 'local_placement'));
}

echo ui::header();
$buttons = [];
if (!$self && perm::can('act_edit')) {
    $buttons[] = html_writer::link(new moodle_url('/local/placement/appedit.php', ['id' => $id]), get_string('edit'),
        ['class' => 'btn btn-outline-secondary']);
}
if (!$self && perm::can('act_delete')) {
    $buttons[] = html_writer::link(new moodle_url($url, ['action' => 'delete', 'sesskey' => sesskey()]), get_string('delete'),
        ['class' => 'btn btn-outline-danger', 'data-lp-confirm' => get_string('confirmdelete', 'local_placement')]);
}
echo html_writer::div(
    html_writer::div(ui::status($app->status) . ' ' . html_writer::link(new moodle_url('/local/placement/' . ($self ? 'profile.php' : 'client.php'),
        $self ? [] : ['id' => $client->id]), s(fullname($client->user))), 'lp-headline') .
    html_writer::div(implode(' ', $buttons), 'lp-actions'), 'lp-titlebar');

// Details.
$industry = $app->industryid ? $DB->get_field('local_placement_industry', 'name', ['id' => $app->industryid]) : get_string('general', 'local_placement');
$link = fn($u) => $u ? html_writer::link($u, s($u), ['target' => '_blank', 'rel' => 'noopener']) : '–';
$resumecell = '–';
if ($app->resumeid && ($r = $DB->get_record('local_placement_resume', ['id' => $app->resumeid]))) {
    $resumecell = 'v' . $r->version . ' · ' . s($r->filename);
    if (resumes::can_open($r, false)) {
        $resumecell = html_writer::link(new moodle_url('/local/placement/file.php', ['id' => $r->id]), $resumecell, ['target' => '_blank']);
    }
}
$details = [
    get_string('company', 'local_placement') => s($app->company),
    get_string('jobtitle', 'local_placement') => s($app->jobtitle),
    get_string('industry', 'local_placement') => s($industry) . ($app->jobcategory ? ' · ' . s($app->jobcategory) : ''),
    get_string('platform', 'local_placement') => s($app->platform ?: '–'),
    get_string('dateapplied', 'local_placement') => ui::date((int) $app->dateapplied),
    get_string('jdurl', 'local_placement') => $link($app->jdurl),
    get_string('applyurl', 'local_placement') => $link($app->applyurl),
    get_string('resumeused', 'local_placement') => $resumecell,
    get_string('followupdate', 'local_placement') => ui::date((int) $app->followupdate),
    get_string('reference', 'local_placement') => s($app->reference ?: '–'),
    get_string('employee', 'local_placement') => $app->employeeid ? s(fullname(core_user::get_user($app->employeeid))) : '–',
];
if (clients::can_see($client, 'salary')) {
    $details[get_string('salaryresponse', 'local_placement')] = s($app->salaryresponse ?: '–');
}
if (clients::can_see($client, 'resume') && $app->coverletter) {
    $details[get_string('coverletter', 'local_placement')] = nl2br(s($app->coverletter));
}
if (!$self && $app->notes) {
    $details[get_string('notes', 'local_placement')] = nl2br(s($app->notes));
}
$dl = '';
foreach ($details as $k => $v) {
    $dl .= html_writer::tag('dt', $k) . html_writer::tag('dd', $v);
}
echo html_writer::div(html_writer::tag('dl', $dl, ['class' => 'lp-dl']), 'lp-card');

// Status update.
if (!$self && perm::can(['feat_app_status', 'act_edit'])) {
    $options = '';
    foreach (ui::lines('statuses') as $s) {
        $options .= html_writer::tag('option', s($s), ['value' => $s] + ($s === $app->status ? ['selected' => 'selected'] : []));
    }
    $fields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'status']) .
        html_writer::tag('select', $options, ['name' => 'status', 'class' => 'form-select', 'aria-label' => get_string('status')]) .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'note', 'class' => 'form-control',
            'placeholder' => get_string('statusnote', 'local_placement')]);
    if (perm::can('feat_followups')) {
        $fields .= html_writer::empty_tag('input', ['type' => 'date', 'name' => 'followup', 'class' => 'form-control',
            'title' => get_string('followupdate', 'local_placement'),
            'value' => $app->followupdate ? date('Y-m-d', (int) $app->followupdate) : '']);
    }
    $fields .= html_writer::tag('button', get_string('updatestatus', 'local_placement'), ['class' => 'btn btn-primary']);
    echo html_writer::tag('h4', get_string('updatestatus', 'local_placement'), ['class' => 'lp-h4']);
    echo html_writer::tag('form', $fields, ['method' => 'post', 'action' => $url->out(false), 'class' => 'lp-card lp-statusform']);
}

// Screening answers kept with this application.
$answers = apps::answers($id);
if ($answers) {
    echo html_writer::tag('h4', get_string('screening', 'local_placement'), ['class' => 'lp-h4']);
    $t = new html_table();
    $t->attributes['class'] = 'generaltable lp-table';
    $t->head = [get_string('question', 'local_placement'), get_string('answer', 'local_placement')];
    foreach ($answers as $a) {
        $t->data[] = [s($a->question), $a->notapplicable ? html_writer::span(get_string('notapplicable', 'local_placement'), 'lp-muted')
            : nl2br(s($a->answer ?: '–'))];
    }
    echo html_writer::table($t);
}

// Status history.
$history = $DB->get_records('local_placement_statuslog', ['appid' => $id], 'timecreated DESC, id DESC');
if ($history) {
    echo html_writer::tag('h4', get_string('statushistory', 'local_placement'), ['class' => 'lp-h4']);
    $items = '';
    foreach ($history as $h) {
        $items .= html_writer::tag('li', html_writer::span(ui::date((int) $h->timecreated, true), 'lp-muted') . ' ' .
            ($h->oldstatus ? s($h->oldstatus) . ' → ' : '') . ui::status($h->newstatus) .
            ($self ? '' : ' · ' . s(fullname(core_user::get_user($h->userid)))) . ($h->note ? html_writer::div(s($h->note), 'lp-muted small') : ''));
    }
    echo html_writer::tag('ul', $items, ['class' => 'lp-timeline']);
}
$PAGE->requires->js_amd_inline("document.querySelectorAll('[data-lp-confirm]').forEach(function(a){a.addEventListener('click',function(e){if(!confirm(a.getAttribute('data-lp-confirm'))){e.preventDefault();}});});");
echo $OUTPUT->footer();
