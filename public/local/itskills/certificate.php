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

/**
 * Certificate of a course: view and download for learners, issue and review for admins.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\certificates;

$courseid = required_param('courseid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
$canmanage = has_capability('local/itskills:managecertificates', $context);

if ($userid && $userid != $USER->id) {
    require_capability('local/itskills:managecertificates', $context);
} else {
    $userid = $USER->id;
}
$user = core_user::get_user($userid, '*', MUST_EXIST);

$url = new moodle_url('/local/itskills/certificate.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('certificate', 'local_itskills') . ' | ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('itskills_certificate');

if (!certificates::enabled_for($course)) {
    throw new moodle_exception('certnotenabled', 'local_itskills');
}

if ($action === 'issue') {
    require_capability('local/itskills:managecertificates', $context);
    require_sesskey();
    certificates::issue_if_eligible($userid, $course->id, true);
    redirect($url, get_string('certissued', 'local_itskills'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$cert = certificates::get($userid, $course->id);
if (!$cert && $userid == $USER->id) {
    // Issued the moment the learner opens the page after becoming eligible.
    $cert = certificates::issue_if_eligible($userid, $course->id);
}
if ($action === 'download' || $action === 'view') {
    if (!$cert) {
        throw new moodle_exception('certnotyet', 'local_itskills');
    }
    certificates::output_pdf($user, $course, $cert, $action === 'view');
    die;
}

$learners = [];
if ($canmanage) {
    foreach (get_enrolled_users($context, 'mod/assign:submit', 0, 'u.*', 'u.lastname, u.firstname') as $u) {
        $c = certificates::get($u->id, $course->id);
        $learners[] = [
            'name' => fullname($u),
            'progress' => (int) round(\core_completion\progress::get_course_progress_percentage($course, $u->id) ?? 0),
            'issued' => (bool) $c,
            'code' => $c ? $c->code : '',
            'override' => $c && $c->override,
            'date' => $c ? userdate($c->timeissued, get_string('strftimedate', 'langconfig')) : '',
            'eligible' => !$c && certificates::eligible($u->id, $course),
            'downloadurl' => (new moodle_url($url, ['userid' => $u->id, 'action' => 'download']))->out(false),
            'issueurl' => (new moodle_url($url, ['userid' => $u->id, 'action' => 'issue', 'sesskey' => sesskey()]))->out(false),
            'confirmissue' => get_string('confirmissue', 'local_itskills', fullname($u)),
        ];
    }
}

$design = certificates::design();
$vars = $cert ? certificates::vars($user, $course, $cert) : [];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/certificate', [
    'issued' => (bool) $cert,
    'student' => $vars['student'] ?? fullname($user),
    'title' => $cert ? certificates::fill($design['title'], $vars) : '',
    'subtitle' => $cert ? certificates::fill($design['subtitle'], $vars) : '',
    'body' => $cert ? certificates::fill($design['body'], $vars) : '',
    'date' => $vars['date'] ?? '',
    'code' => $cert->code ?? '',
    'signname' => certificates::fill($design['signname'], $vars),
    'signtitle' => certificates::fill($design['signtitle'], $vars),
    'primary' => $design['primary'],
    'accent' => $design['accent'],
    'gold' => $design['gold'],
    'logourl' => certificates::design_file_url('cert_logo') ?: $OUTPUT->image_url('logo', 'theme_insuretech')->out(false),
    'bgurl' => certificates::design_file_url('cert_bg'),
    'signurl' => certificates::design_file_url('cert_sign'),
    'downloadurl' => (new moodle_url($url, ['action' => 'download']))->out(false),
    'viewurl' => (new moodle_url($url, ['action' => 'view']))->out(false),
    'verifyurl' => $cert ? certificates::verify_url($cert->code)->out(false) : '',
    'progress' => (int) round(\core_completion\progress::get_course_progress_percentage($course, $userid) ?? 0),
    'rule' => get_config('local_itskills', 'certrule') === 'progress'
        ? get_string('certrule_progress_text', 'local_itskills', (int) (get_config('local_itskills', 'certprogress') ?: 100))
        : get_string('certrule_completion_text', 'local_itskills'),
    'canmanage' => $canmanage,
    'learners' => $learners,
    'haslearners' => !empty($learners),
    'designurl' => is_siteadmin() ? (new moodle_url('/admin/category.php', ['category' => 'local_itskills']))->out(false) : '',
]);
echo $OUTPUT->footer();
