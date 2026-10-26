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

namespace local_placement\local;

/**
 * Audit log: who did what to which record, and when. Details describe the change without copying sensitive values.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit {
    /**
     * Record an action.
     *
     * @param string $action e.g. client_create, resume_download, permission_change
     * @param string $objecttype client, resume, application, permission, industry, question, report, export
     * @param int $objectid
     * @param int $clientid Client affected, if any
     * @param string $details Short description (field names, old -> new status); never sensitive values
     */
    public static function log(string $action, string $objecttype = '', int $objectid = 0, int $clientid = 0,
            string $details = ''): void {
        global $DB, $USER;
        $DB->insert_record('local_placement_audit', [
            'userid' => (int) ($USER->id ?? 0), 'action' => $action, 'objecttype' => $objecttype, 'objectid' => $objectid,
            'clientid' => $clientid, 'details' => \core_text::substr($details, 0, 1000), 'timecreated' => time(),
        ]);
    }
}
