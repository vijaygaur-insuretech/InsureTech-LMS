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

namespace local_itskills\task;

/**
 * Weekly email to site admins listing learners who have not opened their course for a while.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inactive_learners extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_inactive', 'local_itskills');
    }

    /**
     * Build and send the digest.
     */
    public function execute() {
        global $DB;
        $days = (int) get_config('local_itskills', 'inactivedays');
        if ($days <= 0) {
            return;
        }
        $cutoff = time() - $days * DAYSECS;
        $lines = [];
        $courses = $DB->get_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID], 'fullname');
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $learners = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.id, u.firstname, u.lastname, u.email', null, 0, 0, true);
            if (!$learners) {
                continue;
            }
            [$in, $params] = $DB->get_in_or_equal(array_keys($learners), SQL_PARAMS_NAMED);
            $params['courseid'] = $course->id;
            $access = $DB->get_records_select_menu('user_lastaccess', "courseid = :courseid AND userid $in", $params, '',
                'userid, timeaccess');
            foreach ($learners as $learner) {
                $last = $access[$learner->id] ?? 0;
                if ($last < $cutoff) {
                    $lines[] = '- ' . fullname($learner) . ' (' . $learner->email . ') · '
                        . format_string($course->fullname, true, ['escape' => false]) . ' · '
                        . ($last ? get_string('lastseen', 'local_itskills', userdate($last, get_string('strftimedate', 'langconfig')))
                            : get_string('neveropened', 'local_itskills'));
                }
            }
        }
        if (!$lines) {
            mtrace('No inactive learners.');
            return;
        }
        $url = new \moodle_url('/theme/insuretech/manage/people.php');
        foreach (get_admins() as $admin) {
            \local_itskills\local\mailer::notify($admin, 'admin', 'inactive', [
                'count' => count($lines), 'days' => $days, 'list' => implode("\n", $lines), 'peopleurl' => $url->out(false),
            ], $url);
        }
        mtrace('Reported ' . count($lines) . ' inactive learner enrolments.');
    }
}
