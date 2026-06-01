<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Ukrainian language strings for block_workload.
 *
 * @package   block_workload
 * @copyright  2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'Облік навчального навантаження';
$string['workload:myaddinstance'] = 'Додати блок обліку навантаження на мою панель';
$string['workload:addinstance']   = 'Додати блок обліку навантаження на сторінку';
$string['workload:submit']        = 'Вносити години навантаження';
$string['workload:viewownstats']  = 'Переглядати власну статистику навантаження';
$string['workload:manage']        = 'Керувати когортами та налаштуваннями навантаження (менеджер якості)';
$string['workload:viewallstats']  = 'Переглядати статистику навантаження всіх студентів';
$string['workload:export']        = 'Експортувати статистику навантаження у CSV';

// Block display.
$string['blocktitle']         = 'Прогрес цього тижня';
$string['blocktitle_tooltip'] = 'Вкажіть кількість годин, витрачених на кожен курс цього тижня.';
$string['weeknumber']         = 'Тиж. {$a}';
$string['weeknumber_tooltip'] = 'Календарний тиждень {$a->week}, {$a->year}';
$string['columnlecture']   = 'Курс';
$string['columnhours']     = 'г:хв';
$string['hrs']             = 'год.';
$string['decrease']        = 'Зменшити години';
$string['increase']        = 'Збільшити години';
$string['notsubmitted']    = 'Години за цей тиждень ще не внесено';
$string['viewmystats']     = 'Моя статистика';
$string['managedashboard'] = 'Панель QM-Менеджера';
$string['workloadinactive']= 'Опитування щодо навантаження наразі неактивне для вашої когорти.';
$string['hrsplaceholder']  = '0';

// Student statistics page.
$string['mystats']               = 'Моя статистика навантаження';
$string['statsbycourse']         = 'Години за курсами';
$string['statsbyweek']           = 'Години за тижнями';
$string['statstotal']            = 'Загальна кількість годин';
$string['statsavg']              = 'Середнє за тиждень';
$string['statsweek']             = 'Тиждень {$a}';
$string['backtoblock']           = 'Повернутися на панель';
$string['noentriesfound']        = 'Записів про навантаження за вибраний період не знайдено.';
$string['alltime']               = 'Увесь час';
$string['filterperiod']          = 'Фільтр за періодом';
$string['year']                  = 'Рік';

// Management – general.
$string['managetitle']    = 'Управління навантаженням';
$string['cohorts']        = 'Когорти';
$string['cohortname']     = 'Назва когорти';
$string['degreeprogram']  = 'Навчальна програма';
$string['description']    = 'Опис';
$string['active']         = 'Активна';
$string['activecourse']   = 'Активний';
$string['activate']       = 'Активувати';
$string['deactivate']     = 'Деактивувати';
$string['actions']        = 'Дії';
$string['yes']            = 'Так';
$string['no']             = 'Ні';
$string['save']           = 'Зберегти зміни';
$string['cancel']         = 'Скасувати';
$string['confirm']        = 'Підтвердити';
$string['confirmdelete']  = 'Ви справді хочете видалити когорту «{$a}»? Усі пов\'язані дані буде видалено.';

// Management – cohort CRUD.
$string['addcohort']        = 'Додати когорту';
$string['editcohort']       = 'Редагувати когорту';
$string['deletecohort']     = 'Видалити';
$string['cohortsaved']      = 'Когорту успішно збережено.';
$string['cohortdeleted']    = 'Когорту «{$a}» видалено.';
$string['nocohortsfound']   = 'Когорт не знайдено. Створіть одну, щоб почати.';
$string['cohortmembers']    = 'Учасники';
$string['cohortcourses']    = 'Курси';
$string['cohortactivation'] = 'Активація';
$string['studentcount']     = '{$a} студент(ів)';
$string['coursecount']      = '{$a} курс(ів)';
$string['students']         = 'Студенти';
$string['courses']          = 'Курси';
$string['inactive']         = 'Неактивна';

// Management – members.
$string['memberstitle']       = 'Управління учасниками: {$a}';
$string['currentmembers']     = 'Поточні учасники';
$string['addmembers']         = 'Додати учасників';
$string['removemember']       = 'Видалити';
$string['memberremoved']      = 'Учасника видалено.';
$string['membersremoved']     = '{$a} учасника(ів) видалено.';
$string['membersadded']       = '{$a} учасника(ів) додано.';
$string['bulkremove']         = 'Видалити вибраних';
$string['bulkremovecourses']  = 'Видалити вибрані';
$string['confirmbulkremove']    = 'Ви справді хочете видалити {$a} учасника(ів) з цієї когорти?';
$string['confirmsingleremove']  = 'Ви справді хочете видалити {$a} з цієї когорти?';
$string['addselected']        = 'Додати вибраних учасників';
$string['closeaddpanel']      = 'Скасувати додавання учасників';
$string['alreadymembercount'] = '{$a} вже в когорті';
$string['perpage']            = 'На сторінці';
$string['showall']            = 'Показати всіх ({$a})';
$string['searchusers']         = 'Пошук за ім\'ям або електронною поштою';
$string['filterbyenrolled']    = 'Фільтр за зарахуванням до курсу';
$string['selectcourse']        = '-- Усі курси --';
$string['nouserfound']         = 'Користувачів за вашим запитом не знайдено.';
$string['alreadymember']       = 'Вже є учасником цієї когорти.';
$string['selectdepartment']    = '-- Усі відділи --';
$string['selectinstitution']   = '-- Усі установи --';
$string['filtercategoryhelp']  = 'Знаходить користувачів, зарахованих до будь-якого курсу в цій категорії або її підкатегоріях.';
$string['clearfilters']        = 'Очистити фільтри';
$string['searchresultcount']   = 'Знайдено {$a} користувача(ів)';
$string['searchresultlimit']   = '(показано перші 100)';
$string['importfrommoodlecohort']      = 'Імпортувати з системних груп Moodle';
$string['moodlecohortlabel']           = 'Системна група (когорта) Moodle';
$string['selectmoodlecohort']          = '-- Вибрати системну групу --';
$string['nomoodlecohorts']             = 'Системних груп Moodle не знайдено.';
$string['moodlecohortmembers']         = '{$a} учасника(ів) у цій когорті Moodle';
$string['importselected']              = 'Імпортувати вибраних';
$string['nomoodlecohortmembers']       = 'У цій когорті Moodle немає учасників.';

// Management – courses.
$string['coursestitle']      = 'Управління курсами: {$a}';
$string['assignedcourses']   = 'Призначені курси';
$string['addcourses']        = 'Додати курси';
$string['removecourse']      = 'Видалити';
$string['courseremoved']              = 'Курс видалено.';
$string['coursesremoved']             = '{$a} курс(ів) видалено.';
$string['coursesadded']               = '{$a} курс(ів) додано.';
$string['confirmsingleremovecourse']  = 'Ви справді хочете видалити курс «{$a}» з цієї когорти?';
$string['confirmbulkremovecourses']   = 'Ви справді хочете видалити {$a} курс(ів) з цієї когорти?';
$string['order']             = 'Порядок';
$string['filterbycategory']  = 'Перегляд за категорією';
$string['selectcategory']    = '-- Вибрати категорію --';
$string['availablecourses']  = 'Доступні курси у вибраній категорії';
$string['nocoursesincategory']  = 'У цій категорії курсів не знайдено.';
$string['courseresultcount']    = 'Знайдено {$a} курс(ів)';
$string['alreadyassignedcount'] = '{$a} вже призначено';
$string['courseupdated']        = 'Налаштування курсу оновлено.';
$string['coursestartdate']      = 'Дата початку';
$string['courseenddate']        = 'Дата закінчення';
$string['datedisabled']         = 'Вимкнено';

// Management – activation.
$string['activationtitle']   = 'Налаштування активації: {$a}';
$string['activationperiod']  = 'Період активації';
$string['alwaysactive']      = 'Завжди активна (без обмежень у часі)';
$string['weekfrom']          = 'З тижня (ISO)';
$string['yearfrom']          = 'З року';
$string['weekto']            = 'До тижня (ISO)';
$string['yearto']            = 'До року';
$string['activationsaved']   = 'Налаштування активації збережено.';
$string['currentstatus']     = 'Поточний статус';
$string['statusactive']      = 'Активна';
$string['statusinactive']    = 'Неактивна';

// Statistics page.
$string['statisticstitle']  = 'Статистика навантаження';
$string['selectcohort']     = 'Когорта';
$string['allcohorts']       = 'Усі когорти';
$string['datefrom']         = 'Від (тиждень – рік)';
$string['dateto']           = 'До (тиждень – рік)';
$string['week']             = 'Тиждень';
$string['course']           = 'Курс';
$string['hours']            = 'Години';
$string['student']          = 'Студент';
$string['totalhours']       = 'Загальна кількість годин';
$string['averagehours']     = 'Сер. год/тиж';
$string['weeksactive']      = 'Тижні з записами';
$string['exportcsv']              = 'Експортувати у CSV';
$string['exportchoice']           = 'Вибір типу експорту';
$string['exportquick']            = 'Коротко зведена таблиця';
$string['exportquick_desc']       = 'Окремий рядок для кожного студента: ім\'я, e-mail, відділ, установа, загальна кількість годин, активні тижні, середнє.';
$string['exportdetailed']         = 'Детально зведена таблиця';
$string['exportdetailed_desc']    = 'Окремий рядок для кожного курсу студента: містить усі курси та роль учасника в кожному з них.';
$string['exportfilenamedetailed'] = 'statystyka_navantazhennya_detalno';
$string['role']                   = 'Роль';
$string['coursehours']            = 'Години на курсі';
$string['filterresults']    = 'Застосувати фільтр';
$string['viewdetailed']     = 'Детальний перегляд';
$string['viewaggregated']   = 'Агрегований перегляд';
$string['nostatsfound']     = 'Даних за вибраними фільтрами не знайдено.';
$string['exportfilename']   = 'statystyka_navantazhennya';
$string['othercourses']     = '+ ще {$a} курс(ів)';
$string['avghrsperstudent'] = 'Середні години / студент';
$string['activestudents']   = 'Активні студенти';
$string['topstudents']      = 'Топ {$a} студентів за кількістю годин';
$string['viewstudent']      = 'Переглянути студента';
$string['allusers']         = 'Усі студенти';
$string['selectcohortfirst'] = 'Виберіть конкретну когорту вище, щоб знайти окремого студента.';
$string['viewingas']        = 'Статистика для {$a}';
$string['backtooverview']   = 'Повернутися до огляду';
$string['toviewstatistics'] = 'для перегляду статистики.';

// Enrollment mode – settings.
$string['coursemode']            = 'Режим управління курсами';
$string['coursemode_desc']       = 'Визначає, як курси відображаються для кожного студента. У режимі <b>Когорти</b> (за замовчуванням) курси управляються менеджером через вручну створені когорти. У режимі <b>Зарахування</b> кожен студент автоматично бачить курси, на які він зарахований; менеджер може додатково додавати, або виключати окремі курси для кожного студента.';
$string['coursemode_cohort']     = 'Когорти (управляється менеджером через вручну створені когорти)';
$string['coursemode_enrollment'] = 'Зарахування (кожен студент бачить курси, до яких він зарахований)';

// Enrollment mode – statistics page.
$string['enrollmentmode_notice'] = 'Режим зарахування активний. Статистика відображає дані для всіх студентів на основі їх зарахування на курси.';

// Enrollment mode – management dashboard notice.
$string['enrollmentmode_active_notice'] = 'Режим зарахування наразі активний.';
$string['enrollmentmodemanage']         = 'Управління курсами студентів';

// Enrollment mode – student list page.
$string['enrollmenttitle']         = 'Управління курсами студентів';
$string['enrollmenttitle_student'] = 'Курси для {$a}';
$string['backstudentlist']         = 'Повернутися до списку студентів';
$string['nostudentsfound']         = 'Студентів не знайдено.';
$string['enrolledcoursecount']     = '{$a} зарахований(их) курс(ів)';
$string['colenrolled']             = 'Зараховано';
$string['colexcluded']             = 'Виключено';
$string['coladded']                = 'Додано';
$string['coltotal']                = 'Показано всього';
$string['colenrolled_title']       = 'Курси, на які студент зарахований через Moodle';
$string['colexcluded_title']       = 'Зараховані курси, приховані менеджером для цього студента';
$string['coladded_title']          = 'Курси, додані менеджером вручну (не із зарахування)';
$string['coltotal_title']          = 'Загальна кількість курсів, що відображаються студенту (зараховані − виключені + додані)';
$string['managecourses']           = 'Управляти курсами';

// Enrollment mode – student detail page.
$string['enrolledcourses']          = 'Зараховані курси';
$string['manageradded']             = 'Курси, додані менеджером';
$string['noenrolledcourses']        = 'Цей студент не зарахований до жодного видимого курсу.';
$string['nomanagercourses']         = 'Менеджер не додав жодного курсу для цього студента.';
$string['statusheader']             = 'Статус';
$string['statusincluded']           = 'Активний';
$string['statusexcluded']           = 'Виключений';
$string['statusadded']              = 'Додано';
$string['excludecourse']            = 'Виключити';
$string['restorecourse']            = 'Відновити';
$string['addcourseforstudent']      = 'Додати курс';
$string['addcoursesforstudent']     = 'Додати курс(и)';
$string['allcoursesalreadymanaged'] = 'Усі курси в цій категорії вже відображаються для цього студента.';
$string['confirmexclude']           = 'Виключити «{$a}» для цього студента? Курс більше не відображатиметься в блоці навантаження.';
$string['confirmremoveadded']       = 'Видалити вручну доданий курс «{$a}» для цього студента?';
$string['courseexcluded']           = 'Курс виключено для цього студента.';
$string['courserestored']           = 'Курс відновлено для цього студента.';
$string['courseadded']              = 'Курс додано для цього студента.';
$string['coursesexcluded']          = '{$a} курс(ів) виключено для цього студента.';
$string['coursesrestored']          = '{$a} курс(ів) відновлено для цього студента.';
$string['excludeselected']          = 'Виключити вибрані';
$string['restoreselected']          = 'Відновити вибрані';
$string['confirmbulkexclude']       = 'Ви впевнені, що хочете виключити {$a} курс(ів) для цього студента?';
$string['confirmbulkremoveadded']   = 'Ви впевнені, що хочете видалити {$a} вручну доданих курс(ів) для цього студента?';

// Settings.
$string['maxhours']               = 'Максимальна кількість годин для одного курсу на тиждень';
$string['maxhours_desc']          = 'Максимальна кількість годин, яку студент може зареєструвати для одного курсу за тиждень.';
$string['hourstep']      = 'Крок збільшення (хвилини)';
$string['hourstep_desc']    = 'Скільки хвилин додається або віднімається при кожному натисканні + або −. Введіть ціле число не менше 1.';
$string['hourstep_invalid'] = 'Будь ласка, введіть ціле число не менше 1.';
$string['coursesperpage']         = 'Курсів на сторінці (блок студента)';
$string['coursesperpage_desc']    = 'Скільки курсів відображається на сторінці в блоці панелі студента. Встановіть 0, щоб вимкнути пагінацію та завжди показувати всі курси.';
$string['courseorder']            = 'Порядок відображення курсів';
$string['courseorder_desc']       = 'Керує порядком відображення курсів у блоці панелі студента. «Нещодавно відвідані» показує нещодавно відвідані курси першими. «Ручне сортування» використовує порядок, встановлений менеджером на сторінці «Призначені курси».';
$string['courseorder_sortorder']  = 'Ручне сортування';
$string['courseorder_recentaccess'] = 'Нещодавно відвідані';
$string['enablemoodlecohortimport']      = 'Дозволити імпорт із системних груп (когорт) Moodle';
$string['enablemoodlecohortimport_desc'] = 'Якщо увімкнено, на сторінці «Управління учасниками» з\'явиться додатковий розділ «Імпортувати із системних груп Moodle», що дозволяє менеджерам масово додавати користувачів із будь-якої системної групи Moodle до когорт плагіну навантаження.';

// Block pagination.
$string['pageprev'] = 'Назад';
$string['pagenext'] = 'Вперед';

// Event names (shown in Site Administration → Reports → Logs).
$string['event_cohort_created']    = 'Когорту навантаження створено';
$string['event_cohort_updated']    = 'Когорту навантаження оновлено';
$string['event_cohort_deleted']    = 'Когорту навантаження видалено';
$string['event_activation_updated'] = 'Активацію когорти навантаження оновлено';
$string['event_members_added']     = 'Учасників додано до когорти навантаження';
$string['event_members_removed']   = 'Учасників видалено з когорти навантаження';
$string['event_courses_assigned']  = 'Курси призначено когорті навантаження';
$string['event_courses_removed']   = 'Курси видалено з когорти навантаження';
$string['event_course_toggled']    = 'Статус активності курсу навантаження змінено';

// Role created on install.
$string['role_manager_name'] = 'Менеджер навантаження';
$string['role_manager_desc'] = 'Менеджер якості: керує когортами, учасниками, курсами та переглядає всю статистику навантаження.';

// Privacy.
$string['privacy:metadata:block_workload_entries']            = 'Зберігає години навантаження, які студент вносить по кожному курсу за тиждень.';
$string['privacy:metadata:block_workload_entries:userid']     = 'Ідентифікатор студента.';
$string['privacy:metadata:block_workload_entries:courseid']   = 'Ідентифікатор курсу.';
$string['privacy:metadata:block_workload_entries:weeknumber'] = 'Номер тижня за стандартом ISO.';
$string['privacy:metadata:block_workload_entries:year']       = 'Рік запису.';
$string['privacy:metadata:block_workload_entries:hours']      = 'Кількість зареєстрованих годин.';
$string['privacy:metadata:block_workload_members']            = 'Зберігає членство в когорті, пов\'язуючи студентів із когортою їхньої навчальної програми.';
$string['privacy:metadata:block_workload_members:userid']     = 'Ідентифікатор студента.';
$string['privacy:metadata:block_workload_members:cohortid']   = 'Ідентифікатор когорти.';
