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

namespace local_placement\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Screening question: text, type, requirement and choices.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_form extends \moodleform {
    /** @var string[] Question types. */
    const TYPES = ['shorttext', 'longtext', 'yesno', 'select', 'multiselect', 'date', 'number', 'url'];

    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('select', 'industryid', get_string('industry', 'local_placement'), $this->_customdata['industries']);
        $mform->addElement('textarea', 'question', get_string('question', 'local_placement'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('question', PARAM_TEXT);
        $mform->addRule('question', null, 'required', null, 'client');
        $types = [];
        foreach (self::TYPES as $t) {
            $types[$t] = get_string('qt_' . $t, 'local_placement');
        }
        $mform->addElement('select', 'qtype', get_string('qtype', 'local_placement'), $types);
        $mform->addElement('textarea', 'options', get_string('choices', 'local_placement'), ['rows' => 4, 'cols' => 40]);
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'choices', 'local_placement');
        foreach (['shorttext', 'longtext', 'yesno', 'date', 'number', 'url'] as $type) {
            $mform->hideIf('options', 'qtype', 'eq', $type);
        }
        $mform->addElement('select', 'requirement', get_string('requirement', 'local_placement'), [
            'required' => get_string('req_required', 'local_placement'), 'optional' => get_string('req_optional', 'local_placement'),
            'conditional' => get_string('req_conditional', 'local_placement')]);
        $mform->addElement('text', 'conditionnote', get_string('conditionnote', 'local_placement'), ['size' => 60]);
        $mform->setType('conditionnote', PARAM_TEXT);
        $mform->hideIf('conditionnote', 'requirement', 'neq', 'conditional');
        $mform->addElement('advcheckbox', 'active', get_string('visible'));
        $mform->setDefault('active', 1);
        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (in_array($data['qtype'], ['select', 'multiselect']) && count(array_filter(array_map('trim', preg_split('/\R/', $data['options'])))) < 2) {
            $errors['options'] = get_string('needchoices', 'local_placement');
        }
        return $errors;
    }
}
