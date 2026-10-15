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

namespace local_itskills\privacy;

/**
 * Privacy provider.
 *
 * The plugin stores attendance, certificates, feedback and trainer notes about users. A full export/erase
 * implementation is a follow-up; until then this declares the data so administrators know it exists.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describe stored personal data.
     *
     * @param \core_privacy\local\metadata\collection $collection
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table('local_itskills_attendance', [
            'userid' => 'privacy:metadata:userid', 'status' => 'privacy:metadata:attendance', 'timetaken' => 'privacy:metadata:time',
        ], 'privacy:metadata:attendancetable');
        $collection->add_database_table('local_itskills_cert', [
            'userid' => 'privacy:metadata:userid', 'code' => 'privacy:metadata:code', 'timeissued' => 'privacy:metadata:time',
        ], 'privacy:metadata:certtable');
        $collection->add_database_table('local_itskills_feedback', [
            'userid' => 'privacy:metadata:userid', 'rating' => 'privacy:metadata:rating', 'comment' => 'privacy:metadata:comment',
        ], 'privacy:metadata:feedbacktable');
        $collection->add_database_table('local_itskills_note', [
            'userid' => 'privacy:metadata:userid', 'note' => 'privacy:metadata:note', 'trainerid' => 'privacy:metadata:trainerid',
        ], 'privacy:metadata:notetable');
        return $collection;
    }
}
