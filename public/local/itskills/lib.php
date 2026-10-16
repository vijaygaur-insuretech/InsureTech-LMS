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
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add "Live classes", "Handover" and "Certificate" to the course's More menu.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_itskills_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ($course->id == SITEID) {
        return;
    }
    $navigation->add(get_string('liveclasses', 'local_itskills'),
        new moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM, null, 'itskills_sessions', new pix_icon('i/calendar', ''));
    if (has_capability('local/itskills:handover', $context)) {
        $navigation->add(get_string('handover', 'local_itskills'),
            new moodle_url('/local/itskills/handover.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM, null, 'itskills_handover', new pix_icon('i/report', ''));
    }
    if (\local_itskills\local\certificates::enabled_for($course)) {
        $navigation->add(get_string('certificate', 'local_itskills'),
            new moodle_url('/local/itskills/certificate.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM, null, 'itskills_certificate', new pix_icon('i/badge', ''));
    }
}

/**
 * Serve the certificate design files (background, logo, signature) uploaded in the settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_itskills_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM || !in_array($filearea, ['cert_bg', 'cert_logo', 'cert_sign'])) {
        return false;
    }
    require_login();
    $filename = array_pop($args);
    $itemid = (int) array_shift($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'local_itskills', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * Enrolment and role permissions that let someone manage learners' access (subscriptions) or other trainers.
 *
 * @return string[]
 */
function local_itskills_trainer_restricted_caps(): array {
    return [
        'enrol/manual:enrol', 'enrol/manual:manage', 'enrol/manual:unenrol',
        'enrol/self:config', 'enrol/self:manage', 'enrol/self:unenrol',
        'enrol/guest:config', 'enrol/cohort:config', 'enrol/meta:config', 'enrol/category:config',
        'enrol/database:config', 'enrol/imsenterprise:config', 'enrol/lti:config', 'enrol/lti:unenrol',
        'enrol/fee:manage', 'enrol/paypal:manage',
        'moodle/course:enrolconfig', 'moodle/role:assign',
    ];
}

/**
 * Apply the "Trainers can manage enrolments and subscriptions" setting to the trainer roles.
 *
 * Off (default): trainers cannot enrol or unenrol learners, change access dates, configure enrolment methods or
 * assign roles; only admins and managers can. On: the standard permissions are given back.
 */
function local_itskills_apply_trainer_restrictions(): void {
    $allow = (bool) get_config('local_itskills', 'trainersmanageenrol');
    $syscontext = context_system::instance();
    $caps = array_filter(local_itskills_trainer_restricted_caps(), fn($cap) => get_capability_info($cap));
    foreach (['editingteacher', 'teacher'] as $archetype) {
        foreach (get_archetype_roles($archetype) as $role) {
            $defaults = get_default_capabilities($archetype);
            foreach ($caps as $cap) {
                if ($allow) {
                    if (($defaults[$cap] ?? CAP_INHERIT) == CAP_ALLOW) {
                        assign_capability($cap, CAP_ALLOW, $role->id, $syscontext->id, true);
                    }
                } else {
                    unassign_capability($cap, $role->id, $syscontext->id);
                }
            }
        }
    }
    $syscontext->mark_dirty();
}

/**
 * Turn off the mobile app: no "Get the mobile app" link, no app banners and no app access to the site.
 */
function local_itskills_disable_mobile_app(): void {
    global $DB;
    set_config('enablemobilewebservice', 0);
    set_config('setuplink', '', 'tool_mobile');
    set_config('enablesmartappbanners', 0, 'tool_mobile');
    $DB->set_field('external_services', 'enabled', 0, ['shortname' => 'moodle_mobile_app']);
}
