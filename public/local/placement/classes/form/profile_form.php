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

use local_placement\local\clients;
use local_placement\local\fields;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Client master profile. Only the field groups the current user may see are shown (and saved).
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $client = $this->_customdata['client'];
        $mform->addElement('hidden', 'id', $client->id);
        $mform->setType('id', PARAM_INT);

        $bysection = [];
        foreach (fields::all() as $key => $def) {
            if (clients::can_see($client, $def[2])) {
                $bysection[$def[1]][$key] = $def;
            }
        }
        foreach (fields::SECTIONS as $section) {
            if (empty($bysection[$section])) {
                continue;
            }
            $mform->addElement('header', 'h_' . $section, get_string('section_' . $section, 'local_placement'));
            $mform->setExpanded('h_' . $section);
            foreach ($bysection[$section] as $key => $def) {
                $label = get_string('f_' . $key, 'local_placement');
                switch ($def[0]) {
                    case 'textarea':
                        $mform->addElement('textarea', $key, $label, ['rows' => 3, 'cols' => 60]);
                        $mform->setType($key, PARAM_TEXT);
                        break;
                    case 'url':
                        $mform->addElement('text', $key, $label, ['size' => 50]);
                        $mform->setType($key, PARAM_URL);
                        break;
                    case 'date':
                        $mform->addElement('date_selector', $key, $label, ['optional' => true]);
                        break;
                    case 'select':
                        $options = [];
                        foreach ($def[3] as $option) {
                            $options[$option] = $option === '' ? get_string('choosedots') : get_string('opt_' . $option, 'local_placement');
                        }
                        $mform->addElement('select', $key, $label, $options);
                        break;
                    default:
                        $mform->addElement('text', $key, $label, ['size' => 40]);
                        $mform->setType($key, PARAM_TEXT);
                }
                if (get_string_manager()->string_exists('f_' . $key . '_help', 'local_placement')) {
                    $mform->addHelpButton($key, 'f_' . $key, 'local_placement');
                }
            }
        }
        if ($mform->elementExists('legalname')) {
            $mform->addRule('legalname', null, 'required', null, 'client');
        }
        $this->add_action_buttons();
    }
}
