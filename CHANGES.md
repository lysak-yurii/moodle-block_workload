# Changelog

## Changes in version 1.1.3 (Build: 2026071409)

- **New Feature**: In-course widget - students can log hours for a single course and track cumulative progress against the course's target hours directly in that course's block drawer; the widget hides itself in courses where the student has no active workload
- **New Feature**: Per-course target hours - quality managers set the expected workload per course, either inline on the *Course target hours* page or by CSV/Excel bulk upload (export a pre-filled course list, edit the `target_hours` column, and re-upload with a previewed apply step); a site-wide default covers courses without their own target
- **New Feature**: "Enable in-course widget" admin master switch (off by default) - ticking it places the block in every course automatically, with no per-course setup; unticking removes it again. When off, the plugin reverts to dashboard-only behaviour and course targets are not shown to students
- **New Feature**: Course search and paging on every course picker - search by course full or short name, with or without a category, and page the results (25 / 50 / 100 / Show all)
- **Updated**: Privacy provider exports and deletes the manager authorship stored on course targets
- **Fixed**: In enrollment mode, hour saving now honours the per-student widget toggle, so a student whose widget was disabled can no longer record hours via a direct request

## Changes in version 1.1.2 (Build: 2026071007)

- **New Feature**: Central global course hiding (enrollment mode) - quality managers can hide irrelevant courses from every student's workload dashboard at once, while a per-student include override still wins
- **New Feature**: The QM Assigned Courses view now flags courses that fall outside their active dates
- **Updated**: Default course management mode is now Enrollment
- **Updated**: Clarified the anonymisation setting descriptions across English, German and Ukrainian

## Changes in version 1.1.1 (Build: 2026070701)

- **Fixed**: Frankenstyle-prefixed global functions for coding-standards compliance
- **Fixed**: Privacy provider now declares and handles the `block_workload_user_settings` table
- **Fixed**: Eliminated N+1 database queries in cohort listing, reordering and block rendering
- **Updated**: Simplified bulk-add lookups and deduplicated the activation-window logic
- **Updated**: Moved remaining inline HTML/JS into Mustache templates and AMD modules
- **Updated**: Replaced the legacy `ajax_usersearch.php` endpoint with an external service

## Changes in version 1.1.0 (Build: 2026070303)

- **New Feature**: Teacher Course Statistics (enrollment mode) - per-course KPI cards, weekly charts, a top-10 pie chart, a filterable student table, and quick/detailed CSV export, anonymised by default

## Changes in version 1.0.3 (Build: 2026070201)

- **New Feature**: Admin-toggled statistics anonymisation - quality managers see stable, friendly pseudonyms instead of real identities, while roles with the `block/workload:viewrealnames` capability still see real names

## Changes in version 1.0.2 (Build: 2026063003)

- **New Feature**: "Show only active courses" (enrollment mode) - student dashboards list only courses whose start/end dates contain the current date

## Changes in version 1.0.1 (Build: 2026062409)

- **Fixed**: Repaired select-all in management tables on Moodle 5.x after the core master/slave toggle rename

## Changes in version 1.0.0 (Build: 2026062404)

- Initial release
- Weekly per-course hour logging on the dashboard with `+` / `-` buttons or an `h:mm` field, including backfill of recent past weeks
- Quality Manager cohort management (study programme, department, activation period) with member and course assignment
- Aggregate workload statistics with KPI cards, charts and CSV export
- Cohort and Enrollment course-management modes, with per-student course overrides and a per-student widget toggle
- Personal "My Statistics" page with CSV export for students
