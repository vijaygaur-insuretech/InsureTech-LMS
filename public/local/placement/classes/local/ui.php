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

use moodle_url;

/**
 * Page set-up and small display helpers shared by the portal pages.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {
    /**
     * Require a placement role and set up the page.
     *
     * @param moodle_url $url
     * @param string $title
     * @param string $tab Active tab key
     */
    public static function setup(moodle_url $url, string $title, string $tab): void {
        global $PAGE;
        require_login(null, false);
        if (!perm::is_admin() && !perm::is_employee() && !perm::is_client()) {
            throw new \moodle_exception('nopermission', 'local_placement');
        }
        $PAGE->set_url($url);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        $PAGE->add_body_class('lp-page');
        self::$tab = $tab;
    }

    /** @var string Active tab. */
    public static $tab = '';

    /**
     * Tabs for the current role.
     *
     * @return array[]
     */
    public static function tabs(): array {
        if (perm::is_admin()) {
            $tabs = ['home' => 'index.php', 'clients' => 'clients.php', 'employees' => 'employees.php',
                'applications' => 'applications.php', 'questions' => 'industries.php', 'permissions' => 'permissions.php',
                'reports' => 'reports.php', 'audit' => 'audit.php'];
        } else if (perm::is_employee()) {
            $tabs = ['home' => 'index.php', 'clients' => 'clients.php', 'applications' => 'applications.php'];
            if (perm::can('feat_daily_reports')) {
                $tabs['dailyreport'] = 'dailyreport.php';
            }
            if (perm::can('act_manage')) {
                $tabs['questions'] = 'industries.php';
            }
            if (perm::can('feat_reports')) {
                $tabs['reports'] = 'reports.php';
            }
        } else {
            $tabs = ['home' => 'index.php', 'myprofile' => 'profile.php', 'myresumes' => 'resume.php',
                'myapplications' => 'applications.php'];
        }
        $out = [];
        foreach ($tabs as $key => $file) {
            $out[] = ['key' => $key, 'label' => get_string('tab_' . $key, 'local_placement'),
                'url' => (new moodle_url('/local/placement/' . $file))->out(false), 'active' => $key === self::$tab];
        }
        return $out;
    }

    /**
     * Page header with the portal tabs.
     *
     * @return string
     */
    public static function header(): string {
        global $OUTPUT;
        return $OUTPUT->header() . $OUTPUT->render_from_template('local_placement/tabs', ['tabs' => self::tabs()]);
    }

    /**
     * Status badge.
     *
     * @param string $status
     * @return string
     */
    public static function status(string $status): string {
        $class = [
            'Applied' => 'lp-st-blue', 'Interview' => 'lp-st-purple', 'Offer Received' => 'lp-st-green',
            'Rejected' => 'lp-st-red', 'Withdrawn' => 'lp-st-grey', 'Closed' => 'lp-st-grey', 'Follow-up Due' => 'lp-st-amber',
            'approved' => 'lp-st-green', 'rejected' => 'lp-st-red', 'replace' => 'lp-st-amber', 'pending' => 'lp-st-grey',
        ][$status] ?? 'lp-st-grey';
        $label = in_array($status, ['approved', 'rejected', 'replace', 'pending'])
            ? get_string('resume_' . $status, 'local_placement') : $status;
        return \html_writer::span(s($label), 'lp-status ' . $class);
    }

    /**
     * Configured list (one per line) from a plugin setting.
     *
     * @param string $name statuses or platforms
     * @return string[]
     */
    public static function lines(string $name): array {
        $value = get_config('local_placement', $name);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value))));
        return $lines ?: ($name === 'statuses' ? setup::STATUSES : setup::PLATFORMS);
    }

    /**
     * Date or a dash.
     *
     * @param int $time
     * @param bool $withtime
     * @return string
     */
    public static function date(int $time, bool $withtime = false): string {
        return $time ? userdate($time, get_string($withtime ? 'strftimedatetimeshort' : 'strftimedate', 'langconfig')) : '–';
    }

    /**
     * A client's profile, section by section. Field groups the viewer may not see are left out and marked.
     *
     * @param \stdClass $client
     * @return string
     */
    public static function profile_html(\stdClass $client): string {
        $bysection = [];
        foreach (fields::all() as $key => $def) {
            $bysection[$def[1]][$key] = $def;
        }
        $out = '';
        foreach (fields::SECTIONS as $section) {
            $dl = '';
            $hidden = false;
            foreach ($bysection[$section] ?? [] as $key => $def) {
                if (!clients::can_see($client, $def[2])) {
                    $hidden = true;
                    continue;
                }
                $value = fields::display($key, $client->data[$key] ?? '');
                $dl .= \html_writer::tag('dt', get_string('f_' . $key, 'local_placement')) .
                    \html_writer::tag('dd', $value === '' ? \html_writer::span('–', 'lp-muted') : ($def[0] === 'url' ?
                        \html_writer::link($value, s($value), ['target' => '_blank', 'rel' => 'noopener']) : nl2br(s($value))));
            }
            if ($section === 'personal' && clients::can_see($client, 'email')) {
                $dl = \html_writer::tag('dt', get_string('email')) . \html_writer::tag('dd', s($client->user->email)) . $dl;
            }
            if ($dl === '' && !$hidden) {
                continue;
            }
            $out .= \html_writer::div(\html_writer::tag('h4', get_string('section_' . $section, 'local_placement'), ['class' => 'lp-card-title']) .
                ($dl ? \html_writer::tag('dl', $dl, ['class' => 'lp-dl']) : '') .
                ($hidden ? \html_writer::div(\html_writer::tag('i', '', ['class' => 'icon fa fa-lock', 'aria-hidden' => 'true']) .
                    get_string('somehidden', 'local_placement'), 'lp-muted small') : ''), 'lp-card');
        }
        return $out;
    }
}
