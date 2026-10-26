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
 * Add or edit a screening question.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\audit;
use local_placement\local\perm;
use local_placement\local\ui;

$id = optional_param('id', 0, PARAM_INT);
$industryid = optional_param('industryid', 0, PARAM_INT);
$url = new moodle_url('/local/placement/questionedit.php', $id ? ['id' => $id] : ['industryid' => $industryid]);
ui::setup($url, get_string($id ? 'editquestion' : 'addquestion', 'local_placement'), 'questions');
perm::require('act_manage');

$question = $id ? $DB->get_record('local_placement_question', ['id' => $id], '*', MUST_EXIST) : null;
$industries = [0 => get_string('universalquestions', 'local_placement')] +
    $DB->get_records_menu('local_placement_industry', null, 'sortorder, name', 'id, name');
$form = new \local_placement\form\question_form($url, ['industries' => $industries]);
$form->set_data($question ?: ['industryid' => $industryid, 'active' => 1, 'requirement' => 'optional']);
$back = new moodle_url('/local/placement/industries.php', ['industryid' => $question->industryid ?? $industryid]);

if ($form->is_cancelled()) {
    redirect($back);
} else if ($data = $form->get_data()) {
    $record = ['industryid' => (int) $data->industryid, 'question' => trim($data->question), 'qtype' => $data->qtype,
        'options' => trim($data->options ?? ''), 'requirement' => $data->requirement, 'conditionnote' => trim($data->conditionnote ?? ''),
        'active' => (int) $data->active, 'timemodified' => time()];
    if ($question) {
        $record['id'] = $question->id;
        $DB->update_record('local_placement_question', $record);
        audit::log('question_edit', 'question', $question->id);
    } else {
        $record['sortorder'] = 1 + (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {local_placement_question} WHERE industryid = ?',
            [$record['industryid']]);
        $newid = $DB->insert_record('local_placement_question', $record);
        audit::log('question_create', 'question', $newid);
    }
    redirect(new moodle_url('/local/placement/industries.php', ['industryid' => $record['industryid']]),
        get_string('questionsaved', 'local_placement'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo ui::header();
$form->display();
echo $OUTPUT->footer();
