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
 * Daily work reports: employees write theirs (with today's application count filled in); admins read everyone's.
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

$employeeid = optional_param('employeeid', 0, PARAM_INT);
$url = new moodle_url('/local/placement/dailyreport.php', $employeeid ? ['employeeid' => $employeeid] : []);
ui::setup($url, get_string('tab_dailyreport', 'local_placement'), perm::is_admin() ? 'reports' : 'dailyreport');
$admin = perm::is_admin();
if (!$admin) {
    perm::require('feat_daily_reports');
    $employeeid = (int) $USER->id;
}

$today = usergetmidnight(time());
if (!$admin && data_submitted() && confirm_sesskey()) {
    $date = usergetmidnight(strtotime(required_param('reportdate', PARAM_TEXT)) ?: time());
    $count = $DB->count_records_select('local_placement_app', 'employeeid = ? AND timecreated >= ? AND timecreated < ?',
        [$USER->id, $date, $date + DAYSECS]);
    $record = ['employeeid' => $USER->id, 'reportdate' => $date, 'appcount' => $count,
        'summary' => required_param('summary', PARAM_TEXT), 'pending' => optional_param('pending', '', PARAM_TEXT), 'timemodified' => time()];
    if ($existing = $DB->get_record('local_placement_report', ['employeeid' => $USER->id, 'reportdate' => $date])) {
        $record['id'] = $existing->id;
        $DB->update_record('local_placement_report', $record);
    } else {
        $record['timecreated'] = time();
        $record['id'] = $DB->insert_record('local_placement_report', $record);
    }
    audit::log('daily_report', 'report', $record['id'], 0, date('Y-m-d', $date));
    redirect($url, get_string('reportsaved', 'local_placement'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo ui::header();
if (!$admin) {
    $todayreport = $DB->get_record('local_placement_report', ['employeeid' => $USER->id, 'reportdate' => $today]);
    $todaycount = $DB->count_records_select('local_placement_app', 'employeeid = ? AND timecreated >= ?', [$USER->id, $today]);
    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::div(html_writer::tag('label', get_string('reportdate', 'local_placement'), ['for' => 'lp-date']) .
            html_writer::empty_tag('input', ['type' => 'date', 'name' => 'reportdate', 'id' => 'lp-date', 'class' => 'form-control',
                'value' => date('Y-m-d', $today), 'max' => date('Y-m-d', $today)]), 'mb-3') .
        html_writer::div(get_string('appsrecordedtoday', 'local_placement', $todaycount), 'lp-muted mb-3') .
        html_writer::div(html_writer::tag('label', get_string('worksummary', 'local_placement'), ['for' => 'lp-summary']) .
            html_writer::tag('textarea', s($todayreport->summary ?? ''), ['name' => 'summary', 'id' => 'lp-summary', 'rows' => 4,
                'class' => 'form-control', 'required' => 'required']), 'mb-3') .
        html_writer::div(html_writer::tag('label', get_string('pendingwork', 'local_placement'), ['for' => 'lp-pending']) .
            html_writer::tag('textarea', s($todayreport->pending ?? ''), ['name' => 'pending', 'id' => 'lp-pending', 'rows' => 3,
                'class' => 'form-control']), 'mb-3') .
        html_writer::tag('button', get_string('savereport', 'local_placement'), ['class' => 'btn btn-primary']),
        ['method' => 'post', 'action' => $url->out(false), 'class' => 'lp-card lp-narrow']);
} else {
    $opts = html_writer::tag('option', get_string('allemployees', 'local_placement'), ['value' => 0]);
    foreach (clients::employees(false) as $id => $name) {
        $opts .= html_writer::tag('option', s($name), ['value' => $id] + ($id === $employeeid ? ['selected' => 'selected'] : []));
    }
    echo html_writer::tag('form', html_writer::tag('select', $opts, ['name' => 'employeeid', 'class' => 'form-select',
        'onchange' => 'this.form.submit()', 'aria-label' => get_string('employee', 'local_placement')]), ['method' => 'get', 'class' => 'lp-filters']);
}

$reports = $DB->get_records_select('local_placement_report', $employeeid ? 'employeeid = ?' : '1 = 1', $employeeid ? [$employeeid] : [],
    'reportdate DESC, id DESC', '*', 0, 60);
echo html_writer::tag('h4', get_string('recentreports', 'local_placement'), ['class' => 'lp-h4']);
if ($reports) {
    $t = new html_table();
    $t->attributes['class'] = 'generaltable lp-table';
    $t->head = array_merge([get_string('reportdate', 'local_placement')], $employeeid ? [] : [get_string('employee', 'local_placement')],
        [get_string('applications', 'local_placement'), get_string('worksummary', 'local_placement'), get_string('pendingwork', 'local_placement')]);
    foreach ($reports as $r) {
        $t->data[] = array_merge([ui::date((int) $r->reportdate)], $employeeid ? [] : [s(fullname(core_user::get_user($r->employeeid)))],
            [(int) $r->appcount, nl2br(s($r->summary)), nl2br(s($r->pending ?: '–'))]);
    }
    echo html_writer::table($t);
} else {
    echo html_writer::div(get_string('noreports', 'local_placement'), 'lp-muted');
}
echo $OUTPUT->footer();
