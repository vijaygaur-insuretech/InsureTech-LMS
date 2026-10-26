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
 * Client master profile fields. Each field belongs to a section (how the form is laid out) and a permission group
 * (what an admin can show or hide from employees). Add a field here and it appears in the form, profile and export.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields {
    /** @var array Sections in display order. */
    const SECTIONS = ['personal', 'professional', 'preferences', 'workauth', 'references'];

    /**
     * Field definitions: key => [type, section, permission group, options].
     *
     * @return array
     */
    public static function all(): array {
        return [
            'legalname' => ['text', 'personal', 'other'],
            'preferredname' => ['text', 'personal', 'other'],
            'phone' => ['text', 'personal', 'phone'],
            'city' => ['text', 'personal', 'address'],
            'state' => ['text', 'personal', 'address'],
            'country' => ['text', 'personal', 'address'],
            'postcode' => ['text', 'personal', 'address'],
            'linkedin' => ['url', 'personal', 'other'],
            'portfolio' => ['url', 'personal', 'other'],
            'currenttitle' => ['text', 'professional', 'other'],
            'totalexperience' => ['text', 'professional', 'other'],
            'relevantexperience' => ['text', 'professional', 'other'],
            'employers' => ['textarea', 'professional', 'other'],
            'employmenthistory' => ['textarea', 'professional', 'other'],
            'achievements' => ['textarea', 'professional', 'other'],
            'education' => ['textarea', 'professional', 'education'],
            'certifications' => ['textarea', 'professional', 'education'],
            'skills' => ['textarea', 'professional', 'other'],
            'languages' => ['text', 'professional', 'other'],
            'preferredtitles' => ['text', 'preferences', 'other'],
            'preferredindustries' => ['text', 'preferences', 'other'],
            'preferredlocations' => ['text', 'preferences', 'other'],
            'workmode' => ['select', 'preferences', 'other', ['', 'remote', 'hybrid', 'onsite', 'any']],
            'employmenttype' => ['select', 'preferences', 'other', ['', 'fulltime', 'contract', 'parttime', 'any']],
            'salary' => ['text', 'preferences', 'salary'],
            'availability' => ['text', 'preferences', 'other'],
            'relocation' => ['text', 'preferences', 'other'],
            'workauth' => ['text', 'workauth', 'workauth'],
            'sponsorship' => ['select', 'workauth', 'workauth', ['', 'no', 'yes', 'na']],
            'visacategory' => ['text', 'workauth', 'workauth'],
            'authexpiry' => ['date', 'workauth', 'workauth'],
            'references' => ['textarea', 'references', 'references'],
        ];
    }

    /**
     * Readable value of a field.
     *
     * @param string $key
     * @param mixed $value
     * @return string
     */
    public static function display(string $key, $value): string {
        $def = self::all()[$key] ?? ['text'];
        if ($value === null || $value === '' || $value === 0) {
            return '';
        }
        if ($def[0] === 'date') {
            return userdate((int) $value, get_string('strftimedate', 'langconfig'));
        }
        if ($def[0] === 'select') {
            return get_string('opt_' . $value, 'local_placement');
        }
        return (string) $value;
    }
}
