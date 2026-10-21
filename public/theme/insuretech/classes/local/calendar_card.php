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

use context_course;
use core_date;
use moodle_url;

/**
 * Dashboard calendar card: a month grid with dots for live classes, deadlines and other events, and an upcoming list.
 *
 * Reads the standard calendar (course, batch, personal and site events), so anything a trainer or admin adds to the
 * calendar, every assignment and quiz deadline, and every scheduled live class shows up here.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_card {
    /**
     * Card data for the current user.
     *
     * @param string $month Month to show as YYYY-MM; empty for the current month
     * @return array
     */
    public static function data(string $month = ''): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $tz = core_date::get_user_timezone_object();
        $today = new \DateTimeImmutable('today', $tz);
        $first = preg_match('/^\d{4}-\d{2}$/', $month)
            ? \DateTimeImmutable::createFromFormat('!Y-m', $month, $tz) : $today->modify('first day of this month');
        $next = $first->modify('first day of next month');

        // Grid starts on the configured first weekday and ends on the last cell of the last week.
        $startday = (int) calendar_get_starting_weekday();
        $offset = ((int) $first->format('w') - $startday + 7) % 7;
        $gridstart = $first->modify("-{$offset} days");
        $gridend = $next->modify('+' . ((7 - ((int) $next->format('w') - $startday + 7) % 7) % 7) . ' days');

        [$courses, $groups] = self::scope();
        $events = calendar_get_events($gridstart->getTimestamp(), $gridend->getTimestamp() - 1, [$USER->id], $groups,
            $courses, true, true);
        $byday = [];
        foreach ($events as $event) {
            $day = (new \DateTimeImmutable('@' . $event->timestart))->setTimezone($tz)->format('Y-m-d');
            $byday[$day][self::kind($event)] = true;
        }

        $weeks = [];
        $week = [];
        for ($day = $gridstart; $day < $gridend; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $kinds = array_keys($byday[$key] ?? []);
            $week[] = [
                'day' => (int) $day->format('j'),
                'inmonth' => $day->format('Y-m') === $first->format('Y-m'),
                'today' => $key === $today->format('Y-m-d'),
                'hasevents' => !empty($kinds),
                'kinds' => array_map(fn($k) => ['kind' => $k], $kinds),
                'url' => (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $day->getTimestamp()]))->out(false),
                'label' => userdate($day->getTimestamp(), get_string('strftimedaydate', 'langconfig')),
            ];
            if (count($week) === 7) {
                $weeks[] = ['days' => $week];
                $week = [];
            }
        }

        $weekdays = [];
        $names = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        for ($i = 0; $i < 7; $i++) {
            $weekdays[] = ['name' => \core_text::substr(get_string($names[($startday + $i) % 7], 'calendar'), 0, 2)];
        }

        $self = new moodle_url('/my/');
        return [
            'monthlabel' => userdate($first->getTimestamp(), '%B %Y'),
            'weekdays' => $weekdays,
            'weeks' => $weeks,
            'prevurl' => (new moodle_url($self, ['itcal' => $first->modify('-1 month')->format('Y-m')], 'it-calendar'))->out(false),
            'nexturl' => (new moodle_url($self, ['itcal' => $next->format('Y-m')], 'it-calendar'))->out(false),
            'istoday' => $first->format('Y-m') === $today->format('Y-m'),
            'todayurl' => (new moodle_url($self, [], 'it-calendar'))->out(false),
            'fullurl' => (new moodle_url('/calendar/view.php', ['view' => 'month', 'time' => $first->getTimestamp()]))->out(false),
            'upcomingurl' => (new moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false),
            'upcoming' => self::upcoming($courses, $groups),
        ];
    }

    /**
     * Courses and batches whose events the user should see. Trainers see every batch of the courses they teach.
     *
     * @return array [course ids, group ids]
     */
    protected static function scope(): array {
        global $USER;
        $courses = [SITEID];
        $groups = [];
        foreach (enrol_get_my_courses(['id']) as $course) {
            $courses[] = $course->id;
            $all = has_capability('moodle/site:accessallgroups', context_course::instance($course->id));
            $groups = array_merge($groups, array_keys(groups_get_all_groups($course->id, $all ? 0 : $USER->id)));
        }
        return [$courses, $groups ?: false];
    }

    /**
     * Kind of event, for its colour: a live class, a deadline, or anything else.
     *
     * @param \stdClass $event
     * @return string
     */
    protected static function kind(\stdClass $event): string {
        if ($event->component === 'local_itskills') {
            return 'class';
        }
        if (!empty($event->modulename) && in_array($event->eventtype, ['due', 'close', 'gradingdue', 'expectcompletionon'])) {
            return 'deadline';
        }
        return 'event';
    }

    /**
     * The next events in the coming 30 days.
     *
     * @param int[] $courses
     * @param int[]|false $groups
     * @return array[]
     */
    protected static function upcoming(array $courses, $groups): array {
        global $USER;
        $now = time();
        $events = calendar_get_events($now, $now + 30 * DAYSECS, [$USER->id], $groups, $courses, true, true);
        usort($events, fn($a, $b) => $a->timestart <=> $b->timestart);
        $items = [];
        foreach (array_slice($events, 0, 6) as $event) {
            $kind = self::kind($event);
            if ($kind === 'class') {
                $url = new moodle_url('/local/itskills/mylive.php');
            } else if (!empty($event->modulename) && ($cm = get_coursemodule_from_instance($event->modulename, $event->instance))) {
                $url = new moodle_url('/mod/' . $event->modulename . '/view.php', ['id' => $cm->id]);
            } else {
                $url = new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $event->timestart]);
            }
            $course = $event->courseid != SITEID ? get_course($event->courseid) : null;
            $items[] = [
                'name' => format_string($event->name, true, ['escape' => false]),
                'course' => $course ? format_string($course->shortname, true, ['escape' => false]) : '',
                'day' => userdate($event->timestart, '%d'),
                'month' => userdate($event->timestart, '%b'),
                'time' => userdate($event->timestart, get_string('strftimetime', 'langconfig')),
                'kind' => $kind,
                'icon' => ['class' => 'fa-video', 'deadline' => 'fa-flag', 'event' => 'fa-calendar-day'][$kind],
                'url' => $url->out(false),
            ];
        }
        return $items;
    }
}
