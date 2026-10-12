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
 * Recording site settings.
 *
 * @package    mod_itrecording
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('itrecording/intro', '', get_string('settings_intro', 'itrecording')));

    $settings->add(new admin_setting_configcheckbox('itrecording/trainerscanview',
        get_string('trainerscanview', 'itrecording'), get_string('trainerscanview_desc', 'itrecording'), 0));

    $settings->add(new admin_setting_configcheckbox('itrecording/watermark',
        get_string('watermark', 'itrecording'), get_string('watermark_desc', 'itrecording'), 1));

    $settings->add(new admin_setting_configcheckbox('itrecording/blockpopout',
        get_string('blockpopout', 'itrecording'), get_string('blockpopout_desc', 'itrecording'), 1));
}
