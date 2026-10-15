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
 * Attendance: Present, Absent or Late per session; percentages per learner.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance {
    /** @var string[] Valid statuses. */
    const STATUSES = ['P', 'A', 'L'];

    /**
     * Save the attendance of one session.
     *
     * @param int $sessionid
     * @param array $statuses userid => P|A|L
     */
    public static function save(int $sessionid, array $statuses): void {
        global $DB, $USER;
        $existing = $DB->get_records('local_itskills_attendance', ['sessionid' => $sessionid], '', 'userid, id');
        foreach ($statuses as $userid => $status) {
            if (!in_array($status, self::STATUSES, true)) {
                continue;
            }
            $record = (object) [
                'sessionid' => $sessionid, 'userid' => (int) $userid, 'status' => $status,
                'takenby' => $USER->id, 'timetaken' => time(),
            ];
            if (isset($existing[$userid])) {
                $record->id = $existing[$userid]->id;
                $DB->update_record('local_itskills_attendance', $record);
            } else {
                $DB->insert_record('local_itskills_attendance', $record);
            }
        }
    }

    /**
     * Attendance percentage for a learner, in one course or overall.
     * Late counts with the weight set in the plugin settings (default 1 = same as present).
     *
     * @param int $userid
     * @param int $courseid 0 for all courses.
     * @return array ['percent' => int|null, 'present' => int, 'late' => int, 'absent' => int, 'total' => int]
     */
    public static function summary(int $userid, int $courseid = 0): array {
        global $DB;
        $sql = "SELECT a.status, COUNT(1) AS n
                  FROM {local_itskills_attendance} a
                  JOIN {local_itskills_session} s ON s.id = a.sessionid
                 WHERE a.userid = :userid AND s.status = 0" . ($courseid ? ' AND s.courseid = :courseid' : '') . "
              GROUP BY a.status";
        $counts = ['P' => 0, 'A' => 0, 'L' => 0];
        foreach ($DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]) as $row) {
            $counts[$row->status] = (int) $row->n;
        }
        $total = array_sum($counts);
        $lateweight = (float) (get_config('local_itskills', 'lateweight') ?: 1);
        return [
            'percent' => $total ? (int) round(100 * ($counts['P'] + $lateweight * $counts['L']) / $total) : null,
            'present' => $counts['P'], 'late' => $counts['L'], 'absent' => $counts['A'], 'total' => $total,
        ];
    }

    /**
     * A learner's attendance history in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array[]
     */
    public static function history(int $userid, int $courseid): array {
        global $DB;
        $rows = $DB->get_records_sql("SELECT s.id, s.title, s.timestart, a.status
                                        FROM {local_itskills_session} s
                                        JOIN {local_itskills_attendance} a ON a.sessionid = s.id AND a.userid = :userid
                                       WHERE s.courseid = :courseid AND s.status = 0
                                    ORDER BY s.timestart DESC", ['userid' => $userid, 'courseid' => $courseid]);
        return array_values(array_map(fn($r) => [
            'title' => $r->title,
            'date' => userdate($r->timestart, get_string('strftimedatetimeshort', 'langconfig')),
            'status' => $r->status,
            'statuslabel' => get_string('att_' . $r->status, 'local_itskills'),
        ], $rows));
    }
}
