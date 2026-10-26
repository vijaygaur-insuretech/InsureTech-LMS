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
 * Audit log (admins only): permission changes and important data actions.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\perm;
use local_placement\local\ui;

$action = optional_param('action', '', PARAM_ALPHAEXT);
$clientid = optional_param('clientid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$url = new moodle_url('/local/placement/audit.php', array_filter(['action' => $action, 'clientid' => $clientid]));
ui::setup($url, get_string('tab_audit', 'local_placement'), 'audit');
if (!perm::is_admin()) {
    throw new moodle_exception('nopermission', 'local_placement');
}

$where = '1 = 1';
$params = [];
if ($action !== '') {
    $where .= ' AND action = :action';
    $params['action'] = $action;
}
if ($clientid) {
    $where .= ' AND clientid = :clientid';
    $params['clientid'] = $clientid;
}
$total = $DB->count_records_select('local_placement_audit', $where, $params);
$rows = $DB->get_records_select('local_placement_audit', $where, $params, 'timecreated DESC, id DESC', '*', $page * 100, 100);

echo ui::header();
$opts = html_writer::tag('option', get_string('allactions', 'local_placement'), ['value' => '']);
foreach ($DB->get_fieldset_sql('SELECT DISTINCT action FROM {local_placement_audit} ORDER BY action') as $a) {
    $opts .= html_writer::tag('option', s($a), ['value' => $a] + ($a === $action ? ['selected' => 'selected'] : []));
}
echo html_writer::tag('form', html_writer::tag('select', $opts, ['name' => 'action', 'class' => 'form-select', 'onchange' => 'this.form.submit()',
    'aria-label' => get_string('action')]) . ($clientid ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'clientid', 'value' => $clientid]) : ''),
    ['method' => 'get', 'class' => 'lp-filters']);
$t = new html_table();
$t->attributes['class'] = 'generaltable lp-table';
$t->head = [get_string('time'), get_string('user'), get_string('action'), get_string('client', 'local_placement'), get_string('details', 'local_placement')];
foreach ($rows as $r) {
    $client = '';
    if ($r->clientid && ($cu = $DB->get_record_sql('SELECT u.* FROM {local_placement_client} c JOIN {user} u ON u.id = c.userid WHERE c.id = ?', [$r->clientid]))) {
        $client = html_writer::link(new moodle_url('/local/placement/client.php', ['id' => $r->clientid]), s(fullname($cu)));
    }
    $t->data[] = [ui::date((int) $r->timecreated, true), $r->userid ? s(fullname(core_user::get_user($r->userid))) : '–',
        html_writer::tag('code', s($r->action)), $client, s($r->details)];
}
echo html_writer::table($t);
echo $OUTPUT->paging_bar($total, $page, 100, $url);
echo $OUTPUT->footer();
