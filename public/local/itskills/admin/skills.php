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
 * Define the skills shown in the Skill Passport and map them to courses.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_itskills_skills');

$url = new moodle_url('/local/itskills/admin/skills.php');
$action = optional_param('action', '', PARAM_ALPHA);
if ($action) {
    require_sesskey();
    if ($action === 'save') {
        $id = optional_param('id', 0, PARAM_INT);
        $name = trim(required_param('name', PARAM_TEXT));
        $courseids = optional_param_array('courses', [], PARAM_INT);
        if ($name !== '') {
            $record = (object) ['name' => $name, 'description' => optional_param('description', '', PARAM_TEXT),
                'courseids' => implode(',', $courseids), 'sortorder' => optional_param('sortorder', 0, PARAM_INT),
                'timemodified' => time()];
            if ($id) {
                $record->id = $id;
                $DB->update_record('local_itskills_skill', $record);
            } else {
                $DB->insert_record('local_itskills_skill', $record);
            }
        }
    } else if ($action === 'delete') {
        $DB->delete_records('local_itskills_skill', ['id' => required_param('id', PARAM_INT)]);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$courses = [];
foreach (get_courses('all', 'c.fullname ASC', 'c.id, c.fullname') as $c) {
    if ($c->id != SITEID) {
        $courses[$c->id] = format_string($c->fullname, true, ['escape' => false]);
    }
}
$skills = [];
foreach ($DB->get_records('local_itskills_skill', null, 'sortorder, name') as $skill) {
    $selected = array_filter(array_map('intval', explode(',', (string) $skill->courseids)));
    $skills[] = [
        'id' => $skill->id, 'name' => $skill->name, 'description' => $skill->description, 'sortorder' => $skill->sortorder,
        'coursenames' => implode(', ', array_intersect_key($courses, array_flip($selected))),
        'courses' => array_map(fn($id, $name) => ['id' => $id, 'name' => $name, 'selected' => in_array($id, $selected)],
            array_keys($courses), $courses),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/admin_skills', [
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
    'skills' => $skills,
    'hasskills' => !empty($skills),
    'courses' => array_map(fn($id, $name) => ['id' => $id, 'name' => $name], array_keys($courses), $courses),
]);
echo $OUTPUT->footer();
