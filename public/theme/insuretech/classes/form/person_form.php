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
 * Add a student or trainer: creates the account, optionally enrols them, and emails their login details.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class person_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('select', 'type', get_string('assignas', 'theme_insuretech'), [
            people::STUDENT => get_string('student', 'theme_insuretech'),
            people::TRAINER => get_string('trainer', 'theme_insuretech'),
        ]);
        foreach (['firstname', 'lastname'] as $field) {
            $mform->addElement('text', $field, get_string($field), ['size' => 40]);
            $mform->setType($field, PARAM_NOTAGS);
            $mform->addRule($field, null, 'required', null, 'client');
        }
        $mform->addElement('text', 'email', get_string('email'), ['size' => 40]);
        $mform->setType('email', PARAM_RAW_TRIMMED);
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->addElement('text', 'username', get_string('username'), ['size' => 40]);
        $mform->setType('username', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('username', 'personusername', 'theme_insuretech');

        $groups = [get_string('none') => ['0:0' => get_string('notnow', 'theme_insuretech')]];
        foreach ($this->_customdata['courses'] as $courseid => $course) {
            $options = ["{$courseid}:0" => get_string('nobatch', 'theme_insuretech', $course['name'])];
            foreach ($course['batches'] as $groupid => $name) {
                $options["{$courseid}:{$groupid}"] = get_string('batchname', 'theme_insuretech',
                    (object) ['course' => $course['name'], 'batch' => $name]);
            }
            $groups[$course['name']] = $options;
        }
        $mform->addElement('selectgroups', 'coursebatch', get_string('coursebatch', 'theme_insuretech'), $groups);
        $mform->addElement('date_selector', 'timeend', get_string('accessend', 'theme_insuretech'), ['optional' => true]);
        $mform->hideIf('timeend', 'type', 'eq', people::TRAINER);
        $mform->hideIf('timeend', 'coursebatch', 'eq', '0:0');

        $mform->addElement('advcheckbox', 'sendwelcome', get_string('sendwelcome', 'theme_insuretech'),
            get_string('sendwelcome_desc', 'theme_insuretech'));
        $mform->setDefault('sendwelcome', 1);

        $this->add_action_buttons(true, get_string('createaccount', 'theme_insuretech'));
    }

    /**
     * Validation: valid, unused email and username.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB, $CFG;
        $errors = parent::validation($data, $files);
        if (!validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        } else if ($DB->record_exists_select('user', 'LOWER(email) = LOWER(:email) AND deleted = 0',
                ['email' => $data['email']])) {
            $errors['email'] = get_string('emailexists');
        }
        $username = \core_text::strtolower(trim($data['username']) !== '' ? trim($data['username']) : trim($data['email']));
        if ($username !== clean_param($username, PARAM_USERNAME)) {
            $errors['username'] = get_string('invalidusername');
        } else if ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $errors['username'] = get_string('usernameexists');
        }
        return $errors;
    }
}
