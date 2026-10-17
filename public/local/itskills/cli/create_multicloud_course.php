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

[$options] = cli_get_params(['category' => 0, 'trainer' => '', 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "Creates the Multi-Cloud with DevOps & GenAI course.\n--category=ID  Course category (default: Cloud Computing or the default category)\n--trainer=USERNAME  Trainer to assign\n";
    exit(0);
}

\core\session\manager::set_user(get_admin());
$gen = testing_util::get_data_generator();

if ($DB->record_exists('course', ['shortname' => 'MC-DEVOPS-GENAI'])) {
    cli_error('The course MC-DEVOPS-GENAI already exists.');
}

$categoryid = (int) $options['category'];
if (!$categoryid) {
    $categoryid = (int) ($DB->get_field('course_categories', 'id', ['name' => 'Cloud Computing'], IGNORE_MULTIPLE)
        ?: core_course_category::get_default()->id);
}

$p = fn(string ...$lines) => implode('', array_map(fn($l) => "<p>$l</p>", $lines));
$ul = fn(array $items) => '<ul>' . implode('', array_map(fn($i) => "<li>$i</li>", $items)) . '</ul>';

$course = $gen->create_course([
    'fullname' => 'Multi-Cloud with DevOps & GenAI',
    'shortname' => 'MC-DEVOPS-GENAI',
    'category' => $categoryid,
    'summary' => $p('Design, deploy and automate production workloads across AWS, Microsoft Azure and Google Cloud, '
        . 'then add Generative AI on top. Live instructor-led classes, hands-on labs on real cloud accounts, '
        . 'and a capstone project you can show employers.'),
    'summaryformat' => FORMAT_HTML,
    'format' => 'topics',
    'numsections' => 7,
    'enablecompletion' => 1,
    'showcompletionconditions' => 1,
    'startdate' => strtotime('today'),
]);
echo "Course {$course->id} created\n";

// Course details and live class link (custom fields).
\core_course\customfield\course_handler::create()->instance_form_save((object) [
    'id' => $course->id,
    'customfield_itlevel' => 2,
    'customfield_itdelivery' => 1,
    'customfield_itoutcomes_editor' => ['text' => $ul([
        'Design secure landing zones and identity across AWS, Azure and Google Cloud',
        'Build VPC/VNet networks: subnetting, routing, NAT and private connectivity',
        'Containerise apps and run them on Kubernetes (EKS, AKS, GKE)',
        'Provision everything with Terraform and ship it with CI/CD and GitOps',
        'Build GenAI features with Amazon Bedrock, Azure OpenAI and Vertex AI',
        'Deliver a portfolio-ready multi-cloud capstone project',
    ]), 'format' => FORMAT_HTML],
    'customfield_itprereq_editor' => ['text' => $ul([
        'Basic Linux command line',
        'Networking fundamentals (IP addresses, DNS, HTTP)',
        'Any programming or scripting language (Python preferred)',
    ]), 'format' => FORMAT_HTML],
    'customfield_itmeetinglink' => 'https://meet.google.com/mcd-genai-live',
    'customfield_itclassschedule' => 'Mon, Wed, Fri · 7:00–9:00 PM IST',
]);

// Module (section) names.
$sections = [
    1 => 'Module 1: Multi-Cloud Foundations',
    2 => 'Module 2: AWS Networking & Compute',
    3 => 'Module 3: Microsoft Azure & Google Cloud',
    4 => 'Module 4: Containers & Kubernetes',
    5 => 'Module 5: Terraform, CI/CD & GitOps',
    6 => 'Module 6: Generative AI on the Cloud',
    7 => 'Capstone & Final Assessment',
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
$page(0, 'Welcome & how this programme works', $p(
    'Welcome to <strong>Multi-Cloud with DevOps &amp; GenAI</strong>! Over the next weeks you will build and run real workloads on AWS, Azure and Google Cloud, automate them end to end, and add Generative AI.',
    '<strong>How it works:</strong> join the live classes for your batch (Mon, Wed, Fri, 7–9 PM IST), watch the recordings if you miss one, complete each module\'s lessons and quiz, and submit the labs. Each module quiz must be passed (70%) to unlock the next module.',
    '<strong>Certificate:</strong> complete every lesson, pass all quizzes and submit the capstone to earn your insureTech Skills certificate, verifiable online.'
));
$gen->create_module('forum', ['course' => $course->id, 'section' => 0, 'name' => 'Announcements & Q&A']);

// Module 1.
$page(1, 'Why multi-cloud? Strategy and trade-offs', $p(
    'Organisations go multi-cloud to avoid lock-in, meet data-residency rules, use best-of-breed services and improve resilience. The cost is complexity: different IAM models, networking and tooling.',
    'A good strategy decides <em>where each workload belongs</em> (by latency, compliance, team skills and cost) and standardises the layers above the provider: identity, observability, IaC and CI/CD.'
) . $ul(['Workload placement matrix', 'Avoiding the "lowest common denominator" trap', 'FinOps across providers']));
$page(1, 'Landing zones and the shared responsibility model', $p(
    'A landing zone is a pre-configured, secure baseline account/subscription/project structure: organisation hierarchy, guardrails, logging, networking hub and identity.',
    'AWS Control Tower, Azure Landing Zones and Google Cloud\'s foundation blueprint all implement the same idea. The provider secures the cloud; you secure what you put <em>in</em> it.'
));
$page(1, 'Identity across AWS, Azure and Google Cloud', $p(
    'Use one identity provider (e.g. Entra ID or Okta) federated into every cloud with SSO, short-lived credentials and least privilege. Avoid long-lived access keys.',
    'Map roles consistently: AWS IAM roles and permission sets, Azure RBAC role assignments, Google Cloud IAM roles on folders and projects.'
));
$recording(1, 'Video: What is cloud computing?', '6m', 'https://www.youtube.com/watch?v=M988_fsOSWo');
$quiz1 = $quiz(1, 'Module 1 quiz: Foundations', [
    ['single', 'What is the main purpose of a landing zone?', ['A secure, pre-configured baseline for accounts, networking, identity and logging', 'A region where virtual machines are cheapest', 'A storage bucket for backups', 'A Kubernetes namespace'], 'A landing zone is the governed baseline (hierarchy, guardrails, logging, network hub, identity) every workload lands in.', 'Landing zones'],
    ['tf', 'In the shared responsibility model, the customer is responsible for configuring security groups and IAM permissions.', true, 'The provider secures the infrastructure; you secure your configuration, data and identities.', 'Shared responsibility'],
    ['single', 'Which is the recommended way for engineers to access multiple clouds?', ['SSO from one identity provider with short-lived credentials', 'One shared root account per cloud', 'Long-lived access keys stored in a wiki', 'A separate password for every console'], 'Federated SSO with short-lived credentials gives one place to manage access and removes long-lived secrets.', 'Identity & access'],
    ['multi', 'Which are valid reasons to adopt multi-cloud? (Select two)', ['correct' => 2, 'options' => ['Meeting data-residency requirements', 'Using best-of-breed services', 'It always lowers costs', 'It removes the need for IAM']], 'Residency and best-of-breed are common drivers. Multi-cloud adds complexity and does not automatically reduce cost.', 'Multi-cloud strategy'],
], 3);

// Module 2 (unlocked after passing the Module 1 quiz).
$page(2, 'VPC design and subnetting', $p(
    'A VPC uses a private CIDR block such as 10.0.0.0/16 (65,536 addresses). Split it into subnets per Availability Zone and tier: public (load balancers), private (apps) and isolated (databases).',
    'A /24 subnet has 256 addresses; AWS reserves 5 in every subnet, leaving 251 usable. Plan ranges so VPCs across clouds and on-premises never overlap.'
) . $ul(['10.0.0.0/16 → VPC', '10.0.1.0/24, 10.0.2.0/24 → public subnets (AZ a, b)', '10.0.11.0/24, 10.0.12.0/24 → private subnets']));
$page(2, 'Routing, Internet Gateway and NAT Gateway', $p(
    'A subnet is <em>public</em> when its route table sends 0.0.0.0/0 to an Internet Gateway. Private subnets send 0.0.0.0/0 to a NAT Gateway placed in a public subnet, so instances can reach the internet without being reachable from it.',
    'Troubleshooting "EC2 has no internet": check the public IP, the subnet\'s route table, the Internet Gateway attachment, then security groups and NACLs.'
));
$page(2, 'EC2, Auto Scaling and S3', $p(
    'EC2 instances run in subnets behind an Application Load Balancer; an Auto Scaling group keeps the desired capacity across AZs. S3 stores objects with 11 nines of durability; block public access and use bucket policies with least privilege.'
));
$recording(2, 'Video: AWS in 100 seconds', '2m', 'https://www.youtube.com/watch?v=ZzI9JE0i6Lc');
$quiz2 = $quiz(2, 'Module 2 quiz: AWS networking', [
    ['single', 'How many usable IP addresses does a /24 subnet have in AWS?', ['251', '256', '254', '250'], 'A /24 has 256 addresses and AWS reserves 5 in every subnet, leaving 251.', 'Subnetting'],
    ['single', 'Which CIDR block is a valid private range for a VPC?', ['10.0.0.0/16', '8.8.8.0/24', '172.32.0.0/16', '192.169.0.0/16'], '10.0.0.0/8, 172.16.0.0/12 and 192.168.0.0/16 are the RFC 1918 private ranges.', 'Subnetting'],
    ['single', 'What makes a subnet public?', ['Its route table sends 0.0.0.0/0 to an Internet Gateway', 'It has a security group allowing port 80', 'It is in the first Availability Zone', 'It has a NAT Gateway inside it'], 'Public = a route to an Internet Gateway. Security groups filter traffic but do not make a subnet public.', 'Routing'],
    ['single', 'An EC2 instance in a private subnet must download updates but must not be reachable from the internet. What do you add?', ['A NAT Gateway in a public subnet, routed from the private subnet', 'An Internet Gateway route in the private subnet', 'An Elastic IP on the instance', 'A VPC peering connection'], 'A NAT Gateway allows outbound-only internet access for private instances.', 'NAT Gateway'],
    ['tf', 'A NAT Gateway must be placed in a private subnet.', false, 'The NAT Gateway itself lives in a public subnet (it needs the Internet Gateway route); private subnets route to it.', 'NAT Gateway'],
    ['multi', 'Scenario: an EC2 instance in a public subnet has no internet access. Which two checks come first? (Select two)', ['correct' => 2, 'options' => ['Does the instance have a public IP?', 'Does the route table send 0.0.0.0/0 to an attached Internet Gateway?', 'Is the instance type large enough?', 'Is S3 versioning enabled?']], 'Public IP and the route to an attached Internet Gateway are the first things to verify, then security groups and NACLs.', 'Routing'],
], 3);
$DB->set_field('course_sections', 'availability', json_encode([
    'op' => '&', 'showc' => [true],
    'c' => [['type' => 'completion', 'cm' => (int) $quiz1->cmid, 'e' => COMPLETION_COMPLETE_PASS]],
]), ['course' => $course->id, 'section' => 2]);

// Module 3.
$page(3, 'Azure: VNets, Entra ID and AKS', $p(
    'Azure Virtual Networks mirror VPCs; hub-and-spoke topologies centralise firewalls and DNS. Entra ID is the identity plane for both Azure and Microsoft 365. AKS runs managed Kubernetes with Entra-integrated RBAC.'
));
$page(3, 'Google Cloud: GKE, Cloud Run and BigQuery', $p(
    'Google Cloud VPCs are global, with regional subnets. GKE Autopilot runs Kubernetes without managing nodes; Cloud Run runs containers serverlessly; BigQuery is a serverless data warehouse used heavily for analytics and AI.'
));
$quiz(3, 'Module 3 quiz: Azure & Google Cloud', [
    ['single', 'Which service is Azure\'s managed Kubernetes?', ['AKS', 'EKS', 'GKE', 'ECS'], 'AKS = Azure Kubernetes Service. EKS is AWS and GKE is Google Cloud.', 'Azure'],
    ['tf', 'In Google Cloud a VPC network is global, while its subnets are regional.', true, 'Google Cloud VPCs are global resources; subnets belong to a region.', 'Google Cloud'],
    ['single', 'Which Google Cloud service runs containers without managing servers or clusters?', ['Cloud Run', 'Compute Engine', 'BigQuery', 'Cloud Storage'], 'Cloud Run is serverless containers; GKE Autopilot is the managed Kubernetes option.', 'Google Cloud'],
], 3);

// Module 4.
$page(4, 'Docker essentials', $p(
    'Images are built from a Dockerfile in layers; keep them small with multi-stage builds and run as a non-root user. Push images to ECR, ACR or Artifact Registry.'
));
$page(4, 'Kubernetes objects: Pods, Deployments and Services', $p(
    'A Deployment manages ReplicaSets of Pods and performs rolling updates. A Service gives Pods a stable address; an Ingress exposes HTTP routes. ConfigMaps and Secrets inject configuration.'
));
$page(4, 'Helm and multi-cluster operations', $p(
    'Helm packages Kubernetes manifests into versioned charts. Run the same chart on EKS, AKS and GKE with per-cloud values files.'
));
$assign(4, 'Lab: Deploy a containerised app to Kubernetes',
    $p('Containerise the sample API, push it to a registry and deploy it to any managed Kubernetes cluster with a Deployment, Service and Ingress.',
       'Submit: the repository link, your manifests or Helm chart, and a screenshot of the app responding through the Ingress.'), 10);

// Module 5.
$page(5, 'Terraform for multi-cloud', $p(
    'Terraform describes infrastructure as code with providers for every cloud. Use remote state with locking, modules for reusable building blocks, and workspaces or separate stacks per environment.'
));
$page(5, 'CI/CD with GitHub Actions', $p(
    'A pipeline lints, tests, builds an image, scans it, and deploys. Use OIDC federation from GitHub Actions to each cloud instead of storing access keys as secrets.'
));
$page(5, 'GitOps with Argo CD', $p(
    'In GitOps the desired cluster state lives in Git; Argo CD continuously reconciles clusters to it. Rollbacks become git reverts.'
));
$recording(5, 'Video: Terraform in 100 seconds', '2m', 'https://www.youtube.com/watch?v=tomUWcQ0P3k');
$recording(5, 'Video: CI/CD explained', '2m', 'https://www.youtube.com/watch?v=scEDHsr3APg');

// Module 6.
$page(6, 'GenAI services: Amazon Bedrock, Azure OpenAI and Vertex AI', $p(
    'Each cloud offers managed foundation models behind an API with enterprise controls: private networking, no training on your data, content filters and quotas. Choose by model availability, region and existing platform.'
));
$page(6, 'RAG architecture and responsible AI', $p(
    'Retrieval-Augmented Generation embeds your documents into a vector store, retrieves the relevant chunks for each question and passes them to the model, grounding answers in your data. Add evaluation, guardrails and human review for high-impact use.'
));

// Capstone and final assessment.
$assign(7, 'Capstone: Multi-cloud GenAI application with CI/CD',
    $p('Build a small GenAI application (for example a RAG chatbot over your own documents) deployed with Terraform and a CI/CD pipeline. Use at least two clouds (e.g. app on AWS, model on Azure OpenAI).',
       'Submit: repository link, architecture diagram, a 3-minute demo video link and a short write-up of the security and cost decisions.'), 30);
$quiz(7, 'Final assessment', [
    ['single', 'Your private EKS nodes cannot pull images from the internet. What is the most likely missing piece?', ['A route from the private subnets to a NAT Gateway', 'An Elastic IP on each node', 'A larger instance type', 'An S3 bucket policy'], 'Private nodes need outbound internet (via NAT) or VPC endpoints to reach registries.', 'NAT Gateway'],
    ['single', 'Which approach avoids storing long-lived cloud keys in your CI system?', ['OIDC federation from the CI provider to each cloud', 'Rotating keys every year', 'Encrypting the keys in the repository', 'Using the root account'], 'OIDC lets the pipeline exchange a short-lived token for cloud credentials.', 'CI/CD'],
    ['tf', 'In GitOps, rolling back a release is done by reverting the commit in Git.', true, 'Git is the source of truth; the controller reconciles the cluster to the reverted state.', 'GitOps'],
    ['single', 'What does Retrieval-Augmented Generation (RAG) add to a foundation model?', ['Relevant passages from your own data, retrieved at question time', 'More GPU memory', 'Fine-tuned weights', 'A larger context window'], 'RAG grounds answers in retrieved documents without retraining the model.', 'Generative AI'],
    ['single', 'Which Terraform feature prevents two engineers applying changes to the same state at once?', ['State locking with a remote backend', 'Workspaces', 'Variables', 'Output values'], 'Remote backends such as S3+DynamoDB, Azure Storage or GCS provide state locking.', 'Terraform'],
], 3);

rebuild_course_cache($course->id, true);

// Sample videos for the containers and GenAI modules.
$recording(4, 'Video: Docker in 100 seconds', '2m', 'https://www.youtube.com/watch?v=Gjnup-PuquQ');
$recording(4, 'Video: Kubernetes explained', '2m', 'https://www.youtube.com/watch?v=PziYflu8cB8');
$recording(6, 'Video: Large language models explained', '8m', 'https://www.youtube.com/watch?v=LPZh9BOjkQs');

// Course completion: every activity with completion (lessons viewed, quizzes passed, labs and capstone submitted).
$criterion = new completion_criteria_activity();
$criteriadata = (object) ['id' => $course->id, 'criteria_activity' => array_fill_keys($cmids, 1)];
$criterion->update_config($criteriadata);
$aggregation = new completion_aggregation(['course' => $course->id, 'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY]);
$aggregation->setMethod(COMPLETION_AGGREGATION_ALL);
$aggregation->save();
$aggregation = new completion_aggregation(['course' => $course->id, 'criteriatype' => null]);
$aggregation->setMethod(COMPLETION_AGGREGATION_ALL);
$aggregation->save();

// Batch with dates.
$groupid = groups_create_group((object) ['courseid' => $course->id, 'name' => 'MC Batch 1 · Nov 2026']);
\local_itskills\local\fields::save_batch($groupid, strtotime('today'), strtotime('+90 days'));

// Trainer.
if ($options['trainer'] && ($trainer = $DB->get_record('user', ['username' => $options['trainer'], 'deleted' => 0]))) {
    $gen->enrol_user($trainer->id, $course->id, 'editingteacher');
    groups_add_member($groupid, $trainer->id);
    echo "Trainer {$trainer->username} assigned\n";
}

// Skills for the Skill Passport.
$skills = ['Multi-cloud architecture' => [], 'AWS' => ['AWS-SAA'], 'Azure' => ['AZ-900'], 'Kubernetes' => ['CKA'],
    'Networking' => ['AWS-SAA'], 'DevOps & IaC' => [], 'Generative AI' => []];
$order = 0;
foreach ($skills as $name => $shortnames) {
    $ids = [$course->id];
    foreach ($shortnames as $shortname) {
        if ($id = $DB->get_field('course', 'id', ['shortname' => $shortname])) {
            $ids[] = $id;
        }
    }
    $existing = $DB->get_record('local_itskills_skill', ['name' => $name]);
    $record = (object) ['name' => $name, 'courseids' => implode(',', array_unique($ids)), 'sortorder' => $order++, 'timemodified' => time()];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('local_itskills_skill', $record);
    } else {
        $DB->insert_record('local_itskills_skill', $record);
    }
}

echo "Done: course id {$course->id}, batch id {$groupid}, " . count($cmids) . " tracked activities\n";
