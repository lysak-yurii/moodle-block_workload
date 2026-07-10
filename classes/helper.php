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

/**
 * Central helper/business-logic class for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_workload;


/**
 * Helper functions for block_workload.
 */
class helper {
    // Cohort queries.


    /**
     * Return the first active cohort the given user belongs to.
     * Kept for backward compatibility; prefer get_user_active_cohorts() for new code.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_user_cohort(int $userid): ?\stdClass {
        global $DB;
        $sql = "SELECT c.*
                  FROM {block_workload_cohorts} c
                  JOIN {block_workload_members} m ON m.cohortid = c.id
                 WHERE m.userid = :userid
                   AND c.active = 1
              ORDER BY c.name ASC";
        $records = $DB->get_records_sql($sql, ['userid' => $userid], 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Return ALL active cohorts the given user belongs to, ordered by id ASC.
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_active_cohorts(int $userid): array {
        global $DB;
        $sql = "SELECT c.*
                  FROM {block_workload_cohorts} c
                  JOIN {block_workload_members} m ON m.cohortid = c.id
                 WHERE m.userid = :userid
                   AND c.active = 1
              ORDER BY c.id ASC";
        return $DB->get_records_sql($sql, ['userid' => $userid]);
    }

    /**
     * Return deduplicated active courses across multiple cohorts for the student block.
     *
     * When the same course appears in more than one cohort, it is shown once;
     * sort order is the minimum across all cohorts.
     *
     * @param int[]  $cohortids  IDs of the student's currently-active cohorts.
     * @param string $order      'sortorder' (default) or 'recentaccess'.
     * @param int    $userid     Required when $order === 'recentaccess'.
     */
    public static function get_merged_cohort_courses(array $cohortids, string $order = 'sortorder', int $userid = 0): array {
        global $DB;
        if (empty($cohortids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED, 'cid');

        if ($order === 'recentaccess' && $userid > 0) {
            $inparams['userid'] = $userid;
            $sql = "SELECT co.id, co.fullname, co.shortname, MIN(wc.sortorder) AS sortorder
                      FROM {block_workload_courses} wc
                      JOIN {course} co ON co.id = wc.courseid
                      LEFT JOIN {user_lastaccess} la ON la.courseid = co.id AND la.userid = :userid
                     WHERE wc.cohortid $insql
                       AND wc.active = 1
                  GROUP BY co.id, co.fullname, co.shortname
                  ORDER BY COALESCE(MAX(la.timeaccess), 0) DESC, co.fullname ASC";
            return $DB->get_records_sql($sql, $inparams);
        }

        $sql = "SELECT co.id, co.fullname, co.shortname, MIN(wc.sortorder) AS sortorder
                  FROM {block_workload_courses} wc
                  JOIN {course} co ON co.id = wc.courseid
                 WHERE wc.cohortid $insql
                   AND wc.active = 1
              GROUP BY co.id, co.fullname, co.shortname
              ORDER BY MIN(wc.sortorder) ASC, co.fullname ASC";
        return $DB->get_records_sql($sql, $inparams);
    }

    /**
     * Return all active courses assigned to a cohort.
     *
     * $order = 'sortorder'    — quality-manager sort order, then name (default).
     * $order = 'recentaccess' — most-recently visited by $userid first, then name.
     *
     * @param int    $cohortid
     * @param string $order    'sortorder' or 'recentaccess'
     * @param int    $userid   Required when $order === 'recentaccess'.
     * @return array
     */
    public static function get_cohort_courses(int $cohortid, string $order = 'sortorder', int $userid = 0): array {
        global $DB;

        if ($order === 'recentaccess' && $userid > 0) {
            $sql = "SELECT co.id, co.fullname, co.shortname, wc.sortorder, wc.id AS assignid
                      FROM {block_workload_courses} wc
                      JOIN {course} co ON co.id = wc.courseid
                      LEFT JOIN {user_lastaccess} la ON la.courseid = co.id AND la.userid = :userid
                     WHERE wc.cohortid = :cohortid
                       AND wc.active   = 1
                  ORDER BY COALESCE(la.timeaccess, 0) DESC, co.fullname ASC";
            return $DB->get_records_sql($sql, ['cohortid' => $cohortid, 'userid' => $userid]);
        }

        $sql = "SELECT co.id, co.fullname, co.shortname, wc.sortorder, wc.id AS assignid
                  FROM {block_workload_courses} wc
                  JOIN {course} co ON co.id = wc.courseid
                 WHERE wc.cohortid = :cohortid
                   AND wc.active   = 1
              ORDER BY wc.sortorder ASC, co.fullname ASC";
        return $DB->get_records_sql($sql, ['cohortid' => $cohortid]);
    }

    /**
     * Return all courses assigned to a cohort (including inactive), for management UI.
     *
     * @param int $cohortid
     * @return array
     */
    public static function get_cohort_courses_all(int $cohortid): array {
        global $DB;
        $sql = "SELECT co.id, co.fullname, co.shortname, co.startdate, co.enddate,
                       wc.sortorder, wc.active, wc.id AS assignid
                  FROM {block_workload_courses} wc
                  JOIN {course} co ON co.id = wc.courseid
                 WHERE wc.cohortid = :cohortid
              ORDER BY wc.sortorder ASC, co.fullname ASC";
        return $DB->get_records_sql($sql, ['cohortid' => $cohortid]);
    }

    /**
     * Return paged members of a cohort with basic user info.
     * $limit = 0 means no limit (show all).
     *
     * @param int    $cohortid
     * @param int    $limit       0 = no limit.
     * @param int    $offset
     * @param string $firstletter A-Z filter on firstname, or empty string.
     * @param string $lastletter  A-Z filter on lastname, or empty string.
     * @param string $orderby     SQL ORDER BY expression, or empty string for the default.
     * @return array
     */
    public static function get_cohort_members(
        int $cohortid,
        int $limit = 0,
        int $offset = 0,
        string $firstletter = '',
        string $lastletter = '',
        string $orderby = ''
    ): array {
        global $DB;
        [$where, $params] = self::members_where($cohortid, $firstletter, $lastletter);
        $orderby = $orderby !== '' ? $orderby : 'u.lastname ASC, u.firstname ASC';
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.username,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       u.department, u.institution,
                       m.id AS memberid
                  FROM {block_workload_members} m
                  JOIN {user} u ON u.id = m.userid
                 WHERE $where
              ORDER BY $orderby";
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Return the total count of (non-deleted) members in a cohort, with optional letter filters.
     *
     * @param int    $cohortid
     * @param string $firstletter A-Z filter on firstname, or empty string.
     * @param string $lastletter  A-Z filter on lastname, or empty string.
     * @return int
     */
    public static function get_cohort_members_count(
        int $cohortid,
        string $firstletter = '',
        string $lastletter = ''
    ): int {
        global $DB;
        [$where, $params] = self::members_where($cohortid, $firstletter, $lastletter);
        return (int) $DB->count_records_sql(
            "SELECT COUNT(u.id)
               FROM {block_workload_members} m
               JOIN {user} u ON u.id = m.userid
              WHERE $where",
            $params
        );
    }

    /**
     * Build the WHERE clause + params for members queries, optionally filtered by A-Z letters.
     *
     * @param int    $cohortid
     * @param string $firstletter
     * @param string $lastletter
     * @return array [string $where, array $params]
     */
    private static function members_where(
        int $cohortid,
        string $firstletter,
        string $lastletter
    ): array {
        global $DB;
        $where  = 'm.cohortid = :cohortid AND u.deleted = 0';
        $params = ['cohortid' => $cohortid];
        if ($firstletter !== '') {
            $where .= ' AND ' . $DB->sql_like('u.firstname', ':firstletter', false);
            $params['firstletter'] = $DB->sql_like_escape($firstletter) . '%';
        }
        if ($lastletter !== '') {
            $where .= ' AND ' . $DB->sql_like('u.lastname', ':lastletter', false);
            $params['lastletter'] = $DB->sql_like_escape($lastletter) . '%';
        }
        return [$where, $params];
    }

    /**
     * Return a flat array of all user IDs in a cohort (for "already member" checks).
     *
     * @param int $cohortid
     * @return array
     */
    public static function get_all_cohort_member_ids(int $cohortid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT u.id
               FROM {block_workload_members} m
               JOIN {user} u ON u.id = m.userid
              WHERE m.cohortid = :cohortid AND u.deleted = 0",
            ['cohortid' => $cohortid]
        ));
    }


    // Activation.


    /**
     * Check whether workload is currently active for a cohort.
     * Active means: cohort.active = 1, and the current ISO week falls within the
     * optional date range stored on the cohort (week_from/year_from to week_to/year_to).
     * If no range is set the cohort is always active when active = 1.
     *
     * Queries the DB per call — for single-record checks only. When iterating
     * over already-loaded cohort records, use filter_cohorts_active_now() or
     * is_workload_active_for() instead.
     *
     * @param int $cohortid
     * @return bool
     */
    public static function is_workload_active(int $cohortid): bool {
        global $DB;

        $cohort = $DB->get_record('block_workload_cohorts', ['id' => $cohortid, 'active' => 1]);
        if (!$cohort) {
            return false;
        }

        return self::is_workload_active_for($cohort, (int) date('o'), (int) date('W'));
    }

    /**
     * Week-aware variant of is_workload_active(): test whether the given cohort's
     * active window contains the supplied ISO week/year (instead of "now").
     *
     * @param \stdClass $cohort  Cohort record (expects active, week_from/year_from, week_to/year_to).
     * @param int       $year    ISO year of the week to test.
     * @param int       $week    ISO week number to test.
     * @return bool
     */
    public static function is_workload_active_for(\stdClass $cohort, int $year, int $week): bool {
        if ((int) $cohort->active !== 1) {
            return false;
        }

        // No date range → always active.
        if (!$cohort->week_from && !$cohort->week_to) {
            return true;
        }

        $target = $year * 100 + $week;
        $from   = $cohort->year_from ? ((int) $cohort->year_from * 100 + (int) $cohort->week_from) : 0;
        $to     = $cohort->year_to ? ((int) $cohort->year_to * 100 + (int) $cohort->week_to) : PHP_INT_MAX;

        return ($target >= $from && $target <= $to);
    }

    /**
     * Filter cohort records down to those whose activation window contains the
     * current ISO week.
     *
     * In-memory companion to is_workload_active(): callers that already hold
     * full cohort records (e.g. from get_user_active_cohorts()) should use this
     * instead of calling is_workload_active() once per cohort.
     *
     * @param array $cohorts Cohort records, keyed by id.
     * @return array The currently-active subset, original keys preserved.
     */
    public static function filter_cohorts_active_now(array $cohorts): array {
        $year = (int) date('o');
        $week = (int) date('W');
        return array_filter(
            $cohorts,
            static fn($c) => self::is_workload_active_for($c, $year, $week)
        );
    }

    /**
     * Configured backfill window: how many weeks before the current week a student
     * may still edit. Returns 0 (current week only) when backfilling is disabled.
     *
     * @return int
     */
    public static function get_backfill_weeks(): int {
        // Master on/off switch (default on). When explicitly off, only the current week is editable.
        $enabled = get_config('block_workload', 'enablebackfill');
        if ($enabled !== false && (int) $enabled === 0) {
            return 0;
        }
        // When enabled the window is always at least one week (off is handled by the switch above).
        $raw = get_config('block_workload', 'backfillweeks');
        return ($raw !== false) ? max(1, (int) $raw) : 4;
    }

    /**
     * Return the ISO year/week that is $weeksback weeks before the current ISO week.
     * Uses DateTime so the arithmetic is correct across year boundaries (52/53 weeks).
     *
     * @param int $weeksback  Number of weeks to step back (0 = current week).
     * @return array ['year' => int, 'week' => int]
     */
    public static function iso_week_offset(int $weeksback): array {
        $dt = new \DateTime();
        // Normalise to the Monday of the current ISO week before stepping back.
        $dt->setISODate((int) $dt->format('o'), (int) $dt->format('W'));
        if ($weeksback !== 0) {
            $dt->modify('-' . (int) $weeksback . ' weeks');
        }
        return ['year' => (int) $dt->format('o'), 'week' => (int) $dt->format('W')];
    }

    /**
     * Whether the given user may create or edit an entry for the given ISO week/year.
     *
     * This is the single server-side guard for the write path: it rejects out-of-range
     * values, future weeks, weeks older than the rolling backfill window, and (in cohort
     * mode) weeks that fall outside every cohort window the user belongs to.
     *
     * @param int        $userid
     * @param int        $year
     * @param int        $week
     * @param array|null $cohorts Preloaded active cohorts of the user (cohort mode only);
     *                            null = load them here. Lets callers that test several
     *                            weeks avoid one query per week.
     * @return bool
     */
    public static function is_week_editable(int $userid, int $year, int $week, ?array $cohorts = null): bool {
        // Basic range sanity.
        if ($week < 1 || $week > 53 || $year < 2000 || $year > 2100) {
            return false;
        }

        $target  = $year * 100 + $week;
        $current = (int) date('o') * 100 + (int) date('W');

        // No future weeks.
        if ($target > $current) {
            return false;
        }

        // Rolling backfill floor (current week minus the configured window).
        $floor    = self::iso_week_offset(self::get_backfill_weeks());
        $floorint = $floor['year'] * 100 + $floor['week'];
        if ($target < $floorint) {
            return false;
        }

        $coursemode = get_config('block_workload', 'coursemode') ?: 'cohort';
        if ($coursemode === 'enrollment') {
            // No cohort window in enrollment mode; floor + not-future is enough.
            return self::is_user_widget_active($userid);
        }

        // Cohort mode: the week must fall within at least one of the user's cohort windows.
        foreach ($cohorts ?? self::get_user_active_cohorts($userid) as $cohort) {
            if (self::is_workload_active_for($cohort, $year, $week)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Earliest and latest editable weeks for the block's week navigation, as
     * comparable integers (year * 100 + week). The latest is always the current week.
     *
     * Walks back from the current week to the rolling floor and stops at the first
     * non-editable week, so overlapping cohort windows and the cohort start are all
     * respected. Per-week saves are still validated by is_week_editable().
     *
     * @param int $userid
     * @return array ['min' => int, 'max' => int]
     */
    public static function get_editable_week_bounds(int $userid): array {
        $maxint = (int) date('o') * 100 + (int) date('W');
        $minint = $maxint;

        // Load the user's cohorts once for all week checks (unused in enrollment mode).
        $coursemode = get_config('block_workload', 'coursemode') ?: 'cohort';
        $cohorts    = ($coursemode === 'enrollment') ? null : self::get_user_active_cohorts($userid);

        $n = self::get_backfill_weeks();
        for ($i = 1; $i <= $n; $i++) {
            $wk = self::iso_week_offset($i);
            if (self::is_week_editable($userid, $wk['year'], $wk['week'], $cohorts)) {
                $minint = $wk['year'] * 100 + $wk['week'];
            } else {
                break;
            }
        }

        return ['min' => $minint, 'max' => $maxint];
    }

    /**
     * Check whether the workload widget is enabled for a student in enrollment mode.
     * Defaults to enabled when no override row exists.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_user_widget_active(int $userid): bool {
        global $DB;

        $active = $DB->get_field('block_workload_user_settings', 'active', ['userid' => $userid]);

        return $active === false || (int)$active === 1;
    }

    /**
     * Toggle whether the workload widget is enabled for a student (enrollment mode).
     *
     * @param int $userid
     * @return bool The new active state.
     */
    public static function toggle_user_widget_active(int $userid): bool {
        global $DB;

        $now      = time();
        $existing = $DB->get_record('block_workload_user_settings', ['userid' => $userid]);

        if ($existing) {
            $existing->active       = $existing->active ? 0 : 1;
            $existing->timemodified = $now;
            $DB->update_record('block_workload_user_settings', $existing);

            return (bool)$existing->active;
        }

        $DB->insert_record('block_workload_user_settings', (object)[
            'userid'       => $userid,
            'active'       => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        return false;
    }


    // Entries.


    /**
     * Return workload entries for a user in a given week, indexed by courseid.
     *
     * @param int $userid
     * @param int $week ISO week number.
     * @param int $year
     * @return array
     */
    public static function get_week_entries(int $userid, int $week, int $year): array {
        global $DB;
        $records = $DB->get_records('block_workload_entries', [
            'userid'     => $userid,
            'weeknumber' => $week,
            'year'       => $year,
        ]);
        $indexed = [];
        foreach ($records as $r) {
            $indexed[$r->courseid] = (float) $r->hours;
        }
        return $indexed;
    }

    /**
     * Insert or update a single workload entry.
     *
     * @param int      $userid
     * @param int|null $cohortid
     * @param int      $courseid
     * @param int      $week ISO week number.
     * @param int      $year
     * @param float    $hours
     */
    public static function save_entry(int $userid, ?int $cohortid, int $courseid, int $week, int $year, float $hours): void {
        global $DB;

        $existing = $DB->get_record('block_workload_entries', [
            'userid'     => $userid,
            'courseid'   => $courseid,
            'weeknumber' => $week,
            'year'       => $year,
        ]);

        $now = time();
        if ($existing) {
            $existing->hours        = $hours;
            $existing->cohortid     = $cohortid;
            $existing->timemodified = $now;
            $DB->update_record('block_workload_entries', $existing);
        } else {
            $r               = new \stdClass();
            $r->userid       = $userid;
            $r->cohortid     = $cohortid;
            $r->courseid     = $courseid;
            $r->weeknumber   = $week;
            $r->year         = $year;
            $r->hours        = $hours;
            $r->timecreated  = $now;
            $r->timemodified = $now;
            $DB->insert_record('block_workload_entries', $r);
        }
    }


    // Statistics.


    /**
     * Return detailed entries for a cohort, optionally filtered by week range.
     * Each row: userid, firstname, lastname, email, courseid, coursename,
     *           weeknumber, year, hours.
     *
     * @param int      $cohortid
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return array
     */
    public static function get_statistics(
        int $cohortid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): array {
        global $DB;

        $params = ['cohortid' => $cohortid];
        $where  = ['EXISTS (SELECT 1 FROM {block_workload_members} wm'
            . ' WHERE wm.userid = e.userid AND wm.cohortid = :cohortid)'];

        // Year supplied without week → use first/last ISO week of that year.
        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto   = 53;
        }

        if ($weekfrom && $yearfrom) {
            $where[]              = '(e.year * 100 + e.weeknumber) >= :from';
            $params['from']       = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto && $yearto) {
            $where[]              = '(e.year * 100 + e.weeknumber) <= :to';
            $params['to']         = $yearto * 100 + $weekto;
        }

        $sql = "SELECT e.id, e.userid, e.courseid, e.weeknumber, e.year, e.hours,
                       u.firstname, u.lastname, u.email,
                       c.fullname AS coursename, c.shortname AS courseshortname
                  FROM {block_workload_entries} e
                  JOIN {user}   u ON u.id = e.userid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY u.lastname ASC, u.firstname ASC, e.year ASC, e.weeknumber ASC, c.fullname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return per-student aggregated totals for a cohort.
     *
     * @param int      $cohortid
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return array
     */
    public static function get_aggregated_statistics(
        int $cohortid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): array {
        global $DB;

        $params = ['cohortid' => $cohortid];
        $where  = ['EXISTS (SELECT 1 FROM {block_workload_members} wm'
            . ' WHERE wm.userid = e.userid AND wm.cohortid = :cohortid)'];

        // Year supplied without week → use first/last ISO week of that year.
        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto   = 53;
        }

        if ($weekfrom && $yearfrom) {
            $where[]        = '(e.year * 100 + e.weeknumber) >= :from';
            $params['from'] = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto && $yearto) {
            $where[]      = '(e.year * 100 + e.weeknumber) <= :to';
            $params['to'] = $yearto * 100 + $weekto;
        }

        $sql = "SELECT e.userid,
                       u.firstname, u.lastname, u.email,
                       SUM(e.hours)  AS totalhours,
                       COUNT(DISTINCT e.year * 100 + e.weeknumber) AS weeksactive,
                       COUNT(e.id)   AS entrycount
                  FROM {block_workload_entries} e
                  JOIN {user} u ON u.id = e.userid
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY e.userid, u.firstname, u.lastname, u.email
              ORDER BY u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return a student's own entries, optionally filtered by ISO week range.
     *
     * Pass weekfrom+yearfrom and/or weekto+yearto to restrict results.
     * Both halves of a range pair must be non-null to take effect.
     *
     * @param int      $userid
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return array
     */
    public static function get_student_entries(
        int $userid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): array {
        global $DB;
        $params = ['userid' => $userid];
        $where  = 'e.userid = :userid';

        // Year supplied without week → use first/last ISO week of that year.
        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto   = 53;
        }

        if ($weekfrom !== null && $yearfrom !== null) {
            $where           .= ' AND (e.year * 100 + e.weeknumber) >= :wf';
            $params['wf']     = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto !== null && $yearto !== null) {
            $where           .= ' AND (e.year * 100 + e.weeknumber) <= :wt';
            $params['wt']     = $yearto * 100 + $weekto;
        }

        $sql = "SELECT e.id, e.courseid, e.weeknumber, e.year, e.hours,
                       c.fullname AS coursename
                  FROM {block_workload_entries} e
                  JOIN {course} c ON c.id = e.courseid
                 WHERE {$where}
              ORDER BY e.year ASC, e.weeknumber ASC, c.fullname ASC";
        return $DB->get_records_sql($sql, $params);
    }


    // Enrollment-mode helpers.


    /**
     * Return courses shown to a student in enrollment mode:
     * - Moodle-enrolled courses that the manager has NOT excluded (active=0 override)
     * - PLUS courses the manager force-added (active=1 override, not enrolled)
     *
     * $order = 'sortorder'    — per-student manual order set by the QM, then name (default)
     * $order = 'recentaccess' — most-recently visited first, then name
     *
     * @param int    $userid
     * @param string $order 'sortorder' or 'recentaccess'
     * @return array
     */
    public static function get_user_enrolled_courses(int $userid, string $order = 'sortorder'): array {
        global $DB;

        // Optionally restrict to courses that are currently active (admin-configurable,
        // on by default). "Active now" = started and not yet ended; an unset end date
        // (enddate = 0) means open-ended and stays visible once started.
        $rawflag    = get_config('block_workload', 'enrollmentactiveonly');
        $activeonly = ($rawflag === false) ? true : (bool) $rawflag;

        $datewhere  = '';
        $dateparams = [];
        if ($activeonly) {
            $now        = time();
            $datewhere  = ' AND co.startdate <= :now1 AND (co.enddate = 0 OR co.enddate >= :now2) ';
            $dateparams = ['now1' => $now, 'now2' => $now];
        }

        // Enrolled and not force-excluded, and not globally hidden unless the
        // manager force-included this course for this student (active=1 wins).
        $enrolled = $DB->get_records_sql(
            "SELECT DISTINCT co.id, co.fullname, co.shortname
               FROM {course} co
               JOIN {enrol} en ON en.courseid = co.id AND en.status = 0
               JOIN {user_enrolments} ue ON ue.enrolid = en.id AND ue.userid = :uid AND ue.status = 0
              WHERE co.id <> :site AND co.visible = 1
                AND NOT EXISTS (
                    SELECT 1 FROM {block_workload_user_courses} ov
                     WHERE ov.userid = :uid2 AND ov.courseid = co.id AND ov.active = 0
                )
                AND (
                    NOT EXISTS (
                        SELECT 1 FROM {block_workload_global_courses} gh
                         WHERE gh.courseid = co.id
                    )
                    OR EXISTS (
                        SELECT 1 FROM {block_workload_user_courses} inc
                         WHERE inc.userid = :uid3 AND inc.courseid = co.id AND inc.active = 1
                    )
                )" . $datewhere,
            ['uid' => $userid, 'uid2' => $userid, 'uid3' => $userid, 'site' => SITEID] + $dateparams
        );

        // Force-added by manager (active=1) and NOT already enrolled.
        $added = $DB->get_records_sql(
            "SELECT co.id, co.fullname, co.shortname
               FROM {course} co
               JOIN {block_workload_user_courses} ov ON ov.courseid = co.id
                    AND ov.userid = :uid AND ov.active = 1
              WHERE co.id <> :site AND co.visible = 1
                AND NOT EXISTS (
                    SELECT 1 FROM {enrol} en
                    JOIN {user_enrolments} ue ON ue.enrolid = en.id
                    WHERE en.courseid = co.id AND en.status = 0
                      AND ue.userid = :uid2 AND ue.status = 0
                )" . $datewhere,
            ['uid' => $userid, 'uid2' => $userid, 'site' => SITEID] + $dateparams
        );

        // Merge (keyed by courseid → no duplicates).
        $merged = $enrolled + $added;

        if ($order === 'recentaccess') {
            $accesses = $DB->get_records_sql(
                "SELECT courseid, timeaccess FROM {user_lastaccess} WHERE userid = :uid",
                ['uid' => $userid]
            );
            uasort($merged, function ($a, $b) use ($accesses) {
                $ta = isset($accesses[$a->id]) ? (int)$accesses[$a->id]->timeaccess : 0;
                $tb = isset($accesses[$b->id]) ? (int)$accesses[$b->id]->timeaccess : 0;
                if ($ta !== $tb) {
                    return $tb - $ta;
                }
                return strcasecmp($a->fullname, $b->fullname);
            });
        } else {
            // Per-student QM-defined order. Courses without an override record sort last.
            $sortorders = $DB->get_records_menu(
                'block_workload_user_courses',
                ['userid' => $userid],
                '',
                'courseid, sortorder'
            );
            uasort($merged, function ($a, $b) use ($sortorders) {
                $sa = isset($sortorders[$a->id]) ? (int)$sortorders[$a->id] : 99999;
                $sb = isset($sortorders[$b->id]) ? (int)$sortorders[$b->id] : 99999;
                if ($sa !== $sb) {
                    return $sa - $sb;
                }
                return strcasecmp($a->fullname, $b->fullname);
            });
        }

        return $merged;
    }

    /**
     * Return a student's full course picture for the management UI:
     *  - enrolled courses (from Moodle), with override status
     *  - manager-added courses (not enrolled, active=1 override)
     *
     * Each record: id, fullname, shortname, enrolled (bool), override_active (int|null)
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_courses_for_management(int $userid): array {
        global $DB;

        // All enrolled courses.
        $enrolled = $DB->get_records_sql(
            "SELECT DISTINCT co.id, co.fullname, co.shortname, co.startdate, co.enddate
               FROM {course} co
               JOIN {enrol} en ON en.courseid = co.id AND en.status = 0
               JOIN {user_enrolments} ue ON ue.enrolid = en.id AND ue.userid = :uid AND ue.status = 0
              WHERE co.id <> :site AND co.visible = 1
           ORDER BY co.fullname ASC",
            ['uid' => $userid, 'site' => SITEID]
        );

        // All overrides for this user.
        $overrides = $DB->get_records('block_workload_user_courses', ['userid' => $userid], '', 'courseid, active, sortorder');

        // Courses hidden globally (used to flag them in the management view).
        $globallyhidden = self::get_global_hidden_course_ids();

        // Force-added (active=1) and NOT enrolled.
        $added = $DB->get_records_sql(
            "SELECT co.id, co.fullname, co.shortname, co.startdate, co.enddate
               FROM {course} co
               JOIN {block_workload_user_courses} ov ON ov.courseid = co.id
                    AND ov.userid = :uid AND ov.active = 1
              WHERE co.id <> :site AND co.visible = 1
                AND NOT EXISTS (
                    SELECT 1 FROM {enrol} en
                    JOIN {user_enrolments} ue ON ue.enrolid = en.id
                    WHERE en.courseid = co.id AND en.status = 0
                      AND ue.userid = :uid2 AND ue.status = 0
                )
           ORDER BY co.fullname ASC",
            ['uid' => $userid, 'uid2' => $userid, 'site' => SITEID]
        );

        $result = [];

        foreach ($enrolled as $course) {
            $ov = $overrides[$course->id] ?? null;
            $result[$course->id] = (object)[
                'id'              => $course->id,
                'fullname'        => $course->fullname,
                'shortname'       => $course->shortname,
                'startdate'       => $course->startdate,
                'enddate'         => $course->enddate,
                'enrolled'        => true,
                'override_active' => ($ov !== null && $ov->active !== null) ? (int)$ov->active : null,
                'sortorder'       => $ov !== null ? (int)$ov->sortorder : 99999,
                'globally_hidden' => isset($globallyhidden[$course->id]),
            ];
        }

        foreach ($added as $course) {
            $ov = $overrides[$course->id] ?? null;
            $result[$course->id] = (object)[
                'id'              => $course->id,
                'fullname'        => $course->fullname,
                'shortname'       => $course->shortname,
                'startdate'       => $course->startdate,
                'enddate'         => $course->enddate,
                'enrolled'        => false,
                'override_active' => 1,
                'sortorder'       => $ov !== null ? (int)$ov->sortorder : 99999,
                'globally_hidden' => isset($globallyhidden[$course->id]),
            ];
        }

        uasort($result, function ($a, $b) {
            if ($a->sortorder !== $b->sortorder) {
                return $a->sortorder - $b->sortorder;
            }
            return strcasecmp($a->fullname, $b->fullname);
        });

        return $result;
    }

    /**
     * Upsert a per-student course override.
     * active=1 — force-include (shown even if not enrolled)
     * active=0 — force-exclude (hidden even if enrolled)
     *
     * @param int $userid
     * @param int $courseid
     * @param int $active 1 to include, 0 to exclude.
     */
    public static function save_user_course_override(int $userid, int $courseid, int $active): void {
        global $DB;

        $existing = $DB->get_record('block_workload_user_courses', ['userid' => $userid, 'courseid' => $courseid]);
        $now = time();

        if ($existing) {
            $existing->active       = $active;
            $existing->timemodified = $now;
            $DB->update_record('block_workload_user_courses', $existing);
        } else {
            $nextsort = 1 + (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), -1) FROM {block_workload_user_courses} WHERE userid = :uid',
                ['uid' => $userid]
            );
            $DB->insert_record('block_workload_user_courses', (object)[
                'userid'       => $userid,
                'courseid'     => $courseid,
                'active'       => $active,
                'sortorder'    => $nextsort,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Remove a per-student course override, restoring default enrollment behaviour.
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function remove_user_course_override(int $userid, int $courseid): void {
        global $DB;
        $DB->delete_records('block_workload_user_courses', ['userid' => $userid, 'courseid' => $courseid]);
    }

    /**
     * Return the ids of all globally-hidden courses, keyed by courseid.
     *
     * A globally-hidden course is excluded from every student's workload
     * dashboard in enrollment mode by default; a per-student force-include
     * (active=1) override still wins for that individual student.
     *
     * @return array courseid => courseid
     */
    public static function get_global_hidden_course_ids(): array {
        global $DB;
        return $DB->get_records_menu('block_workload_global_courses', null, '', 'courseid, courseid AS cid');
    }

    /**
     * Return the visible course records currently on the global hidden list,
     * ordered by full name, for the management UI.
     *
     * @return array courseid => record (id, fullname, shortname, startdate, enddate)
     */
    public static function get_global_hidden_courses(): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT co.id, co.fullname, co.shortname, co.startdate, co.enddate
               FROM {block_workload_global_courses} gh
               JOIN {course} co ON co.id = gh.courseid
              WHERE co.id <> :site
           ORDER BY co.fullname ASC",
            ['site' => SITEID]
        );
    }

    /**
     * Whether a course is on the global hidden list.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_course_globally_hidden(int $courseid): bool {
        global $DB;
        return $DB->record_exists('block_workload_global_courses', ['courseid' => $courseid]);
    }

    /**
     * Add a course to the global hidden list (idempotent).
     *
     * @param int $courseid
     * @return bool true if a new row was inserted, false if it was already hidden.
     */
    public static function add_global_hidden_course(int $courseid): bool {
        global $DB, $USER;

        if ($DB->record_exists('block_workload_global_courses', ['courseid' => $courseid])) {
            return false;
        }
        $DB->insert_record('block_workload_global_courses', (object)[
            'courseid'    => $courseid,
            'createdby'   => (int)$USER->id,
            'timecreated' => time(),
        ]);
        return true;
    }

    /**
     * Remove a course from the global hidden list.
     *
     * @param int $courseid
     */
    public static function remove_global_hidden_course(int $courseid): void {
        global $DB;
        $DB->delete_records('block_workload_global_courses', ['courseid' => $courseid]);
    }

    /**
     * Move a course up or down in the per-student sort order (enrollment mode).
     *
     * On first call for a student, lazily initialises sortorder records for every
     * visible course so that the full list can be reordered.
     *
     * @param int  $userid
     * @param int  $courseid  The course to move.
     * @param bool $moveup    true = move up, false = move down.
     */
    public static function swap_user_course_sortorder(int $userid, int $courseid, bool $moveup): void {
        global $DB;
        $now = time();

        // Full management list, currently sorted by (sortorder, fullname).
        $all = array_values(self::get_user_courses_for_management($userid));

        // Preload all override rows in one query; (userid, courseid) is unique,
        // so keying by courseid is safe.
        $overrides = $DB->get_records(
            'block_workload_user_courses',
            ['userid' => $userid],
            '',
            'courseid, id, active, sortorder'
        );

        $transaction = $DB->start_delegated_transaction();

        // Lazy-initialise: ensure every visible course has an override row with
        // a valid, sequential sortorder so the whole list can be reordered.
        // Positions in $all are the normalised order, so no reload is needed.
        foreach ($all as $i => $course) {
            $existing = $overrides[$course->id] ?? null;
            if (!$existing) {
                // Enrolled course with no override yet — insert a sort-order
                // tracking record. active=NULL means "ordering only, no
                // include/exclude opinion", so it is never mistaken for a
                // deliberate force-include (which the global-hide override honours).
                $newid = $DB->insert_record('block_workload_user_courses', (object)[
                    'userid'        => $userid,
                    'courseid'      => $course->id,
                    'active'        => null,
                    'sortorder'     => $i,
                    'timecreated'   => $now,
                    'timemodified'  => $now,
                ]);
                $overrides[$course->id] = (object)[
                    'courseid'  => $course->id,
                    'id'        => $newid,
                    'active'    => null,
                    'sortorder' => $i,
                ];
            } else if ((int)$existing->sortorder !== $i) {
                $DB->set_field('block_workload_user_courses', 'sortorder', $i, ['id' => $existing->id]);
            }
        }

        $count = count($all);
        foreach ($all as $i => $course) {
            if ((int)$course->id !== $courseid) {
                continue;
            }
            $swapidx = $moveup ? $i - 1 : $i + 1;
            if ($swapidx >= 0 && $swapidx < $count) {
                $reca = $overrides[$all[$i]->id];
                $recb = $overrides[$all[$swapidx]->id];
                $DB->set_field('block_workload_user_courses', 'sortorder', $swapidx, ['id' => $reca->id]);
                $DB->set_field('block_workload_user_courses', 'sortorder', $i, ['id' => $recb->id]);
            }
            break;
        }

        $transaction->allow_commit();
    }

    /**
     * Return all users enrolled in at least one visible non-site course, for the
     * enrollment-mode management list.
     *
     * $limit = 0 means no limit (show all).
     *
     * @param int    $limit       0 = no limit.
     * @param int    $offset
     * @param string $firstletter A-Z filter on firstname, or empty string.
     * @param string $lastletter  A-Z filter on lastname, or empty string.
     * @param string $orderby     SQL ORDER BY clause, or empty for default.
     * @return array
     */
    public static function get_enrollment_mode_students(
        int $limit = 0,
        int $offset = 0,
        string $firstletter = '',
        string $lastletter = '',
        string $orderby = ''
    ): array {
        global $DB;
        [$where, $params] = self::enrollment_students_where($firstletter, $lastletter);
        $params['site2'] = SITEID;

        $orderbyclause = $orderby ?: 'u.lastname ASC, u.firstname ASC';

        return $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.institution,
                    COUNT(DISTINCT en.courseid) AS enrolledcount,
                    (SELECT COUNT(*)
                       FROM {block_workload_user_courses} ov
                      WHERE ov.userid = u.id AND ov.active = 0) AS excludedcount,
                    (SELECT COUNT(*)
                       FROM {block_workload_user_courses} ov2
                       JOIN {course} co2 ON co2.id = ov2.courseid
                            AND co2.id <> :site2 AND co2.visible = 1
                      WHERE ov2.userid = u.id AND ov2.active = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM {enrol} en2
                            JOIN {user_enrolments} ue2 ON ue2.enrolid = en2.id
                            WHERE en2.courseid = ov2.courseid AND en2.status = 0
                              AND ue2.userid = u.id AND ue2.status = 0
                        )) AS addedcount,
                    COALESCE(
                        (SELECT us.active FROM {block_workload_user_settings} us WHERE us.userid = u.id),
                        1
                    ) AS widgetactive
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol} en ON en.id = ue.enrolid AND en.status = 0
               JOIN {course} co ON co.id = en.courseid AND co.id <> :site AND co.visible = 1
              WHERE $where
           GROUP BY u.id, u.firstname, u.lastname, u.email, u.department, u.institution
           ORDER BY $orderbyclause",
            $params,
            $offset,
            $limit
        );
    }

    /**
     * Total count of enrollment-mode students (for pagination).
     *
     * @param string $firstletter A-Z filter on firstname, or empty string.
     * @param string $lastletter  A-Z filter on lastname, or empty string.
     * @return int
     */
    public static function get_enrollment_mode_students_count(
        string $firstletter = '',
        string $lastletter = ''
    ): int {
        global $DB;
        [$where, $params] = self::enrollment_students_where($firstletter, $lastletter);

        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id)
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol} en ON en.id = ue.enrolid AND en.status = 0
               JOIN {course} co ON co.id = en.courseid AND co.id <> :site AND co.visible = 1
              WHERE $where",
            $params
        );
    }

    /**
     * Build WHERE clause and params for enrollment student list.
     *
     * @param string $firstletter
     * @param string $lastletter
     * @return array
     */
    private static function enrollment_students_where(string $firstletter, string $lastletter): array {
        global $DB;
        $where  = 'u.deleted = 0 AND u.suspended = 0';
        $params = ['site' => SITEID];

        if ($firstletter !== '') {
            $where .= ' AND ' . $DB->sql_like('u.firstname', ':fl', false);
            $params['fl'] = $DB->sql_like_escape($firstletter) . '%';
        }
        if ($lastletter !== '') {
            $where .= ' AND ' . $DB->sql_like('u.lastname', ':ll', false);
            $params['ll'] = $DB->sql_like_escape($lastletter) . '%';
        }

        return [$where, $params];
    }


    // Category helpers for course browser.


    /**
     * Return all course categories as a flat array for select menus,
     * ordered and indented to reflect the full hierarchy (tree view).
     *
     * Uses a depth-first walk so children always appear directly under their
     * parent, and uses UTF-8 box-drawing characters + non-breaking spaces so
     * the indentation is preserved inside HTML <select> elements.
     */
    public static function get_category_options(): array {
        global $DB;
        $cats = $DB->get_records(
            'course_categories',
            ['visible' => 1],
            'sortorder ASC',
            'id, name, parent, depth'
        );

        // Build parent → ordered-children map (sortorder already applied above).
        $children = [];
        foreach ($cats as $cat) {
            $children[(int)$cat->parent][] = $cat;
        }

        // Course counts per category (direct courses only, excluding site course).
        $countrows = $DB->get_records_sql(
            "SELECT category, COUNT(id) AS cnt FROM {course} WHERE id <> :site GROUP BY category",
            ['site' => SITEID]
        );
        $coursecounts = [];
        foreach ($countrows as $row) {
            $coursecounts[(int)$row->category] = (int)$row->cnt;
        }

        $options = [];
        $nbsp   = "\xc2\xa0";                              // UTF-8 non-breaking space.
        $tee    = "\xe2\x94\x9c\xe2\x94\x80\xe2\x94\x80"; // Tree char: 3 chars wide.
        $corner = "\xe2\x94\x94\xe2\x94\x80\xe2\x94\x80"; // Tree corner char: 3 chars wide.
        $pipe   = "\xe2\x94\x82" . $nbsp . $nbsp;          // Tree pipe char: padded to 3 chars.
        $blank  = $nbsp . $nbsp . $nbsp;                   // Three non-breaking spaces.

        // Recursive depth-first walk.
        $walk = function (
            int $parentid,
            ?string $prefix
        ) use (
            &$walk,
            &$options,
            &$children,
            &$coursecounts,
            $nbsp,
            $tee,
            $corner,
            $pipe,
            $blank
        ): void {
            if (empty($children[$parentid])) {
                return;
            }
            $siblings = $children[$parentid];
            $lastidx  = count($siblings) - 1;
            foreach ($siblings as $idx => $cat) {
                $islast = ($idx === $lastidx);
                $count  = $coursecounts[(int)$cat->id] ?? 0;
                $suffix = $count > 0 ? $nbsp . '(' . $count . ')' : '';

                if ($prefix === null) {
                    // True top-level: no symbol, no indent.
                    $options[(int)$cat->id] = $cat->name . $suffix;
                    // Pass '' (not null) so their children know they are NOT root.
                    $walk((int)$cat->id, '');
                } else {
                    $symbol = $islast ? $corner : $tee;
                    $options[(int)$cat->id] = $prefix . $symbol . $nbsp . $cat->name . $suffix;
                    // Under a non-last node continue with a pipe; under a last node use blanks.
                    $walk((int)$cat->id, $prefix . ($islast ? $blank : $pipe) . $nbsp);
                }
            }
        };

        $walk(0, null);

        return $options;
    }

    /**
     * Return courses in a category (non-site courses).
     *
     * @param int  $categoryid
     * @param bool $includesubcats Also include courses from sub-categories.
     * @return array
     */
    public static function get_courses_in_category(int $categoryid, bool $includesubcats = false): array {
        global $DB;
        if ($includesubcats) {
            $catids = self::get_all_subcategory_ids($categoryid);
            [$sql, $params] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cat');
            $params['site'] = SITEID;
            return $DB->get_records_select(
                'course',
                "category $sql AND id <> :site",
                $params,
                'fullname ASC',
                'id, fullname, shortname, startdate, enddate'
            );
        }
        return $DB->get_records_select(
            'course',
            'category = :cat AND id <> :site',
            ['cat' => $categoryid, 'site' => SITEID],
            'fullname ASC',
            'id, fullname, shortname, startdate, enddate'
        );
    }


    // Member filter helpers.


    /**
     * Search Moodle users to add to a workload cohort.
     *
     * Applies any combination of name/email search, department, institution,
     * and course-category filters.  Returns at most 200 rows.
     *
     * @param string $search  Partial name or email match.
     * @param string $dept    Exact department match, or '' to skip.
     * @param string $inst    Exact institution match, or '' to skip.
     * @param int    $catid   Course-category filter (0 = skip).
     * @return array
     */
    public static function search_users_for_member_add(
        string $search,
        string $dept,
        string $inst,
        int $catid
    ): array {
        global $DB;

        $params = ['deleted' => 0, 'notsite' => SITEID];
        $where  = ['u.deleted = :deleted', 'u.id <> :notsite'];

        if ($search !== '') {
            $where[]         = '(' . $DB->sql_like(
                $DB->sql_concat('u.firstname', "' '", 'u.lastname'),
                ':name',
                false
            ) . ' OR ' . $DB->sql_like('u.email', ':email', false) . ')';
            $params['name']  = '%' . $DB->sql_like_escape($search) . '%';
            $params['email'] = '%' . $DB->sql_like_escape($search) . '%';
        }
        if ($dept !== '') {
            $where[]        = 'u.department = :dept';
            $params['dept'] = $dept;
        }
        if ($inst !== '') {
            $where[]        = 'u.institution = :inst';
            $params['inst'] = $inst;
        }
        if ($catid > 0) {
            $catids = self::get_all_subcategory_ids($catid);
            [$catsql, $catparams] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'ccat');
            $where[]  = "EXISTS (
                SELECT 1 FROM {user_enrolments} ue
                  JOIN {enrol} en ON en.id = ue.enrolid
                  JOIN {course} co ON co.id = en.courseid
                 WHERE ue.userid = u.id AND co.category $catsql
            )";
            $params = array_merge($params, $catparams);
        }

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.institution,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {user} u
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params, 0, 200);
    }

    /**
     * Return members of a Moodle system cohort for bulk-import into a workload cohort.
     *
     * @param int $moodlecohortid
     * @return array
     */
    public static function get_moodle_cohort_members(int $moodlecohortid): array {
        global $DB;
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.institution,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {user} u
                  JOIN {cohort_members} cm ON cm.userid = u.id
                 WHERE cm.cohortid = :cohortid
                   AND u.deleted = 0
                   AND u.id <> :siteid
              ORDER BY u.lastname ASC, u.firstname ASC";
        return $DB->get_records_sql($sql, ['cohortid' => $moodlecohortid, 'siteid' => SITEID]);
    }


    /**
     * Return the given category ID plus all descendant category IDs (BFS).
     * Used so filtering by a faculty also matches all sub-semester categories.
     *
     * @param int $categoryid
     * @return array
     */
    public static function get_all_subcategory_ids(int $categoryid): array {
        global $DB;
        $ids   = [$categoryid];
        $queue = [$categoryid];
        while (!empty($queue)) {
            [$placeholders, $params] = $DB->get_in_or_equal($queue, SQL_PARAMS_NAMED, 'cat');
            $children = $DB->get_fieldset_select('course_categories', 'id', "parent $placeholders", $params);
            $queue    = array_diff($children, $ids);
            $ids      = array_merge($ids, $children);
        }
        return $ids;
    }

    /**
     * Return distinct non-empty department values from the workload cohorts table, sorted.
     * Used to populate the autocomplete datalist on the cohort add/edit form.
     */
    public static function get_distinct_cohort_departments(): array {
        global $DB;
        return $DB->get_fieldset_sql(
            "SELECT DISTINCT department FROM {block_workload_cohorts}
              WHERE department <> '' AND department IS NOT NULL
           ORDER BY department ASC"
        );
    }

    /**
     * Return distinct non-empty department values from the user table, sorted.
     */
    public static function get_distinct_departments(): array {
        global $DB;
        $rows = $DB->get_fieldset_sql(
            "SELECT DISTINCT department FROM {user}
              WHERE deleted = 0 AND department <> '' AND department IS NOT NULL
           ORDER BY department ASC"
        );
        return $rows;
    }

    /**
     * Return distinct non-empty institution values from the user table, sorted.
     */
    public static function get_distinct_institutions(): array {
        global $DB;
        $rows = $DB->get_fieldset_sql(
            "SELECT DISTINCT institution FROM {user}
              WHERE deleted = 0 AND institution <> '' AND institution IS NOT NULL
           ORDER BY institution ASC"
        );
        return $rows;
    }


    // Aggregate statistics (manager overview).


    /**
     * Weekly hour totals across a cohort (or all cohorts when cohortid = 0).
     * Uses a single GROUP BY query — never loads individual entry rows.
     * Returns array of stdClass {year, weeknumber, totalhours}, sorted asc.
     *
     * @param int      $cohortid 0 = all cohorts.
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return array
     */
    public static function get_cohort_weekly_totals(
        int $cohortid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): array {
        global $DB;
        $where  = [];
        $params = [];

        if ($cohortid > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM {block_workload_members} wm'
                . ' WHERE wm.userid = e.userid AND wm.cohortid = :cohortid)';
            $params['cohortid'] = $cohortid;
        }

        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto   = 53;
        }

        if ($weekfrom && $yearfrom) {
            $where[]        = '(e.year * 100 + e.weeknumber) >= :wf';
            $params['wf']   = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto && $yearto) {
            $where[]        = '(e.year * 100 + e.weeknumber) <= :wt';
            $params['wt']   = $yearto * 100 + $weekto;
        }

        $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $DB->get_records_sql(
            "SELECT MIN(e.id) AS id, e.year, e.weeknumber,
                    SUM(e.hours)           AS totalhours,
                    COUNT(DISTINCT e.userid) AS usercount
               FROM {block_workload_entries} e
             $wheresql
             GROUP BY e.year, e.weeknumber
             ORDER BY e.year ASC, e.weeknumber ASC",
            $params
        );
    }

    /**
     * Top N users by total hours in a cohort (or all cohorts when cohortid = 0).
     *
     * $limit = 0 means no limit (all matching users).
     * Returns array keyed by userid: {userid, firstname, lastname, email,
     * totalhours, weeksactive}.
     *
     * @param int      $cohortid    0 = all cohorts.
     * @param int      $limit       0 = no limit.
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @param string   $firstletter A-Z filter on firstname, or empty string.
     * @param string   $lastletter  A-Z filter on lastname, or empty string.
     * @param int      $offset      Pagination offset.
     * @return array
     */
    public static function get_cohort_top_users(
        int $cohortid,
        int $limit = 10,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null,
        string $firstletter = '',
        string $lastletter = '',
        int $offset = 0
    ): array {
        global $DB;
        $where  = [];
        $params = [];

        if ($cohortid > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM {block_workload_members} wm'
                . ' WHERE wm.userid = e.userid AND wm.cohortid = :cohortid)';
            $params['cohortid'] = $cohortid;
        }

        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto   = 53;
        }

        if ($weekfrom && $yearfrom) {
            $where[]        = '(e.year * 100 + e.weeknumber) >= :wf';
            $params['wf']   = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto && $yearto) {
            $where[]        = '(e.year * 100 + e.weeknumber) <= :wt';
            $params['wt']   = $yearto * 100 + $weekto;
        }

        if ($firstletter !== '') {
            $where[]               = $DB->sql_like('u.firstname', ':fl', false);
            $params['fl']          = $DB->sql_like_escape($firstletter) . '%';
        }
        if ($lastletter !== '') {
            $where[]               = $DB->sql_like('u.lastname', ':ll', false);
            $params['ll']          = $DB->sql_like_escape($lastletter) . '%';
        }

        $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $DB->get_records_sql(
            "SELECT e.userid,
                    u.firstname, u.lastname, u.email,
                    u.department, u.institution,
                    SUM(e.hours)  AS totalhours,
                    COUNT(DISTINCT e.year * 100 + e.weeknumber) AS weeksactive
               FROM {block_workload_entries} e
               JOIN {user} u ON u.id = e.userid
             $wheresql
             GROUP BY e.userid, u.firstname, u.lastname, u.email, u.department, u.institution
             ORDER BY totalhours DESC",
            $params,
            $offset,
            $limit
        );
    }

    /**
     * Count of users matching the filters — used for pagination of the table.
     * Accepts the same letter filters as get_cohort_top_users().
     *
     * @param int      $cohortid    0 = all cohorts.
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @param string   $firstletter A-Z filter on firstname, or empty string.
     * @param string   $lastletter  A-Z filter on lastname, or empty string.
     * @return int
     */
    public static function get_cohort_top_users_count(
        int $cohortid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null,
        string $firstletter = '',
        string $lastletter = ''
    ): int {
        global $DB;
        $where  = [];
        $params = [];

        if ($cohortid > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM {block_workload_members} wm'
                . ' WHERE wm.userid = e.userid AND wm.cohortid = :cohortid)';
            $params['cohortid'] = $cohortid;
        }

        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto   = 53;
        }

        if ($weekfrom && $yearfrom) {
            $where[]        = '(e.year * 100 + e.weeknumber) >= :wf';
            $params['wf']   = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto && $yearto) {
            $where[]        = '(e.year * 100 + e.weeknumber) <= :wt';
            $params['wt']   = $yearto * 100 + $weekto;
        }

        if ($firstletter !== '') {
            $where[]      = $DB->sql_like('u.firstname', ':fl', false);
            $params['fl'] = $DB->sql_like_escape($firstletter) . '%';
        }
        if ($lastletter !== '') {
            $where[]      = $DB->sql_like('u.lastname', ':ll', false);
            $params['ll'] = $DB->sql_like_escape($lastletter) . '%';
        }

        $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT e.userid)
               FROM {block_workload_entries} e
               JOIN {user} u ON u.id = e.userid
             $wheresql",
            $params
        );
    }

    /**
     * Overview KPIs for a cohort (or all cohorts when cohortid = 0).
     * Single aggregate query: no per-row data returned.
     * Returns stdClass {usercount, coursecount, weekcount, totalhours}.
     *
     * @param int      $cohortid 0 = all cohorts.
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return \stdClass
     */
    public static function get_cohort_overview_kpis(
        int $cohortid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): \stdClass {
        global $DB;
        $where  = [];
        $params = [];

        if ($cohortid > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM {block_workload_members} wm'
                . ' WHERE wm.userid = e.userid AND wm.cohortid = :cohortid)';
            $params['cohortid'] = $cohortid;
        }

        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto   = 53;
        }

        if ($weekfrom && $yearfrom) {
            $where[]        = '(e.year * 100 + e.weeknumber) >= :wf';
            $params['wf']   = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto && $yearto) {
            $where[]        = '(e.year * 100 + e.weeknumber) <= :wt';
            $params['wt']   = $yearto * 100 + $weekto;
        }

        $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $row = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT e.userid)                          AS usercount,
                    COUNT(DISTINCT e.courseid)                        AS coursecount,
                    COUNT(DISTINCT e.year * 100 + e.weeknumber)       AS weekcount,
                    COALESCE(SUM(e.hours), 0)                         AS totalhours
               FROM {block_workload_entries} e
             $wheresql",
            $params
        );

        return $row ?: (object)[
            'usercount' => 0, 'coursecount' => 0,
            'weekcount' => 0, 'totalhours'  => 0,
        ];
    }

    /**
     * Detailed export: one row per user per course with Moodle role information.
     *
     * Returns array of stdClass with fields: userid, courseid, firstname, lastname,
     * email, department, institution, coursename, coursehours, roles (comma-separated).
     *
     * @param int      $cohortid 0 = all cohorts.
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return array
     */
    public static function get_cohort_detailed_export(
        int $cohortid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): array {
        global $DB;

        $where  = [];
        $params = [];

        if ($cohortid > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM {block_workload_members} wm'
                . ' WHERE wm.userid = e.userid AND wm.cohortid = :cohortid)';
            $params['cohortid'] = $cohortid;
        }

        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto   = 53;
        }

        if ($weekfrom && $yearfrom) {
            $where[]        = '(e.year * 100 + e.weeknumber) >= :wf';
            $params['wf']   = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto && $yearto) {
            $where[]        = '(e.year * 100 + e.weeknumber) <= :wt';
            $params['wt']   = $yearto * 100 + $weekto;
        }

        $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = $DB->get_records_sql(
            "SELECT MIN(e.id) AS id, e.userid, e.courseid,
                    u.firstname, u.lastname, u.email,
                    u.department, u.institution,
                    c.fullname AS coursename,
                    SUM(e.hours) AS coursehours
               FROM {block_workload_entries} e
               JOIN {user} u ON u.id = e.userid
               JOIN {course} c ON c.id = e.courseid
             $wheresql
             GROUP BY e.userid, e.courseid,
                      u.firstname, u.lastname, u.email, u.department, u.institution,
                      c.fullname
             ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC",
            $params
        );

        if (empty($rows)) {
            return [];
        }

        $userids   = array_unique(array_map(fn($r) => (int)$r->userid, $rows));
        $courseids = array_unique(array_map(fn($r) => (int)$r->courseid, $rows));

        [$useridsql, $uidparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        [$courseidsql, $cidparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');

        $rolerows = $DB->get_records_sql(
            "SELECT ra.id, ra.userid, ctx.instanceid AS courseid,
                    r.shortname AS roleshortname, r.name AS rolename
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid $useridsql
                AND ctx.instanceid $courseidsql",
            array_merge($uidparams, $cidparams)
        );

        $rolemap = [];
        foreach ($rolerows as $rr) {
            $key           = $rr->userid . '_' . $rr->courseid;
            $display       = ($rr->rolename !== '' && $rr->rolename !== null) ? $rr->rolename : $rr->roleshortname;
            $rolemap[$key][] = $display;
        }

        foreach ($rows as $row) {
            $key        = $row->userid . '_' . $row->courseid;
            $row->roles = isset($rolemap[$key]) ? implode(', ', array_unique($rolemap[$key])) : '';
        }

        return $rows;
    }

    // Course-scoped statistics (teacher view, enrollment mode).


    /**
     * Append the ISO week/year range conditions shared by the course-scoped
     * statistics queries to $where/$params.
     *
     * A year given without a week snaps to the start/end of that year,
     * matching the behaviour of the cohort statistics queries.
     *
     * @param array    $where    SQL conditions, appended in place.
     * @param array    $params   Named parameters, appended in place.
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     */
    private static function apply_weekrange(
        array &$where,
        array &$params,
        ?int $weekfrom,
        ?int $yearfrom,
        ?int $weekto,
        ?int $yearto
    ): void {
        if ($yearfrom !== null && !$weekfrom) {
            $weekfrom = 1;
        }
        if ($yearto !== null && !$weekto) {
            $weekto = 53;
        }

        if ($weekfrom && $yearfrom) {
            $where[]      = '(e.year * 100 + e.weeknumber) >= :wf';
            $params['wf'] = $yearfrom * 100 + $weekfrom;
        }
        if ($weekto && $yearto) {
            $where[]      = '(e.year * 100 + e.weeknumber) <= :wt';
            $params['wt'] = $yearto * 100 + $weekto;
        }
    }

    /**
     * Weekly totals for a single course.
     *
     * Course-scoped counterpart of get_cohort_weekly_totals(). Includes every
     * entry recorded for the course regardless of the student's current
     * enrolment status: dropping since-unenrolled students would silently
     * deflate historical weeks, and the totals must match what a Quality
     * Manager sees for the same course.
     *
     * @param int      $courseid
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return array of stdClass {year, weeknumber, totalhours, usercount}, sorted asc.
     */
    public static function get_course_weekly_totals(
        int $courseid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): array {
        global $DB;
        $where  = ['e.courseid = :courseid'];
        $params = ['courseid' => $courseid];
        self::apply_weekrange($where, $params, $weekfrom, $yearfrom, $weekto, $yearto);

        return $DB->get_records_sql(
            'SELECT MIN(e.id) AS id, e.year, e.weeknumber,
                    SUM(e.hours)             AS totalhours,
                    COUNT(DISTINCT e.userid) AS usercount
               FROM {block_workload_entries} e
              WHERE ' . implode(' AND ', $where) . '
             GROUP BY e.year, e.weeknumber
             ORDER BY e.year ASC, e.weeknumber ASC',
            $params
        );
    }

    /**
     * Top N users by total hours in a single course.
     *
     * $limit = 0 means no limit (all matching users).
     * Returns array keyed by userid: {userid, firstname, lastname, email,
     * department, institution, totalhours, weeksactive}.
     *
     * @param int      $courseid
     * @param int      $limit       0 = no limit.
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @param string   $firstletter A-Z filter on firstname, or empty string.
     * @param string   $lastletter  A-Z filter on lastname, or empty string.
     * @param int      $offset      Pagination offset.
     * @return array
     */
    public static function get_course_top_users(
        int $courseid,
        int $limit = 10,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null,
        string $firstletter = '',
        string $lastletter = '',
        int $offset = 0
    ): array {
        global $DB;
        $where  = ['e.courseid = :courseid'];
        $params = ['courseid' => $courseid];
        self::apply_weekrange($where, $params, $weekfrom, $yearfrom, $weekto, $yearto);

        if ($firstletter !== '') {
            $where[]      = $DB->sql_like('u.firstname', ':fl', false);
            $params['fl'] = $DB->sql_like_escape($firstletter) . '%';
        }
        if ($lastletter !== '') {
            $where[]      = $DB->sql_like('u.lastname', ':ll', false);
            $params['ll'] = $DB->sql_like_escape($lastletter) . '%';
        }

        return $DB->get_records_sql(
            'SELECT e.userid,
                    u.firstname, u.lastname, u.email,
                    u.department, u.institution,
                    SUM(e.hours)  AS totalhours,
                    COUNT(DISTINCT e.year * 100 + e.weeknumber) AS weeksactive
               FROM {block_workload_entries} e
               JOIN {user} u ON u.id = e.userid
              WHERE ' . implode(' AND ', $where) . '
             GROUP BY e.userid, u.firstname, u.lastname, u.email, u.department, u.institution
             ORDER BY totalhours DESC',
            $params,
            $offset,
            $limit
        );
    }

    /**
     * Count of users matching the course filters — pagination counterpart
     * of get_course_top_users().
     *
     * @param int      $courseid
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @param string   $firstletter A-Z filter on firstname, or empty string.
     * @param string   $lastletter  A-Z filter on lastname, or empty string.
     * @return int
     */
    public static function get_course_top_users_count(
        int $courseid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null,
        string $firstletter = '',
        string $lastletter = ''
    ): int {
        global $DB;
        $where  = ['e.courseid = :courseid'];
        $params = ['courseid' => $courseid];
        self::apply_weekrange($where, $params, $weekfrom, $yearfrom, $weekto, $yearto);

        if ($firstletter !== '') {
            $where[]      = $DB->sql_like('u.firstname', ':fl', false);
            $params['fl'] = $DB->sql_like_escape($firstletter) . '%';
        }
        if ($lastletter !== '') {
            $where[]      = $DB->sql_like('u.lastname', ':ll', false);
            $params['ll'] = $DB->sql_like_escape($lastletter) . '%';
        }

        return (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT e.userid)
               FROM {block_workload_entries} e
               JOIN {user} u ON u.id = e.userid
              WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    /**
     * Overview KPIs for a single course.
     * Single aggregate query: no per-row data returned.
     *
     * @param int      $courseid
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return \stdClass {usercount, weekcount, totalhours}.
     */
    public static function get_course_overview_kpis(
        int $courseid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): \stdClass {
        global $DB;
        $where  = ['e.courseid = :courseid'];
        $params = ['courseid' => $courseid];
        self::apply_weekrange($where, $params, $weekfrom, $yearfrom, $weekto, $yearto);

        $row = $DB->get_record_sql(
            'SELECT COUNT(DISTINCT e.userid)                    AS usercount,
                    COUNT(DISTINCT e.year * 100 + e.weeknumber) AS weekcount,
                    COALESCE(SUM(e.hours), 0)                   AS totalhours
               FROM {block_workload_entries} e
              WHERE ' . implode(' AND ', $where),
            $params
        );

        return $row ?: (object)[
            'usercount' => 0, 'weekcount' => 0, 'totalhours' => 0,
        ];
    }

    /**
     * Userids of the course "staff": everyone who may themselves view the
     * course statistics (block/workload:viewcoursestats in this course).
     *
     * Used by the teacher view to decide whose rows are NOT pseudonymized:
     * anonymization protects students from staff, not staff from each other,
     * and identified staff rows let teachers spot (and clean up) accidentally
     * recorded hours. Which roles count as staff is thereby controlled by
     * admins through the same capability that grants access to the page.
     *
     * @param \context_course $context
     * @return int[]
     */
    public static function get_course_staff_userids(\context_course $context): array {
        $users = get_users_by_capability($context, 'block/workload:viewcoursestats', 'u.id');
        return array_map('intval', array_keys($users));
    }

    /**
     * How many of the given users have entries in a course within the range.
     *
     * Used to subtract staff recorders from the anonymization minimum-group
     * check: staff entries must not inflate the count that decides whether
     * the remaining (pseudonymized) students are identifiable.
     *
     * @param int      $courseid
     * @param int[]    $userids  Empty array returns 0.
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return int
     */
    public static function count_course_users_with_entries(
        int $courseid,
        array $userids,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): int {
        global $DB;
        if (empty($userids)) {
            return 0;
        }
        $where  = ['e.courseid = :courseid'];
        $params = ['courseid' => $courseid];
        self::apply_weekrange($where, $params, $weekfrom, $yearfrom, $weekto, $yearto);

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'sid');
        $where[] = "e.userid $insql";

        return (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT e.userid)
               FROM {block_workload_entries} e
              WHERE ' . implode(' AND ', $where),
            array_merge($params, $inparams)
        );
    }

    /**
     * Detailed export for a single course: one row per user per week.
     *
     * Unlike the cohort detailed export (one row per user per course), the
     * per-course breakdown collapses into the quick export inside a single
     * course, so the useful extra detail here is the weekly one. No role
     * column: everyone with entries in the course recorded them as a student.
     *
     * @param int      $courseid
     * @param int|null $weekfrom
     * @param int|null $yearfrom
     * @param int|null $weekto
     * @param int|null $yearto
     * @return array of stdClass {userid, firstname, lastname, email,
     *               department, institution, year, weeknumber, hours}.
     */
    public static function get_course_detailed_export(
        int $courseid,
        ?int $weekfrom = null,
        ?int $yearfrom = null,
        ?int $weekto = null,
        ?int $yearto = null
    ): array {
        global $DB;
        $where  = ['e.courseid = :courseid'];
        $params = ['courseid' => $courseid];
        self::apply_weekrange($where, $params, $weekfrom, $yearfrom, $weekto, $yearto);

        return $DB->get_records_sql(
            'SELECT e.id, e.userid, e.year, e.weeknumber, e.hours,
                    u.firstname, u.lastname, u.email,
                    u.department, u.institution
               FROM {block_workload_entries} e
               JOIN {user} u ON u.id = e.userid
              WHERE ' . implode(' AND ', $where) . '
             ORDER BY u.lastname ASC, u.firstname ASC, e.year ASC, e.weeknumber ASC',
            $params
        );
    }

    // Anonymization.


    /**
     * Should statistics be anonymized for the given viewer?
     *
     * True when the anonymizestats setting is enabled AND the viewer lacks
     * the block/workload:viewrealnames capability (site admins always pass
     * capability checks, so they are never anonymized).
     *
     * @param int|null $userid viewer to check, defaults to the current user.
     * @return bool
     */
    public static function is_anonymized(?int $userid = null): bool {
        if (!get_config('block_workload', 'anonymizestats')) {
            return false;
        }

        // No extra caching: has_capability() caches internally and is
        // invalidated correctly on role changes.
        return !has_capability(
            'block/workload:viewrealnames',
            \context_system::instance(),
            $userid
        );
    }

    /**
     * Minimum number of distinct students with entries before the anonymized
     * teacher view shows per-student data.
     *
     * A teacher knows their roster, so in a very small group pseudonyms are
     * trivially reversible; below this threshold the per-student table, chart
     * and exports are suppressed (aggregates stay visible). Deliberately a
     * constant rather than a setting — a knob would invite lowering it until
     * it protects nobody. The QM statistics page has no threshold: it
     * operates at cohort/site scale where this rarely bites.
     */
    public const ANON_MIN_GROUP = 3;

    /**
     * Should course statistics be anonymized for the given teacher/viewer?
     *
     * Counterpart of is_anonymized() for the teacher course view, controlled
     * by the separate anonymizeteacherstats setting (default ON). The
     * viewrealnames bypass is checked at course context so an admin can
     * de-anonymize selectively via a course or category role override;
     * system-level grants (managers, admins) still pass through context
     * aggregation. Pseudonyms use the same site salt as the QM view, so a
     * student keeps the same codename on both pages — QMs and teachers can
     * discuss "Brave Panda 3F7A" without de-anonymizing anyone.
     *
     * @param \context_course $context course being viewed.
     * @param int|null $userid viewer to check, defaults to the current user.
     * @return bool
     */
    public static function is_teacher_anonymized(\context_course $context, ?int $userid = null): bool {
        $raw = get_config('block_workload', 'anonymizeteacherstats');
        // Unset config (false) must mean enabled: anonymization is the
        // privacy default even if the upgrade step seeding the value has
        // not run yet.
        if ($raw !== false && !$raw) {
            return false;
        }

        return !has_capability('block/workload:viewrealnames', $context, $userid);
    }

    /**
     * Return the per-site secret used to derive pseudonym tokens.
     *
     * Normally created at install/upgrade time; generated lazily here as a
     * fallback so pseudonyms always work.
     *
     * @return string 32 hex chars.
     */
    public static function anonsalt(): string {
        $salt = get_config('block_workload', 'anonsalt');
        if (empty($salt)) {
            $salt = bin2hex(random_bytes(16));
            set_config('anonsalt', $salt, 'block_workload');
        }
        return $salt;
    }

    /**
     * Stable, non-reversible pseudonym token for a user.
     *
     * HMAC of the userid keyed with the per-site secret: stable across
     * sessions/pages/exports, but a viewer can neither derive the userid from
     * the token nor compute the token for a known userid. 10 hex chars
     * (40 bits) keeps labels readable; collision probability is ~n²/2^41
     * (about 0.005% for 10,000 students) and a collision would only make two
     * students share a label — no identity leak.
     *
     * @param int $userid
     * @return string 10 uppercase hex chars, e.g. "3F7A2C91D4".
     */
    public static function pseudonym_token(int $userid): string {
        // Memoized per salt so a config reset (e.g. between unit tests)
        // invalidates the cache naturally.
        static $cache = [];
        $salt = self::anonsalt();
        if (!isset($cache[$salt][$userid])) {
            $cache[$salt][$userid] = strtoupper(
                substr(hash_hmac('sha256', (string)$userid, $salt), 0, 10)
            );
        }
        return $cache[$salt][$userid];
    }

    /**
     * Adjectives for friendly pseudonyms. Deliberately English-only: the
     * names act as language-independent codenames (two managers with
     * different UI languages must see the same name), and adjective–noun
     * agreement does not survive translation anyway.
     */
    private const PSEUDONYM_ADJECTIVES = [
        'Amber', 'Azure', 'Brave', 'Bright', 'Calm', 'Cheerful', 'Clever', 'Cosmic',
        'Crimson', 'Curious', 'Daring', 'Eager', 'Emerald', 'Gentle', 'Golden', 'Happy',
        'Jolly', 'Kind', 'Lively', 'Lucky', 'Mellow', 'Nimble', 'Polite', 'Proud',
        'Quick', 'Quiet', 'Silent', 'Silver', 'Sunny', 'Swift', 'Violet', 'Wise',
    ];

    /** Animals for friendly pseudonyms; see PSEUDONYM_ADJECTIVES on language. */
    private const PSEUDONYM_ANIMALS = [
        'Alpaca', 'Badger', 'Beaver', 'Bison', 'Dolphin', 'Falcon', 'Fox', 'Gazelle',
        'Hedgehog', 'Heron', 'Ibex', 'Koala', 'Lynx', 'Marmot', 'Mole', 'Otter',
        'Owl', 'Panda', 'Parrot', 'Pelican', 'Penguin', 'Rabbit', 'Raccoon', 'Raven',
        'Salamander', 'Seal', 'Sparrow', 'Squirrel', 'Stork', 'Swan', 'Turtle', 'Zebra',
    ];

    /**
     * Display form of a user's pseudonym, e.g. "Brave Panda 3F7A".
     *
     * Adjective and animal are picked deterministically from the same HMAC
     * that produces the token, so the name is as stable as the token itself.
     * The 4-char code is the start of the token: it disambiguates word
     * collisions and lets a manager match the display name to drill-down
     * URLs and export filenames.
     *
     * @param int $userid
     * @return string
     */
    public static function pseudonym(int $userid): string {
        $hash = hash_hmac('sha256', (string)$userid, self::anonsalt());
        return get_string('pseudonym', 'block_workload', (object) [
            'adjective' => self::PSEUDONYM_ADJECTIVES[hexdec(substr($hash, 10, 4)) % count(self::PSEUDONYM_ADJECTIVES)],
            'animal'    => self::PSEUDONYM_ANIMALS[hexdec(substr($hash, 14, 4)) % count(self::PSEUDONYM_ANIMALS)],
            'code'      => substr(self::pseudonym_token($userid), 0, 4),
        ]);
    }

    /**
     * Resolve a pseudonym token back to a userid.
     *
     * The HMAC is not reversible, so this scans the bounded candidate set of
     * users the plugin tracks (cohort members plus anyone with workload
     * entries, covering enrollment mode) and matches tokens.
     *
     * @param string $token as produced by pseudonym_token().
     * @return int|null userid, or null if no tracked user matches.
     */
    public static function resolve_pseudonym(string $token): ?int {
        global $DB;

        $token = strtoupper($token);

        $userids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {block_workload_members}
              UNION
             SELECT DISTINCT userid FROM {block_workload_entries}"
        );
        foreach ($userids as $userid) {
            if (self::pseudonym_token((int)$userid) === $token) {
                return (int)$userid;
            }
        }
        return null;
    }
}
