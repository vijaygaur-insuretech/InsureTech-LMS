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

namespace local_itskills\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Branded emails and notifications. Every subject and body is an admin setting with placeholders.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mailer {
    /** @var bool Set while a flow already sends its own email, so the enrolment observer stays quiet. */
    public static $suppressenrolmentemail = false;

    /**
     * Subject and body of a template, with placeholders replaced.
     *
     * @param string $key Template key: welcome, enrolled, sessionnew, sessionchanged, sessioncancelled, reminder, certificate.
     * @param array $vars Placeholder values, e.g. ['fullname' => 'Priya'].
     * @return array [subject, bodyhtml, bodytext]
     */
    public static function compose(string $key, array $vars): array {
        global $SITE;
        $vars += [
            'sitename' => format_string($SITE->fullname),
            'loginurl' => (new \moodle_url('/login/index.php'))->out(false),
            'dashboardurl' => (new \moodle_url('/my/'))->out(false),
            'supportemail' => \core_user::get_support_user()->email,
        ];
        $subject = self::setting('email_' . $key . '_subject');
        $body = self::setting('email_' . $key . '_body');
        $replace = [];
        foreach ($vars as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }
        $subject = strtr($subject, $replace);
        $text = strtr($body, $replace);

        // HTML version: escape the text, turn URLs into links and line breaks into paragraphs.
        $escaped = s($text);
        $escaped = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#0D7F97">$1</a>', $escaped);
        $paragraphs = array_map(fn($p) => '<p style="margin:0 0 14px">' . nl2br(trim($p)) . '</p>',
            preg_split('/\n\s*\n/', $escaped));
        $html = self::wrap(implode('', $paragraphs));
        return [$subject, $html, $text];
    }

    /**
     * A setting, or its default from the language file.
     *
     * @param string $name
     * @return string
     */
    public static function setting(string $name): string {
        $value = get_config('local_itskills', $name);
        if ($value !== false && $value !== null && trim($value) !== '') {
            return $value;
        }
        return get_string('default_' . $name, 'local_itskills');
    }

    /**
     * Branded HTML email shell.
     *
     * @param string $content
     * @return string
     */
    protected static function wrap(string $content): string {
        global $CFG, $SITE;
        // A plain file URL: building it through the theme would initialise page output, which emails sent from
        // background tasks and event observers must not do.
        $logo = file_exists($CFG->dirroot . '/theme/insuretech/pix/logo.png')
            ? $CFG->wwwroot . '/theme/insuretech/pix/logo.png'
            : (new \moodle_url('/pix/moodlelogo.png'))->out(false);
        $name = s(format_string($SITE->fullname));
        return '<div style="background:#F4F7FB;padding:24px 12px;font-family:Inter,Segoe UI,Arial,sans-serif;color:#1E2B3B">'
            . '<table role="presentation" width="100%" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;'
            . 'overflow:hidden;border-collapse:collapse">'
            . '<tr><td style="background:#0C1A2B;padding:20px 28px"><img src="' . $logo . '" alt="' . $name
            . '" height="38" style="height:38px;background:#fff;border-radius:6px;padding:4px 8px"></td></tr>'
            . '<tr><td style="padding:28px;font-size:15px;line-height:1.6">' . $content . '</td></tr>'
            . '<tr><td style="padding:16px 28px;background:#F4F7FB;font-size:12px;color:#5B6B7F">' . $name . '</td></tr>'
            . '</table></div>';
    }

    /**
     * Send straight to the user's email (used for login credentials, which must never be stored as a notification).
     *
     * @param \stdClass $user
     * @param string $key
     * @param array $vars
     * @return bool
     */
    public static function email(\stdClass $user, string $key, array $vars): bool {
        [$subject, $html, $text] = self::compose($key, $vars + ['firstname' => $user->firstname, 'fullname' => fullname($user)]);
        return email_to_user($user, \core_user::get_noreply_user(), $subject, $text, $html);
    }

    /**
     * Send a notification (web and/or email, as the user prefers).
     *
     * @param \stdClass $user
     * @param string $provider One of db/messages.php.
     * @param string $key Template key.
     * @param array $vars
     * @param \moodle_url|null $url
     * @param int $courseid
     * @return bool
     */
    public static function notify(\stdClass $user, string $provider, string $key, array $vars, ?\moodle_url $url = null,
            int $courseid = SITEID): bool {
        [$subject, $html, $text] = self::compose($key, $vars + ['firstname' => $user->firstname, 'fullname' => fullname($user)]);
        $message = new \core\message\message();
        $message->component = 'local_itskills';
        $message->name = $provider;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->courseid = $courseid;
        if ($url) {
            $message->contexturl = $url->out(false);
            $message->contexturlname = $subject;
        }
        return (bool) message_send($message);
    }
}
