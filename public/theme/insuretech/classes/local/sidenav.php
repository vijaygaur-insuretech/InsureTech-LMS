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

namespace theme_insuretech\local;

use moodle_page;
use moodle_url;

/**
 * Learner sidebar: Dashboard, My courses, Live classes, Assignments, Calendar and Certificates.
 *
 * Shown on every signed-in page for learners and trainers (site administrators keep the standard navigation).
 * On wide screens it is a fixed left column; on phones it becomes a bottom tab bar.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sidenav {
    /** @var string[] Page layouts that never get the sidebar. */
    const SKIP_LAYOUTS = ['login', 'maintenance', 'embedded', 'popup', 'print', 'redirect', 'frontpage', 'insuretechpublic',
        'secure'];

    /**
     * Whether the sidebar belongs on this page for the current user.
     *
     * @param moodle_page $page
     * @return bool
     */
    public static function applies(moodle_page $page): bool {
        if (during_initial_install() || !isloggedin() || isguestuser() || is_siteadmin()) {
            return false;
        }
        if (in_array($page->pagelayout, self::SKIP_LAYOUTS)) {
            return false;
        }
        // Users being forced to change their password or accept policies see no navigation.
        return !get_user_preferences('auth_forcepasswordchange', false);
    }

    /**
     * Sidebar items with the current one marked.
     *
     * @param moodle_page $page
     * @return array
     */
    public static function data(moodle_page $page): array {
        $items = [
            ['key' => 'dashboard', 'icon' => 'fa-gauge-high', 'url' => new moodle_url('/my/'), 'match' => ['/my/index.php', '/my/']],
            ['key' => 'mycourses', 'icon' => 'fa-book-open', 'url' => new moodle_url('/my/courses.php'),
                'match' => ['/my/courses.php', '/course/view.php', '/mod/']],
            ['key' => 'liveclasses', 'icon' => 'fa-video', 'url' => new moodle_url('/local/itskills/mylive.php'),
                'match' => ['/local/itskills/mylive.php', '/local/itskills/sessions.php']],
            ['key' => 'assignments', 'icon' => 'fa-list-check', 'url' => new moodle_url('/local/itskills/myassignments.php'),
                'match' => ['/local/itskills/myassignments.php', '/mod/assign/']],
            ['key' => 'calendar', 'icon' => 'fa-calendar-days', 'url' => new moodle_url('/calendar/view.php', ['view' => 'month']),
                'match' => ['/calendar/']],
            ['key' => 'certificates', 'icon' => 'fa-award', 'url' => new moodle_url('/local/itskills/mycertificates.php'),
                'match' => ['/local/itskills/mycertificates.php', '/local/itskills/certificate.php']],
        ];
        $path = $page->url ? $page->url->get_path() : '';
        // The most specific match wins, so an assignment page highlights Assignments, not My courses.
        $best = ['key' => '', 'length' => 0];
        foreach ($items as $item) {
            foreach ($item['match'] as $match) {
                if (str_contains($path, $match) && strlen($match) > $best['length']) {
                    $best = ['key' => $item['key'], 'length' => strlen($match)];
                }
            }
        }
        $out = [];
        foreach ($items as $item) {
            $out[] = [
                'url' => $item['url']->out(false),
                'icon' => $item['icon'],
                'label' => get_string('nav_' . $item['key'], 'theme_insuretech'),
                'active' => $item['key'] === $best['key'],
            ];
        }
        return ['items' => $out];
    }
}
