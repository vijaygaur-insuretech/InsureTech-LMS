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
 * Course and trainer feedback with moderation.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback {
    /** @var int Waiting for approval. */
    const PENDING = 0;
    /** @var int Shown on the course page. */
    const PUBLISHED = 1;
    /** @var int Hidden by an admin. */
    const HIDDEN = 2;

    /**
     * Save (or update) a learner's feedback.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $rating 1-5
     * @param int $trainerrating 0-5
     * @param string $comment
     */
    public static function save(int $courseid, int $userid, int $rating, int $trainerrating, string $comment): void {
        global $DB;
        $record = $DB->get_record('local_itskills_feedback', ['courseid' => $courseid, 'userid' => $userid]);
        $data = (object) [
            'courseid' => $courseid, 'userid' => $userid,
            'rating' => max(1, min(5, $rating)), 'trainerrating' => max(0, min(5, $trainerrating)),
            'comment' => trim($comment),
            'status' => get_config('local_itskills', 'feedbackmoderation') ? self::PENDING : self::PUBLISHED,
            'timemodified' => time(),
        ];
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('local_itskills_feedback', $data);
        } else {
            $data->timecreated = time();
            $DB->insert_record('local_itskills_feedback', $data);
        }
    }

    /**
     * Published feedback of a course and its average rating.
     *
     * @param int $courseid
     * @return array ['average' => float|null, 'count' => int, 'items' => array[]]
     */
    public static function published(int $courseid): array {
        global $DB;
        $rows = $DB->get_records('local_itskills_feedback', ['courseid' => $courseid, 'status' => self::PUBLISHED],
            'timemodified DESC');
        $items = [];
        foreach ($rows as $row) {
            $user = \core_user::get_user($row->userid);
            $items[] = [
                'name' => $user ? $user->firstname . ' ' . \core_text::substr($user->lastname, 0, 1) . '.' : '',
                'rating' => (int) $row->rating,
                'stars' => self::stars((int) $row->rating),
                'comment' => $row->comment,
                'date' => userdate($row->timemodified, get_string('strftimedate', 'langconfig')),
            ];
        }
        $average = $rows ? round(array_sum(array_column($rows, 'rating')) / count($rows), 1) : null;
        return ['average' => $average, 'count' => count($rows), 'items' => $items, 'hasitems' => !empty($items),
            'stars' => $average ? self::stars((int) round($average)) : ''];
    }

    /**
     * Star string for a rating.
     *
     * @param int $rating
     * @return string
     */
    public static function stars(int $rating): string {
        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }
}
