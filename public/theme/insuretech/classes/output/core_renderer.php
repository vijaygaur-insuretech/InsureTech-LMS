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

namespace theme_insuretech\output;

/**
 * Renderer for the insureTech Skills theme.
 *
 * Everything is inherited from Boost. The only change is that the insureTech logo is shipped with the theme and used
 * whenever an administrator has not uploaded one under Site administration > Appearance > Logos.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * Return the site's logo URL, falling back to the bundled insureTech logo.
     *
     * @param int $maxwidth The maximum width, or null when the maximum width does not matter.
     * @param int $maxheight The maximum height, or null when the maximum height does not matter.
     * @return \moodle_url|false
     */
    public function get_logo_url($maxwidth = null, $maxheight = 200) {
        $url = parent::get_logo_url($maxwidth, $maxheight);
        if (!$url && !during_initial_install()) {
            $url = $this->image_url('logo', 'theme_insuretech');
        }
        return $url;
    }

    /**
     * Page head, with the generic "moodle" keyword swapped for the site's own name, plus SEO tags on public pages.
     *
     * @return string
     */
    public function standard_head_html() {
        $html = str_replace('<meta name="keywords" content="moodle, ',
            '<meta name="keywords" content="' . s(get_string('pluginname', 'theme_insuretech')) . ', ',
            parent::standard_head_html());
        // Description, canonical, Open Graph and schema.org data registered by the public pages.
        return $html . \theme_insuretech\local\landing::meta_html();
    }

    /**
     * Browser and search result title: the SEO title on public pages, Moodle's page title everywhere else.
     *
     * @return string
     */
    public function page_title() {
        $title = \theme_insuretech\local\landing::meta_title();
        return $title !== null ? s($title) : parent::page_title();
    }

    /**
     * No "Moodle Docs for this page" link in the footer: it points learners at moodle.org.
     *
     * @param string|null $text
     * @return string
     */
    public function page_doc_link($text = null) {
        return '';
    }

    /**
     * No "Moodle services and support" link in the footer: it advertises moodle.com.
     *
     * @return string
     */
    public function services_support_link(): string {
        return '';
    }

    /**
     * Main content, with the role-aware insureTech panel placed at the top of the Dashboard and course pages.
     *
     * @return string
     */
    public function main_content() {
        $main = parent::main_content();
        $panel = '';
        try {
            $panel = $this->insuretech_panel();
        } catch (\Throwable $e) {
            // A panel is a convenience only: never let it break the page underneath.
            debugging('theme_insuretech panel failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $panel === '' ? $main : preg_replace('/^<div role="main">/', '<div role="main">' . $panel, $main, 1);
    }

    /**
     * Render the panel that fits the current page and user, if any.
     *
     * @return string
     */
    protected function insuretech_panel(): string {
        if (!isloggedin() || isguestuser() || during_initial_install()) {
            return '';
        }

        if ($this->page->pagetype === 'my-index') {
            $data = role_panels::dashboard();
            return $data ? $this->render_from_template('theme_insuretech/dashboard_panel', $data) : '';
        }

        if (str_starts_with($this->page->pagetype, 'course-view-') && $this->page->course->id != SITEID) {
            $course = $this->page->course;
            $context = \context_course::instance($course->id);
            if (role_panels::is_trainer($context)) {
                return $this->render_from_template('theme_insuretech/course_trainer_bar', role_panels::trainer_course($course));
            }
            if (is_enrolled($context, null, '', true)) {
                $data = role_panels::course_progress($course);
                return ($data['hastracking'] || $data['live'] || $data['features']) ? $this->render_from_template('theme_insuretech/course_progress_bar', $data) : '';
            }
        }

        return '';
    }

    /**
     * Return the site's compact logo URL, falling back to the bundled insureTech logo.
     *
     * @param int $maxwidth The maximum width, or null when the maximum width does not matter.
     * @param int $maxheight The maximum height, or null when the maximum height does not matter.
     * @return \moodle_url|false
     */
    public function get_compact_logo_url($maxwidth = 300, $maxheight = 300) {
        $url = parent::get_compact_logo_url($maxwidth, $maxheight);
        if (!$url && !during_initial_install()) {
            $url = $this->image_url('logo', 'theme_insuretech');
        }
        return $url;
    }
}
