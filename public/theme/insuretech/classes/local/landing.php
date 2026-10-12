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

namespace theme_insuretech\local;

use context_course;
use core_course_category;
use core_course_list_element;
use moodle_url;
use stdClass;

/**
 * Public (logged-out) pages: the landing page and course detail pages.
 *
 * Only shows what Moodle already allows a visitor to see: visible courses in visible categories, their summary,
 * section and activity names (not the content) and course contacts. Opening a course still requires login and
 * enrolment, enforced by Moodle as usual.
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class landing {
    /** @var array|null SEO tags for the current page, picked up by the renderer's standard_head_html(). */
    protected static $meta = null;

    /**
     * A theme setting, or its default text from the language file when it has never been saved.
     * A setting saved empty stays empty (e.g. to drop an FAQ).
     *
     * @param string $name
     * @return string
     */
    public static function setting(string $name): string {
        $value = get_config('theme_insuretech', $name);
        if ($value !== false && $value !== null) {
            return trim($value);
        }
        $sm = get_string_manager();
        return $sm->string_exists('default_' . $name, 'theme_insuretech') ? get_string('default_' . $name, 'theme_insuretech') : '';
    }

    /**
     * Register SEO tags for this page.
     *
     * @param string $title
     * @param string $description
     * @param string $url Canonical URL.
     * @param string $image Absolute image URL.
     * @param array $jsonld Structured data (schema.org) objects.
     */
    public static function set_meta(string $title, string $description, string $url, string $image, array $jsonld): void {
        self::$meta = compact('title', 'description', 'url', 'image', 'jsonld');
    }

    /**
     * The title registered for this page, if any.
     *
     * @return string|null
     */
    public static function meta_title(): ?string {
        return self::$meta['title'] ?? null;
    }

    /**
     * HTML for the registered SEO tags, or '' when none were registered.
     *
     * @return string
     */
    public static function meta_html(): string {
        if (!self::$meta) {
            return '';
        }
        $m = self::$meta;
        $tags = [
            '<meta name="description" content="' . s($m['description']) . '">',
            '<link rel="canonical" href="' . s($m['url']) . '">',
            '<meta property="og:type" content="website">',
            '<meta property="og:site_name" content="' . s(self::setting('brandname')) . '">',
            '<meta property="og:title" content="' . s($m['title']) . '">',
            '<meta property="og:description" content="' . s($m['description']) . '">',
            '<meta property="og:url" content="' . s($m['url']) . '">',
            '<meta property="og:image" content="' . s($m['image']) . '">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="' . s($m['title']) . '">',
            '<meta name="twitter:description" content="' . s($m['description']) . '">',
            '<meta name="twitter:image" content="' . s($m['image']) . '">',
        ];
        foreach ($m['jsonld'] as $data) {
            // JSON_HEX_TAG keeps "</script>" inside any value from closing the tag early.
            $tags[] = '<script type="application/ld+json">' .
                json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
        }
        return implode("\n", $tags) . "\n";
    }

    /**
     * Plain-text excerpt of some HTML.
     *
     * @param string $html
     * @param int $length
     * @return string
     */
    public static function excerpt(string $html, int $length): string {
        $text = trim(preg_replace('/\s+/u', ' ', html_to_text($html, 0, false)));
        return shorten_text($text, $length, true);
    }

    /**
     * Image for a course: its uploaded course image, or the generated pattern Moodle uses on dashboard cards.
     *
     * @param object $course Anything with an id (course record or core_course_list_element).
     * @return string
     */
    public static function course_image(object $course): string {
        global $OUTPUT;
        $image = \core_course\external\course_summary_exporter::get_course_image($course);
        return $image ?: $OUTPUT->get_generated_image_for_id($course->id);
    }

    /**
     * The courses a visitor can see, as card data.
     *
     * @param int $limit
     * @return array[]
     */
    public static function courses(int $limit = 60): array {
        global $DB;

        $courses = core_course_category::top()->get_courses([
            'recursive' => true,
            'sort' => ['sortorder' => 1],
            'limit' => $limit,
        ]);

        $cards = [];
        foreach ($courses as $course) {
            /** @var core_course_list_element $course */
            if ($course->id == SITEID || !core_course_category::can_view_course_info($course)) {
                continue;
            }
            $context = context_course::instance($course->id);
            $category = core_course_category::get($course->category, IGNORE_MISSING);
            $modules = $DB->count_records_select('course_sections', 'course = ? AND section > 0 AND visible = 1',
                [$course->id]);
            $cards[] = [
                'id' => $course->id,
                'name' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
                'category' => $category ? $category->get_formatted_name(['escape' => false]) : '',
                'categoryid' => (int) $course->category,
                'summary' => self::excerpt(format_text($course->summary, $course->summaryformat,
                    ['context' => $context, 'noclean' => false]), 140),
                'image' => self::course_image($course),
                'modules' => $modules,
                'modulestext' => get_string($modules == 1 ? 'modulecount' : 'modulescount', 'theme_insuretech', $modules),
                'url' => (new moodle_url('/theme/insuretech/course.php', ['id' => $course->id]))->out(false),
                'schedule' => live_class_fields::get_course($course->id)['schedule'],
            ];
        }
        return $cards;
    }

    /**
     * Data shared by the public header and footer.
     *
     * @return array
     */
    public static function chrome(): array {
        global $CFG, $USER, $OUTPUT;

        $loggedin = isloggedin() && !isguestuser();
        $socials = [];
        foreach (['linkedin' => 'fa-linkedin', 'instagram' => 'fa-instagram', 'facebook' => 'fa-facebook', 'youtube' => 'fa-youtube'] as
                $key => $icon) {
            $url = clean_param(self::setting('social' . $key), PARAM_URL);
            if ($url) {
                $socials[] = ['url' => $url, 'icon' => $icon, 'label' => get_string('social' . $key, 'theme_insuretech')];
            }
        }
        $email = clean_param(self::setting('contactemail'), PARAM_EMAIL);
        $phone = self::setting('contactphone');

        return [
            'homeurl' => $CFG->wwwroot . '/',
            'logourl' => $OUTPUT->image_url('logo', 'theme_insuretech')->out(false),
            'logolighturl' => $OUTPUT->image_url('logo_light', 'theme_insuretech')->out(false),
            'loginurl' => get_login_url(),
            'dashboardurl' => (new moodle_url('/my/'))->out(false),
            'loggedin' => $loggedin,
            'firstname' => $loggedin ? $USER->firstname : '',
            'brandname' => self::setting('brandname'),
            'footerabout' => self::setting('footerabout'),
            'email' => $email,
            'phone' => $phone,
            'phonelink' => preg_replace('/[^0-9+]/', '', $phone),
            'address' => self::setting('contactaddress'),
            'socials' => $socials,
            'hassocials' => !empty($socials),
            'year' => date('Y'),
            'websiteurl' => clean_param(self::setting('websiteurl'), PARAM_URL),
        ];
    }

    /**
     * Everything the landing page needs.
     *
     * @return array
     */
    public static function page(): array {
        global $CFG, $OUTPUT;

        $courses = self::courses();
        $categories = [];
        foreach ($courses as $c) {
            if ($c['category'] !== '') {
                $categories[$c['categoryid']] = ['id' => $c['categoryid'], 'name' => $c['category']];
            }
        }

        $faqs = [];
        for ($i = 1; $i <= 6; $i++) {
            $q = self::setting('faq' . $i . 'q');
            $a = self::setting('faq' . $i . 'a');
            if ($q !== '' && $a !== '') {
                $faqs[] = ['q' => $q, 'a' => $a];
            }
        }

        $stats = [];
        for ($i = 1; $i <= 4; $i++) {
            $value = self::setting('stat' . $i . 'value');
            $label = self::setting('stat' . $i . 'label');
            if ($value !== '' && $label !== '') {
                $stats[] = ['value' => $value, 'label' => $label];
            }
        }

        $partners = array_values(array_filter(array_map('trim', explode(',', self::setting('partners')))));

        $title = self::setting('seotitle');
        $description = self::setting('seodescription');
        $logo = $OUTPUT->image_url('logo', 'theme_insuretech')->out(false);
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            'name' => self::setting('brandname'),
            'url' => $CFG->wwwroot . '/',
            'logo' => $logo,
            'description' => $description,
            'email' => self::setting('contactemail'),
            'telephone' => self::setting('contactphone'),
            'address' => self::setting('contactaddress'),
            'sameAs' => array_column(self::chrome()['socials'], 'url'),
        ];
        $list = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(fn($c, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => [
                    '@type' => 'Course',
                    'name' => $c['name'],
                    'description' => $c['summary'] ?: $c['name'],
                    'url' => $c['url'],
                    'provider' => ['@type' => 'Organization', 'name' => self::setting('brandname'), 'sameAs' => $CFG->wwwroot],
                ],
            ], $courses, array_keys($courses)),
        ];
        $jsonld = [$org, $list];
        if ($faqs) {
            $jsonld[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn($f) => [
                    '@type' => 'Question',
                    'name' => $f['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                ], $faqs),
            ];
        }
        self::set_meta($title, $description, $CFG->wwwroot . '/', $logo, $jsonld);

        return self::chrome() + [
            'badge' => self::setting('herobadge'),
            'herotitle' => self::setting('herotitle'),
            'herosubtitle' => self::setting('herosubtitle'),
            'stats' => $stats,
            'partners' => $partners,
            'haspartners' => !empty($partners),
            'courses' => $courses,
            'hascourses' => !empty($courses),
            'categories' => array_values($categories),
            'hascategories' => count($categories) > 1,
            'abouttitle' => self::setting('abouttitle'),
            'abouttext' => format_text(self::setting('abouttext'), FORMAT_HTML, ['context' => \context_system::instance()]),
            'faqs' => $faqs,
            'hasfaqs' => !empty($faqs),
            'ctatitle' => self::setting('ctatitle'),
            'ctatext' => self::setting('ctatext'),
        ];
    }
}
