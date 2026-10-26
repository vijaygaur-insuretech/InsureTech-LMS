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
 * Job applications: search, filter, follow-ups due, and CSV export (when permitted).
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
use local_placement\local\ui;

$f = [
    'q' => optional_param('q', '', PARAM_TEXT),
    'clientid' => optional_param('clientid', 0, PARAM_INT),
    'employeeid' => optional_param('employeeid', 0, PARAM_INT),
    'industryid' => optional_param('industryid', 0, PARAM_INT),
    'platform' => optional_param('platform', '', PARAM_TEXT),
    'status' => optional_param('status', '', PARAM_TEXT),
    'from' => optional_param('from', '', PARAM_TEXT),
    'to' => optional_param('to', '', PARAM_TEXT),
    'due' => optional_param('due', 0, PARAM_BOOL),
];
$export = optional_param('export', 0, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_INT);
$url = new moodle_url('/local/placement/applications.php', array_filter($f));
$isclient = perm::is_client();
ui::setup($url, get_string($isclient ? 'tab_myapplications' : 'tab_applications', 'local_placement'),
    $isclient ? 'myapplications' : 'applications');

[$scope, $params] = apps::scope_sql();
$where = [$scope];
if ($f['q'] !== '') {
    $where[] = '(' . $DB->sql_like('a.company', ':q1', false) . ' OR ' . $DB->sql_like('a.jobtitle', ':q2', false) . ')';
    $params['q1'] = $params['q2'] = '%' . $DB->sql_like_escape($f['q']) . '%';
}
foreach (['clientid', 'employeeid', 'industryid'] as $key) {
    if ($f[$key]) {
        $where[] = "a.$key = :$key";
        $params[$key] = $f[$key];
    }
}
foreach (['platform', 'status'] as $key) {
    if ($f[$key] !== '') {
        $where[] = "a.$key = :$key";
        $params[$key] = $f[$key];
    }
}
if ($f['from'] !== '' && ($from = strtotime($f['from']))) {
    $where[] = 'a.dateapplied >= :from';
    $params['from'] = $from;
}
if ($f['to'] !== '' && ($to = strtotime($f['to']))) {
    $where[] = 'a.dateapplied < :to';
    $params['to'] = $to + DAYSECS;
}
if ($f['due']) {
    [$notin, $p] = $DB->get_in_or_equal(apps::FINAL, SQL_PARAMS_NAMED, 'fin', false);
    $where[] = "a.followupdate > 0 AND a.followupdate < :until AND a.status $notin";
    $params['until'] = usergetmidnight(time()) + DAYSECS;
    $params += $p;
}
$sqlwhere = implode(' AND ', $where);
$select = "SELECT a.*, i.name AS industry, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename,
                  u.alternatename, u.email
             FROM {local_placement_app} a
             JOIN {local_placement_client} c ON c.id = a.clientid
             JOIN {user} u ON u.id = c.userid
        LEFT JOIN {local_placement_industry} i ON i.id = a.industryid
            WHERE $sqlwhere
         ORDER BY CASE WHEN a.dateapplied > 0 THEN a.dateapplied ELSE a.timecreated END DESC, a.id DESC";

// CSV export: only with the export action and feature; hidden fields are left out.
if ($export) {
    require_sesskey();
    perm::require(['act_export', 'feat_export']);
    $rows = $DB->get_records_sql($select, $params);
    audit::log('applications_export', 'export', 0, 0, count($rows) . ' rows');
    $columns = ['dateapplied', 'client', 'company', 'jobtitle', 'industry', 'platform', 'status', 'followupdate', 'employee',
        'jdurl', 'applyurl', 'reference'];
    if (perm::field('email')) {
        array_splice($columns, 2, 0, ['email']);
    }
    if (perm::field('salary')) {
        $columns[] = 'salaryresponse';
    }
    $filename = 'applications-' . date('Y-m-d');
    $writer = new csv_export_writer();
    $writer->set_filename($filename);
    $writer->add_data(array_map(fn($c) => get_string($c === 'client' ? 'client' : ($c === 'employee' ? 'employee' : $c),
        in_array($c, ['email']) ? 'moodle' : 'local_placement'), $columns));
    foreach ($rows as $r) {
        $line = [];
        foreach ($columns as $c) {
            $line[] = match ($c) {
                'dateapplied' => $r->dateapplied ? date('Y-m-d', (int) $r->dateapplied) : '',
                'followupdate' => $r->followupdate ? date('Y-m-d', (int) $r->followupdate) : '',
                'client' => fullname($r),
                'employee' => $r->employeeid ? fullname(core_user::get_user($r->employeeid)) : '',
                default => (string) ($r->$c ?? ''),
            };
        }
        $writer->add_data($line);
    }
    $writer->download_file();
    exit;
}

$total = $DB->count_records_sql("SELECT COUNT(1) FROM {local_placement_app} a JOIN {local_placement_client} c ON c.id = a.clientid
                                  JOIN {user} u ON u.id = c.userid WHERE $sqlwhere", $params);
$perpage = 50;
$rows = $DB->get_records_sql($select, $params, $page * $perpage, $perpage);

echo ui::header();
if (!$isclient) {
    // Filters.
    $opts = function(array $items, $selected, string $all) {
        $html = html_writer::tag('option', $all, ['value' => '']);
        foreach ($items as $value => $label) {
            $html .= html_writer::tag('option', s($label), ['value' => $value] + ((string) $value === (string) $selected ? ['selected' => 'selected'] : []));
        }
        return $html;
    };
    $industries = $DB->get_records_menu('local_placement_industry', null, 'sortorder, name', 'id, name');
    $statuses = array_combine(ui::lines('statuses'), ui::lines('statuses'));
    $platforms = array_combine(ui::lines('platforms'), ui::lines('platforms'));
    $controls = html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $f['q'], 'class' => 'form-control',
            'placeholder' => get_string('searchapps', 'local_placement')]) .
        html_writer::tag('select', $opts($statuses, $f['status'], get_string('allstatuses', 'local_placement')), ['name' => 'status', 'class' => 'form-select']) .
        html_writer::tag('select', $opts($industries, $f['industryid'] ?: '', get_string('allindustries', 'local_placement')), ['name' => 'industryid', 'class' => 'form-select']) .
        html_writer::tag('select', $opts($platforms, $f['platform'], get_string('allplatforms', 'local_placement')), ['name' => 'platform', 'class' => 'form-select']);
    if (perm::is_admin()) {
        $controls .= html_writer::tag('select', $opts(clients::employees(false), $f['employeeid'] ?: '', get_string('allemployees', 'local_placement')),
            ['name' => 'employeeid', 'class' => 'form-select']);
    }
    $controls .= html_writer::empty_tag('input', ['type' => 'date', 'name' => 'from', 'value' => $f['from'], 'class' => 'form-control',
            'title' => get_string('datefrom', 'local_placement')]) .
        html_writer::empty_tag('input', ['type' => 'date', 'name' => 'to', 'value' => $f['to'], 'class' => 'form-control', 'title' => get_string('dateto', 'local_placement')]) .
        html_writer::tag('label', html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'due', 'value' => 1] +
            ($f['due'] ? ['checked' => 'checked'] : [])) . ' ' . get_string('followupsdue', 'local_placement'), ['class' => 'lp-check']) .
        ($f['clientid'] ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'clientid', 'value' => $f['clientid']]) : '') .
        html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-primary']);
    echo html_writer::tag('form', $controls, ['method' => 'get', 'class' => 'lp-filters']);

    $buttons = [];
    if (perm::can(['act_add', 'feat_app_create'])) {
        $buttons[] = html_writer::link(new moodle_url('/local/placement/appedit.php', $f['clientid'] ? ['clientid' => $f['clientid']] : []),
            get_string('newapplication', 'local_placement'), ['class' => 'btn btn-primary']);
    }
    if (perm::can(['act_export', 'feat_export'])) {
        $buttons[] = html_writer::link(new moodle_url($url, ['export' => 1, 'sesskey' => sesskey()]), get_string('exportcsv', 'local_placement'),
            ['class' => 'btn btn-outline-secondary']);
    }
    echo html_writer::div(html_writer::span(get_string('resultcount', 'local_placement', $total), 'lp-muted') .
        html_writer::div(implode(' ', $buttons), 'lp-actions'), 'lp-titlebar');
}

if ($rows) {
    $t = new html_table();
    $t->attributes['class'] = 'generaltable lp-table';
    $t->head = [get_string('dateapplied', 'local_placement')];
    if (!$isclient) {
        $t->head[] = get_string('client', 'local_placement');
    }
    array_push($t->head, get_string('company', 'local_placement') . ' / ' . get_string('jobtitle', 'local_placement'),
        get_string('industry', 'local_placement'), get_string('platform', 'local_placement'), get_string('status'),
        get_string('followupdate', 'local_placement'));
    $today = usergetmidnight(time());
    foreach ($rows as $r) {
        $row = [ui::date((int) ($r->dateapplied ?: $r->timecreated))];
        if (!$isclient) {
            $row[] = html_writer::link(new moodle_url('/local/placement/client.php', ['id' => $r->clientid]), s(fullname($r)));
        }
        $overdue = $r->followupdate && $r->followupdate < $today + DAYSECS && !in_array($r->status, apps::FINAL);
        array_push($row,
            html_writer::link(new moodle_url('/local/placement/application.php', ['id' => $r->id]), html_writer::tag('strong', s($r->company))) .
                html_writer::div(s($r->jobtitle), 'lp-muted small'),
            s($r->industry ?? get_string('general', 'local_placement')), s($r->platform ?: '–'), ui::status($r->status),
            html_writer::span(ui::date((int) $r->followupdate), $overdue ? 'lp-overdue' : ''));
        $t->data[] = $row;
    }
    echo html_writer::table($t);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
} else {
    echo html_writer::div(get_string('noapplications', 'local_placement'), 'alert alert-info');
}
echo $OUTPUT->footer();
