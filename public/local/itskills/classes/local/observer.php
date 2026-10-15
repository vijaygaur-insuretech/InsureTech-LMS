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
 * Event observers.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Email learners when they are enrolled in a course (any enrolment method).
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        global $DB;
        if (mailer::$suppressenrolmentemail || !get_config('local_itskills', 'sendenrolemail')) {
            return;
        }
        $user = \core_user::get_user($event->relateduserid);
        $course = get_course($event->courseid);
        if (!$user || $user->deleted || $user->suspended || $course->id == SITEID) {
            return;
        }
        $ue = $DB->get_record('user_enrolments', ['id' => $event->objectid]);
        mailer::notify($user, 'enrolled', 'enrolled', [
            'username' => $user->username,
            'course' => format_string($course->fullname, true, ['escape' => false]),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'accessend' => ($ue && $ue->timeend) ? userdate($ue->timeend, get_string('strftimedate', 'langconfig'))
                : get_string('noenddate', 'local_itskills'),
            'reseturl' => (new \moodle_url('/login/forgot_password.php'))->out(false),
        ], new \moodle_url('/course/view.php', ['id' => $course->id]), $course->id);

        // Tell the course's trainers that a new learner joined.
        $context = \context_course::instance($course->id);
        if (get_config('local_itskills', 'notifytrainers') && !has_capability('moodle/course:manageactivities', $context, $user)) {
            $groups = array_map(fn($g) => format_string($g->name, true, ['escape' => false]),
                groups_get_all_groups($course->id, $user->id));
            foreach (get_enrolled_users($context, 'moodle/course:manageactivities', 0, 'u.*', null, 0, 0, true) as $trainer) {
                if ($trainer->id == $user->id) {
                    continue;
                }
                mailer::notify($trainer, 'trainer', 'newstudent', [
                    'student' => fullname($user),
                    'course' => format_string($course->fullname, true, ['escape' => false]),
                    'batch' => $groups ? implode(', ', $groups) : get_string('allbatches', 'local_itskills'),
                    'participantsurl' => (new \moodle_url('/user/index.php', ['id' => $course->id]))->out(false),
                ], new \moodle_url('/user/index.php', ['id' => $course->id]), $course->id);
            }
        }
    }

    /**
     * Tell learners when a new recording, assignment or quiz is added to their course.
     *
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        $modname = $event->other['modulename'] ?? '';
        if (!get_config('local_itskills', 'notifynewcontent') || !in_array($modname, ['itrecording', 'assign', 'quiz'])) {
            return;
        }
        // Sent from a background task: saving the activity stays fast, and no emails are built mid-request.
        $task = new \local_itskills\task\notify_new_content();
        $task->set_custom_data(['courseid' => $event->courseid, 'cmid' => $event->objectid, 'modname' => $modname]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Issue the certificate as soon as a course is completed.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        certificates::issue_if_eligible($event->relateduserid, $event->courseid);
    }
}
