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
 * Issue certificates to learners who became eligible (catches completions that happened without the event).
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_certificates extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_certificates', 'local_itskills');
    }

    /**
     * Check every learner without a certificate in courses with completion tracking.
     */
    public function execute() {
        global $DB;
        if (!get_config('local_itskills', 'certenabled')) {
            return;
        }
        $courses = $DB->get_records_select('course', 'enablecompletion = 1 AND id <> :site', ['site' => SITEID]);
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            foreach (get_enrolled_users($context, 'mod/assign:submit', 0, 'u.id', null, 0, 0, true) as $user) {
                if (!\local_itskills\local\certificates::get($user->id, $course->id)
                        && \local_itskills\local\certificates::issue_if_eligible($user->id, $course->id)) {
                    mtrace("Issued certificate to user {$user->id} in course {$course->id}");
                }
            }
        }
    }
}
