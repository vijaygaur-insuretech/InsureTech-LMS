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

/**
 * Upgrade steps.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_itskills_upgrade($oldversion) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/local/itskills/lib.php');
    if ($oldversion < 2026101500) {
        // Trainers no longer manage enrolments (subscriptions) or assign roles unless the admin allows it.
        local_itskills_apply_trainer_restrictions();
        upgrade_plugin_savepoint(true, 2026101500, 'local', 'itskills');
    }
    if ($oldversion < 2026101501) {
        // "Subscription expiring" emails: warn the learner and the admin who enrolled them 7 days before access ends.
        if (!get_config('enrol_manual', 'expirynotify')) {
            set_config('expirynotify', 2, 'enrol_manual');
            set_config('expirythreshold', 7 * DAYSECS, 'enrol_manual');
            $DB->execute("UPDATE {enrol} SET expirynotify = 2, notifyall = 1, expirythreshold = ? WHERE enrol = 'manual'",
                [7 * DAYSECS]);
        }
        // New assignments notify their graders (trainers) when something is submitted.
        set_config('sendnotifications', 1, 'assign');
        upgrade_plugin_savepoint(true, 2026101501, 'local', 'itskills');
    }
    if ($oldversion < 2026101600) {
        local_itskills_disable_mobile_app();
        upgrade_plugin_savepoint(true, 2026101600, 'local', 'itskills');
    }
    if ($oldversion < 2026101700) {
        // New "Course type" field (Live classes / Self-paced).
        \local_itskills\local\fields::ensure();
        upgrade_plugin_savepoint(true, 2026101700, 'local', 'itskills');
    }
    if ($oldversion < 2026102000) {
        // The learner sidebar links to "My courses", so the page must be on.
        set_config('enablemycourses', 1);
        upgrade_plugin_savepoint(true, 2026102000, 'local', 'itskills');
    }
    return true;
}
