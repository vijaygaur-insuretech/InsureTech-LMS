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
 * Client records and who may see them.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clients {
    /** @var int Active client. */
    const ACTIVE = 1;
    /** @var int Deactivated client. */
    const INACTIVE = 0;

    /**
     * Load a client with its decoded profile.
     *
     * @param int $id
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        $client = $DB->get_record('local_placement_client', ['id' => $id], '*', MUST_EXIST);
        $client->data = json_decode($client->profile ?: '{}', true) ?: [];
        $client->user = \core_user::get_user($client->userid);
        return $client;
    }

    /**
     * The client record of a user, if any.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function for_user(int $userid): ?\stdClass {
        global $DB;
        $id = $DB->get_field('local_placement_client', 'id', ['userid' => $userid]);
        return $id ? self::get((int) $id) : null;
    }

    /**
     * Whether the current user may open this client's record: admins always, employees only for clients assigned to
     * them (and with View allowed), clients only for themselves.
     *
     * @param \stdClass $client
     * @return bool
     */
    public static function can_access(\stdClass $client): bool {
        global $USER;
        if (perm::is_admin()) {
            return true;
        }
        if (perm::is_employee()) {
            return (int) $client->employeeid === (int) $USER->id && perm::can('act_view');
        }
        return (int) $client->userid === (int) $USER->id;
    }

    /**
     * Throw unless the current user may open the client's record.
     *
     * @param \stdClass $client
     */
    public static function require_access(\stdClass $client): void {
        if (!self::can_access($client)) {
            throw new \moodle_exception('nopermission', 'local_placement');
        }
    }

    /**
     * Whether the current user may see a field group of this client (the client always sees their own data).
     *
     * @param \stdClass $client
     * @param string $group
     * @return bool
     */
    public static function can_see(\stdClass $client, string $group): bool {
        global $USER;
        if ((int) $client->userid === (int) $USER->id) {
            return $group !== 'notes';
        }
        return perm::field($group);
    }

    /**
     * Client ids the current user may list, or null for all (admins).
     *
     * @return int[]|null
     */
    public static function visible_ids(): ?array {
        global $DB, $USER;
        if (perm::is_admin()) {
            return null;
        }
        if (perm::is_employee()) {
            return perm::can('act_view')
                ? array_map('intval', $DB->get_fieldset_select('local_placement_client', 'id', 'employeeid = ?', [$USER->id]))
                : [];
        }
        $own = self::for_user((int) $USER->id);
        return $own ? [(int) $own->id] : [];
    }

    /**
     * Save profile fields. Only fields the current user may see are changed; hidden ones keep their value.
     *
     * @param \stdClass $client
     * @param array $values key => value
     */
    public static function save_profile(\stdClass $client, array $values): void {
        global $DB;
        $data = $client->data;
        $changed = [];
        foreach (fields::all() as $key => $def) {
            if (!array_key_exists($key, $values) || !self::can_see($client, $def[2])) {
                continue;
            }
            $value = is_string($values[$key]) ? trim($values[$key]) : $values[$key];
            if (($data[$key] ?? '') != $value) {
                $changed[] = $key;
            }
            $data[$key] = $value;
        }
        $DB->update_record('local_placement_client', ['id' => $client->id, 'profile' => json_encode($data),
            'timemodified' => time()]);
        $client->data = $data;
        if ($changed) {
            audit::log('client_profile_edit', 'client', $client->id, $client->id, 'fields: ' . implode(', ', $changed));
        }
    }

    /**
     * Assign (or reassign) a client to an employee, and tell the employee.
     *
     * @param \stdClass $client
     * @param int $employeeid 0 to unassign
     */
    public static function assign(\stdClass $client, int $employeeid): void {
        global $DB;
        $old = (int) $client->employeeid;
        if ($old === $employeeid) {
            return;
        }
        $DB->set_field('local_placement_client', 'employeeid', $employeeid, ['id' => $client->id]);
        $name = fn(int $id) => $id ? fullname(\core_user::get_user($id)) : '-';
        audit::log($old ? 'client_reassign' : 'client_assign', 'client', $client->id, $client->id,
            $name($old) . ' -> ' . $name($employeeid));
        $client->employeeid = $employeeid;
        if ($employeeid) {
            notify::send('work', $employeeid, get_string('msg_assigned_subject', 'local_placement'),
                get_string('msg_assigned_body', 'local_placement', fullname($client->user)),
                new \moodle_url('/local/placement/client.php', ['id' => $client->id]));
        }
    }

    /**
     * Placement employees (active and suspended), for menus.
     *
     * @param bool $activeonly
     * @return array id => name
     */
    public static function employees(bool $activeonly = true): array {
        $out = [];
        foreach (get_users_by_capability(\context_system::instance(), 'local/placement:employee',
                'u.id, u.firstname, u.lastname, u.suspended, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
                'u.firstname, u.lastname') as $user) {
            if ($activeonly && $user->suspended) {
                continue;
            }
            if (!has_capability('local/placement:manage', \context_system::instance(), $user->id)) {
                $out[$user->id] = fullname($user);
            }
        }
        return $out;
    }
}
