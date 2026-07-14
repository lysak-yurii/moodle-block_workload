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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_workload;

/**
 * Bulk importer for course workload targets (CSV and xlsx).
 *
 * Both formats converge into the same normalised rows, which are matched
 * against courses (course_id → shortname → idnumber), categorised for a
 * preview, and only applied after explicit confirmation.
 *
 * @package   block_workload
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class targets_importer {
    /**
     * Parse an uploaded .csv or .xlsx file into normalised rows.
     *
     * @param string $content Raw file content.
     * @param string $filename Original file name (extension decides the parser).
     * @return array ['error' => string|null lang key, 'rows' => array]
     */
    public static function parse_file(string $content, string $filename): array {
        $ext   = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $cells = ($ext === 'xlsx') ? self::read_xlsx($content) : self::read_csv($content);

        if (count($cells) < 2) {
            return ['error' => 'importnorows', 'rows' => []];
        }

        // Normalise headers and accept common aliases (round-trips our export).
        $headers = array_map(
            static fn($h) => strtolower(trim((string) $h)),
            array_shift($cells)
        );
        $aliases = [
            'course_id'    => 'course_id',
            'courseid'     => 'course_id',
            'id'           => 'course_id',
            'shortname'    => 'shortname',
            'short_name'   => 'shortname',
            'idnumber'     => 'idnumber',
            'id_number'    => 'idnumber',
            'target_hours' => 'target_hours',
            'targethours'  => 'target_hours',
            'target'       => 'target_hours',
        ];
        $map = [];
        foreach ($headers as $i => $h) {
            if (isset($aliases[$h]) && !isset($map[$aliases[$h]])) {
                $map[$aliases[$h]] = $i;
            }
        }
        $hasidentifier = isset($map['course_id']) || isset($map['shortname']) || isset($map['idnumber']);
        if (!isset($map['target_hours']) || !$hasidentifier) {
            return ['error' => 'importmissingcolumns', 'rows' => []];
        }

        $rows = [];
        $line = 1;
        foreach ($cells as $cell) {
            $line++;
            $get = static fn(string $col): string => isset($map[$col])
                ? trim((string) ($cell[$map[$col]] ?? ''))
                : '';
            $row = [
                'line'         => $line,
                'course_id'    => $get('course_id'),
                'shortname'    => $get('shortname'),
                'idnumber'     => $get('idnumber'),
                'target_hours' => $get('target_hours'),
            ];
            // Skip fully empty lines (trailing newlines in CSVs etc.).
            if (
                $row['course_id'] === '' && $row['shortname'] === ''
                    && $row['idnumber'] === '' && $row['target_hours'] === ''
            ) {
                continue;
            }
            $rows[] = $row;
        }
        if (empty($rows)) {
            return ['error' => 'importnorows', 'rows' => []];
        }
        return ['error' => null, 'rows' => $rows];
    }

    /**
     * Read CSV content into an array of row arrays (first row = headers).
     * Auto-detects comma vs semicolon delimiters (German Excel exports use ;).
     *
     * @param string $content
     * @return array
     */
    protected static function read_csv(string $content): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        // Strip a leading UTF-8 BOM: Windows Excel (and Moodle's own CSV export)
        // prepend one, which would otherwise corrupt the first header cell and
        // break the export → edit → re-import round-trip.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $firstline = strtok($content, "\n");
        $delimiter = (substr_count($firstline, ';') > substr_count($firstline, ','))
            ? 'semicolon' : 'comma';

        $iid = \csv_import_reader::get_new_iid('block_workload_targets');
        $cir = new \csv_import_reader($iid, 'block_workload_targets');
        $count = $cir->load_csv_content($content, 'utf-8', $delimiter);
        if ($count === false || $count == 0) {
            $cir->cleanup();
            return [];
        }
        $cells = [$cir->get_columns()];
        $cir->init();
        while ($row = $cir->next()) {
            $cells[] = $row;
        }
        $cir->close();
        $cir->cleanup();
        return $cells;
    }

    /**
     * Read the first worksheet of an xlsx file into an array of row arrays.
     *
     * @param string $content
     * @return array
     */
    protected static function read_xlsx(string $content): array {
        $path = make_request_directory() . '/targets.xlsx';
        file_put_contents($path, $content);
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (\Exception $e) {
            return [];
        }
        return $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
    }

    /**
     * Normalise a target-hours cell.
     *
     * @param string $raw
     * @return float|null Hours ('' counts as 0.0 = clear); null when invalid.
     */
    public static function normalise_hours(string $raw): ?float {
        $raw = trim($raw);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace(',', '.', $raw);
        if (!is_numeric($raw) || (float) $raw < 0) {
            return null;
        }
        return round((float) $raw, 1);
    }

    /**
     * Match rows to courses and categorise them for the preview.
     * Nothing is written here.
     *
     * @param array $rows Rows from parse_file().
     * @return array ['rows' => preview rows, 'counts' => per-status counts,
     *                'changes' => actionable [['c' => courseid, 'h' => hours], ...]]
     */
    public static function categorise(array $rows): array {
        global $DB;

        $targets = helper::get_all_targets();
        $counts  = ['new' => 0, 'changed' => 0, 'unchanged' => 0, 'cleared' => 0, 'unmatched' => 0, 'invalid' => 0];
        $preview = [];
        $changes = [];

        foreach ($rows as $r) {
            $course = null;
            if ($r['course_id'] !== '' && is_numeric($r['course_id'])) {
                $course = $DB->get_record('course', ['id' => (int) $r['course_id']], 'id, fullname, shortname');
            }
            if (!$course && $r['shortname'] !== '') {
                // IGNORE_MULTIPLE: shortname is normally unique, but never crash the
                // whole preview on a duplicate — take the first match.
                $course = $DB->get_record(
                    'course',
                    ['shortname' => $r['shortname']],
                    'id, fullname, shortname',
                    IGNORE_MULTIPLE
                );
            }
            if (!$course && $r['idnumber'] !== '') {
                // The course.idnumber column has no unique DB constraint, so duplicates
                // are a realistic admin state — IGNORE_MULTIPLE avoids a fatal exception.
                $course = $DB->get_record(
                    'course',
                    ['idnumber' => $r['idnumber']],
                    'id, fullname, shortname',
                    IGNORE_MULTIPLE
                );
            }

            $identifier = ($r['course_id'] !== '') ? $r['course_id']
                : (($r['shortname'] !== '') ? $r['shortname'] : $r['idnumber']);

            if (!$course || (int) $course->id === SITEID) {
                $counts['unmatched']++;
                $preview[] = [
                    'line' => $r['line'], 'identifier' => $identifier, 'coursename' => '',
                    'oldtarget' => '', 'newtarget' => $r['target_hours'], 'status' => 'unmatched',
                ];
                continue;
            }

            $hours = self::normalise_hours($r['target_hours']);
            $old   = isset($targets[$course->id]) ? (float) $targets[$course->id] : 0.0;

            if ($hours === null) {
                $status = 'invalid';
            } else if ($hours == 0.0) {
                $status = ($old > 0) ? 'cleared' : 'unchanged';
            } else if ($old == 0.0) {
                $status = 'new';
            } else if (abs($old - $hours) < 0.05) {
                $status = 'unchanged';
            } else {
                $status = 'changed';
            }
            $counts[$status]++;
            if (in_array($status, ['new', 'changed', 'cleared'], true)) {
                $changes[] = ['c' => (int) $course->id, 'h' => ($status === 'cleared') ? 0.0 : $hours];
            }

            $preview[] = [
                'line'       => $r['line'],
                'identifier' => $identifier,
                'coursename' => format_string($course->fullname),
                'oldtarget'  => ($old > 0) ? number_format($old, 1) : '',
                'newtarget'  => ($hours !== null && $hours > 0) ? number_format($hours, 1) : '',
                'status'     => $status,
            ];
        }

        return ['rows' => $preview, 'counts' => $counts, 'changes' => $changes];
    }

    /**
     * Apply confirmed changes inside a transaction.
     *
     * @param array $changes [['c' => courseid, 'h' => hours], ...]; h = 0 clears.
     * @param int   $userid The manager applying the import (audit).
     * @return array ['created' => int, 'updated' => int, 'cleared' => int]
     */
    public static function apply(array $changes, int $userid): array {
        global $DB;

        $counts   = ['created' => 0, 'updated' => 0, 'cleared' => 0];
        $existing = helper::get_all_targets();

        $tx = $DB->start_delegated_transaction();
        foreach ($changes as $ch) {
            $courseid = (int) ($ch['c'] ?? 0);
            $hours    = isset($ch['h']) && is_numeric($ch['h']) ? round((float) $ch['h'], 1) : -1.0;
            if ($courseid <= 0 || $courseid === SITEID || $hours < 0) {
                continue;
            }
            if (!$DB->record_exists('course', ['id' => $courseid])) {
                continue;
            }
            if ($hours == 0.0) {
                if (isset($existing[$courseid])) {
                    helper::delete_course_target($courseid);
                    $counts['cleared']++;
                    unset($existing[$courseid]);
                }
            } else if (isset($existing[$courseid])) {
                helper::set_course_target($courseid, $hours, $userid);
                $counts['updated']++;
                $existing[$courseid] = $hours;
            } else {
                helper::set_course_target($courseid, $hours, $userid);
                $counts['created']++;
                // Track so a duplicate row for the same course counts as an update.
                $existing[$courseid] = $hours;
            }
        }
        $tx->allow_commit();

        return $counts;
    }
}
