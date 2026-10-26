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
 * Library callbacks.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the portal to the user menu area of the site navigation for admins.
 *
 * @param global_navigation $navigation
 */
function local_placement_extend_navigation(global_navigation $navigation) {
    if (isloggedin() && !isguestuser() && \local_placement\local\perm::is_admin()) {
        $node = $navigation->add(get_string('pluginname', 'local_placement'), new moodle_url('/local/placement/index.php'),
            navigation_node::TYPE_CUSTOM, null, 'local_placement', new pix_icon('i/briefcase', ''));
        $node->showinflatnavigation = true;
    }
}
