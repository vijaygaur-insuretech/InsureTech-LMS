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
 * A client's own profile page.
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\clients;
use local_placement\local\resumes;
use local_placement\local\ui;

$url = new moodle_url('/local/placement/profile.php');
ui::setup($url, get_string('tab_myprofile', 'local_placement'), 'myprofile');
$client = clients::for_user((int) $USER->id);
if (!$client) {
    throw new moodle_exception('notaclient', 'local_placement');
}

echo ui::header();
$latest = resumes::latest((int) $client->id);
echo html_writer::div(
    html_writer::div($latest ? get_string('currentresume', 'local_placement', s($latest->filename) . ' · v' . $latest->version) . ' ' .
        ui::status($latest->status) : html_writer::span(get_string('noresumeyet', 'local_placement'), 'lp-muted'), 'lp-headline') .
    html_writer::div(html_writer::link(new moodle_url('/local/placement/clientedit.php', ['id' => $client->id]),
        get_string('editprofile', 'local_placement'), ['class' => 'btn btn-primary']) . ' ' .
        html_writer::link(new moodle_url('/local/placement/resume.php'), get_string('uploadresume', 'local_placement'),
        ['class' => 'btn btn-outline-secondary']), 'lp-actions'), 'lp-titlebar');
echo html_writer::div(get_string('profile_intro', 'local_placement'), 'alert alert-info');
echo ui::profile_html($client);
echo $OUTPUT->footer();
