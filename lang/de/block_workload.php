<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * German language strings for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'Arbeitsaufwand-Erhebung';
$string['workload:myaddinstance'] = 'Arbeitsaufwand-Erhebung-Block zu Meinem Dashboard hinzufügen';
$string['workload:addinstance']   = 'Arbeitsaufwand-Erhebung-Block zu einer Seite hinzufügen';
$string['workload:submit']        = 'Arbeitsstunden eintragen';
$string['workload:viewownstats']  = 'Eigene Arbeitsaufwand-Statistiken anzeigen';
$string['workload:manage']        = 'Arbeitsaufwand-Kohorten und Einstellungen verwalten (Qualitätsmanager)';
$string['workload:viewallstats']  = 'Arbeitsaufwand-Statistiken aller Studierenden anzeigen';
$string['workload:export']        = 'Arbeitsaufwand-Statistiken als CSV exportieren';

// Block display.
$string['blocktitle']         = 'Fortschritt dieser Woche';
$string['blocktitle_tooltip'] = 'Tragen Sie die Stunden ein, die Sie diese Woche für jeden Kurs aufgewendet haben.';
$string['weeknumber']         = 'KW {$a}';
$string['weeknumber_tooltip'] = 'Kalenderwoche {$a->week}, {$a->year}';
$string['columnlecture']   = 'Kurs';
$string['columnhours']     = 'h:mm';
$string['hrs']             = 'Std.';
$string['decrease']        = 'Stunden verringern';
$string['increase']        = 'Stunden erhöhen';
$string['notsubmitted']    = 'Stunden für diese Woche noch nicht eingetragen';
$string['viewmystats']     = 'Meine Statistiken';
$string['managedashboard'] = 'Dashboard Qualitätsmanager';
$string['workloadinactive']= 'Die Arbeitsaufwand-Erhebung ist für Ihre Kohorte derzeit nicht aktiv.';
$string['hrsplaceholder']  = '0';

// Student statistics page.
$string['mystats']               = 'Meine Arbeitsaufwand-Statistiken';
$string['statsbycourse']         = 'Stunden nach Kurs';
$string['statsbyweek']           = 'Stunden nach Woche';
$string['statstotal']            = 'Gesamtstunden';
$string['statsavg']              = 'Durchschnitt pro Woche';
$string['statsweek']             = 'Woche {$a}';
$string['backtoblock']           = 'Zurück zum Dashboard';
$string['noentriesfound']        = 'Keine Arbeitsaufwand-Einträge für den ausgewählten Zeitraum gefunden.';
$string['alltime']               = 'Gesamter Zeitraum';
$string['filterperiod']          = 'Zeitraum filtern';
$string['year']                  = 'Jahr';

// Management – general.
$string['managetitle']    = 'Verwaltung der Arbeitsaufwand-Erhebung';
$string['cohorts']        = 'Kohorten';
$string['cohortname']     = 'Kohortenname';
$string['degreeprogram']  = 'Studiengang';
$string['description']    = 'Beschreibung';
$string['active']         = 'Aktiv';
$string['activecourse']   = 'Aktiv';
$string['activate']       = 'Aktivieren';
$string['deactivate']     = 'Deaktivieren';
$string['actions']        = 'Aktionen';
$string['yes']            = 'Ja';
$string['no']             = 'Nein';
$string['save']           = 'Änderungen speichern';
$string['cancel']         = 'Abbrechen';
$string['confirm']        = 'Bestätigen';
$string['confirmdelete']  = 'Möchten Sie die Kohorte „{$a}" wirklich löschen? Alle zugehörigen Daten werden entfernt.';

// Management – cohort CRUD.
$string['addcohort']        = 'Kohorte hinzufügen';
$string['editcohort']       = 'Kohorte bearbeiten';
$string['deletecohort']     = 'Löschen';
$string['cohortsaved']      = 'Kohorte erfolgreich gespeichert.';
$string['cohortdeleted']    = 'Kohorte „{$a}" gelöscht.';
$string['nocohortsfound']   = 'Keine Kohorten gefunden. Erstellen Sie eine, um zu beginnen.';
$string['cohortmembers']    = 'Mitglieder';
$string['cohortcourses']    = 'Kurse';
$string['cohortactivation'] = 'Aktivierung';
$string['studentcount']     = '{$a} Studierende(r)';
$string['coursecount']      = '{$a} Kurs(e)';
$string['students']         = 'Studierende';
$string['courses']          = 'Kurse';
$string['inactive']         = 'Inaktiv';

// Management – members.
$string['memberstitle']       = 'Mitglieder verwalten: {$a}';
$string['currentmembers']     = 'Aktuelle Mitglieder';
$string['addmembers']         = 'Mitglieder hinzufügen';
$string['removemember']       = 'Entfernen';
$string['memberremoved']      = 'Mitglied entfernt.';
$string['membersremoved']     = '{$a} Mitglied(er) entfernt.';
$string['membersadded']       = '{$a} Mitglied(er) hinzugefügt.';
$string['bulkremove']         = 'Ausgewählte entfernen';
$string['bulkremovecourses']  = 'Ausgewählte entfernen';
$string['confirmbulkremove']    = 'Möchten Sie wirklich {$a} Mitglied(er) aus dieser Kohorte entfernen?';
$string['confirmsingleremove']  = 'Möchten Sie {$a} wirklich aus dieser Kohorte entfernen?';
$string['addselected']        = 'Ausgewählte Mitglieder hinzufügen';
$string['closeaddpanel']      = 'Hinzufügen abbrechen';
$string['alreadymembercount'] = '{$a} bereits in der Kohorte';
$string['perpage']            = 'Pro Seite';
$string['showall']            = 'Alle anzeigen ({$a})';
$string['searchusers']         = 'Nach Name oder E-Mail suchen';
$string['filterbyenrolled']    = 'Nach Kurseinschreibung filtern';
$string['selectcourse']        = '-- Alle Kurse --';
$string['nouserfound']         = 'Keine Benutzer gefunden, die der Suche entsprechen.';
$string['alreadymember']       = 'Bereits Mitglied dieser Kohorte.';
$string['selectdepartment']    = '-- Alle Abteilungen --';
$string['selectinstitution']   = '-- Alle Institutionen --';
$string['filtercategoryhelp']  = 'Findet Benutzer, die in einem Kurs dieser Kategorie oder ihrer Unterkategorien eingeschrieben sind.';
$string['clearfilters']        = 'Filter zurücksetzen';
$string['searchresultcount']   = '{$a} Benutzer gefunden';
$string['searchresultlimit']   = '(zeige erste 100)';
$string['importfrommoodlecohort']      = 'Aus globalen Gruppen importieren';
$string['moodlecohortlabel']           = 'Globale Gruppe';
$string['selectmoodlecohort']          = '-- Globale Gruppe auswählen --';
$string['nomoodlecohorts']             = 'Keine globalen Gruppen gefunden.';
$string['moodlecohortmembers']         = '{$a} Mitglied(er) in dieser globalen Gruppe';
$string['importselected']              = 'Ausgewählte importieren';
$string['nomoodlecohortmembers']       = 'Diese globale Gruppe hat keine Mitglieder.';

// Management – courses.
$string['coursestitle']      = 'Kurse verwalten: {$a}';
$string['assignedcourses']   = 'Zugewiesene Kurse';
$string['addcourses']        = 'Kurse hinzufügen';
$string['removecourse']      = 'Entfernen';
$string['courseremoved']              = 'Kurs entfernt.';
$string['coursesremoved']             = '{$a} Kurs(e) entfernt.';
$string['coursesadded']               = '{$a} Kurs(e) hinzugefügt.';
$string['confirmsingleremovecourse']  = 'Möchten Sie den Kurs „{$a}" wirklich aus dieser Kohorte entfernen?';
$string['confirmbulkremovecourses']   = 'Möchten Sie wirklich {$a} Kurs(e) aus dieser Kohorte entfernen?';
$string['order']             = 'Reihenfolge';
$string['filterbycategory']  = 'Nach Kategorie suchen';
$string['selectcategory']    = '-- Kategorie auswählen --';
$string['availablecourses']  = 'Verfügbare Kurse in der gewählten Kategorie';
$string['nocoursesincategory']  = 'Keine Kurse in dieser Kategorie gefunden.';
$string['courseresultcount']    = '{$a} Kurs(e) gefunden';
$string['alreadyassignedcount'] = '{$a} bereits zugewiesen';
$string['courseupdated']        = 'Kurseinstellungen aktualisiert.';
$string['coursestartdate']      = 'Startdatum';
$string['courseenddate']        = 'Enddatum';
$string['datedisabled']         = 'Deaktiviert';

// Management – activation.
$string['activationtitle']   = 'Aktivierungseinstellungen: {$a}';
$string['activationperiod']  = 'Aktivierungszeitraum';
$string['alwaysactive']      = 'Immer aktiv (kein Zeitlimit)';
$string['weekfrom']          = 'Von Woche (ISO)';
$string['yearfrom']          = 'Von Jahr';
$string['weekto']            = 'Bis Woche (ISO)';
$string['yearto']            = 'Bis Jahr';
$string['activationsaved']   = 'Aktivierungseinstellungen gespeichert.';
$string['currentstatus']     = 'Aktueller Status';
$string['statusactive']      = 'Aktiv';
$string['statusinactive']    = 'Inaktiv';

// Statistics page.
$string['statisticstitle']  = 'Arbeitsaufwand-Statistiken';
$string['selectcohort']     = 'Kohorte';
$string['allcohorts']       = 'Alle Kohorten';
$string['datefrom']         = 'Von (Woche – Jahr)';
$string['dateto']           = 'Bis (Woche – Jahr)';
$string['week']             = 'Woche';
$string['course']           = 'Kurs';
$string['hours']            = 'Stunden';
$string['student']          = 'Studierende(r)';
$string['totalhours']       = 'Gesamtstunden';
$string['averagehours']     = 'Ø Std./Woche';
$string['weeksactive']      = 'Wochen mit Einträgen';
$string['exportcsv']              = 'Als CSV exportieren';
$string['exportchoice']           = 'Exporttyp wählen';
$string['exportquick']            = 'Kurzübersicht';
$string['exportquick_desc']       = 'Eine Zeile pro Studierenden: Name, E-Mail, Abteilung, Institution, Gesamtstunden, aktive Wochen, Durchschnitt.';
$string['exportdetailed']         = 'Detailübersicht';
$string['exportdetailed_desc']    = 'Eine Zeile pro Studierenden pro Kurs: enthält alle Kurse und die Rolle der/des Studierenden in jedem Kurs.';
$string['exportfilenamedetailed'] = 'arbeitsaufwand_statistiken_detail';
$string['role']                   = 'Rolle';
$string['coursehours']            = 'Stunden im Kurs';
$string['filterresults']    = 'Filter anwenden';
$string['viewdetailed']     = 'Detailansicht';
$string['viewaggregated']   = 'Aggregierte Ansicht';
$string['nostatsfound']     = 'Keine Daten für die ausgewählten Filter gefunden.';
$string['exportfilename']   = 'arbeitsaufwand_statistiken';
$string['othercourses']     = '+ {$a} weitere(r) Kurs(e)';
$string['avghrsperstudent'] = 'Durchschnittliche Stunden / Studierende(r)';
$string['activestudents']   = 'Aktive Studierende';
$string['topstudents']      = 'Top {$a} Studierende nach Stunden';
$string['viewstudent']      = 'Studierende(n) anzeigen';
$string['allusers']         = 'Alle Studierenden';
$string['selectcohortfirst'] = 'Wählen Sie oben eine bestimmte Kohorte aus, um einzelne Studierende zu suchen.';
$string['viewingas']        = 'Statistiken für {$a}';
$string['backtooverview']   = 'Zurück zur Übersicht';
$string['toviewstatistics'] = 'um Statistiken anzuzeigen.';

// Enrollment mode – settings.
$string['coursemode']            = 'Kursverwaltungsmodus';
$string['coursemode_desc']       = 'Steuert, wie Kurse für jeden Studierenden angezeigt werden. Im <b>Kohorten</b>-Modus (Standard) werden Kurse über manuell erstellte Kohorten von Manager verwaltet. Im <b>Einschreibungs</b>-Modus sieht jeder Studierende automatisch die Kurse, in die er eingeschrieben ist; der Qualitätsmanager kann zusätzlich einzelne Kurse pro Studierendem hinzufügen oder ausschließen.';
$string['coursemode_cohort']     = 'Kohorte (über manuell erstellte Kohorten verwaltet)';
$string['coursemode_enrollment'] = 'Einschreibung (jeder Studierende sieht seine eingeschriebenen Kurse)';

// Enrollment mode – statistics page.
$string['enrollmentmode_notice'] = 'Einschreibungsmodus ist aktiv. Die Statistiken zeigen Daten für alle Studierenden auf Basis ihrer Kurseinschreibungen.';

// Enrollment mode – management dashboard notice.
$string['enrollmentmode_active_notice'] = 'Der Einschreibungsmodus ist derzeit aktiv.';
$string['enrollmentmodemanage']         = 'Studierende Kurse verwalten';

// Enrollment mode – student list page.
$string['enrollmenttitle']         = 'Kursverwaltung für Studierende';
$string['enrollmenttitle_student'] = 'Kurse für {$a}';
$string['backstudentlist']         = 'Zurück zur Studierendenliste';
$string['nostudentsfound']         = 'Keine Studierenden gefunden.';
$string['enrolledcoursecount']     = '{$a} eingeschriebene(r) Kurs(e)';
$string['colenrolled']             = 'Eingeschrieben';
$string['colexcluded']             = 'Ausgeschlossen';
$string['coladded']                = 'Hinzugefügt';
$string['coltotal']                = 'Gesamt angezeigt';
$string['colenrolled_title']       = 'Kurse, in die der Studierende über Moodle eingeschrieben ist';
$string['colexcluded_title']       = 'Eingeschriebene Kurse, die der Manager für den Studierenden ausgeblendet hat';
$string['coladded_title']          = 'Kurse, die vom Manager manuell hinzugefügt wurden (nicht aus der Einschreibung)';
$string['coltotal_title']          = 'Kurse, die dem Studierenden aktuell angezeigt werden (Eingeschrieben − Ausgeschlossen + Hinzugefügt)';
$string['managecourses']           = 'Kurse verwalten';

// Enrollment mode – student detail page.
$string['enrolledcourses']          = 'Eingeschriebene Kurse';
$string['manageradded']             = 'Vom Manager hinzugefügte Kurse';
$string['noenrolledcourses']        = 'Dieser Studierende ist in keine sichtbaren Kurse eingeschrieben.';
$string['nomanagercourses']         = 'Es wurden keine Kurse vom Manager für diesen Studierenden hinzugefügt.';
$string['statusheader']             = 'Status';
$string['statusincluded']           = 'Aktiv';
$string['statusexcluded']           = 'Ausgeschlossen';
$string['statusadded']              = 'Hinzugefügt';
$string['excludecourse']            = 'Ausschließen';
$string['restorecourse']            = 'Wiederherstellen';
$string['addcourseforstudent']      = 'Kurs hinzufügen';
$string['addcoursesforstudent']     = 'Kurs/Kurse hinzufügen';
$string['allcoursesalreadymanaged'] = 'Alle Kurse in dieser Kategorie werden diesem Studierenden bereits angezeigt.';
$string['confirmexclude']           = '„{$a}" für diesen Studierenden ausschließen? Der Kurs wird im Arbeitsaufwand-Block nicht mehr angezeigt.';
$string['confirmremoveadded']       = 'Den manuell hinzugefügten Kurs „{$a}" für diesen Studierenden entfernen?';
$string['courseexcluded']           = 'Kurs für diesen Studierenden ausgeschlossen.';
$string['courserestored']           = 'Kurs für diesen Studierenden wiederhergestellt.';
$string['courseadded']              = 'Kurs für diesen Studierenden hinzugefügt.';
$string['coursesexcluded']          = '{$a} Kurs/Kurse für diesen Studierenden ausgeschlossen.';
$string['coursesrestored']          = '{$a} Kurs/Kurse für diesen Studierenden wiederhergestellt.';
$string['excludeselected']          = 'Ausgewählte ausschließen';
$string['restoreselected']          = 'Ausgewählte wiederherstellen';
$string['confirmbulkexclude']       = 'Möchten Sie {$a} Kurs/Kurse für diesen Studierenden wirklich ausschließen?';
$string['confirmbulkremoveadded']   = 'Möchten Sie {$a} manuell hinzugefügte(n) Kurs/Kurse für diesen Studierenden wirklich entfernen?';

// Settings.
$string['maxhours']               = 'Maximale Stunden pro Kurs und Woche';
$string['maxhours_desc']          = 'Die maximale Anzahl an Stunden, die ein Studierender für einen einzelnen Kurs in einer Woche eintragen kann.';
$string['hourstep']      = 'Schrittweite pro Klick (Minuten)';
$string['hourstep_desc']    = 'Wie viele Minuten bei jedem Klick auf + oder − addiert oder subtrahiert werden. Geben Sie eine ganze Zahl von mindestens 1 ein.';
$string['hourstep_invalid'] = 'Bitte geben Sie eine ganze Zahl von mindestens 1 ein.';
$string['coursesperpage']         = 'Kurse pro Seite (Studierenden-Block)';
$string['coursesperpage_desc']    = 'Wie viele Kurse pro Seite im Studierenden-Dashboard-Block angezeigt werden. 0 eingeben, um die Paginierung zu deaktivieren und immer alle Kurse anzuzeigen.';
$string['courseorder']            = 'Kurssortierreihenfolge';
$string['courseorder_desc']       = 'Steuert die Reihenfolge der Kurse im Studierenden-Dashboard-Block. „Zuletzt aufgerufen" zeigt die zuletzt besuchten Kurse zuerst. „Manuelle Sortierreihenfolge" verwendet die Reihenfolge, die der Qualitätsmanager auf der Seite „Zugewiesene Kurse" festgelegt hat.';
$string['courseorder_sortorder']  = 'Manuelle Sortierreihenfolge';
$string['courseorder_recentaccess'] = 'Zuletzt aufgerufen';
$string['enablemoodlecohortimport']      = 'Import aus globalen Gruppen erlauben';
$string['enablemoodlecohortimport_desc'] = 'Wenn aktiviert, erscheint auf der Seite „Mitglieder verwalten" ein zusätzlicher Abschnitt „Aus globalen Gruppen importieren", der Managern ermöglicht, Benutzer aus einer beliebigen globalen Gruppe in eine "manuell" erstellte Kohorte zu importieren.';

// Block pagination.
$string['pageprev'] = 'Zurück';
$string['pagenext'] = 'Weiter';

// Event names (shown in Site Administration → Reports → Logs).
$string['event_cohort_created']    = 'Arbeitsaufwand-Kohorte erstellt';
$string['event_cohort_updated']    = 'Arbeitsaufwand-Kohorte aktualisiert';
$string['event_cohort_deleted']    = 'Arbeitsaufwand-Kohorte gelöscht';
$string['event_activation_updated'] = 'Aktivierung der Arbeitsaufwand-Kohorte aktualisiert';
$string['event_members_added']     = 'Mitglieder zur Arbeitsaufwand-Kohorte hinzugefügt';
$string['event_members_removed']   = 'Mitglieder aus der Arbeitsaufwand-Kohorte entfernt';
$string['event_courses_assigned']  = 'Kurse der Arbeitsaufwand-Kohorte zugewiesen';
$string['event_courses_removed']   = 'Kurse aus der Arbeitsaufwand-Kohorte entfernt';
$string['event_course_toggled']    = 'Aktivierungsstatus des Arbeitsaufwand-Kurses geändert';

// Role created on install.
$string['role_manager_name'] = 'Arbeitsaufwand-Manager';
$string['role_manager_desc'] = 'Qualitätsmanager: verwaltet Kohorten, Mitglieder, Kurse und sieht alle Arbeitsaufwand-Statistiken ein.';

// Privacy.
$string['privacy:metadata:block_workload_entries']            = 'Speichert die Arbeitsstunden, die Studierende pro Kurs und Woche eintragen.';
$string['privacy:metadata:block_workload_entries:userid']     = 'Die ID der/des Studierenden.';
$string['privacy:metadata:block_workload_entries:courseid']   = 'Die ID des Kurses.';
$string['privacy:metadata:block_workload_entries:weeknumber'] = 'Die ISO-Kalenderwochennummer.';
$string['privacy:metadata:block_workload_entries:year']       = 'Das Jahr des Eintrags.';
$string['privacy:metadata:block_workload_entries:hours']      = 'Die Anzahl der eingetragenen Stunden.';
$string['privacy:metadata:block_workload_members']            = 'Speichert die Kohortenmitgliedschaft und verknüpft Studierende mit ihrer Studiengangskohorte.';
$string['privacy:metadata:block_workload_members:userid']     = 'Die ID der/des Studierenden.';
$string['privacy:metadata:block_workload_members:cohortid']   = 'Die ID der Kohorte.';
