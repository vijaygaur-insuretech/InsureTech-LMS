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
 * Notification types.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$defaults = [
    'defaults' => [
        'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    ],
];

$messageproviders = [
    // To clients: resume received, approved, or a new version requested.
    'resume' => $defaults,
    // To clients: application status and interview updates.
    'application' => $defaults,
    // To clients: a request to update profile information.
    'profile' => $defaults,
    // To employees: clients assigned to them and follow-ups due.
    'work' => $defaults,
];
