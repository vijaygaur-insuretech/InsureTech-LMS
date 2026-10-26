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
 * Job application records: each keeps its own answers, resume version and status history.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apps {
    /** @var string[] Statuses after which no follow-up is needed. */
    const FINAL = ['Rejected', 'Offer Received', 'Withdrawn', 'Closed'];

    /**
     * Load an application and check the current user may open it.
     *
     * @param int $id
     * @return array [application, client]
     */
    public static function get_checked(int $id): array {
        global $DB;
        $app = $DB->get_record('local_placement_app', ['id' => $id], '*', MUST_EXIST);
        $client = clients::get((int) $app->clientid);
        clients::require_access($client);
        return [$app, $client];
    }

    /**
     * Questions for an application: the universal bank, then the industry's own questions.
     *
     * @param int $industryid
     * @return \stdClass[]
     */
    public static function questions(int $industryid): array {
        global $DB;
        return $DB->get_records_select('local_placement_question', 'active = 1 AND industryid IN (0, ?)', [$industryid],
            'industryid ASC, sortorder ASC, id ASC');
    }

    /**
     * Saved answers of an application, by question id.
     *
     * @param int $appid
     * @return \stdClass[]
     */
    public static function answers(int $appid): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('local_placement_answer', ['appid' => $appid], 'id') as $row) {
            $out[$row->questionid] = $row;
        }
        return $out;
    }

    /**
     * Possible duplicates: same client and company with the same job link or job title.
     *
     * @param int $clientid
     * @param string $company
     * @param string $jobtitle
     * @param string $url Job description or application URL
     * @param int $excludeid
     * @return \stdClass[]
     */
    public static function duplicates(int $clientid, string $company, string $jobtitle, string $url, int $excludeid = 0): array {
        global $DB;
        $params = ['clientid' => $clientid, 'company' => \core_text::strtolower(trim($company)), 'exclude' => $excludeid,
            'title' => \core_text::strtolower(trim($jobtitle))];
        $or = ['LOWER(jobtitle) = :title'];
        if (trim($url) !== '') {
            $params['url1'] = trim($url);
            $params['url2'] = trim($url);
            $or[] = $DB->sql_compare_text('jdurl', 1000) . ' = ' . $DB->sql_compare_text(':url1', 1000);
            $or[] = $DB->sql_compare_text('applyurl', 1000) . ' = ' . $DB->sql_compare_text(':url2', 1000);
        }
        return $DB->get_records_select('local_placement_app',
            'clientid = :clientid AND id <> :exclude AND LOWER(company) = :company AND (' . implode(' OR ', $or) . ')', $params);
    }

    /**
     * Create or update an application with its answers. Fields the user may not see are left unchanged.
     *
     * @param \stdClass $data Form data
     * @param \stdClass $client
     * @param \stdClass|null $existing
     * @return int Application id
     */
    public static function save(\stdClass $data, \stdClass $client, ?\stdClass $existing): int {
        global $DB, $USER;
        $now = time();
        $record = (object) [
            'clientid' => $client->id,
            'company' => trim($data->company),
            'jobtitle' => trim($data->jobtitle),
            'industryid' => (int) $data->industryid,
            'jobcategory' => trim($data->jobcategory ?? ''),
            'jdurl' => trim($data->jdurl ?? ''),
            'applyurl' => trim($data->applyurl ?? ''),
            'platform' => $data->platform ?? '',
            'dateapplied' => (int) ($data->dateapplied ?? 0),
            'resumeid' => (int) ($data->resumeid ?? 0),
            'notes' => trim($data->notes ?? ''),
            'reference' => trim($data->reference ?? ''),
            'timemodified' => $now,
        ];
        if (clients::can_see($client, 'resume') && isset($data->coverletter)) {
            $record->coverletter = trim($data->coverletter);
        }
        if (clients::can_see($client, 'salary') && isset($data->salaryresponse)) {
            $record->salaryresponse = trim($data->salaryresponse);
        }
        if (perm::can('feat_followups') && isset($data->followupdate)) {
            $record->followupdate = (int) $data->followupdate;
        }
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_placement_app', $record);
            audit::log('application_edit', 'application', $record->id, $client->id, "{$record->company} - {$record->jobtitle}");
        } else {
            $record->employeeid = perm::is_employee() ? $USER->id : (int) $client->employeeid;
            $record->status = $data->status ?? 'Pending';
            $record->createdby = $USER->id;
            $record->timecreated = $now;
            $record->id = $DB->insert_record('local_placement_app', $record);
            $DB->insert_record('local_placement_statuslog', ['appid' => $record->id, 'oldstatus' => '',
                'newstatus' => $record->status, 'note' => '', 'userid' => $USER->id, 'timecreated' => $now]);
            audit::log('application_create', 'application', $record->id, $client->id, "{$record->company} - {$record->jobtitle}");
        }

        // Answers: one row per question, copying the question text so later edits to the bank do not change history.
        $existinganswers = self::answers($record->id);
        foreach (self::questions($record->industryid) as $q) {
            $field = 'q' . $q->id;
            if (!property_exists($data, $field) && !property_exists($data, $field . '_na')) {
                continue;
            }
            $value = $data->$field ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_keys(array_filter($value)));
            } else if ($q->qtype === 'date') {
                $value = $value ? userdate((int) $value, get_string('strftimedate', 'langconfig')) : '';
            }
            $row = ['appid' => $record->id, 'questionid' => $q->id, 'question' => $q->question,
                'answer' => trim((string) $value), 'notapplicable' => (int) !empty($data->{$field . '_na'})];
            if (isset($existinganswers[$q->id])) {
                $row['id'] = $existinganswers[$q->id]->id;
                $DB->update_record('local_placement_answer', $row);
            } else {
                $DB->insert_record('local_placement_answer', $row);
            }
        }
        return (int) $record->id;
    }

    /**
     * Change the status, keep the history and tell the client.
     *
     * @param \stdClass $app
     * @param string $status
     * @param string $note
     */
    public static function set_status(\stdClass $app, string $status, string $note): void {
        global $DB, $USER;
        if ($status === $app->status && $note === '') {
            return;
        }
        $DB->update_record('local_placement_app', ['id' => $app->id, 'status' => $status, 'timemodified' => time()]);
        $DB->insert_record('local_placement_statuslog', ['appid' => $app->id, 'oldstatus' => $app->status,
            'newstatus' => $status, 'note' => $note, 'userid' => $USER->id, 'timecreated' => time()]);
        audit::log('application_status', 'application', $app->id, (int) $app->clientid, "{$app->status} -> $status");
        if ($status !== $app->status) {
            $client = clients::get((int) $app->clientid);
            notify::send('application', (int) $client->userid, get_string('msg_status_subject', 'local_placement',
                (object) ['company' => $app->company, 'status' => $status]),
                get_string('msg_status_body', 'local_placement', (object) ['company' => $app->company,
                    'job' => $app->jobtitle, 'status' => $status, 'note' => $note]),
                new \moodle_url('/local/placement/application.php', ['id' => $app->id]));
        }
        $app->status = $status;
    }

    /**
     * SQL condition limiting applications to what the current user may see.
     *
     * @return array [sql, params]
     */
    public static function scope_sql(): array {
        global $DB;
        $ids = clients::visible_ids();
        if ($ids === null) {
            return ['1 = 1', []];
        }
        if (!$ids) {
            return ['1 = 0', []];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'vc');
        return ["a.clientid $in", $params];
    }
}
