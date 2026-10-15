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
 * Remind learners and trainers before a live class starts.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_reminders extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_reminders', 'local_itskills');
    }

    /**
     * Send reminders for sessions starting within the configured number of hours.
     */
    public function execute() {
        global $DB;
        $hours = (float) get_config('local_itskills', 'reminderhours');
        if ($hours <= 0) {
            return;
        }
        $now = time();
        $sessions = $DB->get_records_select('local_itskills_session',
            'status = 0 AND reminded = 0 AND timestart > :now AND timestart <= :until',
            ['now' => $now, 'until' => $now + (int) ($hours * HOURSECS)]);
        foreach ($sessions as $session) {
            \local_itskills\local\sessions::notify_batch($session->id, 'reminder');
            $DB->set_field('local_itskills_session', 'reminded', 1, ['id' => $session->id]);
            mtrace('Reminded session ' . $session->id);
        }
    }
}
