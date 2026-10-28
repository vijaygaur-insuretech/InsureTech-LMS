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

namespace theme_insuretech\output;

use completion_info;
use context_course;
use core_completion\progress;
use moodle_url;
use stdClass;
use theme_insuretech\local\live_class_fields;

/**
 * Builds the template data for the role-aware panels shown on the Dashboard and course pages.
 *
 * Read-only: everything here comes from existing Moodle APIs (enrolments, completion, groups, assignments).
 * Nothing is written, and access rules are still enforced by Moodle on every link the panels point to.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_panels {
    /** @var int Access ending within this many days is flagged as expiring soon. */
    const EXPIRING_SOON_DAYS = 7;

    /**
     * Whether the user teaches (can see all grades in) the course.
     *
     * @param context_course $context
     * @return bool
     */
    public static function is_trainer(context_course $context): bool {
        return has_capability('moodle/grade:viewall', $context);
    }

    /**
     * Dashboard data for the current user, or null when there is nothing to show.
     *
     * @return array|null
     */
    public static function dashboard(): ?array {
        global $USER;

        $courses = enrol_get_my_courses(['enablecompletion', 'showcompletionconditions'], 'fullname ASC');
        $learning = [];
        $teaching = [];
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            if (self::is_trainer($context)) {
                $teaching[] = $course;
            } else {
                $learning[] = $course;
            }
        }

        $isadmin = is_siteadmin();
        if ($isadmin) {
            // Administrators run every course, whether or not they are enrolled in it.
            $teaching = array_values(array_filter(get_courses('all', 'c.fullname ASC'), fn($c) => $c->id != SITEID));
            $learning = [];
        }

        $data = [
            'greeting' => get_string('dashgreeting', 'theme_insuretech', $USER->firstname),
            'admin' => $isadmin ? self::admin_actions() : null,
            'trainer' => $teaching || $isadmin ? self::trainer($teaching) + ['title' => get_string($isadmin ? 'dashallcourses' : 'mybatches', 'theme_insuretech')] : null,
            'student' => $learning ? self::student($learning) : null,
        ];

        if (!$data['admin'] && !$data['trainer'] && !$data['student']) {
            return null;
        }
        $data['role'] = $data['admin'] ? 'admin' : ($data['trainer'] ? 'trainer' : 'student');
        // Everyone sees the calendar next to their dashboard.
        $data['calendar'] = \theme_insuretech\local\calendar_card::data(optional_param('itcal', '', PARAM_ALPHANUMEXT));
        $data['subtitle'] = get_string('dashsubtitle' . $data['role'], 'theme_insuretech');
        return $data;
    }

    /**
     * Student dashboard data.
     *
     * @param stdClass[] $courses Courses the user is learning in.
     * @return array
     */
    protected static function student(array $courses): array {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $byid = [];
        $live = [];
        $total = 0;
        $completed = 0;
        $notices = [];
        foreach ($courses as $course) {
            $byid[$course->id] = $course;
            $percent = (int) round(progress::get_course_progress_percentage($course, $USER->id) ?? 0);
            $total += $percent;
            if ($percent >= 100) {
                $completed++;
            }
            if ($notice = self::access_notice($course)) {
                $notices[] = $notice;
            }
            if ($class = live_class_fields::for_user($course->id, $USER->id)) {
                $live[] = $class + [
                    'course' => format_string($course->fullname, true, ['context' => context_course::instance($course->id), 'escape' => false]),
                ];
            }
        }

        // Continue learning: the most recently accessed course the user is learning in.
        $resume = null;
        foreach (course_get_recent_courses($USER->id, 10) as $recent) {
            if (isset($byid[$recent->id])) {
                $resume = $byid[$recent->id];
                break;
            }
        }
        $resume = $resume ?? reset($courses);

        $stats = [
            ['value' => count($courses), 'label' => get_string('statenrolled', 'theme_insuretech')],
            ['value' => round($total / count($courses)) . '%', 'label' => get_string('statavgprogress', 'theme_insuretech')],
        ];
        $insights = self::student_insights();
        if ($insights) {
            $stats[] = ['value' => $insights['quizavg'], 'label' => get_string('statquizavg', 'theme_insuretech')];
            $stats[] = ['value' => $insights['attendance'], 'label' => get_string('statattendance', 'theme_insuretech')];
        } else {
            $stats[] = ['value' => $completed, 'label' => get_string('statcompleted', 'theme_insuretech')];
        }

        return [
            'resume' => self::course_progress($resume),
            'stats' => $stats,
            'insights' => $insights,
            'nextclass' => self::next_class(),
            'notices' => $notices,
            'hasnotices' => !empty($notices),
            'liveclasses' => $live,
            'hasliveclasses' => !empty($live),
        ];
    }

    /**
     * Whether the insureTech learning features plugin is installed.
     *
     * @return bool
     */
    public static function has_features(): bool {
        return class_exists('\\local_itskills\\local\\insights');
    }

    /**
     * Quiz average, attendance, weak topics, readiness and certificates for the current learner.
     *
     * @return array|null
     */
    protected static function student_insights(): ?array {
        global $USER;
        if (!self::has_features()) {
            return null;
        }
        $quiz = \local_itskills\local\insights::quiz_average($USER->id);
        $att = \local_itskills\local\attendance::summary($USER->id)['percent'];
        $weak = \local_itskills\local\insights::weak_topics($USER->id, 0, 3);
        $ready = \local_itskills\local\insights::readiness($USER->id)['score'];
        $certs = \local_itskills\local\certificates::for_user($USER->id);
        return [
            'quizavg' => $quiz === null ? '–' : $quiz . '%',
            'attendance' => $att === null ? '–' : $att . '%',
            'weak' => $weak,
            'hasweak' => !empty($weak),
            'readiness' => $ready,
            'hasreadiness' => $ready !== null,
            'certcount' => count($certs),
            'progressurl' => (new moodle_url('/local/itskills/progress.php'))->out(false),
        ];
    }

    /**
     * The next scheduled live class for the current user, from the live class schedule.
     *
     * @return array|null
     */
    protected static function next_class(): ?array {
        global $USER;
        if (!self::has_features()) {
            return null;
        }
        $session = \local_itskills\local\sessions::next_for_user($USER->id);
        if (!$session) {
            return null;
        }
        $end = $session->timestart + $session->duration * MINSECS;
        return [
            'title' => $session->title,
            'course' => $session->coursename,
            'date' => userdate($session->timestart, get_string('strftimedaydate', 'langconfig')),
            'time' => userdate($session->timestart, get_string('strftimetime', 'langconfig')) . ' – '
                . userdate($end, get_string('strftimetime', 'langconfig')),
            'link' => \local_itskills\local\sessions::link($session),
            'live' => $session->timestart - 15 * MINSECS <= time(),
            'allurl' => (new moodle_url('/local/itskills/sessions.php', ['courseid' => $session->courseid]))->out(false),
        ];
    }

    /**
     * Progress data for one course and the current user: percentage, counts, next activity, access end.
     *
     * @param stdClass $course
     * @return array
     */
    public static function course_progress(stdClass $course): array {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/completionlib.php');
        // Callers may pass a partial course record; section names need the full one (format etc.).
        $course = get_course($course->id);

        $percent = (int) round(progress::get_course_progress_percentage($course, $USER->id) ?? 0);
        $done = 0;
        $tracked = 0;
        $next = null;

        $info = new completion_info($course);
        if ($info->is_enabled()) {
            $modinfo = get_fast_modinfo($course);
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible || !$cm->has_view() || $info->is_enabled($cm) == COMPLETION_TRACKING_NONE) {
                    continue;
                }
                $tracked++;
                $state = $info->get_data($cm, true, $USER->id)->completionstate;
                if (in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
                    $done++;
                } else if (!$next) {
                    $next = [
                        'name' => $cm->get_formatted_name(['escape' => false]),
                        'url' => $cm->url ? $cm->url->out(false) : null,
                        'icon' => $cm->get_icon_url()->out(false),
                        'section' => html_entity_decode(get_section_name($course, $cm->sectionnum), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    ];
                }
            }
        }

        return [
            'id' => $course->id,
            'name' => format_string($course->fullname, true, ['context' => context_course::instance($course->id), 'escape' => false]),
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'percent' => $percent,
            'done' => $done,
            'tracked' => $tracked,
            'hastracking' => $tracked > 0,
            'complete' => $tracked > 0 && $done >= $tracked,
            'next' => $next,
            'access' => self::access_notice($course, true),
            'live' => live_class_fields::for_user($course->id, $USER->id),
            'features' => self::has_features() ? [
                'sessionsurl' => (new moodle_url('/local/itskills/sessions.php', ['courseid' => $course->id]))->out(false),
                'certurl' => (new moodle_url('/local/itskills/certificate.php', ['courseid' => $course->id]))->out(false),
                'feedbackurl' => (new moodle_url('/local/itskills/feedback.php', ['courseid' => $course->id]))->out(false),
                'certificate' => (bool) \local_itskills\local\certificates::get($USER->id, $course->id),
            ] : null,
        ];
    }

    /**
     * Describes when the user's access (enrolment end date) to a course ends.
     *
     * @param stdClass $course
     * @param bool $always Return the end date even when it is not close.
     * @return array|null
     */
    protected static function access_notice(stdClass $course, bool $always = false): ?array {
        global $USER;

        $end = enrol_get_enrolment_end($course->id, $USER->id);
        if (!$end) {
            return null;
        }
        $days = (int) ceil(($end - time()) / DAYSECS);
        $soon = $days <= self::EXPIRING_SOON_DAYS;
        if (!$soon && !$always) {
            return null;
        }
        $a = (object) [
            'course' => format_string($course->fullname, true, ['context' => context_course::instance($course->id), 'escape' => false]),
            'date' => userdate($end, get_string('strftimedate', 'langconfig')),
            'days' => max($days, 0),
        ];
        return [
            'soon' => $soon,
            'text' => get_string($soon ? 'accessendssoon' : 'accessuntil', 'theme_insuretech', $a),
        ];
    }

    /**
     * Trainer dashboard data.
     *
     * @param stdClass[] $courses Courses the user teaches.
     * @return array
     */
    protected static function trainer(array $courses): array {
        $items = [];
        $learners = 0;
        $tograde = 0;
        foreach ($courses as $course) {
            $item = self::trainer_course($course);
            $learners += $item['learners'];
            $tograde += $item['tograde'];
            $items[] = $item;
        }
        return [
            'manageurl' => (new moodle_url('/theme/insuretech/manage/classes.php'))->out(false),
            'courses' => $items,
            'stats' => [
                ['value' => count($courses), 'label' => get_string('statcourses', 'theme_insuretech')],
                ['value' => $learners, 'label' => get_string('statlearners', 'theme_insuretech')],
                ['value' => $tograde, 'label' => get_string('stattograde', 'theme_insuretech'), 'highlight' => $tograde > 0],
            ],
        ];
    }

    /**
     * Trainer data for one course: batches (groups), learner count, submissions waiting and quick links.
     *
     * @param stdClass $course
     * @return array
     */
    public static function trainer_course(stdClass $course): array {
        global $DB;

        $context = context_course::instance($course->id);
        $groups = groups_get_all_groups($course->id);
        $batches = array_map(fn($g) => ['name' => format_string($g->name, true, ['context' => $context, 'escape' => false])], array_values($groups));

        $tograde = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
          LEFT JOIN {assign_grades} g
                 ON g.assignment = s.assignment AND g.userid = s.userid AND g.attemptnumber = s.attemptnumber
              WHERE a.course = :courseid AND s.latest = 1 AND s.status = :submitted
                AND (g.id IS NULL OR g.grade IS NULL OR g.grade < 0 OR g.timemodified < s.timemodified)",
            ['courseid' => $course->id, 'submitted' => 'submitted']
        );

        $links = [];
        $add = function(string $key, string $path, array $params, string $icon) use (&$links) {
            $links[] = [
                'label' => get_string('link' . $key, 'theme_insuretech'),
                'url' => (new moodle_url($path, $params))->out(false),
                'icon' => $icon,
            ];
        };
        if (has_capability('moodle/course:viewparticipants', $context)) {
            $add('learners', '/user/index.php', ['id' => $course->id], 'fa-users');
        }
        if (has_capability('moodle/course:managegroups', $context)) {
            $add('batches', '/group/index.php', ['id' => $course->id], 'fa-layer-group');
        }
        if (has_capability('moodle/grade:viewall', $context)) {
            $add('grades', '/grade/report/grader/index.php', ['id' => $course->id], 'fa-check-double');
        }
        if (has_capability('report/progress:view', $context)) {
            $add('progress', '/report/progress/index.php', ['course' => $course->id], 'fa-chart-line');
        }
        if (self::has_features()) {
            $add('sessions', '/local/itskills/sessions.php', ['courseid' => $course->id], 'fa-calendar-days');
            if (has_capability('local/itskills:handover', $context)) {
                $add('handover', '/local/itskills/handover.php', ['courseid' => $course->id], 'fa-right-left');
            }
            if (\local_itskills\local\certificates::enabled_for($course)) {
                $add('certificates', '/local/itskills/certificate.php', ['courseid' => $course->id], 'fa-award');
            }
        }

        $learners = count_enrolled_users($context, 'moodle/course:isincompletionreports', 0, true);
        $classes = array_map(fn($c) => $c + [
            'label' => $c['batch'] ? get_string('joinbatchclass', 'theme_insuretech', $c['batch'])
                : get_string('joinclass', 'theme_insuretech'),
        ], live_class_fields::for_course($course->id));

        return [
            'id' => $course->id,
            'name' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'batches' => $batches,
            'hasbatches' => !empty($batches),
            'learners' => $learners,
            'learnerstext' => get_string($learners == 1 ? 'learnercount' : 'learnerscount', 'theme_insuretech', $learners),
            'tograde' => $tograde,
            'hastograde' => $tograde > 0,
            'links' => $links,
            'classes' => $classes,
            'hasclasses' => !empty($classes),
            'editurl' => has_capability('moodle/course:update', $context)
                ? (new moodle_url('/theme/insuretech/manage/classes.php', [], 'course-' . $course->id))->out(false) : null,
        ];
    }

    /**
     * Shortcuts for site administrators to the screens they use most.
     *
     * @return array
     */
    protected static function admin_actions(): array {
        $actions = [
            ['people', '/theme/insuretech/manage/people.php', [], 'fa-users'],
            ['classes', '/theme/insuretech/manage/classes.php', [], 'fa-video'],
            ['adduser', '/theme/insuretech/manage/addperson.php', [], 'fa-user-plus'],
            ['addcourse', '/course/edit.php', ['category' => \core_course_category::get_default()->id], 'fa-plus'],
            ['courses', '/course/management.php', [], 'fa-book'],
            ['reports', '/admin/category.php', ['category' => 'reports'], 'fa-chart-pie'],
        ];
        if (self::has_features()) {
            $actions[] = ['certdesign', '/admin/settings.php', ['section' => 'local_itskills_certificate'], 'fa-award'];
            $actions[] = ['emails', '/admin/settings.php', ['section' => 'local_itskills_emails'], 'fa-envelope'];
            $actions[] = ['feedback', '/local/itskills/admin/feedback.php', [], 'fa-star'];
            $actions[] = ['skills', '/local/itskills/admin/skills.php', [], 'fa-id-badge'];
        }
        return ['actions' => array_map(fn($a) => [
            'label' => get_string('admin' . $a[0], 'theme_insuretech'),
            'url' => (new moodle_url($a[1], $a[2]))->out(false),
            'icon' => $a[3],
        ], $actions)];
    }
}
