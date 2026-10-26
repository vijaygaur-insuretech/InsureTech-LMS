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
 * Notifications (in-app and email, as each user prefers). Each type can be switched off in the plugin settings.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify {
    /**
     * Send a notification if its type is enabled.
     *
     * @param string $type resume, application, profile or work
     * @param int $userid
     * @param string $subject
     * @param string $body Plain text
     * @param \moodle_url|null $url
     */
    public static function send(string $type, int $userid, string $subject, string $body, ?\moodle_url $url = null): void {
        if (get_config('local_placement', 'notify_' . $type) === '0') {
            return;
        }
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted || $user->suspended) {
            return;
        }
        $message = new \core\message\message();
        $message->component = 'local_placement';
        $message->name = $type;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body . ($url ? "\n\n" . $url->out(false) : '');
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . nl2br(s($body)) . '</p>' . ($url ? '<p><a href="' . $url->out() . '">' .
            get_string('openportal', 'local_placement') . '</a></p>' : '');
        $message->smallmessage = $subject;
        $message->notification = 1;
        if ($url) {
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('pluginname', 'local_placement');
        }
        try {
            message_send($message);
        } catch (\Throwable $e) {
            debugging('Placement notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
