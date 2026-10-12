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
 * Students & trainers: one list of everyone, by type, with their courses, batches and access status.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use theme_insuretech\local\people;

$type = optional_param('type', people::STUDENT, PARAM_ALPHA);
$search = trim(optional_param('search', '', PARAM_NOTAGS));
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;
if (!in_array($type, [people::STUDENT, people::TRAINER, people::UNASSIGNED])) {
    $type = people::STUDENT;
}

$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);

$url = new moodle_url('/theme/insuretech/manage/people.php', ['type' => $type, 'search' => $search]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('managepeople', 'theme_insuretech'));
$PAGE->set_heading(get_string('managepeople', 'theme_insuretech'));
$PAGE->navbar->add(get_string('managepeople', 'theme_insuretech'), $url);

[$users, $total] = people::list($type, $search, $page, $perpage);

$tabs = [];
foreach ([people::STUDENT, people::TRAINER, people::UNASSIGNED] as $t) {
    [, $count] = people::list($t, '', 0, 1);
    $tabs[] = [
        'label' => get_string('tab' . $t, 'theme_insuretech'),
        'count' => $count,
        'url' => (new moodle_url('/theme/insuretech/manage/people.php', ['type' => $t]))->out(false),
        'active' => $t === $type,
    ];
}

$rows = [];
foreach ($users as $user) {
    $assignments = people::assignments($user->id);
    $courses = [];
    foreach ($assignments as $a) {
        $courses[] = [
            'course' => $a['course'],
            'batches' => implode(', ', array_column($a['batches'], 'name')),
            'istrainer' => $a['type'] === people::TRAINER,
            'status' => $a['status'],
            'statuslabel' => get_string('status' . $a['status'], 'theme_insuretech'),
            'showstatus' => $a['type'] === people::STUDENT && $a['status'] !== 'active',
        ];
    }
    $rows[] = [
        'picture' => $OUTPUT->user_picture($user, ['size' => 40, 'link' => false]),
        'name' => fullname($user),
        'email' => $user->email,
        'suspended' => !empty($user->suspended),
        'lastaccess' => $user->lastaccess ? format_time(time() - $user->lastaccess) : get_string('never'),
        'courses' => $courses,
        'hascourses' => !empty($courses),
        'manageurl' => (new moodle_url('/theme/insuretech/manage/person.php', ['id' => $user->id]))->out(false),
    ];
}

$addurl = new moodle_url('/user/editadvanced.php', ['id' => -1]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_insuretech/manage_people', [
    'tabs' => $tabs,
    'type' => $type,
    'search' => $search,
    'formurl' => (new moodle_url('/theme/insuretech/manage/people.php'))->out(false),
    'addurl' => $addurl->out(false),
    'classesurl' => (new moodle_url('/theme/insuretech/manage/classes.php'))->out(false),
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'paging' => $OUTPUT->paging_bar($total, $page, $perpage, $url),
]);
echo $OUTPUT->footer();
