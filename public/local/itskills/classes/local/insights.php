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
 * Learner insights from real records: quiz average, assignments, weak topics, Skill Passport and Career Readiness.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class insights {
    /**
     * Average quiz grade (percent) across a learner's quizzes.
     *
     * @param int $userid
     * @param int $courseid 0 for all courses.
     * @return int|null
     */
    public static function quiz_average(int $userid, int $courseid = 0): ?int {
        global $DB;
        $sql = "SELECT AVG((gg.finalgrade - gi.grademin) / NULLIF(gi.grademax - gi.grademin, 0) * 100) AS avg
                  FROM {grade_items} gi
                  JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE gi.itemtype = 'mod' AND gi.itemmodule = 'quiz' AND gg.finalgrade IS NOT NULL"
            . ($courseid ? ' AND gi.courseid = :courseid' : '');
        $avg = $DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
        return $avg === false || $avg === null ? null : (int) round($avg);
    }

    /**
     * Assignments of a learner with status PENDING / SUBMITTED / REVIEWED.
     *
     * @param int $userid
     * @param int $courseid 0 for all courses.
     * @return array ['items' => array[], 'pending' => int, 'submitted' => int, 'reviewed' => int]
     */
    public static function assignments(int $userid, int $courseid = 0): array {
        global $DB;
        $courses = $courseid ? [$courseid] : array_keys(enrol_get_all_users_courses($userid, true));
        $out = ['items' => [], 'pending' => 0, 'submitted' => 0, 'reviewed' => 0];
        if (!$courses) {
            return $out;
        }
        [$in, $params] = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['userid2'] = $userid;
        $rows = $DB->get_records_sql("SELECT a.id, a.course, a.name, a.duedate, s.status AS substatus, g.grade
                                        FROM {assign} a
                                   LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = :userid AND s.latest = 1
                                   LEFT JOIN {assign_grades} g ON g.assignment = a.id AND g.userid = :userid2
                                             AND g.attemptnumber = COALESCE(s.attemptnumber, 0)
                                       WHERE a.course $in
                                    ORDER BY a.duedate", $params);
        foreach ($rows as $row) {
            $cm = get_coursemodule_from_instance('assign', $row->id, $row->course);
            if (!$cm || !$cm->visible) {
                continue;
            }
            if ($row->grade !== null && $row->grade >= 0) {
                $status = 'reviewed';
            } else if ($row->substatus === 'submitted') {
                $status = 'submitted';
            } else {
                $status = 'pending';
            }
            $out[$status]++;
            $out['items'][] = [
                'name' => format_string($row->name, true, ['escape' => false]),
                'url' => (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
                'due' => $row->duedate ? userdate($row->duedate, get_string('strftimedatetimeshort', 'langconfig')) : '',
                'status' => $status,
                'statuslabel' => get_string('assign_' . $status, 'local_itskills'),
            ];
        }
        return $out;
    }

    /**
     * Topics where a learner got questions wrong in their latest attempt of each quiz, so a successful retry clears them.
     * Topic = the question's tags, or the quiz's module name when the question has no tag.
     *
     * @param int $userid
     * @param int $courseid 0 for all courses.
     * @param int $limit
     * @return array[] ['topic', 'mistakes', 'quizurl', 'reviewurl', 'quizname']
     */
    public static function weak_topics(int $userid, int $courseid = 0, int $limit = 5): array {
        global $DB;
        $params = ['userid' => $userid, 'courseid' => $courseid];
        $sql = "SELECT qa.id, qa.questionid, q.id AS quizid, q.course, q.name AS quizname, laststep.state
                  FROM {quiz_attempts} quiza
                  JOIN {quiz} q ON q.id = quiza.quiz
                  JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                  JOIN {question_attempt_steps} laststep ON laststep.questionattemptid = qa.id
                       AND laststep.sequencenumber = (SELECT MAX(s2.sequencenumber) FROM {question_attempt_steps} s2
                                                       WHERE s2.questionattemptid = qa.id)
                 WHERE quiza.userid = :userid AND quiza.state = 'finished' AND quiza.preview = 0
                       AND laststep.state IN ('gradedwrong', 'gradedpartial')
                       AND quiza.attempt = (SELECT MAX(a2.attempt) FROM {quiz_attempts} a2
                                             WHERE a2.quiz = quiza.quiz AND a2.userid = quiza.userid
                                                   AND a2.state = 'finished' AND a2.preview = 0)"
            . ($courseid ? ' AND q.course = :courseid' : '');
        $wrong = $DB->get_records_sql($sql, $params);
        if (!$wrong) {
            return [];
        }
        $topics = [];
        foreach ($wrong as $row) {
            $tags = \core_tag_tag::get_item_tags_array('core_question', 'question', $row->questionid);
            $cm = get_coursemodule_from_instance('quiz', $row->quizid, $row->course);
            if (!$tags && $cm) {
                $section = get_fast_modinfo($row->course)->get_section_info_by_id($cm->section);
                $tags = [html_entity_decode(get_section_name($row->course, $section), ENT_QUOTES | ENT_HTML5, 'UTF-8')];
            }
            foreach ($tags as $tag) {
                $key = \core_text::strtolower($tag);
                if (!isset($topics[$key])) {
                    $topics[$key] = ['topic' => $tag, 'mistakes' => 0, 'quizname' => format_string($row->quizname, true,
                        ['escape' => false]), 'quizurl' => $cm ? (new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false) : '',
                        'reviewurl' => $cm ? self::review_url($cm) : ''];
                }
                $topics[$key]['mistakes']++;
            }
        }
        usort($topics, fn($a, $b) => $b['mistakes'] <=> $a['mistakes']);
        return array_slice(array_values($topics), 0, $limit);
    }

    /**
     * First lesson in the quiz's module, as the "review lesson" link.
     *
     * @param \stdClass $quizcm
     * @return string
     */
    protected static function review_url(\stdClass $quizcm): string {
        $modinfo = get_fast_modinfo($quizcm->course);
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->section == $quizcm->section && $cm->modname !== 'quiz' && $cm->has_view() && $cm->uservisible && $cm->url) {
                return $cm->url->out(false);
            }
        }
        return (new \moodle_url('/course/view.php', ['id' => $quizcm->course]))->out(false);
    }

    /**
     * Average assignment grade (percent).
     *
     * @param int $userid
     * @param int $courseid
     * @return int|null
     */
    public static function assignment_average(int $userid, int $courseid = 0): ?int {
        global $DB;
        $sql = "SELECT AVG((gg.finalgrade - gi.grademin) / NULLIF(gi.grademax - gi.grademin, 0) * 100)
                  FROM {grade_items} gi
                  JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE gi.itemtype = 'mod' AND gi.itemmodule = 'assign' AND gg.finalgrade IS NOT NULL"
            . ($courseid ? ' AND gi.courseid = :courseid' : '');
        $avg = $DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
        return $avg === false || $avg === null ? null : (int) round($avg);
    }

    /**
     * Weights from the settings, normalised to sum 1 over the factors that have data.
     *
     * @param array $weights name => weight
     * @param array $values name => value|null
     * @return int|null Weighted score.
     */
    protected static function weighted(array $weights, array $values): ?int {
        $sum = 0;
        $total = 0;
        foreach ($weights as $name => $weight) {
            if ($values[$name] === null || $weight <= 0) {
                continue;
            }
            $sum += $weight * $values[$name];
            $total += $weight;
        }
        return $total ? (int) round($sum / $total) : null;
    }

    /**
     * Score of a learner in one course from progress, quizzes and assignments (Skill Passport weights).
     *
     * @param int $userid
     * @param \stdClass $course
     * @return int|null
     */
    public static function course_score(int $userid, \stdClass $course): ?int {
        $values = [
            'progress' => (int) round(\core_completion\progress::get_course_progress_percentage($course, $userid) ?? 0),
            'quiz' => self::quiz_average($userid, $course->id),
            'assign' => self::assignment_average($userid, $course->id),
        ];
        return self::weighted([
            'progress' => (float) get_config('local_itskills', 'skill_wprogress'),
            'quiz' => (float) get_config('local_itskills', 'skill_wquiz'),
            'assign' => (float) get_config('local_itskills', 'skill_wassign'),
        ], $values);
    }

    /**
     * Skill Passport: each admin-defined skill with the learner's score in the courses mapped to it.
     *
     * @param int $userid
     * @return array[]
     */
    public static function skills(int $userid): array {
        global $DB;
        $enrolled = enrol_get_all_users_courses($userid, true);
        $out = [];
        foreach ($DB->get_records('local_itskills_skill', null, 'sortorder, name') as $skill) {
            $scores = [];
            foreach (array_filter(array_map('intval', explode(',', (string) $skill->courseids))) as $courseid) {
                if (isset($enrolled[$courseid])) {
                    $score = self::course_score($userid, get_course($courseid));
                    if ($score !== null) {
                        $scores[] = $score;
                    }
                }
            }
            if (!$scores) {
                continue;
            }
            $score = (int) round(array_sum($scores) / count($scores));
            $out[] = ['name' => format_string($skill->name, true, ['escape' => false]), 'score' => $score,
                'level' => get_string($score >= 80 ? 'skill_strong' : ($score >= 50 ? 'skill_developing' : 'skill_starting'),
                    'local_itskills')];
        }
        return $out;
    }

    /**
     * Career Readiness: weighted score across all courses, its factors and suggestions.
     *
     * @param int $userid
     * @return array ['score' => int|null, 'factors' => array[], 'suggestions' => string[]]
     */
    public static function readiness(int $userid): array {
        $courses = enrol_get_all_users_courses($userid, true, ['enablecompletion']);
        $progress = [];
        foreach ($courses as $course) {
            $progress[] = \core_completion\progress::get_course_progress_percentage($course, $userid) ?? 0;
        }
        $assign = self::assignments($userid);
        $assigntotal = count($assign['items']);
        $values = [
            'progress' => $progress ? (int) round(array_sum($progress) / count($progress)) : null,
            'quiz' => self::quiz_average($userid),
            'assign' => $assigntotal ? (int) round(100 * ($assign['submitted'] + $assign['reviewed']) / $assigntotal) : null,
            'attendance' => attendance::summary($userid)['percent'],
        ];
        $weights = [];
        foreach (array_keys($values) as $name) {
            $weights[$name] = (float) get_config('local_itskills', 'ready_w' . $name);
        }
        $factors = [];
        $suggestions = [];
        foreach ($values as $name => $value) {
            if ($weights[$name] <= 0) {
                continue;
            }
            $factors[] = ['name' => get_string('factor_' . $name, 'local_itskills'),
                'value' => $value === null ? null : $value, 'hasvalue' => $value !== null];
            if ($value !== null && $value < 70) {
                $suggestions[] = get_string('suggest_' . $name, 'local_itskills');
            }
        }
        return ['score' => self::weighted($weights, $values), 'factors' => $factors, 'suggestions' => $suggestions,
            'hassuggestions' => !empty($suggestions)];
    }
}
