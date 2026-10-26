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

namespace local_placement\form;

use local_placement\local\apps;
use local_placement\local\clients;
use local_placement\local\perm;
use local_placement\local\ui;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Job application record: job details, resume version used, and the screening questions for the chosen industry.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class app_form extends \moodleform {
    #[\Override]
    protected function definition() {
        global $DB;
        $mform = $this->_form;
        $client = $this->_customdata['client'];
        $industryid = (int) $this->_customdata['industryid'];
        $isnew = empty($this->_customdata['appid']);

        foreach (['id' => $this->_customdata['appid'] ?? 0, 'clientid' => $client->id, 'industryid' => $industryid] as $k => $v) {
            $mform->addElement('hidden', $k, $v);
            $mform->setType($k, PARAM_INT);
        }

        $mform->addElement('header', 'h_job', get_string('jobdetails', 'local_placement'));
        $mform->addElement('static', 'industryname', get_string('industry', 'local_placement'),
            s($industryid ? $DB->get_field('local_placement_industry', 'name', ['id' => $industryid]) : get_string('general', 'local_placement')));
        $mform->addElement('text', 'company', get_string('company', 'local_placement'), ['size' => 40]);
        $mform->setType('company', PARAM_TEXT);
        $mform->addRule('company', null, 'required', null, 'client');
        $mform->addElement('text', 'jobtitle', get_string('jobtitle', 'local_placement'), ['size' => 40]);
        $mform->setType('jobtitle', PARAM_TEXT);
        $mform->addRule('jobtitle', null, 'required', null, 'client');
        $mform->addElement('text', 'jobcategory', get_string('jobcategory', 'local_placement'), ['size' => 40]);
        $mform->setType('jobcategory', PARAM_TEXT);
        $mform->addElement('text', 'jdurl', get_string('jdurl', 'local_placement'), ['size' => 60]);
        $mform->setType('jdurl', PARAM_URL);
        $mform->addElement('text', 'applyurl', get_string('applyurl', 'local_placement'), ['size' => 60]);
        $mform->setType('applyurl', PARAM_URL);
        $platforms = ['' => get_string('choosedots')];
        foreach (ui::lines('platforms') as $p) {
            $platforms[$p] = $p;
        }
        $mform->addElement('select', 'platform', get_string('platform', 'local_placement'), $platforms);
        $mform->addElement('date_selector', 'dateapplied', get_string('dateapplied', 'local_placement'), ['optional' => true]);
        $resumes = [0 => get_string('none')];
        foreach (\local_placement\local\resumes::for_client((int) $client->id) as $r) {
            if ($r->kind === 'resume') {
                $resumes[$r->id] = 'v' . $r->version . ' · ' . $r->filename;
            }
        }
        $mform->addElement('select', 'resumeid', get_string('resumeused', 'local_placement'), $resumes);
        if (clients::can_see($client, 'resume')) {
            $mform->addElement('textarea', 'coverletter', get_string('coverletter', 'local_placement'), ['rows' => 4, 'cols' => 60]);
            $mform->setType('coverletter', PARAM_TEXT);
        }
        if (clients::can_see($client, 'salary')) {
            $mform->addElement('text', 'salaryresponse', get_string('salaryresponse', 'local_placement'), ['size' => 30]);
            $mform->setType('salaryresponse', PARAM_TEXT);
        }
        if ($isnew && perm::can('feat_app_status')) {
            $statuses = [];
            foreach (ui::lines('statuses') as $s) {
                $statuses[$s] = $s;
            }
            $mform->addElement('select', 'status', get_string('status'), $statuses);
        }
        if (perm::can('feat_followups')) {
            $mform->addElement('date_selector', 'followupdate', get_string('followupdate', 'local_placement'), ['optional' => true]);
        }
        $mform->addElement('text', 'reference', get_string('reference', 'local_placement'), ['size' => 40]);
        $mform->setType('reference', PARAM_TEXT);
        $mform->addElement('textarea', 'notes', get_string('notes', 'local_placement'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('notes', PARAM_TEXT);

        // Screening questions: universal bank first, then the industry's own questions.
        $questions = apps::questions($industryid);
        if ($questions) {
            $mform->addElement('header', 'h_questions', get_string('screening', 'local_placement'));
            $mform->addElement('static', 'screeninghelp', '', get_string('screening_help', 'local_placement'));
            $lastindustry = -1;
            foreach ($questions as $q) {
                if ((int) $q->industryid !== $lastindustry) {
                    $lastindustry = (int) $q->industryid;
                    $mform->addElement('static', 'qgroup' . $lastindustry, '', \html_writer::tag('strong',
                        $lastindustry ? get_string('industryquestions', 'local_placement') : get_string('universalquestions', 'local_placement')));
                }
                $field = 'q' . $q->id;
                $label = s($q->question) . ' ' . \html_writer::span(get_string('req_' . $q->requirement, 'local_placement'),
                    'lp-req lp-req-' . $q->requirement);
                if ($q->requirement === 'conditional' && $q->conditionnote) {
                    $label .= \html_writer::div(s($q->conditionnote), 'lp-muted small');
                }
                $options = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $q->options))));
                switch ($q->qtype) {
                    case 'longtext':
                        $mform->addElement('textarea', $field, $label, ['rows' => 3, 'cols' => 60]);
                        break;
                    case 'yesno':
                        $mform->addElement('select', $field, $label, ['' => get_string('choosedots'),
                            get_string('yes') => get_string('yes'), get_string('no') => get_string('no')]);
                        break;
                    case 'select':
                        $mform->addElement('select', $field, $label, ['' => get_string('choosedots')] + array_combine($options, $options));
                        break;
                    case 'multiselect':
                        $group = [];
                        foreach ($options as $o) {
                            $group[] = $mform->createElement('advcheckbox', $o, '', s($o), ['group' => $field], [0, 1]);
                        }
                        $mform->addGroup($group, $field, $label, ' ', true);
                        break;
                    case 'date':
                        $mform->addElement('date_selector', $field, $label, ['optional' => true]);
                        break;
                    case 'number':
                        $mform->addElement('text', $field, $label, ['size' => 10]);
                        break;
                    default:
                        $mform->addElement('text', $field, $label, ['size' => 50]);
                }
                if (!in_array($q->qtype, ['multiselect', 'date'])) {
                    $mform->setType($field, $q->qtype === 'number' ? PARAM_FLOAT : ($q->qtype === 'url' ? PARAM_URL : PARAM_TEXT));
                }
                if ($q->requirement !== 'required') {
                    $mform->addElement('advcheckbox', $field . '_na', '', get_string('notapplicable', 'local_placement'));
                }
            }
        }

        $mform->addElement('advcheckbox', 'confirmduplicate', '', get_string('confirmduplicate', 'local_placement'));
        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (apps::questions((int) $data['industryid']) as $q) {
            $value = $data['q' . $q->id] ?? '';
            $empty = is_array($value) ? !array_filter($value) : trim((string) $value) === '';
            if ($q->requirement === 'required' && $empty) {
                $errors['q' . $q->id] = get_string('required');
            }
        }
        $dupes = apps::duplicates((int) $data['clientid'], $data['company'] ?? '', $data['jobtitle'] ?? '',
            ($data['applyurl'] ?? '') ?: ($data['jdurl'] ?? ''), (int) $data['id']);
        if ($dupes && empty($data['confirmduplicate'])) {
            $list = [];
            foreach ($dupes as $d) {
                $list[] = s("{$d->company} – {$d->jobtitle} ({$d->status}, " . ui::date((int) ($d->dateapplied ?: $d->timecreated)) . ')');
            }
            $errors['confirmduplicate'] = get_string('duplicatewarning', 'local_placement', implode('; ', $list));
        }
        return $errors;
    }
}
