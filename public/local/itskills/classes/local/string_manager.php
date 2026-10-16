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

namespace local_itskills\local;

/**
 * String manager that shows the site's brand name wherever interface text says "Moodle".
 *
 * Enabled in config.php with $CFG->customstringmanager = '\local_itskills\local\string_manager'.
 * The name comes from $CFG->brandname (default "insureTech Skills"). Only the word "Moodle" on its own is replaced:
 * web addresses (moodle.org), code names and compound words such as MoodleNet are left alone.
 * Language customisation still works as usual; its editing screen shows the original texts.
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class string_manager extends \core_string_manager_standard {
    /** @var array Branded strings already worked out in this request, by language and component. */
    protected $branded = [];

    #[\Override]
    public function load_component_strings($component, $lang, $disablecache = false, $disablelocal = false) {
        $strings = parent::load_component_strings($component, $lang, $disablecache, $disablelocal);
        if ($disablelocal) {
            // Language customisation compares against the untouched originals.
            return $strings;
        }
        $key = $lang . '|' . $component . '|' . ($disablecache ? 1 : 0);
        if (!isset($this->branded[$key])) {
            $this->branded[$key] = self::brand($strings);
        }
        return $this->branded[$key];
    }

    /**
     * Replace the standalone word "Moodle" in every string of a component.
     *
     * @param array $strings
     * @return array
     */
    public static function brand(array $strings): array {
        global $CFG;
        $name = trim($CFG->brandname ?? '') ?: 'insureTech Skills';
        foreach ($strings as $id => $text) {
            if (is_string($text) && strpos($text, 'Moodle') !== false) {
                $strings[$id] = preg_replace('~(?<![\w/.@-])Moodle(?![\w/]|\.\w)~', $name, $text);
            }
        }
        return $strings;
    }
}
