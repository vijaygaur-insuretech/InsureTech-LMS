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
 * Send a resume or cover letter after checking permissions. Preview and download are separate permissions.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\audit;
use local_placement\local\resumes;

require_login(null, false);
$id = required_param('id', PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);

$resume = $DB->get_record('local_placement_resume', ['id' => $id], '*', MUST_EXIST);
if (!resumes::can_open($resume, (bool) $download)) {
    throw new moodle_exception('nopermission', 'local_placement');
}
$file = resumes::file($resume);
if (!$file) {
    send_file_not_found();
}
// Only PDFs can be shown in the browser; anything else would download, so preview-only access cannot open it.
if (!$download && $file->get_mimetype() !== 'application/pdf' && !resumes::can_open($resume, true)) {
    throw new moodle_exception('previewpdfonly', 'local_placement');
}
audit::log($download ? 'resume_download' : 'resume_preview', 'resume', $resume->id, (int) $resume->clientid,
    "v{$resume->version}");
send_stored_file($file, 0, 0, (bool) $download, ['cacheability' => 'private', 'immutable' => false]);
