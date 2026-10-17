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
 * Create the "Multi-Cloud with DevOps & GenAI" programme: modules, lessons, recordings, quizzes with tagged
 * questions and explanations, labs, a capstone, completion rules, a pass-to-unlock rule, a batch and skills.
 *
 * Usage: php local/itskills/cli/create_multicloud_course.php [--category=ID] [--trainer=USERNAME]
 *
 * Everything it creates can be edited afterwards in the course like any other content.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
require_once($CFG->dirroot . '/completion/completion_aggregation.php');

[$options] = cli_get_params(['category' => 0, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "Creates the self-paced Cloud & DevOps Fundamentals sample course.\n--category=ID  Course category (default: Cloud Computing or the default category)\n";
    exit(0);
}

\core\session\manager::set_user(get_admin());
$gen = testing_util::get_data_generator();

if ($DB->record_exists('course', ['shortname' => 'CLOUD-DEVOPS-SP'])) {
    cli_error('The course CLOUD-DEVOPS-SP already exists.');
}

$categoryid = (int) $options['category'];
if (!$categoryid) {
    $categoryid = (int) ($DB->get_field('course_categories', 'id', ['name' => 'Cloud Computing'], IGNORE_MULTIPLE)
        ?: core_course_category::get_default()->id);
}

$p = fn(string ...$lines) => implode('', array_map(fn($l) => "<p>$l</p>", $lines));
$ul = fn(array $items) => '<ul>' . implode('', array_map(fn($i) => "<li>$i</li>", $items)) . '</ul>';

$course = $gen->create_course([
    'fullname' => 'Cloud & DevOps Fundamentals',
    'shortname' => 'CLOUD-DEVOPS-SP',
    'category' => $categoryid,
    'summary' => $p('A self-paced introduction to cloud computing, containers and automation. Watch short video lessons, '
        . 'read the notes, check your understanding with quizzes and earn your insureTech Skills certificate, all at your own pace.'),
    'summaryformat' => FORMAT_HTML,
    'format' => 'topics',
    'numsections' => 3,
    'enablecompletion' => 1,
    'showcompletionconditions' => 1,
    'startdate' => strtotime('today'),
]);
echo "Course {$course->id} created\n";

\core_course\customfield\course_handler::create()->instance_form_save((object) [
    'id' => $course->id,
    'customfield_itlevel' => 1,
    'customfield_itdelivery' => 2,
    'customfield_itoutcomes_editor' => ['text' => $ul([
        'Explain cloud computing, its service models and why companies use it',
        'Understand the core AWS building blocks',
        'Package applications with Docker and know what Kubernetes does',
        'Describe Infrastructure as Code and CI/CD pipelines',
    ]), 'format' => FORMAT_HTML],
    'customfield_itprereq_editor' => ['text' => $ul([
        'No prior cloud experience needed',
        'Basic computer and internet skills',
    ]), 'format' => FORMAT_HTML],
]);

$sections = [
    1 => 'Module 1: Cloud Computing Basics',
    2 => 'Module 2: Containers & Kubernetes',
    3 => 'Module 3: Automation with IaC and CI/CD',
];
foreach ($sections as $num => $name) {
    $DB->set_field('course_sections', 'name', $name, ['course' => $course->id, 'section' => $num]);
}

$cmids = [];
$page = function(int $section, string $name, string $html) use ($gen, $course, &$cmids) {
    $m = $gen->create_module('page', [
        'course' => $course->id, 'section' => $section, 'name' => $name, 'content' => $html, 'contentformat' => FORMAT_HTML,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1, 'display' => RESOURCELIB_DISPLAY_OPEN,
        'printintro' => 0, 'printlastmodified' => 0,
    ]);
    $cmids[] = $m->cmid;
    return $m;
};
$recording = function(int $section, string $name, string $duration, string $url) use ($gen, $course, &$cmids) {
    $m = $gen->create_module('itrecording', [
        'course' => $course->id, 'section' => $section, 'name' => $name, 'duration' => $duration, 'videourl' => $url,
        'intro' => '<p>Sample video. Admin: replace the video link with your Google Drive class recording.</p>',
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
    ]);
    $cmids[] = $m->cmid;
    return $m;
};
$assign = function(int $section, string $name, string $intro, int $duedays) use ($gen, $course, &$cmids) {
    $m = $gen->create_module('assign', [
        'course' => $course->id, 'section' => $section, 'name' => $name, 'intro' => $intro, 'introformat' => FORMAT_HTML,
        'duedate' => time() + $duedays * DAYSECS, 'grade' => 100,
        'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => 1,
        'assignsubmission_file_maxfiles' => 5, 'submissiondrafts' => 0, 'sendnotifications' => 1,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1,
    ]);
    $cmids[] = $m->cmid;
    return $m;
};
/**
 * A quiz with tagged questions. Each question: [type, text, answers (correct first) or bool, explanation, tag].
 */
$quiz = function(int $section, string $name, array $questions, int $attempts) use ($gen, $course, &$cmids) {
    global $DB;
    $m = $gen->create_module('quiz', [
        'course' => $course->id, 'section' => $section, 'name' => $name,
        'intro' => '<p>Pass mark 70%. Your best attempt counts. After each attempt you see the correct answers and explanations.</p>',
        'grade' => 100, 'sumgrades' => count($questions), 'attempts' => $attempts, 'grademethod' => QUIZ_GRADEHIGHEST,
        'preferredbehaviour' => 'deferredfeedback', 'gradepass' => 70,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionusegrade' => 1, 'completionpassgrade' => 1,
    ]);
    $cmids[] = $m->cmid;
    $context = context_module::instance($m->cmid);
    $cat = question_get_default_category($context->id, true);
    $empty = ['text' => '', 'format' => FORMAT_HTML];
    foreach ($questions as $i => [$type, $text, $answers, $explanation, $tag]) {
        $form = [
            'category' => $cat->id . ',' . $cat->contextid,
            'name' => 'Q' . ($i + 1) . ' · ' . $tag,
            'questiontext' => ['text' => $text, 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => $explanation, 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
            'showstandardinstruction' => 0,
        ];
        if ($type === 'tf') {
            $qtype = 'truefalse';
            $form += ['correctanswer' => $answers ? 1 : 0, 'feedbacktrue' => $empty, 'feedbackfalse' => $empty, 'penalty' => 1];
        } else {
            $qtype = 'multichoice';
            $multi = $type === 'multi';
            $correct = $multi ? $answers['correct'] : 1;
            $options = $multi ? $answers['options'] : $answers;
            $answerdata = [];
            $fractions = [];
            $feedback = [];
            foreach ($options as $j => $option) {
                $answerdata[] = ['text' => $option, 'format' => FORMAT_PLAIN];
                $fractions[] = $j < $correct ? ($multi ? (string) round(1 / $correct, 7) : '1.0') : ($multi ? '-1.0' : '0.0');
                $feedback[] = $empty;
            }
            $form += [
                'answer' => $answerdata, 'fraction' => $fractions, 'feedback' => $feedback,
                'single' => $multi ? 0 : 1, 'shuffleanswers' => 1, 'answernumbering' => 'abc', 'penalty' => 0.3333333,
                'correctfeedback' => ['text' => 'Correct!', 'format' => FORMAT_HTML],
                'partiallycorrectfeedback' => ['text' => 'Partly correct.', 'format' => FORMAT_HTML],
                'incorrectfeedback' => ['text' => 'Not quite. Read the explanation below.', 'format' => FORMAT_HTML],
                'shownumcorrect' => 1,
            ];
        }
        $q = question_bank::get_qtype($qtype)->save_question(
            (object) ['category' => $cat->id, 'qtype' => $qtype, 'createdby' => get_admin()->id, 'modifiedby' => get_admin()->id],
            (object) $form);
        core_tag_tag::set_item_tags('core_question', 'question', $q->id, $context, [$tag]);
        quiz_add_quiz_question($q->id, $DB->get_record('quiz', ['id' => $m->id]), 0, 1);
    }
    return $m;
};

// General.
$page(0, 'Welcome & how this course works', $p(
    'Welcome to <strong>Cloud &amp; DevOps Fundamentals</strong>! This course is <strong>self-paced</strong>: there are no fixed class times, so you can start today and study whenever it suits you.',
    '<strong>How it works:</strong> go through each module in order: read the lessons, watch the videos and take the quiz. Pass each module quiz (70%) to unlock the next module.',
    '<strong>Certificate:</strong> complete every lesson and video and pass all quizzes to earn your insureTech Skills certificate, verifiable online.'
));

// Module 1.
$page(1, 'What is cloud computing?', $p(
    'Cloud computing means renting computing power, storage and services over the internet instead of buying and running your own servers. You pay for what you use and can scale up or down in minutes.',
    'The three service models are <strong>IaaS</strong> (virtual machines and networks), <strong>PaaS</strong> (managed platforms for your code) and <strong>SaaS</strong> (ready-to-use software).'
));
$recording(1, 'Video: What is cloud computing?', '6m', 'https://www.youtube.com/watch?v=M988_fsOSWo');
$page(1, 'AWS building blocks', $p(
    'Amazon Web Services is the largest cloud provider. Its core services are <strong>EC2</strong> (virtual servers), <strong>S3</strong> (object storage), <strong>VPC</strong> (private networks), <strong>IAM</strong> (users and permissions) and <strong>RDS</strong> (managed databases).'
));
$recording(1, 'Video: AWS in 100 seconds', '2m', 'https://www.youtube.com/watch?v=ZzI9JE0i6Lc');
$quiz1 = $quiz(1, 'Module 1 quiz: Cloud basics', [
    ['single', 'Which service model gives you virtual machines that you manage yourself?', ['IaaS', 'PaaS', 'SaaS', 'FaaS'], 'Infrastructure as a Service gives you raw compute, storage and networking; you manage the operating system and above.', 'Cloud service models'],
    ['single', 'Which AWS service stores files as objects?', ['Amazon S3', 'Amazon EC2', 'AWS IAM', 'Amazon VPC'], 'S3 (Simple Storage Service) is AWS object storage.', 'AWS'],
    ['tf', 'In the cloud you usually pay only for the resources you use.', true, 'Pay-as-you-go pricing is one of the main benefits of cloud computing.', 'Cloud service models'],
    ['single', 'Which AWS service controls who can access which resources?', ['AWS IAM', 'Amazon RDS', 'Amazon S3', 'Amazon CloudFront'], 'Identity and Access Management (IAM) manages users, roles and permissions.', 'AWS'],
], 0);

// Module 2.
$page(2, 'Containers with Docker', $p(
    'A container packages an application with everything it needs to run, so it behaves the same on a laptop, a test server and in production. Docker builds container images from a Dockerfile and runs them.'
));
$recording(2, 'Video: Docker in 100 seconds', '2m', 'https://www.youtube.com/watch?v=Gjnup-PuquQ');
$page(2, 'Kubernetes in a nutshell', $p(
    'Kubernetes runs containers across many servers. You describe what you want (for example "3 copies of my web app") and Kubernetes keeps it that way, restarting failed containers and spreading load.'
));
$recording(2, 'Video: Kubernetes explained', '2m', 'https://www.youtube.com/watch?v=PziYflu8cB8');
$quiz2 = $quiz(2, 'Module 2 quiz: Containers', [
    ['single', 'What does a Docker image contain?', ['An application and everything it needs to run', 'Only the source code', 'A full virtual machine with its own hardware', 'A database backup'], 'An image bundles the app, runtime, libraries and settings.', 'Docker'],
    ['single', 'What is the main job of Kubernetes?', ['Running and managing containers across many servers', 'Writing Dockerfiles', 'Storing source code', 'Monitoring websites only'], 'Kubernetes orchestrates containers: scheduling, scaling and self-healing.', 'Kubernetes'],
    ['tf', 'A container behaves the same on a laptop and in production.', true, 'Consistency across environments is the key benefit of containers.', 'Docker'],
], 0);

// Module 3.
$page(3, 'Infrastructure as Code with Terraform', $p(
    'Infrastructure as Code means describing servers, networks and databases in text files instead of clicking in a console. Terraform reads those files and creates the same infrastructure on AWS, Azure or Google Cloud every time.'
));
$recording(3, 'Video: Terraform in 100 seconds', '2m', 'https://www.youtube.com/watch?v=tomUWcQ0P3k');
$page(3, 'CI/CD pipelines', $p(
    'Continuous Integration and Continuous Delivery automatically test and deploy every change. A pipeline (for example GitHub Actions) builds the app, runs the tests and releases it, so teams ship small changes safely and often.'
));
$recording(3, 'Video: CI/CD explained', '2m', 'https://www.youtube.com/watch?v=scEDHsr3APg');
$quiz(3, 'Final quiz: Automation', [
    ['single', 'What is Infrastructure as Code?', ['Describing infrastructure in files that tools can apply automatically', 'Writing application code on a server', 'Buying physical servers', 'A type of database'], 'IaC turns infrastructure into versioned, repeatable code.', 'Terraform'],
    ['single', 'What does a CI/CD pipeline do?', ['Automatically builds, tests and deploys each change', 'Stores user passwords', 'Replaces developers', 'Designs the user interface'], 'CI/CD automates the path from a code change to a release.', 'CI/CD'],
    ['tf', 'Terraform can create infrastructure on more than one cloud provider.', true, 'Terraform has providers for AWS, Azure, Google Cloud and many more.', 'Terraform'],
], 0);

rebuild_course_cache($course->id, true);

// Pass-to-unlock: each module opens after the previous module's quiz is passed.
foreach ([2 => $quiz1, 3 => $quiz2] as $section => $q) {
    $restriction = json_encode(['op' => '&', 'showc' => [true],
        'c' => [['type' => 'completion', 'cm' => (int) $q->cmid, 'e' => COMPLETION_COMPLETE_PASS]]]);
    $DB->set_field('course_sections', 'availability', $restriction, ['course' => $course->id, 'section' => $section]);
}
rebuild_course_cache($course->id, true);

// Course completion: every lesson and video viewed and every quiz passed.
$criterion = new completion_criteria_activity();
$criteriadata = (object) ['id' => $course->id, 'criteria_activity' => array_fill_keys($cmids, 1)];
$criterion->update_config($criteriadata);
$aggregation = new completion_aggregation(['course' => $course->id, 'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY]);
$aggregation->setMethod(COMPLETION_AGGREGATION_ALL);
$aggregation->save();
$aggregation = new completion_aggregation(['course' => $course->id, 'criteriatype' => null]);
$aggregation->setMethod(COMPLETION_AGGREGATION_ALL);
$aggregation->save();

echo "Done: course id {$course->id}, " . count($cmids) . " tracked activities\n";
