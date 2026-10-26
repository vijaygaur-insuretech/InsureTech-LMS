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
 * Industries and screening questions (admins, or employees allowed to manage): add without code.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\audit;
use local_placement\local\perm;
use local_placement\local\ui;

$industryid = optional_param('industryid', 0, PARAM_INT);
$url = new moodle_url('/local/placement/industries.php', ['industryid' => $industryid]);
ui::setup($url, get_string('tab_questions', 'local_placement'), 'questions');
perm::require('act_manage');

$action = optional_param('action', '', PARAM_ALPHA);
if ($action && confirm_sesskey()) {
    $now = time();
    if ($action === 'addindustry') {
        $name = trim(required_param('name', PARAM_TEXT));
        if ($name !== '') {
            $id = $DB->insert_record('local_placement_industry', ['name' => $name, 'active' => 1, 'timemodified' => $now,
                'sortorder' => 1 + (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {local_placement_industry}')]);
            audit::log('industry_create', 'industry', $id, 0, $name);
            $url->param('industryid', $id);
        }
    } else if ($action === 'renameindustry' && $industryid) {
        $DB->update_record('local_placement_industry', ['id' => $industryid, 'name' => trim(required_param('name', PARAM_TEXT)), 'timemodified' => $now]);
        audit::log('industry_edit', 'industry', $industryid);
    } else if ($action === 'toggleindustry' && $industryid) {
        $active = (int) $DB->get_field('local_placement_industry', 'active', ['id' => $industryid]);
        $DB->set_field('local_placement_industry', 'active', $active ? 0 : 1, ['id' => $industryid]);
        audit::log($active ? 'industry_hide' : 'industry_show', 'industry', $industryid);
    } else if ($action === 'togglequestion') {
        $qid = required_param('questionid', PARAM_INT);
        $active = (int) $DB->get_field('local_placement_question', 'active', ['id' => $qid], MUST_EXIST);
        $DB->set_field('local_placement_question', 'active', $active ? 0 : 1, ['id' => $qid]);
        audit::log($active ? 'question_hide' : 'question_show', 'question', $qid);
    } else if ($action === 'movequestion') {
        // Swap with the neighbour above or below.
        $qid = required_param('questionid', PARAM_INT);
        $dir = optional_param('dir', 'up', PARAM_ALPHA);
        $list = array_values($DB->get_records('local_placement_question', ['industryid' => $industryid], 'sortorder, id'));
        foreach ($list as $i => $q) {
            $DB->set_field('local_placement_question', 'sortorder', $i, ['id' => $q->id]);
        }
        foreach ($list as $i => $q) {
            $j = $dir === 'up' ? $i - 1 : $i + 1;
            if ((int) $q->id === $qid && isset($list[$j])) {
                $DB->set_field('local_placement_question', 'sortorder', $j, ['id' => $q->id]);
                $DB->set_field('local_placement_question', 'sortorder', $i, ['id' => $list[$j]->id]);
            }
        }
    }
    redirect($url);
}

echo ui::header();
$industries = $DB->get_records('local_placement_industry', null, 'sortorder, name');
$form = fn(string $action, string $inner, array $extra = []) => html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) . $inner,
    ['method' => 'post', 'action' => $url->out(false), 'class' => 'lp-inlineform'] + $extra);

echo html_writer::start_div('lp-grid');
// Industry list.
echo html_writer::start_div('lp-side lp-side-left');
echo html_writer::start_div('lp-card');
echo html_writer::tag('h4', get_string('industries', 'local_placement'), ['class' => 'lp-card-title']);
$items = html_writer::tag('li', html_writer::link(new moodle_url('/local/placement/industries.php', ['industryid' => 0]),
    get_string('universalquestions', 'local_placement')) . ' ' . html_writer::span($DB->count_records('local_placement_question', ['industryid' => 0]), 'lp-count'),
    $industryid === 0 ? ['class' => 'active'] : []);
foreach ($industries as $ind) {
    $items .= html_writer::tag('li', html_writer::link(new moodle_url('/local/placement/industries.php', ['industryid' => $ind->id]), s($ind->name)) .
        ($ind->active ? '' : ' ' . html_writer::span(get_string('hidden', 'local_placement'), 'lp-status lp-st-grey')) . ' ' .
        html_writer::span($DB->count_records('local_placement_question', ['industryid' => $ind->id]), 'lp-count'),
        (int) $ind->id === $industryid ? ['class' => 'active'] : []);
}
echo html_writer::tag('ul', $items, ['class' => 'lp-navlist']);
echo $form('addindustry', html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'class' => 'form-control',
    'placeholder' => get_string('newindustry', 'local_placement'), 'required' => 'required', 'aria-label' => get_string('newindustry', 'local_placement')]) .
    html_writer::tag('button', get_string('add'), ['class' => 'btn btn-sm btn-primary']));
echo html_writer::end_div();
echo html_writer::end_div();

// Questions of the selected industry.
echo html_writer::start_div('lp-main');
$current = $industryid ? ($industries[$industryid] ?? null) : null;
echo html_writer::start_div('lp-card');
if ($current) {
    echo $form('renameindustry', html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'value' => $current->name,
        'class' => 'form-control', 'aria-label' => get_string('industry', 'local_placement')]) .
        html_writer::tag('button', get_string('rename', 'local_placement'), ['class' => 'btn btn-sm btn-outline-secondary'])) . ' ' .
        $form('toggleindustry', html_writer::tag('button', get_string($current->active ? 'hide' : 'show'), ['class' => 'btn btn-sm btn-link']));
} else {
    echo html_writer::tag('h4', get_string('universalquestions', 'local_placement'), ['class' => 'lp-card-title']) .
        html_writer::div(get_string('universal_desc', 'local_placement'), 'lp-muted');
}
echo html_writer::end_div();

echo html_writer::div(html_writer::link(new moodle_url('/local/placement/questionedit.php', ['industryid' => $industryid]),
    get_string('addquestion', 'local_placement'), ['class' => 'btn btn-primary']), 'lp-titlebar');
$questions = $DB->get_records('local_placement_question', ['industryid' => $industryid], 'sortorder, id');
if ($questions) {
    $t = new html_table();
    $t->attributes['class'] = 'generaltable lp-table';
    $t->head = [get_string('question', 'local_placement'), get_string('qtype', 'local_placement'), get_string('requirement', 'local_placement'), ''];
    foreach ($questions as $q) {
        $t->data[] = [
            s($q->question) . ($q->active ? '' : ' ' . html_writer::span(get_string('hidden', 'local_placement'), 'lp-status lp-st-grey')) .
                ($q->conditionnote ? html_writer::div(s($q->conditionnote), 'lp-muted small') : ''),
            get_string('qt_' . $q->qtype, 'local_placement'),
            html_writer::span(get_string('req_' . $q->requirement, 'local_placement'), 'lp-req lp-req-' . $q->requirement),
            html_writer::div(
                $form('movequestion', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $q->id]) .
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'dir', 'value' => 'up']) .
                    html_writer::tag('button', '↑', ['class' => 'btn btn-sm btn-link', 'title' => get_string('moveup')])) .
                $form('movequestion', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $q->id]) .
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'dir', 'value' => 'down']) .
                    html_writer::tag('button', '↓', ['class' => 'btn btn-sm btn-link', 'title' => get_string('movedown')])) .
                html_writer::link(new moodle_url('/local/placement/questionedit.php', ['id' => $q->id]), get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary']) .
                $form('togglequestion', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $q->id]) .
                    html_writer::tag('button', get_string($q->active ? 'hide' : 'show'), ['class' => 'btn btn-sm btn-link'])),
                'lp-actions'),
        ];
    }
    echo html_writer::table($t);
} else {
    echo html_writer::div(get_string('noquestions', 'local_placement'), 'alert alert-info');
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
