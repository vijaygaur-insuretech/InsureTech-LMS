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
 * Event observers.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * After login, placement clients and employees without LMS courses go straight to the placement portal.
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $SESSION;
        $userid = (int) $event->objectid;
        if (!empty($SESSION->wantsurl) || perm::is_admin($userid)) {
            return;
        }
        if ((perm::is_employee($userid) || perm::is_client($userid)) && !enrol_get_all_users_courses($userid, true)) {
            $SESSION->wantsurl = (new \moodle_url('/local/placement/index.php'))->out(false);
        }
    }
}
