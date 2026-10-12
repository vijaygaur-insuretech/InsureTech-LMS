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
 * insureTech Skills theme functions.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/theme/boost/lib.php');

/**
 * Returns the main SCSS content: brand tokens, then Boost, then the insureTech design layer.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_insuretech_get_main_scss_content($theme) {
    global $CFG;

    $scss = file_get_contents($CFG->dirroot . '/theme/insuretech/scss/pre.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/insuretech/scss/post.scss');

    return $scss;
}

/**
 * SCSS to prepend: the admin-configurable brand colour and raw pre-SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_insuretech_get_pre_scss($theme) {
    $scss = '';

    if (!empty($theme->settings->brandcolor)) {
        $scss .= '$primary: ' . $theme->settings->brandcolor . ";\n";
    }

    if (defined('BEHAT_SITE_RUNNING')) {
        $scss .= "\$behatsite: true;\n";
    }

    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * SCSS to append: the admin's raw custom SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_insuretech_get_extra_scss($theme) {
    return !empty($theme->settings->scss) ? $theme->settings->scss : '';
}

/**
 * Fallback CSS used when SCSS compilation is unavailable.
 *
 * @return string
 */
function theme_insuretech_get_precompiled_css() {
    return theme_boost_get_precompiled_css();
}
