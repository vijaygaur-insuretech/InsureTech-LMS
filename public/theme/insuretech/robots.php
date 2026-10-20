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
 * robots.txt for search engines: public pages open, the learning area closed, and a link to the sitemap.
 *
 * Served at /robots.txt by the web server (see deploy/Dockerfile).
 *
 * @package    theme_insuretech
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- public file for search engines.
define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$private = ['/admin/', '/login/', '/my/', '/user/', '/course/', '/mod/', '/grade/', '/message/', '/calendar/',
    '/badges/', '/blog/', '/enrol/', '/report/', '/question/', '/backup/', '/local/itskills/', '/theme/insuretech/manage/'];
echo "User-agent: *\n";
echo "Allow: /$\n";
echo "Allow: /theme/insuretech/course.php\n";
echo "Allow: /local/itskills/verify.php\n";
foreach ($private as $path) {
    echo "Disallow: $path\n";
}
echo "\nSitemap: " . $CFG->wwwroot . "/sitemap.xml\n";
