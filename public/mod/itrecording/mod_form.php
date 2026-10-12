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
 * Recording settings form. Only admins and managers can open it.
 *
 * @package    mod_itrecording
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Recording settings form.
 */
class mod_itrecording_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        global $COURSE;

        // Editing an existing recording shows its link, so trainers (who can edit other activities) are stopped here.
        if (!empty($this->_cm)) {
            require_capability('mod/itrecording:manage', context_module::instance($this->_cm->id));
        } else {
            require_capability('mod/itrecording:addinstance', context_course::instance($COURSE->id));
        }

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'videourl', get_string('videourl', 'itrecording'), ['size' => 80]);
        $mform->setType('videourl', PARAM_RAW_TRIMMED);
        $mform->addRule('videourl', null, 'required', null, 'client');
        $mform->addHelpButton('videourl', 'videourl', 'itrecording');

        $mform->addElement('text', 'duration', get_string('duration', 'itrecording'), ['size' => 12]);
        $mform->setType('duration', PARAM_TEXT);

        $this->standard_intro_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Only accept links that can be embedded.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!\mod_itrecording\video::embed($data['videourl'] ?? '')) {
            $errors['videourl'] = get_string('invalidvideourl', 'itrecording');
        }
        return $errors;
    }
}
