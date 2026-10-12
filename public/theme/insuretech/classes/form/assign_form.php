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

namespace theme_insuretech\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use theme_insuretech\local\people;

/**
 * Assign a person to a course and batch, as a student (with access dates) or as a trainer.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $courses = $this->_customdata['courses'];

        $mform->addElement('hidden', 'id', $this->_customdata['userid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'assign');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('select', 'type', get_string('assignas', 'theme_insuretech'), [
            people::STUDENT => get_string('student', 'theme_insuretech'),
            people::TRAINER => get_string('trainer', 'theme_insuretech'),
        ]);
        $mform->setDefault('type', $this->_customdata['type']);

        // One picker for course + batch: each course is a group of options, its batches listed under it.
        $groups = [];
        foreach ($courses as $courseid => $course) {
            $options = ["{$courseid}:0" => get_string('nobatch', 'theme_insuretech', $course['name'])];
            foreach ($course['batches'] as $groupid => $name) {
                $options["{$courseid}:{$groupid}"] = get_string('batchname', 'theme_insuretech',
                    (object) ['course' => $course['name'], 'batch' => $name]);
            }
            $groups[$course['name']] = $options;
        }
        $mform->addElement('selectgroups', 'coursebatch', get_string('coursebatch', 'theme_insuretech'), $groups);
        $mform->addRule('coursebatch', null, 'required', null, 'client');

        $mform->addElement('date_selector', 'timestart', get_string('accessstart', 'theme_insuretech'), ['optional' => true]);
        $mform->setDefault('timestart', time());
        $mform->addElement('date_selector', 'timeend', get_string('accessend', 'theme_insuretech'), ['optional' => true]);
        $mform->addHelpButton('timeend', 'accessend', 'theme_insuretech');
        $mform->hideIf('timestart', 'type', 'eq', people::TRAINER);
        $mform->hideIf('timeend', 'type', 'eq', people::TRAINER);

        $this->add_action_buttons(false, get_string('assign', 'theme_insuretech'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!preg_match('/^\d+:\d+$/', $data['coursebatch'] ?? '')) {
            $errors['coursebatch'] = get_string('required');
        }
        if ($data['type'] === people::STUDENT && !empty($data['timestart']) && !empty($data['timeend'])
                && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('accessendbeforestart', 'theme_insuretech');
        }
        return $errors;
    }
}
