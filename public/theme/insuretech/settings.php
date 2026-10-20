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
 * insureTech Skills theme settings.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettinginsuretech', get_string('configtitle', 'theme_insuretech'));

    $page = new admin_settingpage('theme_insuretech_general', get_string('generalsettings', 'theme_insuretech'));

    // Optional override of the brand colour. Leave empty to use the insureTech teal.
    $setting = new admin_setting_configcolourpicker('theme_insuretech/brandcolor',
        get_string('brandcolor', 'theme_insuretech'), get_string('brandcolor_desc', 'theme_insuretech'), '');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // Public landing page (what visitors see before logging in). Every text has a sensible default.
    $page = new admin_settingpage('theme_insuretech_landing', get_string('landingsettings', 'theme_insuretech'));
    $page->add(new admin_setting_heading('theme_insuretech/landingintro', '',
        get_string('landingsettings_desc', 'theme_insuretech')));
    $add = function(string $name, string $type = 'text') use ($page) {
        $title = get_string('set_' . $name, 'theme_insuretech');
        $default = get_string_manager()->string_exists('default_' . $name, 'theme_insuretech')
            ? get_string('default_' . $name, 'theme_insuretech') : '';
        $key = 'theme_insuretech/' . $name;
        switch ($type) {
            case 'textarea':
                $setting = new admin_setting_configtextarea($key, $title, '', $default, PARAM_TEXT, 60, 3);
                break;
            case 'html':
                $setting = new admin_setting_confightmleditor($key, $title, '', $default);
                break;
            case 'url':
                $setting = new admin_setting_configtext($key, $title, '', $default, PARAM_URL);
                break;
            default:
                $setting = new admin_setting_configtext($key, $title, '', $default, PARAM_TEXT);
        }
        $page->add($setting);
    };

    $page->add(new admin_setting_heading('theme_insuretech/h_brand', get_string('h_brand', 'theme_insuretech'), ''));
    $add('brandname');
    $add('websiteurl', 'url');
    $add('getstartedurl');
    $add('seotitle');
    $add('seodescription', 'textarea');

    $page->add(new admin_setting_heading('theme_insuretech/h_hero', get_string('h_hero', 'theme_insuretech'), ''));
    $add('herobadge');
    $add('herotitle');
    $add('herohighlight');
    $add('herosubtitle', 'textarea');
    for ($i = 1; $i <= 4; $i++) {
        $add('stat' . $i . 'value');
        $add('stat' . $i . 'label');
    }
    for ($i = 1; $i <= 4; $i++) {
        $add('herofeature' . $i);
    }
    $add('partners', 'textarea');

    // Photos. Each has a bundled default; uploading one here replaces it.
    $page->add(new admin_setting_heading('theme_insuretech/h_images', get_string('h_images', 'theme_insuretech'),
        get_string('h_images_desc', 'theme_insuretech')));
    foreach (['imglive', 'imgselfpaced', 'imgabout', 'ogimage'] as $image) {
        $setting = new admin_setting_configstoredfile('theme_insuretech/' . $image, get_string('set_' . $image, 'theme_insuretech'),
            get_string('set_' . $image . '_desc', 'theme_insuretech'), $image, 0,
            ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp']]);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $page->add($setting);
    }
    $add('aboutcaption');

    $page->add(new admin_setting_heading('theme_insuretech/h_about', get_string('h_about', 'theme_insuretech'), ''));
    $add('abouttitle');
    $add('abouttext', 'html');
    $add('ctatitle');
    $add('ctatext', 'textarea');

    $page->add(new admin_setting_heading('theme_insuretech/h_faq', get_string('h_faq', 'theme_insuretech'),
        get_string('h_faq_desc', 'theme_insuretech')));
    for ($i = 1; $i <= 6; $i++) {
        $add('faq' . $i . 'q');
        $add('faq' . $i . 'a', 'textarea');
    }

    // Testimonials: only quotes from real learners who agreed to be quoted. The section is hidden while all are empty.
    $page->add(new admin_setting_heading('theme_insuretech/h_testimonials', get_string('h_testimonials', 'theme_insuretech'),
        get_string('h_testimonials_desc', 'theme_insuretech')));
    for ($i = 1; $i <= 6; $i++) {
        $add('testimonial' . $i . 'quote', 'textarea');
        $add('testimonial' . $i . 'name');
        $add('testimonial' . $i . 'role');
    }

    $page->add(new admin_setting_heading('theme_insuretech/h_contact', get_string('h_contact', 'theme_insuretech'), ''));
    $add('contactemail');
    $add('contactphone');
    $add('contactaddress', 'textarea');
    $add('footerabout', 'textarea');
    foreach (['linkedin', 'instagram', 'facebook', 'youtube'] as $social) {
        $add('social' . $social, 'url');
    }
    foreach (['privacyurl', 'termsurl', 'refundurl'] as $link) {
        $add($link);
    }

    $settings->add($page);

    $page = new admin_settingpage('theme_insuretech_advanced', get_string('advancedsettings', 'theme_insuretech'));

    $setting = new admin_setting_scsscode('theme_insuretech/scsspre',
        get_string('rawscsspre', 'theme_insuretech'), get_string('rawscsspre_desc', 'theme_insuretech'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $setting = new admin_setting_scsscode('theme_insuretech/scss',
        get_string('rawscss', 'theme_insuretech'), get_string('rawscss_desc', 'theme_insuretech'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
