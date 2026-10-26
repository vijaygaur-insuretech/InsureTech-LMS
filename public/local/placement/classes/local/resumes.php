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
 * Resume and cover letter versions. Files live in private file storage and are only ever sent by file.php after a
 * permission check; there is no public URL.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resumes {
    /** @var string[] Accepted file types. */
    const TYPES = ['.pdf', '.doc', '.docx'];

    /**
     * All versions of a client's documents, newest first.
     *
     * @param int $clientid
     * @return \stdClass[]
     */
    public static function for_client(int $clientid): array {
        global $DB;
        return $DB->get_records('local_placement_resume', ['clientid' => $clientid], 'timecreated DESC, id DESC');
    }

    /**
     * Latest resume (not cover letter) of a client.
     *
     * @param int $clientid
     * @return \stdClass|null
     */
    public static function latest(int $clientid): ?\stdClass {
        global $DB;
        $rows = $DB->get_records('local_placement_resume', ['clientid' => $clientid, 'kind' => 'resume'], 'version DESC', '*', 0, 1);
        return $rows ? reset($rows) : null;
    }

    /**
     * Store an uploaded file from a draft area as a new version.
     *
     * @param \stdClass $client
     * @param int $draftitemid
     * @param string $kind resume or coverletter
     * @return \stdClass The new version
     */
    public static function upload(\stdClass $client, int $draftitemid, string $kind): \stdClass {
        global $DB, $USER;
        $kind = $kind === 'coverletter' ? 'coverletter' : 'resume';
        $version = 1 + (int) $DB->get_field_sql('SELECT MAX(version) FROM {local_placement_resume} WHERE clientid = ? AND kind = ?',
            [$client->id, $kind]);
        $record = (object) ['clientid' => $client->id, 'version' => $version, 'filename' => '', 'kind' => $kind,
            'status' => 'pending', 'uploadedby' => $USER->id, 'timecreated' => time()];
        $record->id = $DB->insert_record('local_placement_resume', $record);
        $context = \context_system::instance();
        file_save_draft_area_files($draftitemid, $context->id, 'local_placement', 'resume', $record->id,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => self::TYPES]);
        $file = self::file($record);
        if (!$file) {
            $DB->delete_records('local_placement_resume', ['id' => $record->id]);
            throw new \moodle_exception('nofile', 'local_placement');
        }
        $record->filename = $file->get_filename();
        $DB->set_field('local_placement_resume', 'filename', $record->filename, ['id' => $record->id]);
        audit::log('resume_upload', 'resume', $record->id, $client->id, "$kind v$version");
        notify::send('resume', (int) $client->userid, get_string('msg_resume_received_subject', 'local_placement'),
            get_string('msg_resume_received_body', 'local_placement', (object) ['file' => $record->filename, 'version' => $version]),
            new \moodle_url('/local/placement/resume.php'));
        return $record;
    }

    /**
     * The stored file of a version.
     *
     * @param \stdClass $resume
     * @return \stored_file|null
     */
    public static function file(\stdClass $resume): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'local_placement', 'resume', $resume->id,
            'id', false);
        return $files ? reset($files) : null;
    }

    /**
     * Approve, reject, or ask for a replacement; tells the client.
     *
     * @param \stdClass $resume
     * @param string $status approved, rejected or replace
     * @param string $note
     */
    public static function review(\stdClass $resume, string $status, string $note): void {
        global $DB, $USER;
        $DB->update_record('local_placement_resume', ['id' => $resume->id, 'status' => $status, 'reviewnote' => $note,
            'reviewedby' => $USER->id, 'timereviewed' => time()]);
        $client = clients::get((int) $resume->clientid);
        audit::log('resume_' . $status, 'resume', $resume->id, $client->id, "v{$resume->version}");
        notify::send('resume', (int) $client->userid, get_string('msg_resume_' . $status . '_subject', 'local_placement'),
            get_string('msg_resume_' . $status . '_body', 'local_placement', (object) ['file' => $resume->filename,
                'version' => $resume->version, 'note' => $note]),
            new \moodle_url('/local/placement/resume.php'));
    }

    /**
     * Whether the current user may preview or download a version.
     *
     * @param \stdClass $resume
     * @param bool $download
     * @return bool
     */
    public static function can_open(\stdClass $resume, bool $download): bool {
        global $USER;
        $client = clients::get((int) $resume->clientid);
        if ((int) $client->userid === (int) $USER->id || perm::is_admin()) {
            return true;
        }
        if (!clients::can_access($client)) {
            return false;
        }
        return $download ? perm::can(['field_resume', 'act_download', 'feat_resume_download'])
            : perm::can(['field_resume', 'act_view', 'feat_resume_preview']);
    }

    /**
     * Whether the current user may approve or reject resumes.
     *
     * @return bool
     */
    public static function can_review(): bool {
        return perm::can(['act_approve', 'field_resume']);
    }
}
