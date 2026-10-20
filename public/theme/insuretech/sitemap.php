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
 * XML sitemap of the public pages: the landing page and every public course page.
 *
 * Served at /sitemap.xml by the web server (see deploy/Dockerfile).
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- public file for search engines.
define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$urls = [[$CFG->wwwroot . '/', time(), '1.0']];
foreach (\theme_insuretech\local\landing::courses(500) as $course) {
    $modified = (int) $DB->get_field('course', 'timemodified', ['id' => $course['id']]);
    $urls[] = [$course['url'], $modified, '0.8'];
}
$urls[] = [$CFG->wwwroot . '/local/itskills/verify.php', 0, '0.3'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $modified, $priority]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';
    if ($modified) {
        echo '<lastmod>' . date('Y-m-d', $modified) . '</lastmod>';
    }
    echo '<priority>' . $priority . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
