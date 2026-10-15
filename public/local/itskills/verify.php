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
 * Public certificate verification.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_itskills\local\certificates;

$code = trim(optional_param('code', '', PARAM_ALPHANUMEXT));
$url = new moodle_url('/local/itskills/verify.php', $code ? ['code' => $code] : []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout(class_exists('\\theme_insuretech\\local\\landing') ? 'insuretechpublic' : 'standard');
$PAGE->set_title(get_string('verifycert', 'local_itskills'));

$result = null;
if ($code !== '') {
    $cert = $DB->get_record('local_itskills_cert', ['code' => core_text::strtoupper($code)]);
    if ($cert && ($user = core_user::get_user($cert->userid)) && !$user->deleted
            && ($course = $DB->get_record('course', ['id' => $cert->courseid]))) {
        $result = [
            'valid' => true,
            'student' => fullname($user),
            'course' => format_string($course->fullname, true, ['escape' => false]),
            'date' => userdate($cert->timeissued, get_string('strftimedate', 'langconfig')),
            'code' => $cert->code,
        ];
    } else {
        $result = ['valid' => false, 'code' => $code];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_itskills/verify', [
    'actionurl' => (new moodle_url('/local/itskills/verify.php'))->out(false),
    'code' => $code,
    'result' => $result,
    'hasresult' => $result !== null,
]);
echo $OUTPUT->footer();
