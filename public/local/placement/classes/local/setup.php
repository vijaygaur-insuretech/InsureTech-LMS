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

/**
 * Install-time set-up: roles and starter data. Everything here can be changed later in the admin screens.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup {
    /** @var string[] Default application statuses, in order. */
    const STATUSES = ['Pending', 'Ready to Apply', 'Applied', 'Follow-up Due', 'Interview', 'Rejected', 'Offer Received',
        'Withdrawn', 'Closed'];

    /** @var string[] Default application platforms. */
    const PLATFORMS = ['LinkedIn', 'Dice', 'Indeed', 'Naukri', 'Employer career website', 'Referral', 'Other job board'];

    /**
     * Create the Placement employee and Placement client roles (system level).
     */
    public static function roles(): void {
        global $DB;
        $system = \context_system::instance();
        foreach (['placementemployee' => 'local/placement:employee', 'placementclient' => 'local/placement:client'] as
                $shortname => $capability) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
            if (!$roleid) {
                $roleid = create_role(get_string('role_' . $shortname, 'local_placement'), $shortname,
                    get_string('role_' . $shortname . '_desc', 'local_placement'));
            }
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
            assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
        }
    }

    /**
     * Industries, the universal question bank and industry question templates, statuses and platforms.
     */
    public static function starter_data(): void {
        global $DB;
        set_config('statuses', implode("\n", self::STATUSES), 'local_placement');
        set_config('platforms', implode("\n", self::PLATFORMS), 'local_placement');
        if ($DB->record_exists('local_placement_industry', [])) {
            return;
        }
        $now = time();
        $industries = [
            'Information Technology and Software Development' => [
                ['Which programming languages have you used professionally?', 'multiselect', "Java\nPython\nJavaScript / TypeScript\nC#\nGo\nPHP\nOther"],
                ['Frameworks and APIs you have built with', 'longtext'],
                ['Databases you have worked with', 'shorttext'],
                ['Years of software development experience', 'number'],
            ],
            'Cloud Computing, DevOps and Site Reliability Engineering' => [
                ['Cloud platforms you have hands-on experience with', 'multiselect', "AWS\nMicrosoft Azure\nGoogle Cloud\nOther"],
                ['Infrastructure automation tools (e.g. Terraform, Ansible)', 'shorttext'],
                ['Container and orchestration experience (Docker, Kubernetes)', 'longtext'],
                ['CI/CD and monitoring tools used', 'shorttext'],
            ],
            'Data Engineering, Analytics and Artificial Intelligence' => [
                ['SQL experience (years)', 'number'],
                ['Python and data libraries used', 'shorttext'],
                ['Data pipeline and analytics tools', 'longtext'],
                ['Machine learning or AI frameworks used', 'shorttext'],
            ],
            'Cybersecurity and Information Security' => [
                ['Security tools you have used', 'longtext'],
                ['Incident response experience', 'yesno'],
                ['Identity and access management (IAM) experience', 'longtext'],
                ['Security certifications held', 'shorttext'],
            ],
            'Software Testing and Quality Assurance' => [
                ['Manual testing experience (years)', 'number'],
                ['Automation frameworks used', 'shorttext'],
                ['API and performance testing experience', 'longtext'],
            ],
            'Database and Enterprise Platforms' => [
                ['Database platforms administered', 'shorttext'],
                ['Enterprise platforms (e.g. SAP, Salesforce, ServiceNow)', 'shorttext'],
            ],
            'Healthcare' => [
                ['Relevant licenses or registrations', 'longtext'],
                ['Clinical or non-clinical experience', 'select', "Clinical\nNon-clinical\nBoth"],
            ],
            'Finance and Accounting' => [
                ['Accounting systems used', 'shorttext'],
                ['Financial reporting experience', 'longtext'],
                ['Professional qualifications (e.g. CA, CPA, ACCA)', 'shorttext'],
            ],
            'Business Analysis and Project Management' => [
                ['Agile / Scrum experience', 'yesno'],
                ['Project tools used (e.g. Jira, MS Project)', 'shorttext'],
                ['Stakeholder management experience', 'longtext'],
            ],
            'Sales and Marketing' => [
                ['CRM platforms used', 'shorttext'],
                ['Sales targets achieved or campaigns run', 'longtext'],
            ],
            'Customer Support and Operations' => [
                ['Support or ticketing tools used', 'shorttext'],
                ['Process improvement experience', 'longtext'],
            ],
            'Engineering and other professional industries' => [
                ['Relevant tools, equipment and qualifications', 'longtext'],
                ['Certifications required for the role', 'shorttext'],
            ],
        ];
        $order = 0;
        foreach ($industries as $name => $questions) {
            $industryid = $DB->insert_record('local_placement_industry',
                ['name' => $name, 'sortorder' => $order++, 'active' => 1, 'timemodified' => $now]);
            foreach ($questions as $i => $q) {
                $DB->insert_record('local_placement_question', ['industryid' => $industryid, 'question' => $q[0],
                    'qtype' => $q[1], 'options' => $q[2] ?? '', 'requirement' => 'optional', 'sortorder' => $i,
                    'active' => 1, 'timemodified' => $now]);
            }
        }
        // Universal question bank (industry 0): asked only when the employer asks them.
        $universal = [
            ['Position applied for', 'shorttext', '', 'required'],
            ['Relevant work experience', 'longtext', '', 'required'],
            ['Education and qualifications', 'longtext', '', 'optional'],
            ['Required professional certifications or licenses', 'shorttext', '', 'conditional', 'Only if the employer requires them'],
            ['Work authorization for this country', 'select', "Citizen\nPermanent resident\nWork visa\nRequires sponsorship", 'conditional', 'Only where relevant to the target country'],
            ['Requires visa sponsorship?', 'yesno', '', 'conditional', 'Only where relevant to the target country'],
            ['Salary or hourly-rate expectation', 'shorttext', '', 'conditional', 'Only when the employer asks'],
            ['Availability and notice period', 'shorttext', '', 'optional'],
            ['Relocation and work-location preference', 'select', "Remote\nHybrid\nOn-site\nOpen to relocation", 'optional'],
            ['Can meet the required schedule or travel?', 'yesno', '', 'conditional', 'Only when the job states a schedule or travel'],
            ['Motivation for applying', 'longtext', '', 'optional'],
            ['Referral source', 'shorttext', '', 'optional'],
            ['Additional information requested by the employer', 'longtext', '', 'optional'],
            ['Employer declarations and privacy consent accepted by the client', 'yesno', '', 'required'],
        ];
        foreach ($universal as $i => $q) {
            $DB->insert_record('local_placement_question', ['industryid' => 0, 'question' => $q[0], 'qtype' => $q[1],
                'options' => $q[2], 'requirement' => $q[3], 'conditionnote' => $q[4] ?? '', 'sortorder' => $i, 'active' => 1,
                'timemodified' => $now]);
        }
    }
}
