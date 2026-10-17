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

namespace local_itskills\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Course fields (level, outcomes, prerequisites) and batch fields (start and end dates), stored as standard custom fields.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields {
    /** @var array Course fields: shortname => [type, configdata extras]. */
    const COURSE = [
        'itlevel' => ['select', ['options' => "Beginner\nIntermediate\nAdvanced"]],
        'itdelivery' => ['select', ['options' => "Live classes\nSelf-paced", 'defaultvalue' => 'Live classes']],
        'itoutcomes' => ['textarea', []],
        'itprereq' => ['textarea', []],
    ];

    /** @var array Batch (group) fields. */
    const GROUP = [
        'itbatchstart' => ['date', ['includetime' => 0]],
        'itbatchend' => ['date', ['includetime' => 0]],
    ];

    /**
     * Create any missing fields. Safe to call repeatedly.
     */
    public static function ensure(): void {
        self::ensure_for(\core_course\customfield\course_handler::create(), self::COURSE, 'coursefields');
        self::ensure_for(\core_group\customfield\group_handler::create(), self::GROUP, 'batchfields');
    }

    /**
     * Create fields in one area.
     *
     * @param \core_customfield\handler $handler
     * @param array $fields
     * @param string $categorystring
     */
    protected static function ensure_for(\core_customfield\handler $handler, array $fields, string $categorystring): void {
        $existing = [];
        foreach ($handler->get_fields() as $field) {
            $existing[$field->get('shortname')] = true;
        }
        $missing = array_diff_key($fields, $existing);
        if (!$missing) {
            return;
        }
        // Add to our existing field group when there is one, so new fields sit with the others.
        $categoryname = get_string($categorystring, 'local_itskills');
        $category = null;
        foreach ($handler->get_categories_with_fields() as $existingcategory) {
            if ($existingcategory->get('name') === $categoryname) {
                $category = $existingcategory;
            }
        }
        $category = $category ?? \core_customfield\category_controller::create($handler->create_category($categoryname));
        $sortorder = count($category->get_fields());
        foreach ($missing as $shortname => [$type, $extra]) {
            $config = $extra + [
                'required' => 0, 'uniquevalues' => 0, 'locked' => 0,
                'visibility' => \core_course\customfield\course_handler::VISIBLETOALL,
                'defaultvalue' => '', 'defaultvalueformat' => FORMAT_HTML,
            ];
            $field = \core_customfield\field_controller::create(0, (object) ['type' => $type], $category);
            $handler->save_field_configuration($field, (object) [
                'name' => get_string('field_' . $shortname, 'local_itskills'),
                'shortname' => $shortname,
                'type' => $type,
                'description' => get_string('field_' . $shortname . '_desc', 'local_itskills'),
                'descriptionformat' => FORMAT_HTML,
                'sortorder' => $sortorder++,
                'configdata' => json_encode($config),
            ]);
        }
    }

    /**
     * Display values of a course's fields.
     *
     * @param int $courseid
     * @return array ['itlevel' => 'Intermediate', 'itoutcomes' => html, 'itprereq' => html]
     */
    public static function course(int $courseid): array {
        $out = ['itlevel' => '', 'itoutcomes' => '', 'itprereq' => ''];
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($courseid, true) as $data) {
            $shortname = $data->get_field()->get('shortname');
            if (array_key_exists($shortname, $out) && !empty($data->get_value())) {
                $out[$shortname] = trim(strip_tags($data->export_value() ?? '', '<p><br><ul><ol><li><strong><em>'));
            }
        }
        return $out;
    }

    /**
     * How a course is delivered: 'live' or 'selfpaced'.
     *
     * Uses the course's "Course type" field. Courses that never had it set count as live when they have a live-class
     * link or timing, and as self-paced otherwise.
     *
     * @param int $courseid
     * @param array $live The course's live-class link and timing (['link' => ..., 'schedule' => ...]), if known
     * @return string
     */
    public static function delivery(int $courseid, array $live = []): string {
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($courseid) as $data) {
            if ($data->get_field()->get('shortname') === 'itdelivery' && $data->get('id')) {
                return (int) $data->get_value() === 2 ? 'selfpaced' : 'live';
            }
        }
        return (!empty($live['link']) || !empty($live['schedule'])) ? 'live' : 'selfpaced';
    }

    /**
     * A batch's start and end dates (timestamps, 0 when not set).
     *
     * @param int $groupid
     * @return array ['start' => int, 'end' => int]
     */
    public static function batch(int $groupid): array {
        $out = ['start' => 0, 'end' => 0];
        foreach (\core_group\customfield\group_handler::create()->get_instance_data($groupid, true) as $data) {
            $shortname = $data->get_field()->get('shortname');
            if ($shortname === 'itbatchstart') {
                $out['start'] = (int) $data->get_value();
            } else if ($shortname === 'itbatchend') {
                $out['end'] = (int) $data->get_value();
            }
        }
        return $out;
    }

    /**
     * Save a batch's dates.
     *
     * @param int $groupid
     * @param int $start
     * @param int $end
     */
    public static function save_batch(int $groupid, int $start, int $end): void {
        \core_group\customfield\group_handler::create()->instance_form_save((object) [
            'id' => $groupid,
            'customfield_itbatchstart' => $start,
            'customfield_itbatchend' => $end,
        ]);
    }
}
