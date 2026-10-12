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

namespace mod_itrecording;

/**
 * Turns a shared video link into an embeddable player address, and decides who may watch.
 *
 * @package    mod_itrecording
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video {
    /**
     * Embed details for a shared link, or null when the link is not a supported video.
     *
     * Supported: Google Drive file links, YouTube (incl. unlisted) and Vimeo.
     *
     * @param string $url
     * @return array|null ['provider' => drive|youtube|vimeo, 'src' => string]
     */
    public static function embed(string $url): ?array {
        $url = trim($url);
        if (!preg_match('#^https://#i', $url)) {
            return null;
        }
        // Google Drive: /file/d/ID/view, /open?id=ID, /uc?id=ID.
        if (preg_match('#^https://drive\.google\.com/(?:file/d/([\w-]{10,})|(?:open|uc)\?(?:.*&)?id=([\w-]{10,}))#i', $url, $m)) {
            $id = $m[1] ?: $m[2];
            return ['provider' => 'drive', 'src' => 'https://drive.google.com/file/d/' . $id . '/preview'];
        }
        // YouTube: watch?v=ID, youtu.be/ID, /embed/ID, /shorts/ID, /live/ID.
        if (preg_match('#^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})#i',
                $url, $m)) {
            return ['provider' => 'youtube',
                'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1'];
        }
        // Vimeo: vimeo.com/ID or vimeo.com/ID/HASH (unlisted) or player.vimeo.com/video/ID.
        if (preg_match('#^https://(?:www\.)?(?:player\.)?vimeo\.com/(?:video/)?(\d+)(?:/([0-9a-f]+))?#i', $url, $m)) {
            $src = 'https://player.vimeo.com/video/' . $m[1];
            if (!empty($m[2])) {
                $src .= '?h=' . $m[2];
            }
            return ['provider' => 'vimeo', 'src' => $src];
        }
        return null;
    }

    /**
     * Whether the current user may watch a recording.
     *
     * Needs mod/itrecording:view (students and managers by default). A trainer who uses "Switch role to student" does
     * not get in that way, unless the "Trainers can watch recordings" setting is on.
     *
     * @param \context_module $context
     * @param int $courseid
     * @return bool
     */
    public static function can_view(\context_module $context, int $courseid): bool {
        if (is_siteadmin()) {
            return true;
        }
        $trainerscanview = (bool) get_config('itrecording', 'trainerscanview');
        if ($trainerscanview && has_capability('moodle/course:manageactivities', $context)) {
            return true;
        }
        if (is_role_switched($courseid) && !$trainerscanview) {
            return false;
        }
        return has_capability('mod/itrecording:view', $context);
    }
}
