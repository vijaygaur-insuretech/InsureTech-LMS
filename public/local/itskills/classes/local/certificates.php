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

defined('MOODLE_INTERNAL') || die();

/**
 * Certificates: eligibility, issuing with a unique ID, and rendering from the admin design (always the current design).
 *
 * @package    local_itskills
 * @copyright  2026 insureTech Skills
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificates {
    /**
     * Whether certificates are switched on for a course.
     *
     * @param \stdClass $course
     * @return bool
     */
    public static function enabled_for(\stdClass $course): bool {
        return (bool) get_config('local_itskills', 'certenabled') && !empty($course->enablecompletion) && $course->id != SITEID;
    }

    /**
     * Whether a learner meets the certificate rule for a course.
     *
     * Rule "completion": the course completion criteria set in the course (e.g. all lessons done + final quiz passed).
     * Rule "progress": activity progress reaches the configured percentage.
     *
     * @param int $userid
     * @param \stdClass $course
     * @return bool
     */
    public static function eligible(int $userid, \stdClass $course): bool {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        if (!self::enabled_for($course)) {
            return false;
        }
        $info = new \completion_info($course);
        if (get_config('local_itskills', 'certrule') !== 'progress' && $info->has_criteria()) {
            return $info->is_course_complete($userid);
        }
        $threshold = (int) (get_config('local_itskills', 'certprogress') ?: 100);
        return (\core_completion\progress::get_course_progress_percentage($course, $userid) ?? 0) >= $threshold;
    }

    /**
     * The issued certificate of a learner in a course, if any.
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get(int $userid, int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_itskills_cert', ['userid' => $userid, 'courseid' => $courseid]) ?: null;
    }

    /**
     * Issue the certificate when the learner is eligible (or when forced by an admin, which is recorded).
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $force Admin override.
     * @return \stdClass|null The certificate.
     */
    public static function issue_if_eligible(int $userid, int $courseid, bool $force = false): ?\stdClass {
        global $DB, $USER;
        if ($existing = self::get($userid, $courseid)) {
            return $existing;
        }
        $course = get_course($courseid);
        if (!$force && !self::eligible($userid, $course)) {
            return null;
        }
        do {
            $code = 'ITS-' . strtoupper(random_string(4) . '-' . random_string(4));
        } while ($DB->record_exists('local_itskills_cert', ['code' => $code]));
        $cert = (object) [
            'userid' => $userid, 'courseid' => $courseid, 'code' => $code, 'timeissued' => time(),
            'override' => $force ? 1 : 0, 'issuedby' => $force ? $USER->id : 0, 'emailed' => 0,
        ];
        $cert->id = $DB->insert_record('local_itskills_cert', $cert);

        $user = \core_user::get_user($userid);
        if ($user && !$user->deleted) {
            $url = new \moodle_url('/local/itskills/certificate.php', ['courseid' => $courseid]);
            mailer::notify($user, 'certificate', 'certificate', [
                'course' => format_string($course->fullname, true, ['escape' => false]),
                'code' => $code,
                'certificateurl' => $url->out(false),
                'verifyurl' => self::verify_url($code)->out(false),
            ], $url, $courseid);
            $DB->set_field('local_itskills_cert', 'emailed', 1, ['id' => $cert->id]);
        }
        return $cert;
    }

    /**
     * Public verification link.
     *
     * @param string $code
     * @return \moodle_url
     */
    public static function verify_url(string $code): \moodle_url {
        return new \moodle_url('/local/itskills/verify.php', ['code' => $code]);
    }

    /**
     * The current design, from the admin settings.
     *
     * @return array
     */
    public static function design(): array {
        $get = fn($name) => mailer::setting($name);
        $color = function(string $name, string $default) {
            $value = get_config('local_itskills', $name);
            return preg_match('/^#[0-9a-f]{6}$/i', (string) $value) ? $value : $default;
        };
        return [
            'title' => $get('cert_title'),
            'subtitle' => $get('cert_subtitle'),
            'body' => $get('cert_body'),
            'signname' => $get('cert_signname'),
            'signtitle' => $get('cert_signtitle'),
            'footer' => $get('cert_footer'),
            'primary' => $color('cert_primary', '#0C1A2B'),
            'accent' => $color('cert_accent', '#1199B6'),
            'gold' => $color('cert_gold', '#D4AF37'),
            'showqr' => (bool) get_config('local_itskills', 'cert_showqr'),
        ];
    }

    /**
     * A file uploaded in the design settings (background, logo or signature), as raw content.
     *
     * @param string $area cert_bg, cert_logo or cert_sign
     * @return string|null
     */
    public static function design_file(string $area): ?string {
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'local_itskills', $area, 0, 'id', false);
        $file = reset($files);
        return $file ? $file->get_content() : null;
    }

    /**
     * URL of a design file, for the on-screen preview.
     *
     * @param string $area
     * @return string|null
     */
    public static function design_file_url(string $area): ?string {
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'local_itskills', $area, 0, 'id', false);
        $file = reset($files);
        if (!$file) {
            return null;
        }
        return \moodle_url::make_pluginfile_url($file->get_contextid(), 'local_itskills', $area, 0,
            $file->get_filepath(), $file->get_filename())->out(false);
    }

    /**
     * Text with placeholders filled.
     *
     * @param string $text
     * @param array $vars
     * @return string
     */
    public static function fill(string $text, array $vars): string {
        $replace = [];
        foreach ($vars as $k => $v) {
            $replace['{' . $k . '}'] = $v;
        }
        return strtr($text, $replace);
    }

    /**
     * Placeholder values for a certificate.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param \stdClass $cert
     * @return array
     */
    public static function vars(\stdClass $user, \stdClass $course, \stdClass $cert): array {
        return [
            'student' => fullname($user),
            'course' => format_string($course->fullname, true, ['escape' => false]),
            'date' => userdate($cert->timeissued, get_string('strftimedate', 'langconfig')),
            'code' => $cert->code,
            'level' => fields::course($course->id)['itlevel'],
        ];
    }

    /**
     * Send the certificate as a PDF download, drawn with the current design.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param \stdClass $cert
     * @param bool $inline Show in the browser instead of downloading.
     */
    public static function output_pdf(\stdClass $user, \stdClass $course, \stdClass $cert, bool $inline = false): void {
        global $CFG, $OUTPUT;
        require_once($CFG->libdir . '/pdflib.php');

        $d = self::design();
        $vars = self::vars($user, $course, $cert);
        $rgb = fn($hex) => sscanf($hex, '#%02x%02x%02x');

        $pdf = new \pdf('L', 'mm', 'A4');
        $pdf->SetCreator('insureTech Skills');
        $pdf->SetTitle($d['title'] . ' - ' . $vars['student']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();
        $w = 297;
        $h = 210;

        if ($bg = self::design_file('cert_bg')) {
            $pdf->Image('@' . $bg, 0, 0, $w, $h, '', '', '', false, 300, '', false, false, 0);
        } else {
            // Default frame: navy band left, accent and gold borders.
            [$r, $g, $b] = $rgb($d['primary']);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect(0, 0, 12, $h, 'F');
            [$r, $g, $b] = $rgb($d['accent']);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect(12, 0, 3, $h, 'F');
            [$r, $g, $b] = $rgb($d['gold']);
            $pdf->SetDrawColor($r, $g, $b);
            $pdf->SetLineWidth(0.6);
            $pdf->Rect(24, 10, $w - 34, $h - 20, 'D');
            $pdf->SetLineWidth(0.2);
            $pdf->Rect(26, 12, $w - 38, $h - 24, 'D');
        }

        $logo = self::design_file('cert_logo');
        if ($logo) {
            $pdf->Image('@' . $logo, ($w - 70) / 2 + 6, 20, 70, 0, '', '', '', true, 300);
        } else {
            $logopath = $CFG->dirroot . '/theme/insuretech/pix/logo.png';
            if (file_exists($logopath)) {
                $pdf->Image($logopath, ($w - 70) / 2 + 6, 20, 70, 0, 'PNG', '', '', true, 300);
            }
        }

        $center = function(float $y, string $text, string $style, float $size, string $hex) use ($pdf, $rgb, $w) {
            [$r, $g, $b] = $rgb($hex);
            $pdf->SetTextColor($r, $g, $b);
            $pdf->SetFont('helvetica', $style, $size);
            $pdf->SetXY(30, $y);
            $pdf->MultiCell($w - 48, 0, $text, 0, 'C', false, 1);
        };

        $center(48, \core_text::strtoupper(self::fill($d['title'], $vars)), 'B', 30, $d['primary']);
        $center(64, self::fill($d['subtitle'], $vars), '', 13, '#5B6B7F');
        $center(76, $vars['student'], 'B', 34, $d['accent']);
        [$r, $g, $b] = $rgb($d['gold']);
        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($w / 2 - 50 + 6, 94, $w / 2 + 50 + 6, 94);
        $center(100, self::fill($d['body'], $vars), '', 13, '#1E2B3B');

        // Bottom: date and ID (left), signature (right), QR code to the verification page (middle).
        [$r, $g, $b] = $rgb('#1E2B3B');
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(40, 158);
        $pdf->Cell(80, 6, get_string('cert_issued', 'local_itskills') . ': ' . $vars['date'], 0, 2);
        $pdf->Cell(80, 6, get_string('cert_id', 'local_itskills') . ': ' . $cert->code, 0, 2);

        if ($sign = self::design_file('cert_sign')) {
            $pdf->Image('@' . $sign, 200, 146, 50, 14, '', '', '', true, 300, '', false, false, 0, 'CM');
        }
        $pdf->SetDrawColor(30, 43, 59);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(195, 162, 260, 162);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(185, 164);
        $pdf->Cell(85, 6, self::fill($d['signname'], $vars), 0, 2, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(85, 5, self::fill($d['signtitle'], $vars), 0, 2, 'C');

        if ($d['showqr']) {
            $pdf->write2DBarcode(self::verify_url($cert->code)->out(false), 'QRCODE,M', 140, 150, 26, 26);
        }
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(91, 107, 127);
        $pdf->SetXY(30, 190);
        $pdf->MultiCell($w - 48, 0, self::fill($d['footer'], $vars) . '  ' . self::verify_url($cert->code)->out(false),
            0, 'C', false, 1);

        $filename = clean_filename('certificate-' . $course->shortname . '-' . fullname($user) . '.pdf');
        $pdf->Output($filename, $inline ? 'I' : 'D');
    }

    /**
     * All certificates of a learner.
     *
     * @param int $userid
     * @return array[]
     */
    public static function for_user(int $userid): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('local_itskills_cert', ['userid' => $userid], 'timeissued DESC') as $cert) {
            $course = $DB->get_record('course', ['id' => $cert->courseid]);
            if (!$course) {
                continue;
            }
            $out[] = [
                'course' => format_string($course->fullname, true, ['escape' => false]),
                'date' => userdate($cert->timeissued, get_string('strftimedate', 'langconfig')),
                'code' => $cert->code,
                'url' => (new \moodle_url('/local/itskills/certificate.php', ['courseid' => $course->id]))->out(false),
                'downloadurl' => (new \moodle_url('/local/itskills/certificate.php',
                    ['courseid' => $course->id, 'action' => 'download']))->out(false),
                'verifyurl' => self::verify_url($cert->code)->out(false),
            ];
        }
        return $out;
    }
}
