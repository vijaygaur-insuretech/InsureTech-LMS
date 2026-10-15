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
 * Admin settings: emails, live classes, certificate design, Skill Passport and Career Readiness.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('root', new admin_category('local_itskills', get_string('pluginname', 'local_itskills')));

    $str = fn($k, $a = null) => get_string($k, 'local_itskills', $a);
    $default = fn($k) => get_string('default_' . $k, 'local_itskills');

    // General.
    $page = new admin_settingpage('local_itskills_general', $str('settings_general'));
    if ($ADMIN->fulltree) {
        $page->add(new admin_setting_configcheckbox('local_itskills/sendenrolemail', $str('sendenrolemail'),
            $str('sendenrolemail_desc'), 1));
        $page->add(new admin_setting_configcheckbox('local_itskills/sendsessionemails', $str('sendsessionemails'),
            $str('sendsessionemails_desc'), 1));
        $page->add(new admin_setting_configcheckbox('local_itskills/notifynewcontent', $str('notifynewcontent'),
            $str('notifynewcontent_desc'), 1));
        $page->add(new admin_setting_configcheckbox('local_itskills/notifytrainers', $str('notifytrainers'),
            $str('notifytrainers_desc'), 1));
        $page->add(new admin_setting_configtext('local_itskills/inactivedays', $str('inactivedays'),
            $str('inactivedays_desc'), 14, PARAM_INT));
        $page->add(new admin_setting_configtext('local_itskills/reminderhours', $str('reminderhours'),
            $str('reminderhours_desc'), 2, PARAM_FLOAT));
        $page->add(new admin_setting_configselect('local_itskills/lateweight', $str('lateweight'), $str('lateweight_desc'), '1',
            ['1' => $str('late_full'), '0.5' => $str('late_half'), '0' => $str('late_none')]));
        $setting = new admin_setting_configcheckbox('local_itskills/trainersmanageenrol', $str('trainersmanageenrol'),
            $str('trainersmanageenrol_desc'), 0);
        $setting->set_updatedcallback('local_itskills_apply_trainer_restrictions');
        $page->add($setting);
        $page->add(new admin_setting_configcheckbox('local_itskills/feedbackmoderation', $str('feedbackmoderation'),
            $str('feedbackmoderation_desc'), 1));
        $page->add(new admin_setting_configtext('local_itskills/googlereviewurl', $str('googlereviewurl'),
            $str('googlereviewurl_desc'), '', PARAM_URL));
    }
    $ADMIN->add('local_itskills', $page);

    // Email templates.
    $page = new admin_settingpage('local_itskills_emails', $str('settings_emails'));
    if ($ADMIN->fulltree) {
        $page->add(new admin_setting_heading('local_itskills/emailsintro', '', $str('emails_intro')));
        foreach (['welcome', 'enrolled', 'sessionnew', 'sessionchanged', 'sessioncancelled', 'reminder', 'certificate',
                'newcontent', 'newstudent', 'inactive'] as $key) {
            $page->add(new admin_setting_heading('local_itskills/h_' . $key, $str('email_' . $key), $str('email_' . $key . '_vars')));
            $page->add(new admin_setting_configtext('local_itskills/email_' . $key . '_subject', $str('emailsubject'), '',
                $default('email_' . $key . '_subject'), PARAM_TEXT, 80));
            $page->add(new admin_setting_configtextarea('local_itskills/email_' . $key . '_body', $str('emailbody'), '',
                $default('email_' . $key . '_body'), PARAM_RAW, 80, 10));
        }
    }
    $ADMIN->add('local_itskills', $page);

    // Certificate design.
    $page = new admin_settingpage('local_itskills_certificate', $str('settings_certificate'));
    if ($ADMIN->fulltree) {
        $page->add(new admin_setting_heading('local_itskills/certintro', '',
            $str('cert_intro', (new moodle_url('/local/itskills/admin/certpreview.php'))->out(false))));
        $page->add(new admin_setting_configcheckbox('local_itskills/certenabled', $str('certenabled'), $str('certenabled_desc'), 1));
        $page->add(new admin_setting_configselect('local_itskills/certrule', $str('certrule'), $str('certrule_desc'), 'completion',
            ['completion' => $str('certrule_completion'), 'progress' => $str('certrule_progress')]));
        $page->add(new admin_setting_configtext('local_itskills/certprogress', $str('certprogress'), '', 100, PARAM_INT));
        foreach (['cert_title', 'cert_subtitle', 'cert_body', 'cert_signname', 'cert_signtitle', 'cert_footer'] as $key) {
            $class = $key === 'cert_body' ? 'admin_setting_configtextarea' : 'admin_setting_configtext';
            $page->add(new $class('local_itskills/' . $key, $str($key), $key === 'cert_body' ? $str('cert_vars') : '',
                $default($key), PARAM_TEXT));
        }
        foreach (['cert_primary' => '#0C1A2B', 'cert_accent' => '#1199B6', 'cert_gold' => '#D4AF37'] as $key => $colour) {
            $page->add(new admin_setting_configcolourpicker('local_itskills/' . $key, $str($key), '', $colour));
        }
        $page->add(new admin_setting_configcheckbox('local_itskills/cert_showqr', $str('cert_showqr'), $str('cert_showqr_desc'), 1));
        $opts = ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg']];
        $page->add(new admin_setting_configstoredfile('local_itskills/cert_logo', $str('cert_logo'), $str('cert_logo_desc'),
            'cert_logo', 0, $opts));
        $page->add(new admin_setting_configstoredfile('local_itskills/cert_bg', $str('cert_bg'), $str('cert_bg_desc'),
            'cert_bg', 0, $opts));
        $page->add(new admin_setting_configstoredfile('local_itskills/cert_sign', $str('cert_sign'), $str('cert_sign_desc'),
            'cert_sign', 0, $opts));
    }
    $ADMIN->add('local_itskills', $page);
    $ADMIN->add('local_itskills', new admin_externalpage('local_itskills_certpreview', $str('certpreview'),
        new moodle_url('/local/itskills/admin/certpreview.php')));

    // Skill Passport and Career Readiness.
    $page = new admin_settingpage('local_itskills_scores', $str('settings_scores'));
    if ($ADMIN->fulltree) {
        $page->add(new admin_setting_heading('local_itskills/scoresintro', '', $str('scores_intro')));
        $page->add(new admin_setting_heading('local_itskills/h_skill', $str('skillpassport'), $str('skillweights_desc')));
        foreach (['skill_wprogress' => 40, 'skill_wquiz' => 40, 'skill_wassign' => 20] as $key => $value) {
            $page->add(new admin_setting_configtext('local_itskills/' . $key, $str($key), '', $value, PARAM_INT, 4));
        }
        $page->add(new admin_setting_heading('local_itskills/h_ready', $str('careerreadiness'), $str('readyweights_desc')));
        foreach (['ready_wprogress' => 30, 'ready_wquiz' => 30, 'ready_wassign' => 25, 'ready_wattendance' => 15] as $key => $value) {
            $page->add(new admin_setting_configtext('local_itskills/' . $key, $str($key), '', $value, PARAM_INT, 4));
        }
    }
    $ADMIN->add('local_itskills', $page);
    $ADMIN->add('local_itskills', new admin_externalpage('local_itskills_skills', $str('manageskills'),
        new moodle_url('/local/itskills/admin/skills.php')));
    $ADMIN->add('local_itskills', new admin_externalpage('local_itskills_feedback', $str('managefeedback'),
        new moodle_url('/local/itskills/admin/feedback.php')));
}
