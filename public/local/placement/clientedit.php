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
 * Edit a client's master profile (admins, permitted employees, and clients themselves).
 *
 * @package    local_placement
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_placement\local\clients;
use local_placement\local\fields;
use local_placement\local\perm;
use local_placement\local\ui;

$id = required_param('id', PARAM_INT);
$client = clients::get($id);
$self = (int) $client->userid === (int) $USER->id;
$url = new moodle_url('/local/placement/clientedit.php', ['id' => $id]);
ui::setup($url, get_string('editprofile', 'local_placement'), $self ? 'myprofile' : 'clients');
clients::require_access($client);
if (!$self && !perm::can(['feat_client_edit', 'act_edit'])) {
    throw new moodle_exception('nopermission', 'local_placement');
}
$back = $self ? new moodle_url('/local/placement/profile.php') : new moodle_url('/local/placement/client.php', ['id' => $id]);

$form = new \local_placement\form\profile_form($url, ['client' => $client]);
$defaults = ['id' => $id];
foreach (fields::all() as $key => $def) {
    $defaults[$key] = $client->data[$key] ?? ($def[0] === 'date' ? 0 : '');
}
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($back);
} else if ($data = $form->get_data()) {
    clients::save_profile($client, (array) $data);
    redirect($back, get_string('profilesaved', 'local_placement'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo ui::header();
echo html_writer::tag('h3', s(fullname($client->user)), ['class' => 'lp-h3']);
$form->display();
echo $OUTPUT->footer();
