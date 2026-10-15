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

namespace local_itskills\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Schedule or edit a live class session.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('sessiontitle', 'local_itskills'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $batches = [0 => get_string('allbatches', 'local_itskills')] + $this->_customdata['batches'];
        $mform->addElement('select', 'groupid', get_string('batch', 'local_itskills'), $batches);

        $mform->addElement('date_time_selector', 'timestart', get_string('sessionstart', 'local_itskills'), ['step' => 5]);
        $mform->addElement('text', 'duration', get_string('sessionduration', 'local_itskills'), ['size' => 6]);
        $mform->setType('duration', PARAM_INT);
        $mform->setDefault('duration', 60);
        $mform->addRule('duration', null, 'numeric', null, 'client');

        $mform->addElement('text', 'meetingurl', get_string('meetingurl', 'local_itskills'), ['size' => 60]);
        $mform->setType('meetingurl', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('meetingurl', 'meetingurl', 'local_itskills');

        $mform->addElement('textarea', 'description', get_string('sessiondescription', 'local_itskills'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('savesession', 'local_itskills'));
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
        if (trim($data['meetingurl']) !== '' && \local_itskills\local\sessions::clean_url($data['meetingurl']) === '') {
            $errors['meetingurl'] = get_string('invalidurl', 'local_itskills');
        }
        if ((int) $data['duration'] < 5 || (int) $data['duration'] > 600) {
            $errors['duration'] = get_string('invalidduration', 'local_itskills');
        }
        return $errors;
    }
}
