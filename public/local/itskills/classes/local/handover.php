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
 * Trainer handover: module and topic status per batch, current topic, and private trainer notes.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handover {
    /** @var string[] Status names by value. */
    const STATUSES = [0 => 'pending', 1 => 'partial', 2 => 'completed'];

    /**
     * Modules (sections) and their topics (activities) with their status for a batch.
     *
     * @param \stdClass $course
     * @param int $groupid
     * @return array[]
     */
    public static function items(\stdClass $course, int $groupid): array {
        global $DB;
        $saved = [];
        foreach ($DB->get_records('local_itskills_topic', ['courseid' => $course->id, 'groupid' => $groupid]) as $row) {
            $saved[$row->itemtype . ':' . $row->itemid] = $row;
        }
        $modinfo = get_fast_modinfo($course);
        $modules = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0 || $section->is_delegated()) {
                continue;
            }
            $topics = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                // Recordings are admin/student-only content, so they never appear in the trainer's handover.
                if ($cm->deletioninprogress || !$cm->has_view() || $cm->modname === 'itrecording') {
                    continue;
                }
                $key = 'cm:' . $cm->id;
                $topics[] = self::item($key, $cm->get_formatted_name(['escape' => false]), $saved[$key] ?? null);
            }
            $key = 'section:' . $section->id;
            $modules[] = self::item($key, html_entity_decode(get_section_name($course, $section), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $saved[$key] ?? null) + ['topics' => $topics, 'hastopics' => !empty($topics),
                'open' => !empty(array_filter($topics, fn($t) => $t['iscurrent'])) || !empty($saved[$key]->iscurrent)];
        }
        return $modules;
    }

    /**
     * One status row.
     *
     * @param string $key
     * @param string $name
     * @param \stdClass|null $saved
     * @return array
     */
    protected static function item(string $key, string $name, ?\stdClass $saved): array {
        $status = $saved ? (int) $saved->status : 0;
        return [
            'key' => $key,
            'name' => $name,
            'status' => $status,
            'statusname' => self::STATUSES[$status],
            'statuslabel' => get_string('topic_' . self::STATUSES[$status], 'local_itskills'),
            'iscurrent' => $saved && $saved->iscurrent,
            'options' => array_map(fn($v) => [
                'value' => $v, 'label' => get_string('topic_' . self::STATUSES[$v], 'local_itskills'), 'selected' => $v === $status,
            ], array_keys(self::STATUSES)),
            'updated' => $saved && $saved->timemodified ? userdate($saved->timemodified, get_string('strftimedatetimeshort', 'langconfig')) : '',
        ];
    }

    /**
     * Save statuses and the current topic for a batch.
     *
     * @param int $courseid
     * @param int $groupid
     * @param array $statuses key (section:ID / cm:ID) => 0|1|2
     * @param string $current key of the current topic, or ''
     */
    public static function save(int $courseid, int $groupid, array $statuses, string $current): void {
        global $DB, $USER;
        foreach ($statuses as $key => $status) {
            if (!preg_match('/^(section|cm):(\d+)$/', $key, $m) || !isset(self::STATUSES[(int) $status])) {
                continue;
            }
            $params = ['courseid' => $courseid, 'groupid' => $groupid, 'itemtype' => $m[1], 'itemid' => (int) $m[2]];
            $record = $DB->get_record('local_itskills_topic', $params);
            $data = (object) ($params + [
                'status' => (int) $status, 'iscurrent' => $key === $current ? 1 : 0,
                'usermodified' => $USER->id, 'timemodified' => time(),
            ]);
            if ($record) {
                if ($record->status == $data->status && $record->iscurrent == $data->iscurrent) {
                    continue;
                }
                $data->id = $record->id;
                $DB->update_record('local_itskills_topic', $data);
            } else if ($data->status || $data->iscurrent) {
                $DB->insert_record('local_itskills_topic', $data);
            }
        }
    }

    /**
     * Add a private note.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int $userid Learner, 0 for a batch note.
     * @param string $note
     */
    public static function add_note(int $courseid, int $groupid, int $userid, string $note): void {
        global $DB, $USER;
        $note = trim($note);
        if ($note === '') {
            return;
        }
        $DB->insert_record('local_itskills_note', (object) [
            'courseid' => $courseid, 'groupid' => $groupid, 'userid' => $userid, 'sectionid' => 0,
            'note' => $note, 'trainerid' => $USER->id, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Notes of a batch (newest first), including notes written by previous trainers.
     *
     * @param int $courseid
     * @param int $groupid
     * @return array[]
     */
    public static function notes(int $courseid, int $groupid): array {
        global $DB, $USER;
        $rows = $DB->get_records('local_itskills_note', ['courseid' => $courseid, 'groupid' => $groupid], 'timecreated DESC');
        $out = [];
        foreach ($rows as $row) {
            $trainer = \core_user::get_user($row->trainerid);
            $learner = $row->userid ? \core_user::get_user($row->userid) : null;
            $out[] = [
                'id' => $row->id,
                'note' => $row->note,
                'trainer' => $trainer ? fullname($trainer) : '-',
                'learner' => $learner ? fullname($learner) : '',
                'date' => userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'mine' => $row->trainerid == $USER->id || is_siteadmin(),
            ];
        }
        return $out;
    }

    /**
     * Delete a note (its author or an admin).
     *
     * @param int $id
     */
    public static function delete_note(int $id): void {
        global $DB, $USER;
        $note = $DB->get_record('local_itskills_note', ['id' => $id], '*', MUST_EXIST);
        if ($note->trainerid != $USER->id && !is_siteadmin()) {
            throw new \required_capability_exception(\context_course::instance($note->courseid), 'local/itskills:handover',
                'nopermissions', '');
        }
        $DB->delete_records('local_itskills_note', ['id' => $id]);
    }
}
