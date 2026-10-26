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

namespace local_placement\task;

use local_placement\local\notify;

/**
 * Morning reminder to each employee about follow-ups due today or overdue.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class followups extends \core\task\scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('task_followups', 'local_placement');
    }

    #[\Override]
    public function execute() {
        global $DB;
        $closed = ['Rejected', 'Offer Received', 'Withdrawn', 'Closed'];
        [$notin, $params] = $DB->get_in_or_equal($closed, SQL_PARAMS_NAMED, 'st', false);
        $params['until'] = usergetmidnight(time()) + DAYSECS;
        $rows = $DB->get_records_sql("SELECT employeeid, COUNT(1) AS due
                                         FROM {local_placement_app}
                                        WHERE followupdate > 0 AND followupdate < :until AND employeeid > 0 AND status $notin
                                     GROUP BY employeeid", $params);
        foreach ($rows as $row) {
            notify::send('work', (int) $row->employeeid, get_string('msg_followups_subject', 'local_placement', $row->due),
                get_string('msg_followups_body', 'local_placement', $row->due),
                new \moodle_url('/local/placement/applications.php', ['due' => 1]));
            mtrace("Placement follow-ups: employee {$row->employeeid}, {$row->due} due");
        }
    }
}
