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
 * Recording activity library.
 *
 * @package    mod_itrecording
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Features supported by the activity.
 *
 * @param string $feature
 * @return mixed
 */
function itrecording_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_RESOURCE,
        FEATURE_GROUPS => false,
        FEATURE_GROUPINGS => false,
        FEATURE_MOD_INTRO => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        // No backup support on purpose: a course backup must not carry the private video links to whoever restores it.
        FEATURE_BACKUP_MOODLE2 => false,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_CONTENT,
        default => null,
    };
}

/**
 * Add a recording.
 *
 * @param stdClass $data
 * @param mod_itrecording_mod_form|null $mform
 * @return int
 */
function itrecording_add_instance($data, $mform = null) {
    global $DB;
    $data->videourl = trim($data->videourl);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    return $DB->insert_record('itrecording', $data);
}

/**
 * Update a recording. Only people with mod/itrecording:manage may change it.
 *
 * @param stdClass $data
 * @param mod_itrecording_mod_form|null $mform
 * @return bool
 */
function itrecording_update_instance($data, $mform = null) {
    global $DB;
    require_capability('mod/itrecording:manage', context_module::instance($data->coursemodule));
    $data->id = $data->instance;
    $data->videourl = trim($data->videourl);
    $data->timemodified = time();
    return $DB->update_record('itrecording', $data);
}

/**
 * Delete a recording.
 *
 * @param int $id
 * @return bool
 */
function itrecording_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('itrecording', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('itrecording', ['id' => $id]);
    return true;
}

/**
 * Hide recordings completely from anyone who may not watch them, including a trainer who switched role to student.
 * (People without mod/itrecording:view, such as trainers, are already hidden by Moodle itself.)
 *
 * @param cm_info $cm
 */
function itrecording_cm_info_dynamic(cm_info $cm) {
    if (!\mod_itrecording\video::can_view($cm->context, $cm->course)) {
        // Marking it unavailable without a message recalculates visibility, so it also leaves the course page.
        $cm->set_available(false, 0, '');
        $cm->set_user_visible(false);
    }
}

/**
 * Mark the activity viewed (for completion) and log it.
 *
 * @param stdClass $recording
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param context_module $context
 */
function itrecording_view($recording, $course, $cm, $context) {
    $event = \mod_itrecording\event\course_module_viewed::create([
        'context' => $context,
        'objectid' => $recording->id,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('itrecording', $recording);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}
