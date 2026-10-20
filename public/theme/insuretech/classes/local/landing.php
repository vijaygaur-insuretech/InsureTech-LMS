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
        return $image ?: self::course_artwork($course);
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
            $live = live_class_fields::get_course($course->id);
            $delivery = self::delivery($course->id, $live);
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
                'schedule' => $delivery === 'live' ? $live['schedule'] : '',
                'delivery' => $delivery,
                'islive' => $delivery === 'live',
                'level' => $level = (class_exists('\\local_itskills\\local\\fields')
                    ? \local_itskills\local\fields::course($course->id)['itlevel'] : ''),
                'levelkey' => \core_text::strtolower(trim(strip_tags($level))),
            ];
        }
        return $cards;
    }

    /**
     * Branded artwork for a course without its own image: navy gradient, glowing grid and a topic icon.
     *
     * @param object $course
     * @return string data: URI of an SVG
     */
    public static function course_artwork(object $course): string {
        $name = \core_text::strtolower($course->fullname . ' ' . $course->shortname);
        // Simple topic icons drawn as SVG paths, picked by keywords in the course name.
        $icons = [
            '\bsecur|hack|cyber' => 'M60 20l30 12v20c0 20-13 34-30 40-17-6-30-20-30-40V32z M50 54h20v16H50z M54 54v-6a6 6 0 0 1 12 0v6',
            '\bdata\b|python|analytic|\bsql\b' => 'M30 85V60h12v25z M54 85V40h12v45z M78 85V25h12v60z',
            '\bai\b|genai|machine learning|\bml\b|\bllm' => 'M60 25a22 22 0 0 1 22 22c0 9-5 14-9 18v12H47V65c-4-4-9-9-9-18a22 22 0 0 1 22-22z M50 85h20',
            'kube|docker|devops|ci/cd|terraform|container' => 'M60 18l32 18v36L60 90 28 72V36z M60 18v36 M28 36l32 18 32-18',
        ];
        $path = 'M38 78a18 18 0 0 1 2-36 24 24 0 0 1 46 6 15 15 0 0 1-2 30z'; // Cloud.
        foreach ($icons as $pattern => $icon) {
            if (preg_match('~' . $pattern . '~', $name)) {
                $path = $icon;
                break;
            }
        }
        $hue = [190, 205, 215, 180][$course->id % 4];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 180">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0C1A2B"/>'
            . '<stop offset="1" stop-color="hsl(' . $hue . ',70%,24%)"/></linearGradient>'
            . '<radialGradient id="r" cx=".7" cy=".45" r=".6"><stop offset="0" stop-color="#1199B6" stop-opacity=".55"/>'
            . '<stop offset="1" stop-color="#1199B6" stop-opacity="0"/></radialGradient>'
            . '<pattern id="p" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M20 0H0V20" fill="none" '
            . 'stroke="#4CC3DD" stroke-opacity=".12"/></pattern></defs>'
            . '<rect width="320" height="180" fill="url(#g)"/><rect width="320" height="180" fill="url(#p)"/>'
            . '<rect width="320" height="180" fill="url(#r)"/>'
            . '<g transform="translate(165 35) scale(1.1)" fill="none" stroke="#7FE0F2" stroke-width="4" '
            . 'stroke-linecap="round" stroke-linejoin="round"><path d="' . $path . '"/></g></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * URL of a landing page photo: the one uploaded in the theme settings, or the bundled default.
     *
     * @param string $setting imglive, imgselfpaced, imgabout or ogimage
     * @param string $default Bundled image name under pix/landing
     * @return string
     */
    public static function photo(string $setting, string $default): string {
        global $OUTPUT, $PAGE;
        if (get_config('theme_insuretech', $setting)) {
            $url = $PAGE->theme->setting_file_url($setting, $setting);
            if ($url) {
                return (new moodle_url($url))->out(false);
            }
        }
        return $OUTPUT->image_url('landing/' . $default, 'theme_insuretech')->out(false);
    }

    /**
     * Home page slider: the uploaded slides in file-name order, each with its optional link and description.
     *
     * @return array[]
     */
    public static function slides(): array {
        global $CFG;
        $context = \context_system::instance();
        $files = get_file_storage()->get_area_files($context->id, 'theme_insuretech', 'heroslides', 0, 'filename', false);
        $links = preg_split('/\R/', self::setting('heroslidelinks'));
        $alts = preg_split('/\R/', self::setting('heroslidealts'));
        $revision = theme_get_revision();
        $slides = [];
        foreach (array_values($files) as $i => $file) {
            $link = trim($links[$i] ?? '');
            if ($link !== '' && $link[0] === '/') {
                $link = $CFG->wwwroot . $link;
            } else if ($link !== '' && $link[0] !== '#') {
                $link = clean_param($link, PARAM_URL);
            }
            $slides[] = [
                'index' => $i,
                'number' => $i + 1,
                'first' => $i === 0,
                'url' => moodle_url::make_pluginfile_url($context->id, 'theme_insuretech', 'heroslides', $revision, '/',
                    $file->get_filename())->out(false),
                'link' => $link,
                'alt' => trim($alts[$i] ?? ''),
            ];
        }
        return $slides;
    }

    /**
     * Testimonials: quotes entered by the admin, then approved course feedback with a written comment.
     *
     * @return array[]
     */
    public static function testimonials(): array {
        global $DB;
        $items = [];
        for ($i = 1; $i <= 6; $i++) {
            $quote = self::setting('testimonial' . $i . 'quote');
            $name = self::setting('testimonial' . $i . 'name');
            if ($quote !== '' && $name !== '') {
                $items[] = ['quote' => $quote, 'name' => $name, 'role' => self::setting('testimonial' . $i . 'role'),
                    'initial' => \core_text::strtoupper(\core_text::substr($name, 0, 1)), 'stars' => ''];
            }
        }
        $dbman = $DB->get_manager();
        if (class_exists('\\local_itskills\\local\\feedback') && $dbman->table_exists('local_itskills_feedback')) {
            $rows = $DB->get_records_select('local_itskills_feedback', "status = ? AND rating >= 4 AND comment <> ''",
                [\local_itskills\local\feedback::PUBLISHED], 'timemodified DESC', '*', 0, 6);
            foreach ($rows as $row) {
                $user = \core_user::get_user($row->userid);
                $course = $DB->get_record('course', ['id' => $row->courseid], 'id, fullname');
                if (!$user || !$course) {
                    continue;
                }
                $name = $user->firstname . ' ' . \core_text::substr($user->lastname, 0, 1) . '.';
                $items[] = ['quote' => $row->comment, 'name' => $name,
                    'role' => format_string($course->fullname, true, ['escape' => false]),
                    'initial' => \core_text::strtoupper(\core_text::substr($name, 0, 1)),
                    'stars' => \local_itskills\local\feedback::stars((int) $row->rating)];
            }
        }
        return $items;
    }

    /**
     * How a course is delivered: 'live' or 'selfpaced'.
     *
     * @param int $courseid
     * @param array $live The course's live-class link and timing
     * @return string
     */
    public static function delivery(int $courseid, array $live): string {
        if (class_exists('\\local_itskills\\local\\fields')) {
            return \local_itskills\local\fields::delivery($courseid, $live);
        }
        return ($live['link'] !== '' || $live['schedule'] !== '') ? 'live' : 'selfpaced';
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
            'getstartedurl' => self::setting('getstartedurl') ?: '#contact',
            'privacyurl' => $privacy = self::link('privacyurl'),
            'termsurl' => $terms = self::link('termsurl'),
            'refundurl' => $refund = self::link('refundurl'),
            'haslegal' => $privacy || $terms || $refund,
        ];
    }

    /**
     * A link setting as a usable URL: absolute links as they are, site paths made absolute, anything else dropped.
     *
     * @param string $name
     * @return string
     */
    protected static function link(string $name): string {
        global $CFG;
        $value = self::setting($name);
        if ($value === '') {
            return '';
        }
        if ($value[0] === '/') {
            return $CFG->wwwroot . $value;
        }
        return clean_param($value, PARAM_URL);
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

        // Headline with the highlighted words split out, so they can be coloured.
        $herotitle = self::setting('herotitle');
        $highlight = self::setting('herohighlight');
        if ($highlight !== '' && ($pos = \core_text::strpos($herotitle, $highlight)) !== false) {
            $herotitle = \core_text::substr($herotitle, 0, $pos);
        } else {
            $highlight = '';
        }
        // The last sentence starts a new line: "Get certified. Get hired." / "Get ahead in AI & tech."
        $herolead = '';
        if (preg_match('/^(.*[.!?])\s+(\S[^.!?]*)$/u', $herotitle . ($highlight === '' ? '' : 'x'), $m)) {
            $herolead = $m[1];
            $herotitle = \core_text::substr($herotitle, \core_text::strlen($m[1]) + 1);
        }
        $herofeatures = [];
        foreach (['fa-video', 'fa-flask', 'fa-certificate', 'fa-briefcase'] as $i => $icon) {
            if (($text = self::setting('herofeature' . ($i + 1))) !== '') {
                $herofeatures[] = ['text' => $text, 'icon' => $icon];
            }
        }
        foreach ($stats as $i => $stat) {
            $stats[$i]['icon'] = ['fa-user-graduate', 'fa-layer-group', 'fa-briefcase', 'fa-star'][$i] ?? 'fa-circle-check';
        }
        $testimonials = self::testimonials();
        $slides = self::slides();

        $title = self::setting('seotitle');
        $description = self::setting('seodescription');
        $logo = $OUTPUT->image_url('logo', 'theme_insuretech')->out(false);
        $ogimage = self::photo('ogimage', 'og-image');
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
        self::set_meta($title, $description, $CFG->wwwroot . '/', $ogimage, $jsonld);

        return self::chrome() + [
            'badge' => self::setting('herobadge'),
            'herotitle' => ltrim($herotitle),
            'herolead' => $herolead,
            'herohighlight' => $highlight,
            'herofeatures' => $herofeatures,
            'statsicons' => true,
            'imglive' => self::photo('imglive', 'live'),
            'imgselfpaced' => self::photo('imgselfpaced', 'selfpaced'),
            'imgabout' => self::photo('imgabout', 'about'),
            'aboutcaption' => self::setting('aboutcaption'),
            'testimonials' => $testimonials,
            'hastestimonials' => !empty($testimonials),
            'slides' => $slides,
            'hasslides' => !empty($slides),
            'multipleslides' => count($slides) > 1,
            'slidecount' => count($slides),
            'slideinterval' => max(0, (int) self::setting('heroslideinterval')),
            'getstartedurl' => self::setting('getstartedurl') ?: '#contact',
            'herosubtitle' => self::setting('herosubtitle'),
            'stats' => $stats,
            'partners' => $partners,
            'haspartners' => !empty($partners),
            'courses' => $courses,
            'hascourses' => !empty($courses),
            'categories' => array_values($categories),
            'hascategories' => count($categories) > 1,
            'livecount' => count(array_filter($courses, fn($c) => $c['islive'])),
            'selfpacedcount' => count(array_filter($courses, fn($c) => !$c['islive'])),
            'abouttitle' => self::setting('abouttitle'),
            'abouttext' => format_text(self::setting('abouttext'), FORMAT_HTML, ['context' => \context_system::instance()]),
            'faqs' => $faqs,
            'hasfaqs' => !empty($faqs),
            'ctatitle' => self::setting('ctatitle'),
            'ctatext' => self::setting('ctatext'),
        ];
    }
}
