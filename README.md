# Workload Assessment Block for Moodle

![Moodle](https://img.shields.io/badge/Moodle-4.5+-orange?logo=moodle)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPL%20v3-green?logo=gnu)
![Version](https://img.shields.io/badge/Release-1.0.3%20(beta)-blue)

A Moodle block that lets students log the hours they spend on each course every week, and
gives quality managers cohort-wide statistics on student workload across the semester.

<p align="center"><img src="screenshots/block_workload.png" alt="Workload Assessment Block" width="320" /></p>

## Features

**For students**
- Log weekly hours per course directly in the block with `+` / `−` buttons or an `h:mm` field.
- Personal **My Statistics** page: KPI cards, weekly and per-course charts, and CSV export.

**For quality managers**
- Manage student **cohorts** (study programme, department, activation period).
- Assign members (search, bulk by department/institution, or import from a Moodle system
  cohort) and courses (from the category tree, reorderable, toggleable per cohort).
- Aggregate **statistics**: KPI cards, average-hours and active-students charts, top-10 pie
  chart, and a filterable student table with one-click drill-down and CSV export.

**Admin / configuration**
- Site-wide defaults: max hours per course/week, increment per click, courses per page, and
  course display order.
- **Course management mode** — *Cohort* (courses come from cohort assignments) or
  *Enrollment* (courses come from each student's Moodle enrolments, with per-student
  excludes and manual additions).
- **Show only active courses** (Enrollment mode, on by default) — limits the student's block
  to courses whose start/end dates contain the current date; courses with no end date stay
  visible once started.
- **Anonymise statistics** (off by default) — quality managers see stable, friendly pseudonyms 
  instead of real names, emails, departments and institutions in statistics, drill-downs and 
  CSV exports. Identities stay hidden while individual workload patterns remain trackable; 
  roles holding the `block/workload:viewrealnames` capability (and site administrators) still 
  see real names.

## Screenshots

<details>
<summary><strong>📸 View screenshot gallery</strong></summary>

**Workload Management** — cohort dashboard for quality managers.
<p align="center"><img src="screenshots/workload_management.png" alt="Workload Management" /></p>

**Workload Statistics** — KPIs, charts, top-10, and student table.
<p align="center"><img src="screenshots/qm_statistics.png" alt="QM Statistics — charts" /></p>
<p align="center"><img src="screenshots/qm_statistics_2.png" alt="QM Statistics — pie chart and table" /></p>

**My Statistics** — each student's personal workload history.
<p align="center"><img src="screenshots/my_statistics.png" alt="My Statistics — weekly chart" /></p>
<p align="center"><img src="screenshots/my_statistics_2.png" alt="My Statistics — course breakdown" /></p>

**Enrollment mode** — per-student course management.
<p align="center"><img src="screenshots/enrollment_mode.png" alt="Enrollment Mode" /></p>

**Admin settings** — site-wide defaults.
<p align="center"><img src="screenshots/admin_settings.png" alt="Admin Settings" /></p>

</details>

## Requirements

- Moodle 4.5 or higher
- PHP 7.4 or higher

## Installation

1. Install the plugin either way:
   - **Admin UI:** *Site administration → Plugins → Install plugins*, then upload
     `block_workload.zip`.
   - **Manual:** extract the `workload` folder into `/path/to/moodle/blocks/`.
2. Visit *Site administration → Notifications* and complete the upgrade prompts.
3. **Assign the Quality Manager role.** Installation creates a `workload_manager` role; grant
   it under *Site administration → Users → Permissions → Assign system roles → Workload
   Manager*. Without it, users cannot access cohort management or the aggregate statistics.
4. **Add the block to every user's dashboard.** Go to *Site administration → Appearance →
   Default Dashboard page*, click **Blocks editing on**, add the *Workload Assessment* block,
   then **Reset Dashboard for all users** so it appears for everyone.

## Usage

**Students** — add the *Workload Assessment* block to your dashboard, log hours each week per
course, and open **My Statistics** for your history.

**Quality managers** — open **Workload Management**, create a cohort, assign students and
courses (optionally set an activation period), then review **Workload Statistics**.

## License

[GNU GPL v3 or later](LICENSE).

## Credits

Yurii Lysak (2026) — HNEE (Hochschule für nachhaltige Entwicklung Eberswalde).

## Contributing & Support

Contributions are welcome!

- Report bugs and request features via the [issue tracker](https://github.com/lysak-yurii/moodle-block_workload/issues).
- Fork the repo, create a feature branch, and open a pull request.

Translations are especially appreciated — the plugin currently ships English, German, and
Ukrainian.

By contributing you agree that your work is licensed under the GNU GPL v3 or later.
