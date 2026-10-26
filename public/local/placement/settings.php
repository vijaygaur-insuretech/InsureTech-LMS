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
 * Admin settings: statuses, platforms and notifications, plus links to the portal's admin pages.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('root', new admin_category('local_placement', get_string('pluginname', 'local_placement')));
    $ADMIN->add('local_placement', new admin_externalpage('local_placement_portal', get_string('openportal', 'local_placement'),
        new moodle_url('/local/placement/index.php'), 'local/placement:manage'));
    $ADMIN->add('local_placement', new admin_externalpage('local_placement_permissions', get_string('tab_permissions', 'local_placement'),
        new moodle_url('/local/placement/permissions.php'), 'local/placement:manage'));
    $ADMIN->add('local_placement', new admin_externalpage('local_placement_questions', get_string('tab_questions', 'local_placement'),
        new moodle_url('/local/placement/industries.php'), 'local/placement:manage'));

    $settings = new admin_settingpage('local_placement_settings', get_string('settings'));
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtextarea('local_placement/statuses', get_string('set_statuses', 'local_placement'),
            get_string('set_statuses_desc', 'local_placement'), implode("\n", \local_placement\local\setup::STATUSES), PARAM_TEXT, 30, 10));
        $settings->add(new admin_setting_configtextarea('local_placement/platforms', get_string('set_platforms', 'local_placement'),
            get_string('set_platforms_desc', 'local_placement'), implode("\n", \local_placement\local\setup::PLATFORMS), PARAM_TEXT, 30, 8));
        $settings->add(new admin_setting_heading('local_placement/h_notify', get_string('h_notify', 'local_placement'),
            get_string('h_notify_desc', 'local_placement')));
        foreach (['resume', 'application', 'profile', 'work'] as $type) {
            $settings->add(new admin_setting_configcheckbox('local_placement/notify_' . $type,
                get_string('messageprovider:' . $type, 'local_placement'), '', 1));
        }
    }
    $ADMIN->add('local_placement', $settings);
}
