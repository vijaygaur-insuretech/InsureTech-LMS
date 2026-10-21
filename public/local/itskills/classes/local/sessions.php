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
 * Live class sessions: schedule, change, cancel, list, and notify the batch.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions {
    /** @var int Scheduled. */
    const SCHEDULED = 0;
    /** @var int Cancelled. */
    const CANCELLED = 1;

    /**
     * Save a session (insert or update) and notify the learners when something they care about changed.
     *
     * @param \stdClass $data courseid, groupid, title, description, timestart, duration, meetingurl, (id)
     * @return int Session id.
     */
    public static function save(\stdClass $data): int {
        global $DB, $USER;
        $now = time();
        $data->usermodified = $USER->id;
        $data->timemodified = $now;
        $data->meetingurl = self::clean_url($data->meetingurl ?? '');
        if (empty($data->id)) {
            $data->status = self::SCHEDULED;
            $data->reminded = 0;
            $data->timecreated = $now;
            $data->id = $DB->insert_record('local_itskills_session', $data);
            self::sync_calendar($data->id);
            self::notify_batch($data->id, 'sessionnew');
            return $data->id;
        }
        $old = $DB->get_record('local_itskills_session', ['id' => $data->id], '*', MUST_EXIST);
        if ($old->timestart != $data->timestart) {
            $data->reminded = 0;
        }
        $DB->update_record('local_itskills_session', $data);
        self::sync_calendar($data->id);
        if ($old->timestart != $data->timestart || $old->meetingurl !== $data->meetingurl || $old->duration != $data->duration) {
            self::notify_batch($data->id, 'sessionchanged');
        }
        return $data->id;
    }

    /**
     * Keep the session's calendar event in step: a course (or batch) event while scheduled, removed when cancelled.
     *
     * @param int $id Session id
     */
    public static function sync_calendar(int $id): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $session = $DB->get_record('local_itskills_session', ['id' => $id]);
        $existing = $DB->get_record('event', ['component' => 'local_itskills', 'instance' => $id], '*', IGNORE_MULTIPLE);
        if (!$session || $session->status == self::CANCELLED) {
            if ($existing) {
                \calendar_event::load($existing)->delete();
            }
            return;
        }
        $link = self::link($session);
        $description = trim($session->description ?? '');
        if ($link !== '') {
            $description .= ($description === '' ? '' : "\n\n") . get_string('joinclass', 'local_itskills') . ': ' . $link;
        }
        $event = (object) [
            'name' => $session->title,
            'description' => s($description),
            'format' => FORMAT_PLAIN,
            'courseid' => $session->courseid,
            'groupid' => (int) $session->groupid,
            'userid' => 0,
            'eventtype' => $session->groupid ? 'group' : 'course',
            'component' => 'local_itskills',
            'instance' => $session->id,
            'timestart' => $session->timestart,
            'timeduration' => $session->duration * MINSECS,
            'visible' => 1,
        ];
        if ($existing) {
            \calendar_event::load($existing)->update($event, false);
        } else {
            \calendar_event::create($event, false);
        }
    }

    /**
     * Cancel a session and tell the learners.
     *
     * @param int $id
     */
    public static function cancel(int $id): void {
        global $DB, $USER;
        $DB->update_record('local_itskills_session', (object) [
            'id' => $id, 'status' => self::CANCELLED, 'usermodified' => $USER->id, 'timemodified' => time(),
        ]);
        self::sync_calendar($id);
        self::notify_batch($id, 'sessioncancelled');
    }

    /**
     * The meeting link for a session: its own, else the batch's or course's live class link.
     *
     * @param \stdClass $session
     * @return string
     */
    public static function link(\stdClass $session): string {
        if (!empty($session->meetingurl)) {
            return $session->meetingurl;
        }
        if (class_exists('\\theme_insuretech\\local\\live_class_fields')) {
            if ($session->groupid) {
                $link = \theme_insuretech\local\live_class_fields::get_group($session->groupid)['link'];
                if ($link) {
                    return $link;
                }
            }
            return \theme_insuretech\local\live_class_fields::get_course($session->courseid)['link'];
        }
        return '';
    }

    /**
     * Only absolute http(s) links.
     *
     * @param string $url
     * @return string
     */
    public static function clean_url(string $url): string {
        $url = clean_param(trim($url), PARAM_URL);
        return preg_match('#^https?://[^\s/]+#i', $url) ? $url : '';
    }

    /**
     * Students a session is for: the batch's members, or every active student of the course.
     *
     * @param \stdClass $session
     * @return \stdClass[]
     */
    public static function learners(\stdClass $session): array {
        $context = \context_course::instance($session->courseid);
        $users = get_enrolled_users($context, 'mod/assign:submit', $session->groupid, 'u.*', 'u.lastname, u.firstname', 0, 0, true);
        return array_filter($users, fn($u) => !$u->deleted && !$u->suspended);
    }

    /**
     * Placeholder values for a session email.
     *
     * @param \stdClass $session
     * @param \stdClass $user Recipient (dates in their timezone).
     * @return array
     */
    public static function vars(\stdClass $session, \stdClass $user): array {
        $course = get_course($session->courseid);
        $group = $session->groupid ? groups_get_group($session->groupid) : null;
        $trainers = [];
        foreach (get_enrolled_users(\context_course::instance($course->id), 'moodle/course:manageactivities', $session->groupid)
                as $t) {
            $trainers[] = fullname($t);
        }
        return [
            'title' => $session->title,
            'course' => format_string($course->fullname, true, ['escape' => false]),
            'batch' => $group ? format_string($group->name, true, ['escape' => false]) : get_string('allbatches', 'local_itskills'),
            'date' => userdate($session->timestart, get_string('strftimedaydate', 'langconfig'), $user->timezone),
            'time' => userdate($session->timestart, get_string('strftimetime', 'langconfig'), $user->timezone),
            'duration' => $session->duration,
            'meetinglink' => self::link($session) ?: get_string('linksoon', 'local_itskills'),
            'trainer' => $trainers ? implode(', ', array_unique($trainers)) : '-',
            'sessionsurl' => (new \moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]))->out(false),
        ];
    }

    /**
     * Notify every learner (and the trainers) of a session.
     *
     * @param int $id
     * @param string $template sessionnew, sessionchanged, sessioncancelled or reminder.
     */
    public static function notify_batch(int $id, string $template): void {
        global $DB;
        if (!get_config('local_itskills', 'sendsessionemails')) {
            return;
        }
        $session = $DB->get_record('local_itskills_session', ['id' => $id], '*', MUST_EXIST);
        $context = \context_course::instance($session->courseid);
        $recipients = self::learners($session);
        foreach (get_enrolled_users($context, 'moodle/course:manageactivities', $session->groupid, 'u.*') as $trainer) {
            $recipients[$trainer->id] = $trainer;
        }
        $url = new \moodle_url('/local/itskills/sessions.php', ['courseid' => $session->courseid]);
        foreach ($recipients as $user) {
            mailer::notify($user, $template === 'reminder' ? 'reminder' : 'session', $template, self::vars($session, $user),
                $url, $session->courseid);
        }
    }

    /**
     * Sessions of a course a user can see (trainers: all; learners: their batches and whole-course sessions).
     *
     * @param int $courseid
     * @param int $userid
     * @param bool $upcoming Only future (and running) sessions.
     * @return \stdClass[]
     */
    public static function for_course(int $courseid, int $userid, bool $upcoming = false): array {
        global $DB;
        $context = \context_course::instance($courseid);
        $params = ['courseid' => $courseid];
        $where = 'courseid = :courseid';
        if (!has_capability('local/itskills:managesessions', $context, $userid)) {
            $groupids = array_keys(groups_get_all_groups($courseid, $userid));
            [$in, $inparams] = $DB->get_in_or_equal(array_merge([0], $groupids), SQL_PARAMS_NAMED);
            $where .= " AND groupid $in";
            $params += $inparams;
        }
        if ($upcoming) {
            $where .= ' AND timestart + duration * 60 >= :now AND status = 0';
            $params['now'] = time();
        }
        return $DB->get_records_select('local_itskills_session', $where, $params, 'timestart ' . ($upcoming ? 'ASC' : 'DESC'));
    }

    /**
     * The next upcoming session for a user across all their courses.
     *
     * @param int $userid
     * @return \stdClass|null Session with ->coursename.
     */
    public static function next_for_user(int $userid): ?\stdClass {
        $next = null;
        foreach (enrol_get_all_users_courses($userid, true) as $course) {
            $sessions = self::for_course($course->id, $userid, true);
            $first = reset($sessions);
            if ($first && (!$next || $first->timestart < $next->timestart)) {
                $first->coursename = format_string($course->fullname, true, ['escape' => false]);
                $next = $first;
            }
        }
        return $next;
    }
}
