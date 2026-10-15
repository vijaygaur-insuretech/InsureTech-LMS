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

use local_itskills\local\mailer;

/**
 * Email learners about a new recording, assignment or quiz in their course.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_new_content extends \core\task\adhoc_task {
    /**
     * Send the emails.
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        if (!$DB->record_exists('course_modules', ['id' => $data->cmid]) || !($course = $DB->get_record('course', ['id' => $data->courseid]))) {
            return;
        }
        $cm = get_fast_modinfo($course)->get_cm($data->cmid);
        if (!$cm->visible || !$cm->visibleoncoursepage || $cm->deletioninprogress) {
            return;
        }
        $context = \context_course::instance($course->id);
        $url = new \moodle_url('/mod/' . $data->modname . '/view.php', ['id' => $cm->id]);
        $sent = 0;
        foreach (get_enrolled_users($context, 'mod/assign:submit', 0, 'u.*', null, 0, 0, true) as $user) {
            if ($user->suspended || $user->deleted) {
                continue;
            }
            mailer::notify($user, 'content', 'newcontent', [
                'activity' => $cm->get_formatted_name(['escape' => false]),
                'type' => get_string('modulename', $data->modname),
                'course' => format_string($course->fullname, true, ['escape' => false]),
                'activityurl' => $url->out(false),
            ], $url, $course->id);
            $sent++;
        }
        mtrace("Notified $sent learners about {$data->modname} {$cm->id}");
    }
}
