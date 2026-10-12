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
 * insureTech Skills theme upgrade steps.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the theme.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_theme_insuretech_upgrade($oldversion) {
    if ($oldversion < 2026101000) {
        // Live class meeting link fields for courses and batches (groups).
        \theme_insuretech\local\live_class_fields::ensure();
        upgrade_plugin_savepoint(true, 2026101000, 'theme', 'insuretech');
    }
    return true;
}
