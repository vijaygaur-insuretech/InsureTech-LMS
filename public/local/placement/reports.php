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
 * Reports: admins see everything; employees (when allowed) see their own work only.
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

$from = optional_param('from', date('Y-m-d', time() - 29 * DAYSECS), PARAM_TEXT);
$to = optional_param('to', date('Y-m-d'), PARAM_TEXT);
$url = new moodle_url('/local/placement/reports.php', ['from' => $from, 'to' => $to]);
ui::setup($url, get_string('tab_reports', 'local_placement'), 'reports');
perm::require('feat_reports');
$admin = perm::is_admin();

$start = strtotime($from) ?: time() - 29 * DAYSECS;
$end = (strtotime($to) ?: time()) + DAYSECS;
[$scope, $params] = apps::scope_sql();
if (!$admin) {
    $scope .= ' AND a.employeeid = :me';
    $params['me'] = $USER->id;
}
$inrange = "$scope AND CASE WHEN a.dateapplied > 0 THEN a.dateapplied ELSE a.timecreated END >= :s
             AND CASE WHEN a.dateapplied > 0 THEN a.dateapplied ELSE a.timecreated END < :e";
$rparams = $params + ['s' => $start, 'e' => $end];
$count = fn(string $extra = '', array $p = []) => (int) $DB->count_records_sql(
    "SELECT COUNT(1) FROM {local_placement_app} a WHERE $inrange $extra", $rparams + $p);
[$notin, $finalp] = $DB->get_in_or_equal(apps::FINAL, SQL_PARAMS_NAMED, 'fin', false);
$overdue = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {local_placement_app} a WHERE $scope AND a.followupdate > 0
    AND a.followupdate < :today AND a.status $notin", $params + $finalp + ['today' => usergetmidnight(time())]);

echo ui::header();
echo html_writer::tag('form', html_writer::tag('label', get_string('datefrom', 'local_placement'), ['for' => 'lp-from']) .
    html_writer::empty_tag('input', ['type' => 'date', 'name' => 'from', 'id' => 'lp-from', 'value' => $from, 'class' => 'form-control']) .
    html_writer::tag('label', get_string('dateto', 'local_placement'), ['for' => 'lp-to']) .
    html_writer::empty_tag('input', ['type' => 'date', 'name' => 'to', 'id' => 'lp-to', 'value' => $to, 'class' => 'form-control']) .
    html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-primary']), ['method' => 'get', 'class' => 'lp-filters']);

// Headline numbers.
$tiles = [
    [get_string('applicationsinrange', 'local_placement'), $count()],
    [get_string('interviews', 'local_placement'), $count('AND a.status = :st', ['st' => 'Interview'])],
    [get_string('offers', 'local_placement'), $count('AND a.status = :st', ['st' => 'Offer Received'])],
    [get_string('rejections', 'local_placement'), $count('AND a.status = :st', ['st' => 'Rejected'])],
    [get_string('followupsoverdue', 'local_placement'), $overdue],
];
if ($admin) {
    array_unshift($tiles, [get_string('activeclients', 'local_placement'), $DB->count_records('local_placement_client', ['status' => 1])],
        [get_string('activeemployees', 'local_placement'), count(clients::employees())]);
} else {
    array_unshift($tiles, [get_string('assignedclients', 'local_placement'), $DB->count_records('local_placement_client',
        ['employeeid' => $USER->id, 'status' => 1])]);
}
$html = '';
foreach ($tiles as [$label, $value]) {
    $html .= html_writer::div(html_writer::tag('strong', $value) . html_writer::span($label), 'lp-tile');
}
echo html_writer::div($html, 'lp-tiles');

// Breakdown tables.
$breakdown = function(string $title, string $sql, array $p) use ($DB) {
    $rows = $DB->get_records_sql($sql, $p);
    $t = new html_table();
    $t->attributes['class'] = 'generaltable lp-table';
    $t->head = ['', get_string('applications', 'local_placement')];
    $max = max([1] + array_map(fn($r) => (int) $r->n, $rows));
    foreach ($rows as $r) {
        $t->data[] = [s($r->label ?: '–'), html_writer::div(html_writer::span('', 'lp-bar-fill', ['style' => 'width:' . round(100 * $r->n / $max) . '%']) .
            html_writer::span($r->n, 'lp-bar-n'), 'lp-bar')];
    }
    return html_writer::div(html_writer::tag('h4', $title, ['class' => 'lp-card-title']) .
        ($rows ? html_writer::table($t) : html_writer::div(get_string('nodata', 'local_placement'), 'lp-muted')), 'lp-card');
};
$statusexpr = 'a.status';
echo html_writer::start_div('lp-cols');
echo $breakdown(get_string('bystatus', 'local_placement'),
    "SELECT a.status AS label, COUNT(1) AS n FROM {local_placement_app} a WHERE $inrange GROUP BY a.status ORDER BY n DESC", $rparams);
echo $breakdown(get_string('byindustry', 'local_placement'),
    "SELECT COALESCE(i.name, '') AS label, COUNT(1) AS n FROM {local_placement_app} a
       LEFT JOIN {local_placement_industry} i ON i.id = a.industryid WHERE $inrange GROUP BY i.name ORDER BY n DESC", $rparams);
echo $breakdown(get_string('byjobtitle', 'local_placement'),
    "SELECT a.jobtitle AS label, COUNT(1) AS n FROM {local_placement_app} a WHERE $inrange GROUP BY a.jobtitle ORDER BY n DESC", $rparams);
echo $breakdown(get_string('byplatform', 'local_placement'),
    "SELECT a.platform AS label, COUNT(1) AS n FROM {local_placement_app} a WHERE $inrange GROUP BY a.platform ORDER BY n DESC", $rparams);
if ($admin) {
    echo $breakdown(get_string('byemployee', 'local_placement'),
        "SELECT " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS label, COUNT(1) AS n FROM {local_placement_app} a
           LEFT JOIN {user} u ON u.id = a.employeeid WHERE $inrange GROUP BY u.firstname, u.lastname ORDER BY n DESC", $rparams);
    echo $breakdown(get_string('resumestatus', 'local_placement'),
        "SELECT r.status AS label, COUNT(1) AS n FROM {local_placement_resume} r
          WHERE r.id IN (SELECT MAX(r2.id) FROM {local_placement_resume} r2 WHERE r2.kind = 'resume' GROUP BY r2.clientid)
       GROUP BY r.status", []);
    echo $breakdown(get_string('perday', 'local_placement'),
        "SELECT TO_CHAR(TO_TIMESTAMP(a.timecreated), 'YYYY-MM-DD') AS label, COUNT(1) AS n FROM {local_placement_app} a
          WHERE $inrange GROUP BY TO_CHAR(TO_TIMESTAMP(a.timecreated), 'YYYY-MM-DD') ORDER BY label DESC", $rparams);
}
echo html_writer::end_div();
if ($admin) {
    echo html_writer::div(html_writer::link(new moodle_url('/local/placement/dailyreport.php'), get_string('dailyreports', 'local_placement'),
        ['class' => 'btn btn-outline-secondary']) . ' ' . html_writer::link(new moodle_url('/local/placement/audit.php'),
        get_string('tab_audit', 'local_placement'), ['class' => 'btn btn-outline-secondary']), 'lp-actions mt-3');
}
echo $OUTPUT->footer();
