<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'Workload Assessment';
$string['workload:myaddinstance'] = 'Add Workload Assessment block to My Dashboard';
$string['workload:addinstance']   = 'Add Workload Assessment block to a page';
$string['workload:submit']        = 'Submit workload hours';
$string['workload:viewownstats']  = 'View own workload statistics';
$string['workload:manage']        = 'Manage workload cohorts and settings (Quality Manager)';
$string['workload:viewallstats']  = 'View all students\' workload statistics';
$string['workload:export']        = 'Export workload statistics to CSV';

// Block display.
$string['blocktitle']         = 'This Week\'s Progress';
$string['blocktitle_tooltip'] = 'Log the hours you spent on each course this week.';
$string['weeknumber']         = 'W {$a}';
$string['weeknumber_tooltip'] = 'Calendar week {$a->week}, {$a->year}';
$string['columnlecture']   = 'Course';
$string['columnhours']     = 'h:mm';
$string['hrs']             = 'hrs.';
$string['decrease']        = 'Decrease hours';
$string['increase']        = 'Increase hours';
$string['notsubmitted']    = 'Hours not yet entered for this week';
$string['viewmystats']     = 'My Statistics';
$string['managedashboard'] = 'Quality Manager Dashboard';
$string['workloadinactive']= 'The workload survey is currently not active for your cohort.';
$string['hrsplaceholder']  = '0';

// Student statistics page.
$string['mystats']               = 'My Workload Statistics';
$string['statsbycourse']         = 'Hours by Course';
$string['statsbyweek']           = 'Hours by Week';
$string['statstotal']            = 'Total Hours';
$string['statsavg']              = 'Average per Week';
$string['statsweek']             = 'Week {$a}';
$string['backtoblock']           = 'Back to Dashboard';
$string['noentriesfound']        = 'No workload entries found for the selected period.';
$string['alltime']               = 'All time';
$string['filterperiod']          = 'Filter period';
$string['year']                  = 'Year';

// Management – general.
$string['managetitle']    = 'Workload Management';
$string['cohorts']        = 'Cohorts';
$string['cohortname']     = 'Cohort Name';
$string['degreeprogram']  = 'Study Program';
$string['description']    = 'Description';
$string['active']         = 'Active';
$string['activecourse']   = 'Active';
$string['activate']       = 'Activate';
$string['deactivate']     = 'Deactivate';
$string['actions']        = 'Actions';
$string['yes']            = 'Yes';
$string['no']             = 'No';
$string['save']           = 'Save changes';
$string['cancel']         = 'Cancel';
$string['confirm']        = 'Confirm';
$string['confirmdelete']  = 'Are you sure you want to delete cohort "{$a}"? All associated data will be removed.';

// Management – cohort CRUD.
$string['addcohort']        = 'Add Cohort';
$string['editcohort']       = 'Edit Cohort';
$string['deletecohort']     = 'Delete';
$string['cohortsaved']      = 'Cohort saved successfully.';
$string['cohortdeleted']    = 'Cohort "{$a}" deleted.';
$string['nocohortsfound']   = 'No cohorts found. Create one to get started.';
$string['cohortmembers']    = 'Members';
$string['cohortcourses']    = 'Courses';
$string['cohortactivation'] = 'Activation';
$string['studentcount']     = '{$a} student(s)';
$string['coursecount']      = '{$a} course(s)';
$string['students']         = 'Students';
$string['courses']          = 'Courses';
$string['inactive']         = 'Inactive';

// Management – members.
$string['memberstitle']       = 'Manage Members: {$a}';
$string['currentmembers']     = 'Current Members';
$string['addmembers']         = 'Add Members';
$string['removemember']       = 'Remove';
$string['memberremoved']      = 'Member removed.';
$string['membersremoved']     = '{$a} member(s) removed.';
$string['membersadded']       = '{$a} member(s) added.';
$string['bulkremove']         = 'Remove selected';
$string['bulkremovecourses']  = 'Remove selected';
$string['confirmbulkremove']    = 'Are you sure you want to remove {$a} member(s) from this cohort?';
$string['confirmsingleremove']  = 'Are you sure you want to remove {$a} from this cohort?';
$string['addselected']        = 'Add selected members';
$string['closeaddpanel']      = 'Cancel adding members';
$string['alreadymembercount'] = '{$a} already in cohort';
$string['perpage']            = 'Per page';
$string['showall']            = 'Show all ({$a})';
$string['searchusers']         = 'Search by name or email';
$string['filterbyenrolled']    = 'Filter by course enrolment';
$string['selectcourse']        = '-- All courses --';
$string['nouserfound']         = 'No users found matching the search.';
$string['alreadymember']       = 'Already a member of this cohort.';
$string['selectdepartment']    = '-- All departments --';
$string['selectinstitution']   = '-- All institutions --';
$string['filtercategoryhelp']  = 'Matches users enrolled in any course in this category or its sub-categories.';
$string['clearfilters']        = 'Clear filters';
$string['searchresultcount']   = '{$a} user(s) found';
$string['searchresultlimit']   = '(showing first 100)';
$string['importfrommoodlecohort']      = 'Import from Moodle System Cohorts';
$string['moodlecohortlabel']           = 'Moodle system cohort';
$string['selectmoodlecohort']          = '-- Select Moodle cohort --';
$string['nomoodlecohorts']             = 'No Moodle system cohorts found.';
$string['moodlecohortmembers']         = '{$a} member(s) in this Moodle cohort';
$string['importselected']              = 'Import selected';
$string['nomoodlecohortmembers']       = 'This Moodle cohort has no members.';

// Management – courses.
$string['coursestitle']      = 'Manage Courses: {$a}';
$string['assignedcourses']   = 'Assigned Courses';
$string['addcourses']        = 'Add Courses';
$string['removecourse']      = 'Remove';
$string['courseremoved']              = 'Course removed.';
$string['coursesremoved']             = '{$a} course(s) removed.';
$string['coursesadded']               = '{$a} course(s) added.';
$string['confirmsingleremovecourse']  = 'Are you sure you want to remove the course "{$a}" from this cohort?';
$string['confirmbulkremovecourses']   = 'Are you sure you want to remove {$a} course(s) from this cohort?';
$string['order']             = 'Order';
$string['filterbycategory']  = 'Browse by category';
$string['selectcategory']    = '-- Select category --';
$string['availablecourses']  = 'Available Courses in Selected Category';
$string['nocoursesincategory']  = 'No courses found in this category.';
$string['courseresultcount']    = '{$a} course(s) found';
$string['alreadyassignedcount'] = '{$a} already assigned';
$string['courseupdated']        = 'Course settings updated.';
$string['coursestartdate']      = 'Start Date';
$string['courseenddate']        = 'End Date';
$string['datedisabled']         = 'Disabled';

// Management – activation.
$string['activationtitle']   = 'Activation Settings: {$a}';
$string['activationperiod']  = 'Activation Period';
$string['alwaysactive']      = 'Always active (no time limit)';
$string['weekfrom']          = 'From Week (ISO)';
$string['yearfrom']          = 'From Year';
$string['weekto']            = 'To Week (ISO)';
$string['yearto']            = 'To Year';
$string['activationsaved']   = 'Activation settings saved.';
$string['currentstatus']     = 'Current Status';
$string['statusactive']      = 'Active';
$string['statusinactive']    = 'Inactive';

// Statistics page.
$string['statisticstitle']  = 'Workload Statistics';
$string['selectcohort']     = 'Cohort';
$string['allcohorts']       = 'All cohorts';
$string['datefrom']         = 'From (Week – Year)';
$string['dateto']           = 'To (Week – Year)';
$string['week']             = 'Week';
$string['course']           = 'Course';
$string['hours']            = 'Hours';
$string['student']          = 'Student';
$string['totalhours']       = 'Total Hours';
$string['averagehours']     = 'Avg hrs/week';
$string['weeksactive']      = 'Weeks with entries';
$string['exportcsv']              = 'Export to CSV';
$string['exportchoice']           = 'Choose export type';
$string['exportquick']            = 'Quick overview';
$string['exportquick_desc']       = 'One row per student: name, email, department, institution, total hours, active weeks, average.';
$string['exportdetailed']         = 'Detailed overview';
$string['exportdetailed_desc']    = 'One row per student per course: includes all courses and the student\'s role in each course.';
$string['exportfilenamedetailed'] = 'workload_statistics_detailed';
$string['role']                   = 'Role';
$string['coursehours']            = 'Hours in Course';
$string['filterresults']    = 'Apply Filter';
$string['viewdetailed']     = 'Detailed view';
$string['viewaggregated']   = 'Aggregated view';
$string['nostatsfound']     = 'No data found for the selected filters.';
$string['exportfilename']   = 'workload_statistics';
$string['othercourses']     = '+ {$a} course(s) more';
$string['avghrsperstudent'] = 'Average hours / Student';
$string['activestudents']   = 'Active students';
$string['topstudents']      = 'Top {$a} Students by Hours';
$string['viewstudent']      = 'View student';
$string['allusers']         = 'All students';
$string['selectcohortfirst'] = 'Select a specific cohort above to look up an individual student.';
$string['viewingas']        = 'Statistics for {$a}';
$string['backtooverview']   = 'Back to overview';
$string['toviewstatistics'] = 'to view statistics.';

// Enrollment mode – settings.
$string['coursemode']            = 'Course management mode';
$string['coursemode_desc']       = 'Controls how courses are shown to each student. In <b>Cohort</b> mode (default) courses are managed by assigning them to manually created cohorts by the Quality Manager. In <b>Enrollment</b> mode each student automatically sees the courses they are enrolled in; the Quality Manager can additionally add or exclude individual courses per student.';
$string['coursemode_cohort']     = 'Cohort (managed via manually created cohorts by the manager)';
$string['coursemode_enrollment'] = 'Enrollment (each student sees their enrolled courses)';

// Enrollment mode – statistics page.
$string['enrollmentmode_notice'] = 'Enrollment mode is active. Statistics show data for all students based on their course enrollments.';

// Enrollment mode – management dashboard notice.
$string['enrollmentmode_active_notice'] = 'Enrollment mode is currently active.';
$string['enrollmentmodemanage']         = 'Manage Student Courses';

// Enrollment mode – student list page.
$string['enrollmenttitle']         = 'Student Course Management';
$string['enrollmenttitle_student'] = 'Courses for {$a}';
$string['backstudentlist']         = 'Back to student list';
$string['nostudentsfound']         = 'No students found.';
$string['enrolledcoursecount']     = '{$a} enrolled course(s)';
$string['colenrolled']             = 'Enrolled';
$string['colexcluded']             = 'Excluded';
$string['coladded']                = 'Added';
$string['coltotal']                = 'Total shown';
$string['colenrolled_title']       = 'Courses the student is enrolled in via Moodle';
$string['colexcluded_title']       = 'Enrolled courses hidden from the student by the manager';
$string['coladded_title']          = 'Courses manually added by the manager (not from enrollment)';
$string['coltotal_title']          = 'Total courses currently visible to the student (enrolled − excluded + added)';
$string['managecourses']           = 'Manage Courses';

// Enrollment mode – student detail page.
$string['enrolledcourses']         = 'Enrolled Courses';
$string['manageradded']            = 'Manager-Added Courses';
$string['noenrolledcourses']       = 'This student is not enrolled in any visible courses.';
$string['nomanagercourses']        = 'No courses have been added by the manager for this student.';
$string['statusheader']            = 'Status';
$string['statusincluded']          = 'Included';
$string['statusexcluded']          = 'Excluded';
$string['statusadded']             = 'Added';
$string['excludecourse']           = 'Exclude';
$string['restorecourse']           = 'Restore';
$string['addcourseforstudent']     = 'Add Course';
$string['addcoursesforstudent']    = 'Add Course(s)';
$string['allcoursesalreadymanaged'] = 'All courses in this category are already shown to this student.';
$string['confirmexclude']          = 'Exclude "{$a}" for this student? They will no longer see it in their workload block.';
$string['confirmremoveadded']      = 'Remove the manually added course "{$a}" for this student?';
$string['courseexcluded']          = 'Course excluded for this student.';
$string['courserestored']          = 'Course restored for this student.';
$string['courseadded']             = 'Course added for this student.';
$string['coursesexcluded']         = '{$a} course(s) excluded for this student.';
$string['coursesrestored']         = '{$a} course(s) restored for this student.';
$string['excludeselected']         = 'Exclude selected';
$string['restoreselected']         = 'Restore selected';
$string['confirmbulkexclude']      = 'Are you sure you want to exclude {$a} course(s) for this student?';
$string['confirmbulkremoveadded']  = 'Are you sure you want to remove {$a} manager-added course(s) for this student?';

// Settings.
$string['maxhours']               = 'Maximum hours per course per week';
$string['maxhours_desc']          = 'The maximum number of hours a student can log for a single course in one week.';
$string['hourstep']      = 'Increment per click (minutes)';
$string['hourstep_desc']    = 'How many minutes are added or subtracted each time a student clicks + or −. Enter a whole number of at least 1.';
$string['hourstep_invalid'] = 'Please enter a whole number of at least 1.';
$string['coursesperpage']         = 'Courses per page (student block)';
$string['coursesperpage_desc']    = 'How many courses are shown per page in the student dashboard block. Set to 0 to disable pagination and always show all courses.';
$string['courseorder']            = 'Course display order';
$string['courseorder_desc']       = 'Controls the order courses appear in the student dashboard block. "Recently accessed" puts the most recently visited courses first. "Manual sort order" uses the order the Quality Manager set on the Assigned Courses page.';
$string['courseorder_sortorder']  = 'Manual sort order';
$string['courseorder_recentaccess'] = 'Recently accessed';
$string['enablemoodlecohortimport']      = 'Allow import from Moodle System Cohorts';
$string['enablemoodlecohortimport_desc'] = 'When enabled, an additional "Import from Moodle System Cohorts" section appears on the Manage Members page, letting managers bulk-add users from any Moodle system cohort into a manually created workload cohort.';

// Block pagination.
$string['pageprev'] = 'Previous';
$string['pagenext'] = 'Next';

// Event names (shown in Site Administration → Reports → Logs).
$string['event_cohort_created']    = 'Workload cohort created';
$string['event_cohort_updated']    = 'Workload cohort updated';
$string['event_cohort_deleted']    = 'Workload cohort deleted';
$string['event_activation_updated'] = 'Workload cohort activation updated';
$string['event_members_added']     = 'Workload cohort members added';
$string['event_members_removed']   = 'Workload cohort members removed';
$string['event_courses_assigned']  = 'Workload courses assigned to cohort';
$string['event_courses_removed']   = 'Workload courses removed from cohort';
$string['event_course_toggled']    = 'Workload course active status toggled';

// Role created on install.
$string['role_manager_name'] = 'Workload Manager';
$string['role_manager_desc'] = 'Quality Manager: manages cohorts, members, courses, and views all workload statistics.';

// Privacy.
$string['privacy:metadata:block_workload_entries']            = 'Stores the workload hours a student enters per course per week.';
$string['privacy:metadata:block_workload_entries:userid']     = 'The ID of the student.';
$string['privacy:metadata:block_workload_entries:courseid']   = 'The ID of the course.';
$string['privacy:metadata:block_workload_entries:weeknumber'] = 'The ISO week number.';
$string['privacy:metadata:block_workload_entries:year']       = 'The year of the entry.';
$string['privacy:metadata:block_workload_entries:hours']      = 'The number of hours logged.';
$string['privacy:metadata:block_workload_members']            = 'Stores cohort membership, linking students to their degree program cohort.';
$string['privacy:metadata:block_workload_members:userid']     = 'The ID of the student.';
$string['privacy:metadata:block_workload_members:cohortid']   = 'The ID of the cohort.';
