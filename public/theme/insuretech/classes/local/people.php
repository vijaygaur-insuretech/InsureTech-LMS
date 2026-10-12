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

use context_course;
use stdClass;

/**
 * Students and trainers, as the insureTech admin pages see them.
 *
 * A thin layer over Moodle's own APIs, nothing is stored separately:
 *  - "student in a course"  = manual enrolment with the student role; its start/end dates are the subscription,
 *  - "trainer in a course"  = manual enrolment with the editing teacher role,
 *  - "batch"                = a group in the course.
 * Moodle keeps enforcing access everywhere else (an expired or suspended enrolment loses course access).
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class people {
    /** @var string Learners. */
    const STUDENT = 'student';

    /** @var string Trainers. */
    const TRAINER = 'trainer';

    /** @var string Users with no course role yet. */
    const UNASSIGNED = 'unassigned';

    /** @var int Access ending within this many days counts as expiring soon. */
    const EXPIRING_SOON_DAYS = 7;

    /**
     * Role id used for a type of person.
     *
     * @param string $type self::STUDENT or self::TRAINER
     * @return int
     */
    public static function roleid(string $type): int {
        $archetype = $type === self::TRAINER ? 'editingteacher' : 'student';
        $roles = get_archetype_roles($archetype);
        if (!$roles) {
            throw new \moodle_exception('invalidrole');
        }
        return (int) reset($roles)->id;
    }

    /**
     * Role ids counted as "trainer" when listing people (editing and non-editing teachers).
     *
     * @return int[]
     */
    protected static function trainer_roleids(): array {
        return array_map(fn($r) => (int) $r->id,
            array_merge(get_archetype_roles('editingteacher'), get_archetype_roles('teacher')));
    }

    /**
     * Role ids counted as "student".
     *
     * @return int[]
     */
    protected static function student_roleids(): array {
        return array_map(fn($r) => (int) $r->id, get_archetype_roles('student'));
    }

    /**
     * List people of one type, with search and paging.
     *
     * @param string $type self::STUDENT, self::TRAINER or self::UNASSIGNED
     * @param string $search Matches first name, last name or email.
     * @param int $page
     * @param int $perpage
     * @return array [stdClass[] users, int total]
     */
    public static function list(string $type, string $search, int $page, int $perpage): array {
        global $DB, $CFG;

        $params = ['guestid' => $CFG->siteguest, 'courselevel' => CONTEXT_COURSE];
        $where = "u.deleted = 0 AND u.id <> :guestid";

        $roles = $type === self::TRAINER ? self::trainer_roleids() : self::student_roleids();
        if ($type === self::UNASSIGNED) {
            $roles = array_merge(self::trainer_roleids(), self::student_roleids());
        }
        [$insql, $inparams] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'role');
        $params += $inparams;
        $hasrole = "EXISTS (SELECT 1 FROM {role_assignments} ra
                              JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :courselevel
                             WHERE ra.userid = u.id AND ra.roleid $insql)";
        $where .= $type === self::UNASSIGNED ? " AND NOT $hasrole" : " AND $hasrole";

        if ($type === self::UNASSIGNED) {
            // Site admins are not learners or trainers waiting to be placed.
            [$adminsql, $adminparams] = $DB->get_in_or_equal(explode(',', $CFG->siteadmins), SQL_PARAMS_NAMED, 'adm', false);
            $where .= " AND u.id $adminsql";
            $params += $adminparams;
        }

        if ($search !== '') {
            $like = [];
            foreach (['firstname', 'lastname', 'email'] as $i => $field) {
                $like[] = $DB->sql_like("u.$field", ":s$i", false);
                $params["s$i"] = '%' . $DB->sql_like_escape($search) . '%';
            }
            $like[] = $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':sfull', false);
            $params['sfull'] = '%' . $DB->sql_like_escape($search) . '%';
            $where .= ' AND (' . implode(' OR ', $like) . ')';
        }

        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM {user} u WHERE $where", $params);
        $users = $DB->get_records_sql("SELECT $fields, u.suspended, u.lastaccess
                                         FROM {user} u
                                        WHERE $where
                                     ORDER BY u.firstname, u.lastname, u.id", $params, $page * $perpage, $perpage);
        return [$users, $total];
    }

    /**
     * Every course a user is a student or trainer in, with batches and access status.
     *
     * @param int $userid
     * @return array[] Keyed by course id.
     */
    public static function assignments(int $userid): array {
        $studentroles = self::student_roleids();
        $trainerroles = self::trainer_roleids();

        $result = [];
        foreach (enrol_get_all_users_courses($userid, false, ['fullname', 'shortname']) as $course) {
            $context = context_course::instance($course->id);
            $roleids = array_map(fn($ra) => (int) $ra->roleid, get_user_roles($context, $userid, false));
            $istrainer = (bool) array_intersect($roleids, $trainerroles);
            $isstudent = (bool) array_intersect($roleids, $studentroles);
            if (!$istrainer && !$isstudent) {
                continue;
            }
            $enrol = self::manual_enrolment($course->id, $userid);
            $result[$course->id] = [
                'courseid' => (int) $course->id,
                'course' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
                'type' => $istrainer ? self::TRAINER : self::STUDENT,
                'batches' => array_values(array_map(fn($g) => [
                    'id' => (int) $g->id,
                    'name' => format_string($g->name, true, ['context' => $context, 'escape' => false]),
                ], groups_get_all_groups($course->id, $userid))),
                'timestart' => $enrol ? (int) $enrol->timestart : 0,
                'timeend' => $enrol ? (int) $enrol->timeend : 0,
                'status' => self::status($enrol),
                'manual' => (bool) $enrol,
            ];
        }
        return $result;
    }

    /**
     * Subscription status of an enrolment: active, expiringsoon, expired, suspended or notstarted.
     *
     * @param stdClass|null $enrol user_enrolments record
     * @return string
     */
    public static function status(?stdClass $enrol): string {
        if (!$enrol) {
            return 'active';
        }
        $now = time();
        if ($enrol->status == ENROL_USER_SUSPENDED) {
            return 'suspended';
        }
        if ($enrol->timestart && $enrol->timestart > $now) {
            return 'notstarted';
        }
        if ($enrol->timeend && $enrol->timeend < $now) {
            return 'expired';
        }
        if ($enrol->timeend && $enrol->timeend - $now <= self::EXPIRING_SOON_DAYS * DAYSECS) {
            return 'expiringsoon';
        }
        return 'active';
    }

    /**
     * The manual enrolment instance of a course, created when missing.
     *
     * @param int $courseid
     * @return stdClass
     */
    protected static function manual_instance(int $courseid): stdClass {
        global $DB;
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            throw new \moodle_exception('pluginnotinstalled', 'core', '', 'enrol_manual');
        }
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $id = $plugin->add_default_instance(get_course($courseid));
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }
        return $instance;
    }

    /**
     * A user's manual enrolment record in a course, if any.
     *
     * @param int $courseid
     * @param int $userid
     * @return stdClass|null
     */
    protected static function manual_enrolment(int $courseid, int $userid): ?stdClass {
        global $DB;
        return $DB->get_record_sql("SELECT ue.*
                                      FROM {user_enrolments} ue
                                      JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'manual'
                                     WHERE e.courseid = :courseid AND ue.userid = :userid",
            ['courseid' => $courseid, 'userid' => $userid], IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Check the current user may manage enrolments in a course.
     *
     * @param int $courseid
     */
    protected static function require_manage(int $courseid): void {
        $context = context_course::instance($courseid);
        require_capability('enrol/manual:enrol', $context);
        require_capability('moodle/course:managegroups', $context);
    }

    /**
     * Assign a person to a course (and optionally a batch).
     *
     * @param int $userid
     * @param int $courseid
     * @param string $type self::STUDENT or self::TRAINER
     * @param int $groupid 0 for no batch.
     * @param int $timestart Students only, 0 for no start date.
     * @param int $timeend Students only, 0 for no end date.
     */
    public static function assign(int $userid, int $courseid, string $type, int $groupid, int $timestart, int $timeend): void {
        self::require_manage($courseid);
        $instance = self::manual_instance($courseid);
        $plugin = enrol_get_plugin('manual');
        if ($type === self::TRAINER) {
            $timestart = 0;
            $timeend = 0;
        }
        $plugin->enrol_user($instance, $userid, self::roleid($type), $timestart, $timeend, ENROL_USER_ACTIVE);
        // enrol_user() leaves the dates alone for someone already enrolled, so apply them explicitly.
        $plugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE, $timestart, $timeend);
        if ($groupid) {
            self::add_to_batch($userid, $courseid, $groupid);
        }
    }

    /**
     * Put an enrolled person in a batch of the course.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $groupid
     */
    public static function add_to_batch(int $userid, int $courseid, int $groupid): void {
        self::require_manage($courseid);
        $group = groups_get_group($groupid, '*', MUST_EXIST);
        if ((int) $group->courseid !== $courseid) {
            throw new \moodle_exception('invalidgroupid');
        }
        groups_add_member($group, $userid);
    }

    /**
     * Take a person out of a batch.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $groupid
     */
    public static function remove_from_batch(int $userid, int $courseid, int $groupid): void {
        self::require_manage($courseid);
        $group = groups_get_group($groupid, '*', MUST_EXIST);
        if ((int) $group->courseid !== $courseid) {
            throw new \moodle_exception('invalidgroupid');
        }
        groups_remove_member($group, $userid);
    }

    /**
     * Change a student's access dates (renew), or suspend / reactivate them.
     *
     * @param int $userid
     * @param int $courseid
     * @param int|null $timestart
     * @param int|null $timeend
     * @param int|null $status ENROL_USER_ACTIVE or ENROL_USER_SUSPENDED, null to keep.
     */
    public static function update_access(int $userid, int $courseid, ?int $timestart, ?int $timeend, ?int $status): void {
        self::require_manage($courseid);
        if (!self::manual_enrolment($courseid, $userid)) {
            throw new \moodle_exception('notenrolled', 'theme_insuretech');
        }
        enrol_get_plugin('manual')->update_user_enrol(self::manual_instance($courseid), $userid, $status, $timestart, $timeend);
    }

    /**
     * Remove a person from a course (unenrol from the manual enrolment).
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function remove(int $userid, int $courseid): void {
        self::require_manage($courseid);
        if (!self::manual_enrolment($courseid, $userid)) {
            throw new \moodle_exception('notenrolled', 'theme_insuretech');
        }
        enrol_get_plugin('manual')->unenrol_user(self::manual_instance($courseid), $userid);
    }

    /**
     * Courses and their batches, for the assign form.
     *
     * @return array [courseid => ['name' => string, 'batches' => [groupid => name]]]
     */
    public static function course_options(): array {
        $options = [];
        foreach (get_courses('all', 'c.fullname ASC', 'c.id, c.fullname, c.category') as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $context = context_course::instance($course->id);
            $batches = [];
            foreach (groups_get_all_groups($course->id) as $group) {
                $batches[$group->id] = format_string($group->name, true, ['context' => $context, 'escape' => false]);
            }
            $options[$course->id] = [
                'name' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
                'batches' => $batches,
            ];
        }
        return $options;
    }
}
