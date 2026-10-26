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
 * Add a client or employee: a new account, or an existing LMS account found by email.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class person_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $type = $this->_customdata['type'];
        $mform->addElement('hidden', 'type', $type);
        $mform->setType('type', PARAM_ALPHA);
        $mform->addElement('static', 'intro', '', get_string('addperson_intro', 'local_placement'));
        $mform->addElement('text', 'email', get_string('email'), ['size' => 40]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->addElement('text', 'firstname', get_string('firstname'));
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addElement('text', 'lastname', get_string('lastname'));
        $mform->setType('lastname', PARAM_NOTAGS);
        if ($type === 'client') {
            $mform->addElement('select', 'employeeid', get_string('assignedemployee', 'local_placement'),
                [0 => get_string('notassigned', 'local_placement')] + $this->_customdata['employees']);
        }
        $mform->addElement('advcheckbox', 'sendwelcome', get_string('sendwelcome', 'local_placement'),
            get_string('sendwelcome_desc', 'local_placement'));
        $mform->setDefault('sendwelcome', 1);
        $this->add_action_buttons(true, get_string('add' . $type, 'local_placement'));
    }

    #[\Override]
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        $user = $DB->get_record('user', ['email' => trim($data['email']), 'deleted' => 0], 'id', IGNORE_MULTIPLE);
        if (!$user) {
            foreach (['firstname', 'lastname'] as $field) {
                if (trim($data[$field]) === '') {
                    $errors[$field] = get_string('required');
                }
            }
            if ($DB->record_exists('user', ['username' => \core_text::strtolower(trim($data['email'])), 'deleted' => 0])) {
                $errors['email'] = get_string('usernameexists');
            }
        } else if ($data['type'] === 'client' && $DB->record_exists('local_placement_client', ['userid' => $user->id])) {
            $errors['email'] = get_string('alreadyclient', 'local_placement');
        }
        return $errors;
    }
}
