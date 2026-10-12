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
 * Site home layout: the public landing page for visitors, Boost's drawers layout for everyone signed in.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (isloggedin() && !isguestuser()) {
    require($CFG->dirroot . '/theme/boost/layout/drawers.php');
    return;
}

$templatecontext = \theme_insuretech\local\landing::page() + [
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(['it-public-body']),
];

echo $OUTPUT->render_from_template('theme_insuretech/landing', $templatecontext);
