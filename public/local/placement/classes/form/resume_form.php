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
 * Upload a new resume or cover letter version (PDF, DOC or DOCX).
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resume_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'clientid', $this->_customdata['clientid']);
        $mform->setType('clientid', PARAM_INT);
        $mform->addElement('select', 'kind', get_string('doctype', 'local_placement'),
            ['resume' => get_string('kind_resume', 'local_placement'), 'coverletter' => get_string('kind_coverletter', 'local_placement')]);
        $mform->addElement('filepicker', 'file', get_string('file'), null,
            ['maxbytes' => 10 * 1024 * 1024, 'accepted_types' => \local_placement\local\resumes::TYPES]);
        $mform->addRule('file', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('upload'));
    }
}
