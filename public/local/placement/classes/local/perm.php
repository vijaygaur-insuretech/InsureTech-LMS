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
 * Permission and feature control. Admins always have full access. Each employee's access is: their own setting if the
 * admin set one, else the "all employees" setting, else the default below. Checked on the server for every page,
 * action and file, so hidden buttons cannot be bypassed with a direct URL.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class perm {
    /** @var array Permission keys by panel, with their default for employees. */
    const KEYS = [
        'field' => [
            'field_phone' => 1, 'field_email' => 1, 'field_address' => 0, 'field_resume' => 1, 'field_salary' => 0,
            'field_workauth' => 0, 'field_education' => 1, 'field_references' => 0, 'field_notes' => 0, 'field_other' => 1,
        ],
        'action' => [
            'act_view' => 1, 'act_add' => 1, 'act_edit' => 1, 'act_delete' => 0, 'act_download' => 0, 'act_export' => 0,
            'act_assign' => 0, 'act_reassign' => 0, 'act_approve' => 0, 'act_manage' => 0,
        ],
        'feature' => [
            'feat_resume_preview' => 1, 'feat_resume_download' => 0, 'feat_client_edit' => 0, 'feat_app_create' => 1,
            'feat_app_status' => 1, 'feat_followups' => 1, 'feat_bulk' => 0, 'feat_export' => 0, 'feat_reports' => 1,
            'feat_dash_stats' => 1, 'feat_daily_reports' => 1,
        ],
    ];

    /** @var array Request cache: userid => [key => bool]. */
    protected static $cache = [];

    /**
     * Whether a user is a placement admin.
     *
     * @param int|null $userid
     * @return bool
     */
    public static function is_admin(?int $userid = null): bool {
        return has_capability('local/placement:manage', \context_system::instance(), $userid);
    }

    /**
     * Whether a user is a placement employee (and not an admin).
     *
     * @param int|null $userid
     * @return bool
     */
    public static function is_employee(?int $userid = null): bool {
        return !self::is_admin($userid) && has_capability('local/placement:employee', \context_system::instance(), $userid);
    }

    /**
     * Whether a user is a placement client (and neither admin nor employee).
     *
     * @param int|null $userid
     * @return bool
     */
    public static function is_client(?int $userid = null): bool {
        return !self::is_admin($userid) && !self::is_employee($userid)
            && has_capability('local/placement:client', \context_system::instance(), $userid);
    }

    /**
     * Default of a key.
     *
     * @param string $key
     * @return int
     */
    public static function default(string $key): int {
        foreach (self::KEYS as $keys) {
            if (array_key_exists($key, $keys)) {
                return $keys[$key];
            }
        }
        throw new \coding_exception('Unknown placement permission ' . $key);
    }

    /**
     * Whether a user may do something. Admins always may; non-employees never may.
     *
     * @param string|string[] $keys One key, or several that must all be allowed
     * @param int|null $userid
     * @return bool
     */
    public static function can($keys, ?int $userid = null): bool {
        global $USER;
        $userid = $userid ?? (int) $USER->id;
        if (self::is_admin($userid)) {
            return true;
        }
        if (!self::is_employee($userid)) {
            return false;
        }
        foreach ((array) $keys as $key) {
            if (!self::effective($userid)[$key]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Throw if not allowed.
     *
     * @param string|string[] $keys
     */
    public static function require($keys): void {
        if (!self::can($keys)) {
            throw new \moodle_exception('nopermission', 'local_placement');
        }
    }

    /**
     * All effective permissions of an employee.
     *
     * @param int $userid
     * @return array key => bool
     */
    public static function effective(int $userid): array {
        global $DB;
        if (!isset(self::$cache[$userid])) {
            $rows = $DB->get_records_select('local_placement_perm', 'userid IN (0, ?)', [$userid]);
            $global = [];
            $own = [];
            foreach ($rows as $row) {
                if ($row->userid) {
                    $own[$row->permkey] = (bool) $row->allowed;
                } else {
                    $global[$row->permkey] = (bool) $row->allowed;
                }
            }
            $out = [];
            foreach (self::KEYS as $keys) {
                foreach ($keys as $key => $default) {
                    $out[$key] = $own[$key] ?? $global[$key] ?? (bool) $default;
                }
            }
            self::$cache[$userid] = $out;
        }
        return self::$cache[$userid];
    }

    /**
     * Stored setting for a scope: null = not set (inherits).
     *
     * @param int $userid 0 for all employees
     * @param string $key
     * @return bool|null
     */
    public static function stored(int $userid, string $key): ?bool {
        global $DB;
        $value = $DB->get_field('local_placement_perm', 'allowed', ['userid' => $userid, 'permkey' => $key]);
        return $value === false ? null : (bool) $value;
    }

    /**
     * Change a setting (null removes it, so it inherits again). Logged in the audit log; takes effect immediately.
     *
     * @param int $userid 0 for all employees
     * @param string $key
     * @param bool|null $allowed
     */
    public static function set(int $userid, string $key, ?bool $allowed): void {
        global $DB, $USER;
        self::default($key);
        $old = self::stored($userid, $key);
        if ($old === $allowed) {
            return;
        }
        $existing = $DB->get_record('local_placement_perm', ['userid' => $userid, 'permkey' => $key]);
        if ($allowed === null) {
            $DB->delete_records('local_placement_perm', ['userid' => $userid, 'permkey' => $key]);
        } else if ($existing) {
            $DB->update_record('local_placement_perm', ['id' => $existing->id, 'allowed' => (int) $allowed,
                'modifiedby' => $USER->id, 'timemodified' => time()]);
        } else {
            $DB->insert_record('local_placement_perm', ['userid' => $userid, 'permkey' => $key, 'allowed' => (int) $allowed,
                'modifiedby' => $USER->id, 'timemodified' => time()]);
        }
        unset(self::$cache[$userid]);
        if ($userid === 0) {
            self::$cache = [];
        }
        $label = fn(?bool $v) => $v === null ? 'inherit' : ($v ? 'allow' : 'deny');
        audit::log('permission_change', 'permission', $userid, 0,
            ($userid ? 'employee ' . fullname(\core_user::get_user($userid)) : 'all employees') . ": $key " .
            $label($old) . ' -> ' . $label($allowed));
    }

    /**
     * Whether the user may see a profile field's permission group.
     *
     * @param string $group phone, email, address, salary, workauth, education, references, notes, resume, other
     * @param int|null $userid
     * @return bool
     */
    public static function field(string $group, ?int $userid = null): bool {
        return self::can('field_' . $group, $userid);
    }
}
