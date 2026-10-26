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
 * Placement portal home: a different dashboard for admins, employees and clients.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\apps;
use local_placement\local\clients;
use local_placement\local\fields;
use local_placement\local\perm;
use local_placement\local\resumes;
use local_placement\local\ui;

$url = new moodle_url('/local/placement/index.php');
ui::setup($url, get_string('pluginname', 'local_placement'), 'home');

$today = usergetmidnight(time());
$tiles = function(array $items): string {
    $html = '';
    foreach ($items as [$label, $value, $link, $warn]) {
        $inner = html_writer::tag('strong', $value) . html_writer::span($label);
        $html .= $link ? html_writer::link($link, $inner, ['class' => 'lp-tile' . ($warn && $value ? ' lp-tile-warn' : '')])
            : html_writer::div($inner, 'lp-tile');
    }
    return html_writer::div($html, 'lp-tiles');
};
$applist = function(array $rows, bool $showclient) use ($DB): string {
    if (!$rows) {
        return html_writer::div(get_string('nothinghere', 'local_placement'), 'lp-muted');
    }
    $items = '';
    foreach ($rows as $a) {
        $who = '';
        if ($showclient) {
            $u = $DB->get_record_sql('SELECT u.* FROM {local_placement_client} c JOIN {user} u ON u.id = c.userid WHERE c.id = ?', [$a->clientid]);
            $who = $u ? s(fullname($u)) . ' · ' : '';
        }
        $items .= html_writer::tag('li', html_writer::link(new moodle_url('/local/placement/application.php', ['id' => $a->id]),
            $who . html_writer::tag('strong', s($a->company)) . ' · ' . s($a->jobtitle)) . ' ' . ui::status($a->status) .
            ($a->followupdate ? html_writer::span(get_string('followupon', 'local_placement', ui::date((int) $a->followupdate)),
                'lp-muted small' . ($a->followupdate < usergetmidnight(time()) ? ' lp-overdue' : '')) : ''));
    }
    return html_writer::tag('ul', $items, ['class' => 'lp-list']);
};
[$notin, $finalp] = $DB->get_in_or_equal(apps::FINAL, SQL_PARAMS_NAMED, 'fin', false);
$card = fn(string $title, string $body, ?moodle_url $more = null) => html_writer::div(
    html_writer::div(html_writer::tag('h4', $title, ['class' => 'lp-card-title']) .
        ($more ? html_writer::link($more, get_string('viewall', 'local_placement')) : ''), 'lp-card-head') . $body, 'lp-card');

echo ui::header();

if (perm::is_admin()) {
    $due = $DB->count_records_select('local_placement_app', "followupdate > 0 AND followupdate < :u AND status $notin",
        ['u' => $today + DAYSECS] + $finalp);
    $pendingresumes = $DB->get_records_sql("SELECT r.* FROM {local_placement_resume} r WHERE r.status = 'pending' ORDER BY r.timecreated", [], 0, 8);
    echo $tiles([
        [get_string('activeclients', 'local_placement'), $DB->count_records('local_placement_client', ['status' => 1]), new moodle_url('/local/placement/clients.php'), false],
        [get_string('activeemployees', 'local_placement'), count(clients::employees()), new moodle_url('/local/placement/employees.php'), false],
        [get_string('appstoday', 'local_placement'), $DB->count_records_select('local_placement_app', 'timecreated >= ?', [$today]), new moodle_url('/local/placement/applications.php'), false],
        [get_string('apps7days', 'local_placement'), $DB->count_records_select('local_placement_app', 'timecreated >= ?', [$today - 6 * DAYSECS]), new moodle_url('/local/placement/reports.php'), false],
        [get_string('followupsdue', 'local_placement'), $due, new moodle_url('/local/placement/applications.php', ['due' => 1]), true],
        [get_string('resumestoreview', 'local_placement'), $DB->count_records('local_placement_resume', ['status' => 'pending']), null, true],
        [get_string('unassignedclients', 'local_placement'), $DB->count_records('local_placement_client', ['status' => 1, 'employeeid' => 0]),
            new moodle_url('/local/placement/clients.php', ['employeeid' => 0]), true],
    ]);
    echo html_writer::start_div('lp-cols');
    $items = '';
    foreach ($pendingresumes as $r) {
        $c = clients::get((int) $r->clientid);
        $items .= html_writer::tag('li', html_writer::link(new moodle_url('/local/placement/resume.php', ['clientid' => $c->id]),
            s(fullname($c->user)) . ' · ' . s($r->filename)) . ' ' . html_writer::span(ui::date((int) $r->timecreated, true), 'lp-muted small'));
    }
    echo $card(get_string('resumestoreview', 'local_placement'), $items ? html_writer::tag('ul', $items, ['class' => 'lp-list'])
        : html_writer::div(get_string('nothinghere', 'local_placement'), 'lp-muted'));
    echo $card(get_string('followupsdue', 'local_placement'), $applist($DB->get_records_select('local_placement_app',
        "followupdate > 0 AND followupdate < :u AND status $notin", ['u' => $today + DAYSECS] + $finalp, 'followupdate', '*', 0, 8), true),
        new moodle_url('/local/placement/applications.php', ['due' => 1]));
    echo $card(get_string('recentapplications', 'local_placement'), $applist($DB->get_records('local_placement_app', null, 'timecreated DESC', '*', 0, 8), true),
        new moodle_url('/local/placement/applications.php'));
    echo html_writer::end_div();
} else if (perm::is_employee()) {
    $mine = clients::visible_ids();
    $in = $mine ? 'clientid IN (' . implode(',', $mine) . ')' : '1 = 0';
    if (perm::can('feat_dash_stats')) {
        echo $tiles([
            [get_string('assignedclients', 'local_placement'), count($mine), new moodle_url('/local/placement/clients.php'), false],
            [get_string('appstoday', 'local_placement'), $DB->count_records_select('local_placement_app', 'employeeid = ? AND timecreated >= ?', [$USER->id, $today]), null, false],
            [get_string('followupsdue', 'local_placement'), $DB->count_records_select('local_placement_app', "$in AND followupdate > 0 AND followupdate < :u AND status $notin",
                ['u' => $today + DAYSECS] + $finalp), new moodle_url('/local/placement/applications.php', ['due' => 1]), true],
            [get_string('pendingapps', 'local_placement'), $DB->count_records_select('local_placement_app', "$in AND status IN ('Pending', 'Ready to Apply')"),
                new moodle_url('/local/placement/applications.php', ['status' => 'Pending']), false],
        ]);
    }
    if (perm::can('feat_daily_reports') && !$DB->record_exists('local_placement_report', ['employeeid' => $USER->id, 'reportdate' => $today])) {
        echo html_writer::div(get_string('reportreminder', 'local_placement') . ' ' .
            html_writer::link(new moodle_url('/local/placement/dailyreport.php'), get_string('writereport', 'local_placement'), ['class' => 'alert-link']),
            'alert alert-warning');
    }
    echo html_writer::start_div('lp-cols');
    if (perm::can('feat_followups')) {
        echo $card(get_string('followupsdue', 'local_placement'), $applist($DB->get_records_select('local_placement_app',
            "$in AND followupdate > 0 AND followupdate < :u AND status $notin", ['u' => $today + DAYSECS] + $finalp, 'followupdate', '*', 0, 8), true),
            new moodle_url('/local/placement/applications.php', ['due' => 1]));
    }
    $items = '';
    foreach ($mine as $cid) {
        $c = clients::get($cid);
        if ($c->status) {
            $items .= html_writer::tag('li', html_writer::link(new moodle_url('/local/placement/client.php', ['id' => $cid]), s(fullname($c->user))) . ' ' .
                html_writer::span(s($c->data['preferredtitles'] ?? ''), 'lp-muted small'));
        }
    }
    echo $card(get_string('myclients', 'local_placement'), $items ? html_writer::tag('ul', $items, ['class' => 'lp-list'])
        : html_writer::div(get_string('noclientsassigned', 'local_placement'), 'lp-muted'), new moodle_url('/local/placement/clients.php'));
    echo $card(get_string('recentapplications', 'local_placement'), $applist($DB->get_records_select('local_placement_app', $in, [], 'timecreated DESC', '*', 0, 8), true),
        new moodle_url('/local/placement/applications.php'));
    echo html_writer::end_div();
} else {
    $client = clients::for_user((int) $USER->id);
    if (!$client) {
        echo html_writer::div(get_string('notaclient', 'local_placement'), 'alert alert-info');
        echo $OUTPUT->footer();
        exit;
    }
    // Profile completeness: share of profile fields filled in.
    $filled = count(array_filter(array_intersect_key($client->data, fields::all()), fn($v) => $v !== '' && $v !== null && $v !== 0));
    $percent = (int) round(100 * $filled / count(fields::all()));
    $latest = resumes::latest((int) $client->id);
    $counts = $DB->get_records_sql_menu('SELECT status, COUNT(1) FROM {local_placement_app} WHERE clientid = ? GROUP BY status', [$client->id]);
    echo html_writer::div(html_writer::tag('h3', get_string('welcomeclient', 'local_placement', s($USER->firstname)), ['class' => 'lp-h3']) .
        html_writer::tag('p', get_string('welcomeclient_desc', 'local_placement'), ['class' => 'lp-muted']), 'lp-hello');
    echo $tiles([
        [get_string('profilecomplete', 'local_placement'), $percent . '%', new moodle_url('/local/placement/profile.php'), false],
        [get_string('applications', 'local_placement'), array_sum($counts), new moodle_url('/local/placement/applications.php'), false],
        [get_string('interviews', 'local_placement'), $counts['Interview'] ?? 0, null, false],
        [get_string('offers', 'local_placement'), $counts['Offer Received'] ?? 0, null, false],
    ]);
    echo html_writer::start_div('lp-cols');
    $resumebody = $latest ? html_writer::div(s($latest->filename) . ' · v' . $latest->version . ' ' . ui::status($latest->status), 'mb-2') .
        ($latest->reviewnote ? html_writer::div(s($latest->reviewnote), 'alert alert-warning py-2') : '')
        : html_writer::div(get_string('noresumeyet', 'local_placement'), 'lp-muted mb-2');
    $resumebody .= html_writer::link(new moodle_url('/local/placement/resume.php'), get_string($latest ? 'uploadnew' : 'uploadresume', 'local_placement'),
        ['class' => 'btn btn-primary btn-sm']);
    echo $card(get_string('resume', 'local_placement'), $resumebody);
    echo $card(get_string('tab_myapplications', 'local_placement'), $applist($DB->get_records('local_placement_app', ['clientid' => $client->id],
        'timemodified DESC', '*', 0, 8), false), new moodle_url('/local/placement/applications.php'));
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
