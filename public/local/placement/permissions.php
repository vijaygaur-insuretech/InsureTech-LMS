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
 * Permission and feature control panel (admins only): field, action and feature switches for all employees or for
 * individual employees. Changes apply immediately and are written to the audit log.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\clients;
use local_placement\local\perm;
use local_placement\local\ui;

$userid = optional_param('userid', 0, PARAM_INT);
$url = new moodle_url('/local/placement/permissions.php', $userid ? ['userid' => $userid] : []);
ui::setup($url, get_string('tab_permissions', 'local_placement'), 'permissions');
if (!perm::is_admin()) {
    throw new moodle_exception('nopermission', 'local_placement');
}
$employees = clients::employees(false);
if ($userid && !isset($employees[$userid])) {
    throw new moodle_exception('nopermission', 'local_placement');
}

if (data_submitted() && confirm_sesskey()) {
    // Apply to this scope, and to any other selected employees.
    $targets = array_unique(array_merge([$userid], array_map('intval', optional_param_array('alsoapply', [], PARAM_INT))));
    foreach (perm::KEYS as $keys) {
        foreach (array_keys($keys) as $key) {
            $value = optional_param('p_' . $key, 'inherit', PARAM_ALPHA);
            foreach ($targets as $target) {
                if ($target && !isset($employees[$target])) {
                    continue;
                }
                if ($target === 0 && $value === 'inherit') {
                    // All-employees scope: "inherit" means back to the default.
                    perm::set(0, $key, null);
                } else {
                    perm::set($target, $key, $value === 'inherit' ? null : $value === 'allow');
                }
            }
        }
    }
    redirect($url, get_string('permissionssaved', 'local_placement'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo ui::header();
// Scope switcher.
$opts = html_writer::tag('option', get_string('allemployees', 'local_placement'), ['value' => 0]);
foreach ($employees as $id => $name) {
    $opts .= html_writer::tag('option', s($name), ['value' => $id] + ($id === $userid ? ['selected' => 'selected'] : []));
}
echo html_writer::tag('form', html_writer::tag('label', get_string('permscope', 'local_placement'), ['for' => 'lp-scope', 'class' => 'me-2']) .
    html_writer::tag('select', $opts, ['name' => 'userid', 'id' => 'lp-scope', 'class' => 'form-select', 'onchange' => 'this.form.submit()']),
    ['method' => 'get', 'class' => 'lp-filters']);
echo html_writer::div(get_string($userid ? 'perm_intro_user' : 'perm_intro_all', 'local_placement',
    $userid ? s($employees[$userid]) : ''), 'alert alert-info');

$out = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$effective = $userid ? perm::effective($userid) : null;
foreach (perm::KEYS as $panel => $keys) {
    $rows = '';
    foreach ($keys as $key => $default) {
        $stored = perm::stored($userid, $key);
        if ($userid) {
            $inherited = perm::stored(0, $key) ?? (bool) $default;
            $inheritlabel = get_string('inheritfrom', 'local_placement', get_string($inherited ? 'allow' : 'deny', 'local_placement'));
        } else {
            $inheritlabel = get_string('defaultis', 'local_placement', get_string($default ? 'allow' : 'deny', 'local_placement'));
        }
        $current = $stored === null ? 'inherit' : ($stored ? 'allow' : 'deny');
        $radios = '';
        foreach (['allow' => get_string('allow', 'local_placement'), 'deny' => get_string('deny', 'local_placement'), 'inherit' => $inheritlabel] as $v => $label) {
            $radios .= html_writer::tag('label', html_writer::empty_tag('input', ['type' => 'radio', 'name' => 'p_' . $key, 'value' => $v] +
                ($v === $current ? ['checked' => 'checked'] : [])) . ' ' . $label, ['class' => 'lp-radio lp-radio-' . $v]);
        }
        $state = $effective ? html_writer::span(get_string($effective[$key] ? 'allowed' : 'denied', 'local_placement'),
            'lp-status ' . ($effective[$key] ? 'lp-st-green' : 'lp-st-red')) : '';
        $rows .= html_writer::tag('tr', html_writer::tag('td', html_writer::tag('strong', get_string('perm_' . $key, 'local_placement')) .
            html_writer::div(get_string('perm_' . $key . '_desc', 'local_placement'), 'lp-muted small')) .
            html_writer::tag('td', $radios, ['class' => 'lp-radios']) . ($userid ? html_writer::tag('td', $state) : ''));
    }
    $head = html_writer::tag('tr', html_writer::tag('th', get_string('panel_' . $panel, 'local_placement')) .
        html_writer::tag('th', get_string('setting', 'local_placement')) . ($userid ? html_writer::tag('th', get_string('result', 'local_placement')) : ''));
    $out .= html_writer::tag('h4', get_string('panel_' . $panel, 'local_placement'), ['class' => 'lp-h4']) .
        html_writer::tag('p', get_string('panel_' . $panel . '_desc', 'local_placement'), ['class' => 'lp-muted']) .
        html_writer::tag('table', html_writer::tag('thead', $head) . html_writer::tag('tbody', $rows), ['class' => 'generaltable lp-table lp-permtable']);
}
// Apply the same settings to other selected employees.
if ($employees) {
    $checks = '';
    foreach ($employees as $id => $name) {
        if ($id !== $userid) {
            $checks .= html_writer::tag('label', html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'alsoapply[]', 'value' => $id]) .
                ' ' . s($name), ['class' => 'lp-check']);
        }
    }
    if ($checks) {
        $out .= html_writer::div(html_writer::tag('strong', get_string('alsoapply', 'local_placement')) . html_writer::div($checks, 'lp-checks'), 'lp-card');
    }
}
$out .= html_writer::tag('button', get_string('savechanges'), ['class' => 'btn btn-primary btn-lg']);
echo html_writer::tag('form', $out, ['method' => 'post', 'action' => $url->out(false)]);
echo $OUTPUT->footer();
