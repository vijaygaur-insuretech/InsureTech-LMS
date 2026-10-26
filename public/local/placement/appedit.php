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
 * Record a new job application or edit one.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\apps;
use local_placement\local\clients;
use local_placement\local\perm;
use local_placement\local\ui;

$id = optional_param('id', 0, PARAM_INT);
$clientid = optional_param('clientid', 0, PARAM_INT);
$industryid = optional_param('industryid', -1, PARAM_INT);
$url = new moodle_url('/local/placement/appedit.php', array_filter(['id' => $id, 'clientid' => $clientid]));
ui::setup($url, get_string($id ? 'editapplication' : 'newapplication', 'local_placement'), 'applications');

$existing = null;
if ($id) {
    [$existing, $client] = apps::get_checked($id);
    perm::require(['act_edit']);
    $clientid = (int) $client->id;
    if ($industryid < 0) {
        $industryid = (int) $existing->industryid;
    }
} else {
    perm::require(['act_add', 'feat_app_create']);
}

// Step 1: choose the client and the industry (the industry decides which screening questions appear).
if (!$clientid || $industryid < 0) {
    echo ui::header();
    $visible = clients::visible_ids();
    $where = 'c.status = 1' . ($visible === null ? '' : ($visible ? ' AND c.id IN (' . implode(',', $visible) . ')' : ' AND 1 = 0'));
    $rows = $DB->get_records_sql("SELECT c.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename,
                                         u.alternatename
                                    FROM {local_placement_client} c JOIN {user} u ON u.id = c.userid
                                   WHERE $where ORDER BY u.firstname, u.lastname");
    $clientoptions = '';
    foreach ($rows as $r) {
        $clientoptions .= html_writer::tag('option', s(fullname($r)), ['value' => $r->id] + ($r->id == $clientid ? ['selected' => 'selected'] : []));
    }
    $industryoptions = html_writer::tag('option', get_string('general', 'local_placement'), ['value' => 0]);
    foreach ($DB->get_records('local_placement_industry', ['active' => 1], 'sortorder, name') as $ind) {
        $industryoptions .= html_writer::tag('option', s($ind->name), ['value' => $ind->id]);
    }
    echo html_writer::tag('form',
        html_writer::div(html_writer::tag('label', get_string('client', 'local_placement'), ['for' => 'lp-client']) .
            html_writer::tag('select', $clientoptions, ['name' => 'clientid', 'id' => 'lp-client', 'class' => 'form-select', 'required' => 'required']), 'mb-3') .
        html_writer::div(html_writer::tag('label', get_string('industry', 'local_placement'), ['for' => 'lp-industry']) .
            html_writer::tag('select', $industryoptions, ['name' => 'industryid', 'id' => 'lp-industry', 'class' => 'form-select']), 'mb-3') .
        html_writer::tag('button', get_string('continue'), ['class' => 'btn btn-primary']),
        ['method' => 'get', 'action' => $url->out_omit_querystring(), 'class' => 'lp-card lp-narrow']);
    echo $OUTPUT->footer();
    exit;
}

$client = $client ?? clients::get($clientid);
clients::require_access($client);
$url->param('industryid', $industryid);
$url->param('clientid', $clientid);
$form = new \local_placement\form\app_form($url, ['client' => $client, 'industryid' => $industryid, 'appid' => $id]);

if ($existing) {
    $defaults = (array) $existing;
    $defaults['industryid'] = $industryid;
    foreach (apps::answers($id) as $qid => $answer) {
        $q = $DB->get_record('local_placement_question', ['id' => $qid]);
        if ($q && $q->qtype === 'multiselect') {
            $defaults['q' . $qid] = array_fill_keys(array_map('trim', explode(',', $answer->answer)), 1);
        } else if (!$q || $q->qtype !== 'date') {
            $defaults['q' . $qid] = $answer->answer;
        }
        $defaults['q' . $qid . '_na'] = $answer->notapplicable;
    }
    $form->set_data($defaults);
}

$back = $existing ? new moodle_url('/local/placement/application.php', ['id' => $id])
    : new moodle_url('/local/placement/client.php', ['id' => $clientid]);
if ($form->is_cancelled()) {
    redirect($back);
} else if ($data = $form->get_data()) {
    $appid = apps::save($data, $client, $existing);
    redirect(new moodle_url('/local/placement/application.php', ['id' => $appid]), get_string('applicationsaved', 'local_placement'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo ui::header();
echo html_writer::tag('h3', s(fullname($client->user)), ['class' => 'lp-h3']);
$form->display();
echo $OUTPUT->footer();
