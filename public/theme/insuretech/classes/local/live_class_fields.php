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

namespace theme_insuretech\local;

use core_customfield\category_controller;
use core_customfield\field_controller;
use core_customfield\handler;

/**
 * Live class meeting links, stored in standard Moodle custom fields.
 *
 * Course fields hold the default link and timing for a class; the same fields on a group (batch) override them for
 * the learners in that batch. Trainers fill them in under Course settings (or Groups > Edit group settings), and the
 * theme turns them into "Join live class" buttons. The meeting itself (Google Meet, Zoom, Teams...) stays external.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class live_class_fields {
    /** @var string Short name of the meeting link field. */
    const LINK = 'itmeetinglink';

    /** @var string Short name of the class timing field. */
    const SCHEDULE = 'itclassschedule';

    /**
     * Create the course and group custom fields if they do not exist yet. Safe to call repeatedly.
     */
    public static function ensure(): void {
        self::ensure_for(\core_course\customfield\course_handler::create());
        self::ensure_for(\core_group\customfield\group_handler::create());
    }

    /**
     * Create the fields for one custom field area.
     *
     * @param handler $handler
     */
    protected static function ensure_for(handler $handler): void {
        $existing = [];
        foreach ($handler->get_fields() as $field) {
            $existing[$field->get('shortname')] = true;
        }
        if (isset($existing[self::LINK]) && isset($existing[self::SCHEDULE])) {
            return;
        }

        $categoryid = $handler->create_category(get_string('liveclasscategory', 'theme_insuretech'));
        $category = category_controller::create($categoryid);

        $fields = [
            self::LINK => [
                'name' => get_string('liveclasslink', 'theme_insuretech'),
                'description' => get_string('liveclasslink_desc', 'theme_insuretech'),
                'displaysize' => 60,
                'maxlength' => 1333,
            ],
            self::SCHEDULE => [
                'name' => get_string('liveclassschedule', 'theme_insuretech'),
                'description' => get_string('liveclassschedule_desc', 'theme_insuretech'),
                'displaysize' => 40,
                'maxlength' => 255,
            ],
        ];
        $sortorder = 0;
        foreach ($fields as $shortname => $def) {
            if (isset($existing[$shortname])) {
                continue;
            }
            $record = (object) [
                'name' => $def['name'],
                'shortname' => $shortname,
                'type' => 'text',
                'description' => $def['description'],
                'descriptionformat' => FORMAT_HTML,
                'sortorder' => $sortorder++,
                'configdata' => json_encode([
                    'required' => 0,
                    'uniquevalues' => 0,
                    'locked' => 0,
                    // Only staff see the raw value in course listings; learners get the Join button instead.
                    'visibility' => \core_course\customfield\course_handler::VISIBLETOTEACHERS,
                    'defaultvalue' => '',
                    'displaysize' => $def['displaysize'],
                    'maxlength' => $def['maxlength'],
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ]),
            ];
            $field = field_controller::create(0, (object) ['type' => 'text'], $category);
            $handler->save_field_configuration($field, $record);
        }
    }

    /**
     * Read the two fields for one course or group.
     *
     * @param handler $handler
     * @param int $instanceid Course or group id.
     * @return array [link, schedule]
     */
    protected static function read(handler $handler, int $instanceid): array {
        $link = '';
        $schedule = '';
        foreach ($handler->get_instance_data($instanceid, true) as $data) {
            $shortname = $data->get_field()->get('shortname');
            if ($shortname === self::LINK) {
                $link = trim((string) $data->get_value());
            } else if ($shortname === self::SCHEDULE) {
                $schedule = trim((string) $data->get_value());
            }
        }
        return [$link, $schedule];
    }

    /**
     * Read the link and timing stored on a course.
     *
     * @param int $courseid
     * @return array ['link' => string, 'schedule' => string]
     */
    public static function get_course(int $courseid): array {
        [$link, $schedule] = self::read(\core_course\customfield\course_handler::create(), $courseid);
        return ['link' => $link, 'schedule' => $schedule];
    }

    /**
     * Read the link and timing stored on a batch (group).
     *
     * @param int $groupid
     * @return array ['link' => string, 'schedule' => string]
     */
    public static function get_group(int $groupid): array {
        [$link, $schedule] = self::read(\core_group\customfield\group_handler::create(), $groupid);
        return ['link' => $link, 'schedule' => $schedule];
    }

    /**
     * Save the link and timing on a course. Moodle's own edit permissions for the field apply.
     *
     * @param int $courseid
     * @param string $link Empty to clear.
     * @param string $schedule
     * @return bool False when the link is not a valid http(s) address.
     */
    public static function save_course(int $courseid, string $link, string $schedule): bool {
        return self::save(\core_course\customfield\course_handler::create(), $courseid, $link, $schedule);
    }

    /**
     * Save the link and timing on a batch (group).
     *
     * @param int $groupid
     * @param string $link Empty to clear.
     * @param string $schedule
     * @return bool False when the link is not a valid http(s) address.
     */
    public static function save_group(int $groupid, string $link, string $schedule): bool {
        return self::save(\core_group\customfield\group_handler::create(), $groupid, $link, $schedule);
    }

    /**
     * Write both fields for one course or group.
     *
     * @param handler $handler
     * @param int $instanceid
     * @param string $link
     * @param string $schedule
     * @return bool
     */
    protected static function save(handler $handler, int $instanceid, string $link, string $schedule): bool {
        $link = trim($link);
        if ($link !== '' && self::clean_url($link) === '') {
            return false;
        }
        $handler->instance_form_save((object) [
            'id' => $instanceid,
            'customfield_' . self::LINK => $link === '' ? '' : self::clean_url($link),
            'customfield_' . self::SCHEDULE => clean_param(trim($schedule), PARAM_TEXT),
        ]);
        return true;
    }

    /**
     * Only allow absolute http(s) links, so a field value can never become a javascript: or relative URL.
     *
     * @param string $url
     * @return string The cleaned URL, or '' when not acceptable.
     */
    protected static function clean_url(string $url): string {
        $url = clean_param($url, PARAM_URL);
        return preg_match('#^https?://[^\s/]+#i', $url) ? $url : '';
    }

    /**
     * The live class for a user in a course: their batch's link when it has one, otherwise the course's.
     *
     * @param int $courseid
     * @param int $userid
     * @return array|null ['url', 'schedule', 'batch']
     */
    public static function for_user(int $courseid, int $userid): ?array {
        $grouphandler = \core_group\customfield\group_handler::create();
        foreach (groups_get_all_groups($courseid, $userid) as $group) {
            [$link, $schedule] = self::read($grouphandler, $group->id);
            if ($url = self::clean_url($link)) {
                return ['url' => $url, 'schedule' => $schedule, 'batch' => format_string($group->name, true, ['escape' => false])];
            }
        }

        [$link, $schedule] = self::read(\core_course\customfield\course_handler::create(), $courseid);
        if ($url = self::clean_url($link)) {
            return ['url' => $url, 'schedule' => $schedule, 'batch' => ''];
        }
        return null;
    }

    /**
     * Per-batch links for a course, for trainers: every group with its own link, plus the course link.
     *
     * @param int $courseid
     * @return array[] Each ['url', 'schedule', 'batch'].
     */
    public static function for_course(int $courseid): array {
        $classes = [];
        [$link, $schedule] = self::read(\core_course\customfield\course_handler::create(), $courseid);
        if ($url = self::clean_url($link)) {
            $classes[] = ['url' => $url, 'schedule' => $schedule, 'batch' => ''];
        }
        $grouphandler = \core_group\customfield\group_handler::create();
        foreach (groups_get_all_groups($courseid) as $group) {
            [$link, $schedule] = self::read($grouphandler, $group->id);
            if ($url = self::clean_url($link)) {
                $classes[] = ['url' => $url, 'schedule' => $schedule, 'batch' => format_string($group->name, true, ['escape' => false])];
            }
        }
        return $classes;
    }
}
