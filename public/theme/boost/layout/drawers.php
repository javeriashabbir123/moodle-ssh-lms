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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $selectmenu = new \core\output\select_menu(
            'tertiarynavigation',
            $overflowdata->urls,
            $overflowdata->selected,
        );
        $selectmenu->set_label($overflowdata->label, $overflowdata->labelattributes);
        $overflow = $selectmenu->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$coursefullname = ($PAGE->course?->fullname) ? format_string(
    $PAGE->course->fullname,
    true,
    ['context' => context_course::instance($PAGE->course->id), 'escape' => false],
) : '';
$courseurl = $PAGE->course ? new \core\url('/course/view.php', ['id' => $PAGE->course->id]) : null;

// ==========================================
// ROLE DETECTION & FORM SUBMISSIONS
// ==========================================
global $USER, $DB;
$is_admin = is_siteadmin();
$is_instructor = false;

if ($is_admin && isset($_POST['action']) && $_POST['action'] === 'add_instructor') {
    $email = clean_param($_POST['email'], PARAM_EMAIL);
    $firstname = clean_param($_POST['firstname'], PARAM_TEXT);
    $lastname = clean_param($_POST['lastname'], PARAM_TEXT);

    if (!empty($email)) {
        if (!$DB->record_exists('user', ['email' => $email])) {
            $userobj = new stdClass();
            $userobj->username = strtolower(clean_param($firstname . '_' . rand(100, 999), PARAM_USERNAME));
            $userobj->email = $email;
            $userobj->firstname = !empty($firstname) ? $firstname : 'New';
            $userobj->lastname = !empty($lastname) ? $lastname : 'Instructor';
            $userobj->confirmed = 1;
            $userobj->mnethostid = $CFG->mnet_localhost_id;
            $userobj->auth = 'manual';
            $userobj->timecreated = time();
            $userobj->password = hash_internal_user_password('MoodlePassword123!');

            $newuserid = user_create_user($userobj, false, false);

            if ($newuserid) {
                $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', IGNORE_MISSING);
                if (!$teacherrole) {
                    $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', IGNORE_MISSING);
                }
                if ($teacherclass = $DB->get_record('context', ['contextlevel' => CONTEXT_SYSTEM], '*', IGNORE_MISSING)) {
                    role_assign($teacherrole->id, $newuserid, $teacherclass->id);
                }
            }
        }
    }
    redirect(new \core\url('/my/'));
}

if ($is_admin && isset($_POST['action']) && $_POST['action'] === 'add_class') {
    $classname = clean_param($_POST['classname'], PARAM_TEXT);
    $coursetitle = clean_param($_POST['coursetitle'], PARAM_TEXT);
    $instructorname = clean_param($_POST['instructor'], PARAM_TEXT);
    $startdate = strtotime($_POST['startdate']);
    $enddate = strtotime($_POST['enddate']);

    if (!empty($classname) && !empty($coursetitle)) {
        $course_record = $DB->get_record('course', ['fullname' => $coursetitle]);
        if (!$course_record) {
            $coursedata = new stdClass();
            $coursedata->fullname = $coursetitle;
            $coursedata->shortname = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $coursetitle), 0, 10)) . rand(10,99);
            $coursedata->category = 1;
            $coursedata->format = 'topics';
            $coursedata->startdate = $startdate ? $startdate : time();
            $coursedata->enddate = $enddate ? $enddate : (time() + 15768000);
            $coursedata->timecreated = time();
            $coursedata->timemodified = time();
            $course_record = create_course($coursedata);
        }
    }
    redirect(new \core\url('/my/'));
}

if (isloggedin() && !$is_admin) {
    $teacher_check = $DB->get_record_sql("
        SELECT ra.id 
        FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        WHERE ra.userid = ? AND (r.shortname = 'teacher' OR r.shortname = 'editingteacher')
        LIMIT 1
    ", [$USER->id]);
    if ($teacher_check) {
        $is_instructor = true;
    }
}

// ==========================================
// REAL-TIME DATABASE FETCHING
// ==========================================
$db_instructors = [];
$db_students = [];
$db_courses = [];

if ($is_admin) {
    $raw_instructors = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lastaccess, u.lastlogin, u.timecreated 
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {role} r ON r.id = ra.roleid
        WHERE u.deleted = 0 AND u.confirmed = 1 AND (r.shortname = 'teacher' OR r.shortname = 'editingteacher')
        ORDER BY u.id DESC LIMIT 50
    ");
    if (empty($raw_instructors)) {
        $raw_instructors = $DB->get_records_sql("SELECT id, firstname, lastname, email, lastaccess, lastlogin, timecreated FROM {user} WHERE deleted = 0 AND confirmed = 1 ORDER BY id DESC LIMIT 10");
    }

    foreach ($raw_instructors as $inst) {
        $instructor_courses = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.fullname, c.shortname, c.startdate, c.enddate 
            FROM {course} c
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id
            WHERE ra.userid = ? AND c.id <> 1
        ", [$inst->id]);
        
        $coursenames = [];
        $inst->courses_list = [];
        foreach ($instructor_courses as $ic) {
            $coursenames[] = $ic->fullname;
            $inst->courses_list[] = [
                'id' => $ic->id,
                'fullname' => $ic->fullname,
                'shortname' => $ic->shortname,
                'startdate' => $ic->startdate ? date('m/d/y', $ic->startdate) : '04/30/26',
                'enddate' => $ic->enddate ? date('m/d/y', $ic->enddate) : '12/31/26',
                'url' => $CFG->wwwroot . '/course/view.php?id=' . $ic->id
            ];
        }
        $inst->coursestaught = !empty($coursenames) ? implode(', ', $coursenames) : 'Assigned General Course';
        if (empty($inst->courses_list)) {
            $inst->courses_list[] = [
                'id' => 1,
                'fullname' => 'Certified Ethical Hacker CEHv13 (312-50)',
                'shortname' => 'CEH',
                'startdate' => '04/30/26',
                'enddate' => '12/31/26',
                'url' => $CFG->wwwroot . '/course/view.php'
            ];
        }
        $db_instructors[] = $inst;
    }

    $raw_students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lastaccess, u.timecreated, u.lastlogin 
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {role} r ON r.id = ra.roleid
        WHERE u.deleted = 0 AND u.confirmed = 1 AND r.shortname = 'student'
        ORDER BY u.id DESC LIMIT 50
    ");
    if (empty($raw_students)) {
        $raw_students = $DB->get_records_sql("SELECT id, firstname, lastname, email, lastaccess, timecreated, lastlogin FROM {user} WHERE deleted = 0 AND confirmed = 1 ORDER BY id DESC LIMIT 10");
    }

    foreach ($raw_students as $st) {
        $student_courses = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.fullname, c.shortname, c.startdate, c.enddate 
            FROM {course} c
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id
            WHERE ra.userid = ? AND c.id <> 1
        ", [$st->id]);

        $st->courses_list = [];
        foreach ($student_courses as $sc) {
            $st->courses_list[] = [
                'id' => $sc->id,
                'fullname' => $sc->fullname,
                'shortname' => $sc->shortname,
                'startdate' => $sc->startdate ? date('m/d/y', $sc->startdate) : '05/07/26',
                'enddate' => $sc->enddate ? date('m/d/y', $sc->enddate) : '06/30/26',
                'url' => $CFG->wwwroot . '/course/view.php?id=' . $sc->id
            ];
        }
        if (empty($st->courses_list)) {
            $st->courses_list[] = [
                'id' => 1,
                'fullname' => 'Generative AI Fundamentals CCS-100',
                'shortname' => 'CCS-100',
                'startdate' => '05/07/26',
                'enddate' => '06/30/26',
                'url' => $CFG->wwwroot . '/course/view.php'
            ];
        }
        $db_students[] = $st;
    }

    $raw_courses = $DB->get_records_sql("SELECT id, fullname, shortname, timecreated, startdate, enddate FROM {course} WHERE id <> 1 ORDER BY id DESC LIMIT 50");
    foreach ($raw_courses as $c) {
        $enrolled_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT u.id) 
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            WHERE ctx.contextlevel = 50 AND ctx.instanceid = ? AND r.shortname = 'student'
        ", [$c->id]);

        $c->enrolled_count = $enrolled_count > 0 ? $enrolled_count : 0;
        $c->url = $CFG->wwwroot . '/course/view.php?id=' . $c->id;
        $db_courses[] = $c;
    }
} else if ($is_instructor) {
    $raw_instructor_courses = $DB->get_records_sql("
        SELECT DISTINCT c.id, c.fullname, c.shortname, c.startdate, c.enddate 
        FROM {course} c
        JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
        JOIN {role_assignments} ra ON ra.contextid = ctx.id
        WHERE ra.userid = ? AND c.id <> 1
    ", [$USER->id]);

    if (empty($raw_instructor_courses)) {
        $raw_instructor_courses = $DB->get_records_sql("SELECT id, fullname, shortname, startdate, enddate FROM {course} WHERE id <> 1 LIMIT 5");
    }

    foreach ($raw_instructor_courses as $ic) {
        $ic->url = $CFG->wwwroot . '/course/view.php?id=' . $ic->id;
        $ic->startdate_formatted = $ic->startdate ? date('m/d/y', $ic->startdate) : '05/07/26';
        $ic->enddate_formatted = $ic->enddate ? date('m/d/y', $ic->enddate) : '06/30/26';
        $db_courses[] = $ic;
    }

    $raw_students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lastaccess, u.timecreated, u.lastlogin 
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {role} r ON r.id = ra.roleid
        WHERE u.deleted = 0 AND u.confirmed = 1 AND r.shortname = 'student'
        ORDER BY u.id DESC LIMIT 50
    ");

    foreach ($raw_students as $st) {
        $st_courses = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.fullname, c.shortname, c.startdate, c.enddate 
            FROM {course} c
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id
            WHERE ra.userid = ? AND c.id <> 1
        ", [$st->id]);

        $st->courses_list = [];
        foreach ($st_courses as $sc) {
            $st->courses_list[] = [
                'id' => $sc->id,
                'fullname' => $sc->fullname,
                'shortname' => $sc->shortname,
                'startdate' => $sc->startdate ? date('m/d/y', $sc->startdate) : '05/07/26',
                'enddate' => $sc->enddate ? date('m/d/y', $sc->enddate) : '06/30/26',
                'url' => $CFG->wwwroot . '/course/view.php?id=' . $sc->id
            ];
        }
        if (empty($st->courses_list)) {
            $st->courses_list[] = [
                'id' => 1,
                'fullname' => 'Generative AI Fundamentals CCS-100',
                'shortname' => 'CCS-100',
                'startdate' => '05/07/26',
                'enddate' => '06/30/26',
                'url' => $CFG->wwwroot . '/course/view.php'
            ];
        }
        $st->lastlogin_fmt = !empty($st->lastlogin) ? date('m/d/y', $st->lastlogin) : '11/05/26';
        $st->lastaccess_fmt = !empty($st->lastaccess) ? date('m/d/y', $st->lastaccess) : '11/06/26';
        $db_students[] = $st;
    }
}

// ==========================================
// PEARSON SIDEBAR & VIEWS HTML (ONLY ON DASHBOARD)
// ==========================================
$pearson_sidebar_html = '';

if (($is_admin || $is_instructor) && $PAGE->pagetype === 'my-index') {

    if ($is_admin) {
        // ==========================================
        // ADMIN SIDEBAR HTML
        // ==========================================
        $pearson_sidebar_html = '
        <style>
            body { padding-left: 270px !important; background-color: #f7fafc !important; transition: padding-left 0.3s ease; }
            body.sidebar-closed { padding-left: 0 !important; }
            
            /* Block drawer ko completely hide karne ke liye */
            .drawer-right-toggle, div.drawer-right-toggle, button[data-action="closedrawer"][aria-controls="block-region-side-pre"] {
                display: none !important;
            }
            body {
                padding-right: 0 !important;
            }

            .pearson-global-sidebar { position: fixed !important; top: 60px !important; left: 0 !important; width: 270px !important; height: calc(100vh - 60px) !important; background-color: #124776 !important; color: #ffffff !important; border-right: 1px solid #0f385c !important; z-index: 1000 !important; overflow-y: auto !important; padding-top: 10px !important; box-shadow: 2px 0 10px rgba(0,0,0,0.2) !important; font-family: \'Open Sans\', Arial, sans-serif !important; transition: transform 0.3s ease; }
            .pearson-global-sidebar.closed { transform: translateX(-100%); }
            .pearson-sidebar-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }
            .pearson-sidebar-title { font-size: 14px; font-weight: 700; color: #ffffff; letter-spacing: 0.5px; }
            .pearson-close-btn { background: transparent !important; border: none !important; color: #ffffff !important; font-size: 18px !important; cursor: pointer !important; padding: 2px 8px !important; border-radius: 4px; }
            .pearson-close-btn:hover { background: rgba(255,255,255,0.15) !important; }
            .pearson-open-btn { position: fixed !important; top: 70px !important; left: 15px !important; z-index: 1100 !important; background: #124776 !important; color: white !important; border: none !important; padding: 8px 12px !important; border-radius: 4px !important; cursor: pointer !important; display: none; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
            .pearson-open-btn.visible { display: block !important; }
            .pearson-menu { list-style: none !important; padding: 0 !important; margin: 0 !important; }
            .pearson-menu li a { display: flex !important; align-items: center !important; padding: 12px 24px !important; color: #ffffff !important; text-decoration: none !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.2s ease !important; cursor: pointer !important; }
            .pearson-menu li a svg { width: 18px !important; height: 18px !important; min-width: 18px !important; min-height: 18px !important; margin-right: 14px !important; fill: #ffffff !important; flex-shrink: 0 !important; }
            .pearson-menu li a:hover, .pearson-menu li.active a { background-color: #0f385c !important; color: #ffffff !important; }
            .sidebar-heading { font-size: 11px !important; font-weight: 700 !important; color: #cbd5e1 !important; padding: 24px 24px 8px 24px !important; letter-spacing: 0.5px !important; }

            .pearson-dynamic-view { display: none; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin: 40px 20px 25px 20px !important; width: auto !important; border: 1px solid #e2e8f0; box-sizing: border-box; overflow-x: auto; }
            .pearson-dynamic-view.active-view { display: block !important; }
            .pearson-dynamic-view h2 { font-size: 24px; font-weight: 700; color: #1a202c; margin-bottom: 0; }
            
            .view-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .pearson-main-btn { background: #124776; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; font-family: \'Open Sans\', Arial, sans-serif; }
            .pearson-main-btn:hover { background: #0f385c; }

            .p-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; white-space: nowrap; }
            .p-table th { background: #f8fafc; color: #4a5568; text-align: left; padding: 12px 14px; font-weight: 700; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 11px; }
            .p-table td { padding: 12px 14px; border-bottom: 1px solid #edf2f7; color: #2d3748; }
            .p-table td a { color: #124776 !important; text-decoration: none !important; font-weight: 600; cursor: pointer; }
            .p-table td a:hover { text-decoration: underline !important; }
            .status-badge { background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }
            .status-badge.in-progress { background: #e9d8fd; color: #44337a; }
            .p-filter-box { display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; background: #fcfcfc; padding: 20px; border-radius: 6px; border: 1px solid #e2e8f0; }
            .radio-group { display: flex; gap: 20px; font-size: 13px; font-weight: 600; color: #2d3748; align-items: center; }
            .radio-group label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
            .p-filter-row { display: flex; gap: 15px; }
            .p-input, .p-select { padding: 9px 12px; border: 1px solid #cbd5e0; border-radius: 4px; width: 100%; font-size: 13px; background: #ffffff; }
            .filter-btn-group { display: flex; gap: 5px; margin-bottom: 15px; }
            .filter-pill { padding: 6px 16px; border: 1px solid #cbd5e0; border-radius: 20px; background: #fff; font-size: 12px; font-weight: 600; cursor: pointer; color: #4a5568; }
            .filter-pill.active { background: #124776; color: #fff; border-color: #124776; }

            .action-dropdown-container { position: relative; display: inline-block; }
            .action-dots-btn { background: #e2e8f0; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-weight: 700; color: #4a5568; display: flex; align-items: center; justify-content: center; }
            .action-dots-btn:hover { background: #cbd5e0; }
            .action-menu-popup { display: none; position: absolute; left: 35px; top: 0; background: #ffffff; border: 1px solid #cbd5e0; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100; min-width: 150px; padding: 6px 0; }
            .action-menu-popup.show { display: block; }
            .action-menu-item { padding: 8px 16px; font-size: 12px; color: #2d3748; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap; text-decoration: none !important; }
            .action-menu-item:hover { background: #f7fafc; color: #124776; }

            .pearson-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.4); z-index: 2000; justify-content: center; align-items: center; }
            .pearson-modal-overlay.show { display: flex; }
            .pearson-modal { background: #ffffff; width: 480px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); padding: 25px; position: relative; font-family: \'Open Sans\', Arial, sans-serif; }
            .pearson-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .pearson-modal-title { font-size: 18px; font-weight: 700; color: #1a202c; }
            .pearson-modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #718096; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0; }
            .pearson-modal-close:hover { background: #f7fafc; color: #1a202c; }
            
            .modal-input-group { margin-bottom: 16px; }
            .modal-input-group label { display: block; font-size: 12px; font-weight: 600; color: #4a5568; margin-bottom: 6px; }
            .modal-input-group input, .modal-input-group select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; box-sizing: border-box; background: #fff; }
            .modal-row-2 { display: flex; gap: 15px; }
            .modal-toggle-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; font-size: 13px; font-weight: 600; color: #2d3748; margin-top: 20px; }
            .pearson-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; align-items: center; }
            .pearson-btn-secondary { background: transparent; border: none; color: #718096; font-weight: 600; cursor: pointer; font-size: 13px; padding: 8px 12px; }
            .pearson-btn-primary { background: #124776; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; }
            .pearson-btn-primary:hover { background: #0f385c; }

            .switch { position: relative; display: inline-block; width: 40px; height: 22px; }
            .switch input { opacity: 0; width: 0; height: 0; }
            .slider-switch { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e0; transition: .3s; border-radius: 22px; }
            .slider-switch:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
            input:checked + .slider-switch { background-color: #124776; }
            input:checked + .slider-switch:before { transform: translateX(18px); }

            .instructor-profile-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin-top: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
            .instructor-profile-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
            .instructor-profile-name { font-size: 20px; font-weight: 700; color: #1a202c; }
        </style>

        <button id="sidebarOpenBtn" class="pearson-open-btn" onclick="togglePearsonSidebar()" title="Open Menu">☰</button>
        <div class="pearson-global-sidebar" id="pearsonSidebar">
            <div class="pearson-sidebar-header">
                <span class="pearson-sidebar-title">SSH ADMIN</span>
                <button class="pearson-close-btn" onclick="togglePearsonSidebar()" title="Close Sidebar">✕</button>
            </div>
            <ul class="pearson-menu">
                <li class="nav-item active" onclick="switchPearsonTab(\'view-home\', this)"><a><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>Home</a></li>
                <li class="nav-item" onclick="switchPearsonTab(\'view-license\', this)"><a><svg viewBox="0 0 24 24"><path d="M4 10h12v2H4zm0-4h12v2H4zm0 8h8v2H4zm10 0l6 4-6 4z"/></svg>License inventory</a></li>
                <li class="nav-item" onclick="switchPearsonTab(\'view-instructors\', this)"><a><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>Instructors</a></li>
                <li class="nav-item" onclick="switchPearsonTab(\'view-students\', this)"><a><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>Students</a></li>
                <li class="nav-item" onclick="switchPearsonTab(\'view-courses\', this)"><a><svg viewBox="0 0 24 24"><path d="M4 6h18v2H4zm0 5h18v2H4zm0 5h18v2H4z"/></svg>Courses</a></li>
                <li class="nav-item" onclick="switchPearsonTab(\'view-classes\', this)"><a><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/></svg>Classes</a></li>
            </ul>
            <div class="sidebar-heading">HELP AND SUPPORT</div>
            <ul class="pearson-menu">
                <li><a href="https://www.pearsonvue.com" target="_blank"><svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7z"/></svg>Knowledge Center ↗</a></li>
            </ul>
        </div>

        <div id="addInstructorModal" class="pearson-modal-overlay">
            <div class="pearson-modal">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_instructor">
                    <div class="pearson-modal-header">
                        <span class="pearson-modal-title">Add new instructor</span>
                        <button type="button" class="pearson-modal-close" onclick="closeAddInstructorModal()">✕</button>
                    </div>
                    <div class="modal-input-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="newInstEmail" placeholder="Email *" required>
                    </div>
                    <div class="modal-input-group">
                        <label>First name</label>
                        <input type="text" name="firstname" id="newInstFirstName" placeholder="First name">
                    </div>
                    <div class="modal-input-group">
                        <label>Last name</label>
                        <input type="text" name="lastname" id="newInstLastName" placeholder="Last name">
                    </div>
                    <div class="modal-toggle-row">
                        <span>Has enrollment permission</span>
                        <label class="switch">
                            <input type="checkbox" id="newInstEnrollmentToggle" checked>
                            <span class="slider-switch"></span>
                        </label>
                    </div>
                    <div class="pearson-modal-footer">
                        <button type="button" class="pearson-btn-secondary" onclick="closeAddInstructorModal()">Cancel</button>
                        <button type="submit" class="pearson-btn-primary">Add instructor</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="addClassModal" class="pearson-modal-overlay">
            <div class="pearson-modal" style="width: 520px;">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_class">
                    <input type="hidden" name="coursetitle" id="addClassCourseTitleInput">
                    <div class="pearson-modal-header">
                        <div>
                            <span class="pearson-modal-title">Add Class</span>
                            <div id="addClassCourseTitleDisplay" style="font-size: 12px; color: #718096; margin-top: 4px;">Course Title</div>
                        </div>
                        <button type="button" class="pearson-modal-close" onclick="closeAddClassModal()">✕</button>
                    </div>
                    <div class="modal-input-group">
                        <label>Class name / Title *</label>
                        <input type="text" name="classname" id="addClassNameInput" placeholder="Class name / Title *" required>
                    </div>
                    <div class="modal-input-group">
                        <label>Instructor</label>
                        <select name="instructor" id="addClassInstructorSelect">
                            <option value="">Instructor</option>';
                            foreach ($db_instructors as $inst) {
                                $pearson_sidebar_html .= '<option value="' . s($inst->firstname . ' ' . $inst->lastname) . '">' . s($inst->firstname . ' ' . $inst->lastname) . '</option>';
                            }
                        $pearson_sidebar_html .= '</select>
                    </div>
                    <div class="modal-row-2">
                        <div class="modal-input-group" style="flex:1;">
                            <label>Start date *</label>
                            <input type="date" name="startdate" id="addClassStartDate" value="' . date('Y-m-d') . '">
                        </div>
                        <div class="modal-input-group" style="flex:1;">
                            <label>End date</label>
                            <input type="date" name="enddate" id="addClassEndDate" value="' . date('Y-m-d', strtotime('+6 months')) . '">
                        </div>
                    </div>
                    <div class="pearson-modal-footer">
                        <button type="button" class="pearson-btn-secondary" onclick="closeAddClassModal()">Cancel</button>
                        <button type="submit" class="pearson-btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="editInstructorModal" class="pearson-modal-overlay">
            <div class="pearson-modal">
                <div class="pearson-modal-header">
                    <span class="pearson-modal-title" id="modalEditTitle">Edit Instructor</span>
                    <button class="pearson-modal-close" onclick="closeEditInstructorModal()">✕</button>
                </div>
                <div class="modal-toggle-row">
                    <span>Has enrollment permission</span>
                    <label class="switch">
                        <input type="checkbox" id="modalEnrollmentToggle" checked>
                        <span class="slider-switch"></span>
                    </label>
                </div>
                <div class="modal-toggle-row">
                    <span id="modalStatusLabel">Instructor status: Active</span>
                    <label class="switch">
                        <input type="checkbox" id="modalStatusToggle" checked onchange="updateModalStatusText(this)">
                        <span class="slider-switch"></span>
                    </label>
                </div>
                <div class="pearson-modal-footer">
                    <button class="pearson-btn-secondary" onclick="closeEditInstructorModal()">Cancel</button>
                    <button class="pearson-btn-primary" onclick="saveInstructorChanges()">Edit instructor</button>
                </div>
            </div>
        </div>

        <div id="pearsonDynamicContainer">
            <div id="view-license" class="pearson-dynamic-view">
                <h2>License pool inventory</h2>
                <table class="p-table"><thead><tr><th>LICENSE POOL</th><th>PURCHASED</th><th>ENROLLED</th></tr></thead><tbody><tr><td>Pearson Skilling Suite</td><td>8</td><td>8</td></tr></tbody></table>
            </div>

            <div id="view-instructors" class="pearson-dynamic-view">
                <div class="view-header-flex">
                    <h2>Instructors</h2>
                    <button class="pearson-main-btn" onclick="openAddInstructorModal()">Add new instructor</button>
                </div>
                <div class="p-filter-box">
                    <div class="radio-group">
                        <label><input type="radio" name="adminInstSearchType" value="0" checked onchange="updateAdminInstPlaceholder()"> Instructor name</label>
                        <label><input type="radio" name="adminInstSearchType" value="2" onchange="updateAdminInstPlaceholder()"> Instructor email</label>
                    </div>
                    <div class="p-filter-row">
                        <input type="text" id="adminInstSearchInput" class="p-input" placeholder="Instructor Name" onkeyup="filterAdminInstructors()">
                    </div>
                    <div class="p-filter-row">
                        <select class="p-select" id="adminInstCourseFilter" onchange="filterAdminInstructors()">
                            <option value="">Course</option>';
                            foreach ($db_courses as $c) {
                                $pearson_sidebar_html .= '<option value="' . s($c->fullname) . '">' . s($c->fullname) . '</option>';
                            }
                        $pearson_sidebar_html .= '</select>
                    </div>
                </div>
                <div class="filter-btn-group" id="instructorStatusFilterGroup">
                    <button class="filter-pill" onclick="filterInstructorStatus(\'all\', this)">All</button>
                    <button class="filter-pill active" onclick="filterInstructorStatus(\'active\', this)">Active</button>
                    <button class="filter-pill" onclick="filterInstructorStatus(\'inactive\', this)">Inactive</button>
                </div>
                <table class="p-table" id="instructorTable">
                    <thead><tr><th></th><th>INSTRUCTOR</th><th>LAST SEEN</th><th>EMAIL</th><th>COURSES TAUGHT</th><th>STATUS</th></tr></thead>
                    <tbody>';
                    foreach ($db_instructors as $u) {
                        $lastseen = $u->lastaccess ? date('m/d/y H:i', $u->lastaccess) : 'Never';
                        $fullname = s($u->firstname . ' ' . $u->lastname);
                        $email = s($u->email);
                        $since = $u->timecreated ? date('m/d/y', $u->timecreated) : '01/02/26';
                        $lastonline = $u->lastlogin ? date('m/d/y', $u->lastlogin) : '10/30/26';
                        
                        $pearson_sidebar_html .= '<tr data-status="active" 
                            data-name="' . $fullname . '" 
                            data-email="' . $email . '" 
                            data-since="' . $since . '" 
                            data-lastonline="' . $lastonline . '" 
                            data-courses=\'' . htmlspecialchars(json_encode($u->courses_list), ENT_QUOTES, 'UTF-8') . '\'>
                            <td>
                                <div class="action-dropdown-container">
                                    <button class="action-dots-btn" onclick="toggleActionMenu(event, this)">...</button>
                                    <div class="action-menu-popup">
                                        <div class="action-menu-item" onclick="openEditInstructorModal(this)"><svg viewBox="0 0 24 24" width="14" height="14" fill="#124776"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Edit Instructor</div>
                                    </div>
                                </div>
                            </td>
                            <td><a onclick="openInstructorProfile(this)">' . $fullname . '</a></td>
                            <td>' . $lastseen . '</td>
                            <td><a href="mailto:' . $email . '">' . $email . '</a></td>
                            <td>' . s($u->coursestaught) . '</td>
                            <td><span class="status-badge">Active</span></td>
                        </tr>';
                    }
                    $pearson_sidebar_html .= '</tbody></table>
            </div>

            <div id="view-instructor-profile" class="pearson-dynamic-view">
                <div style="margin-bottom:15px;">
                    <a onclick="switchPearsonTab(\'view-instructors\', document.querySelectorAll(\'.pearson-menu .nav-item\')[2])" style="cursor:pointer; color:#124776; font-weight:600; text-decoration:none;">← Back to Instructors</a>
                </div>
                <h2 id="profInstName" style="margin-bottom:15px;">Instructor Name</h2>
                
                <div style="display:flex; gap:20px; border-bottom:1px solid #e2e8f0; margin-bottom:20px; font-weight:600; font-size:14px;">
                    <span id="tabBtnProfile" onclick="switchInstSubTab(\'profile\')" style="padding-bottom:8px; border-bottom:2px solid #124776; color:#124776; cursor:pointer;">Profile</span>
                    <span id="tabBtnClasses" onclick="switchInstSubTab(\'classes\')" style="padding-bottom:8px; color:#718096; cursor:pointer;">Classes</span>
                    <span id="tabBtnAvailability" onclick="switchInstSubTab(\'availability\')" style="padding-bottom:8px; color:#718096; cursor:pointer;">Availability</span>
                </div>

                <div id="instSubViewProfile" class="inst-sub-view">
                    <div class="instructor-profile-card">
                        <div class="instructor-profile-header">
                            <span class="instructor-profile-name" id="profInstTitle">Instructor Name</span>
                            <span class="status-badge">Active</span>
                        </div>
                        <div style="margin-bottom:25px;">
                            <a id="profInstEmail" href="#" style="color:#124776; text-decoration:none; font-weight:500;">email@ssh.com.pk</a>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-top:1px solid #edf2f7; padding-top:20px;">
                            <div>
                                <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:8px;">Courses Taught</p>
                                <ul id="profInstCoursesList" style="padding-left:20px; color:#2d3748; font-size:13px; line-height:1.6;"></ul>
                            </div>
                            <div style="display:flex; gap:40px; text-align:right;">
                                <div>
                                    <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:4px;">Instructor Since</p>
                                    <p id="profInstSince" style="font-size:13px; font-weight:600; color:#2d3748;">01/02/26</p>
                                </div>
                                <div>
                                    <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:4px;">Last Online</p>
                                    <p id="profInstLastOnline" style="font-size:13px; font-weight:600; color:#2d3748;">10/30/26</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="instSubViewClasses" class="inst-sub-view" style="display:none;">
                    <table class="p-table">
                        <thead>
                            <tr>
                                <th>CLASS</th>
                                <th>COURSE</th>
                                <th>START - END DATE</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody id="profInstClassesTableBody"></tbody>
                    </table>
                    <div style="margin-top:15px; font-size:13px; color:#4a5568;" id="profInstClassesCount">Showing 1 - 4 of 4.</div>
                </div>

                <div id="instSubViewAvailability" class="inst-sub-view" style="display:none;">
                    <div class="instructor-profile-card">
                        <p style="font-weight:600; color:#2d3748;">Instructor Availability Schedule is active for all assigned slots.</p>
                    </div>
                </div>
            </div>

            <div id="view-students" class="pearson-dynamic-view">
                <h2>Students</h2>
                <div class="p-filter-box">
                    <div class="radio-group">
                        <label><input type="radio" name="adminStudentSearchType" value="0" checked onchange="updateAdminPlaceholder()"> Student name</label>
                        <label><input type="radio" name="adminStudentSearchType" value="1" onchange="updateAdminPlaceholder()"> Student email</label>
                    </div>
                    <div class="p-filter-row">
                        <input type="text" id="adminStudentSearchInput" class="p-input" placeholder="Student Name" onkeyup="filterAdminStudents()">
                    </div>
                    <div class="p-filter-row">
                        <select class="p-select" id="adminCourseFilter" onchange="filterAdminStudents()"><option value="">Course</option>';
                        foreach ($db_courses as $c) { $pearson_sidebar_html .= '<option value="' . s($c->fullname) . '">' . s($c->fullname) . '</option>'; }
                        $pearson_sidebar_html .= '</select>
                        <select class="p-select" id="adminClassFilter" onchange="filterAdminStudents()"><option value="">Class</option><option value="Standard Class">Standard Class</option></select>
                        <select class="p-select"><option value="">Exam ready</option></select>
                    </div>
                </div>
                <table class="p-table" id="studentTable">
                    <thead>
                        <tr>
                            <th>STUDENT</th>
                            <th>EMAIL</th>
                            <th>LAST LOGIN</th>
                            <th>LAST ACCESS</th>
                            <th>INSTITUTION</th>
                            <th>STATUS</th>
                            <th>CLASS NAME</th>
                            <th>START - END DATE</th>
                            <th>CURRENT GRADE</th>
                            <th>EXAM READY</th>
                            <th>LAST EXAM DATE</th>
                            <th>EPP DAYS LEFT</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach ($db_students as $s) {
                        $llogin = $s->lastlogin ? date('m/d/y', $s->lastlogin) : '-';
                        $laccess = $s->lastaccess ? date('m/d/y', $s->lastaccess) : '-';
                        $semail = s($s->email);
                        $stname = s($s->firstname . ' ' . $s->lastname);
                        $s_since = !empty($s->timecreated) ? date('m/d/y', $s->timecreated) : '08/04/26';
                        $s_lastonline = !empty($s->lastaccess) ? date('m/d/y', $s->lastaccess) : '11/06/26';
                        $pearson_sidebar_html .= '<tr data-name="' . $stname . '" data-email="' . $semail . '" data-since="' . $s_since . '" data-lastonline="' . $s_lastonline . '" data-courses=\'' . htmlspecialchars(json_encode($s->courses_list), ENT_QUOTES, 'UTF-8') . '\'>
                            <td><a onclick="openAdminStudentProfile(this)">' . $stname . '</a></td>
                            <td><a href="mailto:' . $semail . '">' . $semail . '</a></td>
                            <td>' . $llogin . '</td>
                            <td>' . $laccess . '</td>
                            <td>SSH Institute</td>
                            <td><span class="status-badge">Active</span></td>
                            <td>Standard Class</td>
                            <td>01/01/26 - 12/31/26</td>
                            <td>85%</td>
                            <td>In Progress</td>
                            <td>-</td>
                            <td>30 Days</td>
                        </tr>';
                    }
                    $pearson_sidebar_html .= '</tbody></table>
            </div>

            <div id="view-admin-student-profile" class="pearson-dynamic-view">
                <div style="margin-bottom:15px;">
                    <a onclick="switchPearsonTab(\'view-students\', document.querySelectorAll(\'.pearson-menu .nav-item\')[3])" style="cursor:pointer; color:#124776; font-weight:600; text-decoration:none;">← Back to Students</a>
                </div>
                <h2 id="adminProfStudentName" style="margin-bottom:15px;">Student Name</h2>

                <div style="display:flex; gap:20px; border-bottom:1px solid #e2e8f0; margin-bottom:20px; font-weight:600; font-size:14px;">
                    <span id="tabBtnAdminStudentClasses" onclick="switchAdminStudentSubTab(\'classes\')" style="padding-bottom:8px; border-bottom:2px solid #124776; color:#124776; cursor:pointer;">Classes and performance</span>
                    <span id="tabBtnAdminStudentProfile" onclick="switchAdminStudentSubTab(\'profile\')" style="padding-bottom:8px; color:#718096; cursor:pointer;">Profile</span>
                </div>

                <div id="adminStudentSubViewClasses" class="admin-student-sub-view">
                    <table class="p-table">
                        <thead>
                            <tr>
                                <th>CLASS</th>
                                <th>COURSE</th>
                                <th>START - END DATE</th>
                                <th>STATUS</th>
                                <th>CURRENT GRADE</th>
                            </tr>
                        </thead>
                        <tbody id="adminStudentProfileClassesBody"></tbody>
                    </table>
                </div>

                <div id="adminStudentSubViewProfile" class="admin-student-sub-view" style="display:none;">
                    <div class="instructor-profile-card">
                        <div class="instructor-profile-header">
                            <div style="background:#edf2f7; width:45px; height:45px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#4a5568;">👤</div>
                            <div>
                                <div style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase;">Student</div>
                                <div class="instructor-profile-name" id="adminProfStudentCardName">Student Name</div>
                            </div>
                        </div>
                        <div style="margin-bottom:25px;">
                            <a id="adminProfStudentCardEmail" href="#" style="color:#124776; text-decoration:none; font-weight:500;">email@domain.com</a>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-top:1px solid #edf2f7; padding-top:20px;">
                            <div>
                                <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:8px;">Recent Courses Taken</p>
                                <ul id="adminProfStudentCoursesList" style="padding-left:20px; color:#124776; font-size:13px; line-height:1.6; font-weight:600;"></ul>
                            </div>
                            <div style="display:flex; gap:40px; text-align:right;">
                                <div>
                                    <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:4px;">Student Since</p>
                                    <p id="adminProfStudentSince" style="font-size:13px; font-weight:600; color:#2d3748;">08/04/26</p>
                                </div>
                                <div>
                                    <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:4px;">Last Online</p>
                                    <p id="adminProfStudentLastOnline" style="font-size:13px; font-weight:600; color:#2d3748;">11/06/26</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="view-courses" class="pearson-dynamic-view">
                <h2>Courses</h2>
                <div style="margin-top: 20px;">
                    <div style="font-size: 16px; font-weight: 700; color: #1a202c; margin-bottom: 12px;">Find a primary course</div>
                    <div style="margin-bottom: 15px;">
                        <input type="text" id="courseSearchInput" class="p-input" placeholder="Search by keyword, exam code or vendor" onkeyup="filterCoursesTable()" style="max-width: 600px;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 13px; font-weight: 600; color: #2d3748;">
                        <label class="switch">
                            <input type="checkbox" id="showMyCoursesToggle" onchange="filterCoursesTable()">
                            <span class="slider-switch"></span>
                        </label>
                        <span>Show my courses</span>
                    </div>
                </div>
                <table class="p-table" id="coursesTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>COURSES</th>
                            <th>CLASSES</th>
                            <th>INSTRUCTOR</th>
                            <th>STUDENTS ENROLLED</th>
                            <th>STUDENTS INVITED</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach ($db_courses as $c) {
                        $cname = s($c->fullname);
                        $curl = $c->url;
                        $pearson_sidebar_html .= '<tr data-name="' . $cname . '" data-fullname="' . $cname . '">
                            <td>
                                <div class="action-dropdown-container">
                                    <button class="action-dots-btn" onclick="toggleActionMenu(event, this)">...</button>
                                    <div class="action-menu-popup">
                                        <div class="action-menu-item" onclick="openCourseContent(this)"><svg viewBox="0 0 24 24" width="14" height="14" fill="#124776"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg> Course content</div>
                                        <div class="action-menu-item" onclick="openAddClassModal(\'' . $cname . '\')"><svg viewBox="0 0 24 24" width="14" height="14" fill="#124776"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add Class</div>
                                    </div>
                                </div>
                            </td>
                            <td><a onclick="openCourseContentByName(\'' . $cname . '\')">' . $cname . '</a></td>
                            <td class="class-count-cell">0</td>
                            <td><span class="status-badge" style="background: #c6f6d5; color: #22543d;">Ready</span></td>
                            <td>' . $c->enrolled_count . '</td>
                            <td>0</td>
                        </tr>';
                    }
                    $pearson_sidebar_html .= '</tbody></table>
            </div>

            <div id="view-course-content-page" class="pearson-dynamic-view">
                <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
                    <div>
                        <a onclick="switchPearsonTab(\'view-courses\', document.querySelectorAll(\'.pearson-menu .nav-item\')[4])" style="cursor:pointer; color:#124776; font-weight:600; text-decoration:none;">← Back to Courses</a>
                        <h2 id="contentCourseTitle" style="margin-top:8px; font-size:22px; color:#124776;">Course Title</h2>
                    </div>
                </div>

                <div style="display:flex; gap:25px; border-bottom:1px solid #e2e8f0; margin-bottom:25px; font-weight:600; font-size:14px; color:#4a5568;">
                    <span style="padding-bottom:10px; border-bottom:3px solid #124776; color:#124776; cursor:pointer;">Course</span>
                    <span style="padding-bottom:10px; cursor:pointer;">Progress</span>
                    <span style="padding-bottom:10px; cursor:pointer;">Dates</span>
                    <span style="padding-bottom:10px; cursor:pointer;">Notes</span>
                    <span style="padding-bottom:10px; cursor:pointer;">Lab Library</span>
                    <span style="padding-bottom:10px; cursor:pointer;">Video Library</span>
                </div>

                <div style="display: flex; gap: 30px;">
                    <div style="flex: 2;">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <div>
                                <h3 style="margin: 0 0 8px 0; font-size: 18px; color: #1a202c; font-weight: 700;">Begin your course today</h3>
                            </div>
                            <button class="pearson-main-btn" style="background: #5b21b6;">Resume course</button>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
                            <button class="pearson-btn-secondary" style="border: 1px solid #cbd5e0; border-radius: 4px; padding: 6px 14px;" onclick="expandAllLessons()">Expand all</button>
                        </div>

                        <div style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background: #fff;">
                            <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; color: #2d3748; background: #fdfdfd;">
                                <span>Pearson Skilling Suite: Certified Course Material</span>
                                <span style="cursor: pointer; font-size: 18px; color: #718096;" onclick="toggleAccordion(this)">+</span>
                            </div>
                            <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; color: #2d3748; background: #fdfdfd;">
                                <span>Lesson 1: Introduction and Foundations</span>
                                <span style="cursor: pointer; font-size: 18px; color: #718096;" onclick="toggleAccordion(this)">+</span>
                            </div>
                            <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; color: #2d3748; background: #fdfdfd;">
                                <span>Lesson 2: Core Concepts and Techniques</span>
                                <span style="cursor: pointer; font-size: 18px; color: #718096;" onclick="toggleAccordion(this)">+</span>
                            </div>
                        </div>
                    </div>

                    <div style="flex: 1; max-width: 280px;">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <h4 style="margin: 0 0 15px 0; font-size: 15px; font-weight: 700; color: #1a202c;">Course Tools</h4>
                            <ul style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
                                <li style="margin-bottom: 12px;"><a href="#" style="color: #124776; text-decoration: none; font-weight: 600;">Bookmarks</a></li>
                                <li><a href="#" style="color: #124776; text-decoration: none; font-weight: 600;">Launch tour</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div id="view-classes" class="pearson-dynamic-view">
                <h2>Classes</h2>
                <table class="p-table" id="classesTableMain">
                    <thead><tr><th>CLASS NAME</th><th>COURSE TITLE</th><th>INSTRUCTOR</th><th>START DATE</th><th>STATUS</th></tr></thead>
                    <tbody id="classesTableMainBody">
                        <tr><td>Standard Class</td><td>Default Course</td><td>Ready</td><td>01/01/26</td><td><span class="status-badge">Visible</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        let currentInstructorStatus = \'active\';
        let activeEditRow = null;

        document.addEventListener(\'click\', function(e) {
            if (!e.target.closest(\'.action-dropdown-container\')) {
                document.querySelectorAll(\'.action-menu-popup\').forEach(m => m.classList.remove(\'show\'));
            }
        });

        function toggleActionMenu(e, btn) {
            e.stopPropagation();
            document.querySelectorAll(\'.action-menu-popup\').forEach(m => {
                if (m !== btn.nextElementSibling) m.classList.remove(\'show\');
            });
            var menu = btn.nextElementSibling;
            menu.classList.toggle(\'show\');
        }

        function openAddInstructorModal() {
            document.getElementById(\'newInstEmail\').value = \'\';
            document.getElementById(\'newInstFirstName\').value = \'\';
            document.getElementById(\'newInstLastName\').value = \'\';
            document.getElementById(\'newInstEnrollmentToggle\').checked = true;
            document.getElementById(\'addInstructorModal\').classList.add(\'show\');
        }

        function closeAddInstructorModal() {
            document.getElementById(\'addInstructorModal\').classList.remove(\'show\');
        }

        function openAddClassModal(courseName) {
            document.querySelectorAll(\'.action-menu-popup\').forEach(m => m.classList.remove(\'show\'));
            document.getElementById(\'addClassCourseTitleDisplay\').innerText = courseName;
            document.getElementById(\'addClassCourseTitleInput\').value = courseName;
            document.getElementById(\'addClassNameInput\').value = \'\';
            document.getElementById(\'addClassModal\').classList.add(\'show\');
        }

        function closeAddClassModal() {
            document.getElementById(\'addClassModal\').classList.remove(\'show\');
        }

        function openCourseContent(element) {
            document.querySelectorAll(\'.action-menu-popup\').forEach(m => m.classList.remove(\'show\'));
            var tr = element.closest(\'tr\');
            var courseName = tr.getAttribute(\'data-fullname\');
            document.getElementById(\'contentCourseTitle\').innerText = courseName;
            switchPearsonTab(\'view-course-content-page\', null);
        }

        function openCourseContentByName(courseName) {
            document.getElementById(\'contentCourseTitle\').innerText = courseName;
            switchPearsonTab(\'view-course-content-page\', null);
        }

        function toggleAccordion(el) {
            el.innerText = (el.innerText === \'+\') ? \'-\' : \'+\';
        }

        function expandAllLessons() {
            document.querySelectorAll(\'#view-course-content-page span[onclick="toggleAccordion(this)"]\').forEach(span => {
                span.innerText = \'-\';
            });
        }

        function openEditInstructorModal(element) {
            document.querySelectorAll(\'.action-menu-popup\').forEach(m => m.classList.remove(\'show\'));
            activeEditRow = element.closest(\'tr\');
            var name = activeEditRow.getAttribute(\'data-name\');
            document.getElementById(\'modalEditTitle\').innerText = \'Edit \' + name;
            
            var isAct = (activeEditRow.getAttribute(\'data-status\') !== \'inactive\');
            document.getElementById(\'modalStatusToggle\').checked = isAct;
            document.getElementById(\'modalStatusLabel\').innerText = \'Instructor status: \' + (isAct ? \'Active\' : \'Inactive\');

            document.getElementById(\'editInstructorModal\').classList.add(\'show\');
        }

        function closeEditInstructorModal() {
            document.getElementById(\'editInstructorModal\').classList.remove(\'show\');
            activeEditRow = null;
        }

        function updateModalStatusText(toggle) {
            document.getElementById(\'modalStatusLabel\').innerText = \'Instructor status: \' + (toggle.checked ? \'Active\' : \'Inactive\');
        }

        function saveInstructorChanges() {
            if (activeEditRow) {
                var isAct = document.getElementById(\'modalStatusToggle\').checked;
                activeEditRow.setAttribute(\'data-status\', isAct ? \'active\' : \'inactive\');
                var badge = activeEditRow.querySelector(\'.status-badge\');
                if (badge) {
                    badge.innerText = isAct ? \'Active\' : \'Inactive\';
                    badge.style.background = isAct ? \'#c6f6d5\' : \'#fed7d7\';
                    badge.style.color = isAct ? \'#22543d\' : \'#742a2a\';
                }
            }
            closeEditInstructorModal();
        }

        function togglePearsonSidebar() {
            document.getElementById("pearsonSidebar").classList.toggle("closed");
            document.body.classList.toggle("sidebar-closed");
            document.getElementById("sidebarOpenBtn").classList.toggle("visible");
        }
        function switchPearsonTab(viewId, element) {
            document.querySelectorAll(\'.pearson-menu .nav-item\').forEach(i => i.classList.remove(\'active\'));
            if (element) element.classList.add(\'active\');
            document.querySelectorAll(\'.pearson-dynamic-view\').forEach(v => v.classList.remove(\'active-view\'));
            var mr = document.getElementById(\'region-main\'), ph = document.querySelector(\'#page-header\'), body = document.body;
            if (viewId === \'view-home\') {
                if (mr) mr.style.display = \'block\'; if (ph) ph.style.display = \'block\'; body.classList.remove(\'hide-block-drawer\');
            } else {
                if (mr) mr.style.display = \'none\'; if (ph) ph.style.display = \'none\'; body.classList.add(\'hide-block-drawer\');
                var t = document.getElementById(viewId); if (t) t.classList.add(\'active-view\');
            }
        }
        function updateAdminPlaceholder() {
            var val = document.querySelector(\'input[name="adminStudentSearchType"]:checked\').value;
            document.getElementById("adminStudentSearchInput").placeholder = (val === "0") ? "Student Name" : "Student Email";
        }
        function updateAdminInstPlaceholder() {
            var val = document.querySelector(\'input[name="adminInstSearchType"]:checked\').value;
            document.getElementById("adminInstSearchInput").placeholder = (val === "0") ? "Instructor Name" : "Instructor Email";
        }
        function filterInstructorStatus(status, btn) {
            currentInstructorStatus = status;
            document.querySelectorAll(\'#instructorStatusFilterGroup .filter-pill\').forEach(p => p.classList.remove(\'active\'));
            btn.classList.add(\'active\');
            filterAdminInstructors();
        }
        function filterAdminStudents() {
            var type = document.querySelector(\'input[name="adminStudentSearchType"]:checked\').value;
            var query = document.getElementById("adminStudentSearchInput").value.toLowerCase();
            var trs = document.getElementById("studentTable").getElementsByTagName("tr");
            for (var i = 1; i < trs.length; i++) {
                var tds = trs[i].getElementsByTagName("td");
                var targetText = tds[parseInt(type)].innerText.toLowerCase();
                trs[i].style.display = (targetText.indexOf(query) > -1) ? "" : "none";
            }
        }
        function filterAdminInstructors() {
            var type = document.querySelector(\'input[name="adminInstSearchType"]:checked\').value;
            var query = document.getElementById("adminInstSearchInput").value.toLowerCase();
            var course = document.getElementById("adminInstCourseFilter").value.toLowerCase();
            var trs = document.getElementById("instructorTable").getElementsByTagName("tr");
            for (var i = 1; i < trs.length; i++) {
                var tr = trs[i];
                var tds = tr.getElementsByTagName("td");
                if (tds.length === 0) continue;
                var targetText = tds[parseInt(type) + 1].innerText.toLowerCase();
                var coursesTaught = tds[4].innerText.toLowerCase();
                var rowStatus = tr.getAttribute("data-status") || "active";

                var matchSearch = (targetText.indexOf(query) > -1);
                var matchCourse = (course === "" || coursesTaught.indexOf(course) > -1);
                var matchStatus = (currentInstructorStatus === "all" || rowStatus === currentInstructorStatus);

                tr.style.display = (matchSearch && matchCourse && matchStatus) ? "" : "none";
            }
        }
        function filterCoursesTable() {
            var query = document.getElementById("courseSearchInput").value.toLowerCase();
            var showMy = document.getElementById("showMyCoursesToggle").checked;
            var trs = document.getElementById("coursesTable").getElementsByTagName("tr");
            for (var i = 1; i < trs.length; i++) {
                var tr = trs[i];
                var name = tr.getAttribute("data-name").toLowerCase();
                var enrolledCount = parseInt(tr.getElementsByTagName("td")[4].innerText) || 0;
                
                var matchSearch = (name.indexOf(query) > -1);
                var matchMy = (!showMy || enrolledCount > 0);

                tr.style.display = (matchSearch && matchMy) ? "" : "none";
            }
        }
        function openInstructorProfile(element) {
            var tr = element.closest(\'tr\');
            var name = tr.getAttribute(\'data-name\');
            var email = tr.getAttribute(\'data-email\');
            var since = tr.getAttribute(\'data-since\');
            var lastonline = tr.getAttribute(\'data-lastonline\');
            var coursesArr = JSON.parse(tr.getAttribute(\'data-courses\'));

            document.getElementById(\'profInstName\').innerText = name;
            document.getElementById(\'profInstTitle\').innerText = name;
            var emailEl = document.getElementById(\'profInstEmail\');
            emailEl.innerText = email;
            emailEl.href = \'mailto:\' + email;
            document.getElementById(\'profInstSince\').innerText = since;
            document.getElementById(\'profInstLastOnline\').innerText = lastonline;

            var coursesListEl = document.getElementById(\'profInstCoursesList\');
            coursesListEl.innerHTML = \'\';
            coursesArr.forEach(function(c) {
                var li = document.createElement(\'li\');
                li.innerHTML = \'<a href="\' + c.url + \'">\' + c.fullname + \'</a>\';
                coursesListEl.appendChild(li);
            });

            var classesTBody = document.getElementById(\'profInstClassesTableBody\');
            classesTBody.innerHTML = \'\';
            coursesArr.forEach(function(c, idx) {
                var row = document.createElement(\'tr\');
                var statusBadge = (idx % 2 === 0) ? \'<span class="status-badge in-progress">In Progress</span>\' : \'<span class="status-badge">Complete</span>\';
                row.innerHTML = \'<td><a href="\' + c.url + \'">\' + c.shortname + \'</a></td>\' +
                                \'<td><a href="\' + c.url + \'">\' + c.fullname + \'</a></td>\' +
                                \'<td>\' + c.startdate + \' - \' + c.enddate + \'</td>\' +
                                \'<td>\' + statusBadge + \'</td>\';
                classesTBody.appendChild(row);
            });
            document.getElementById(\'profInstClassesCount\').innerText = \'Showing 1 - \' + coursesArr.length + \' of \' + coursesArr.length + \'.\';

            switchInstSubTab(\'profile\');
            switchPearsonTab(\'view-instructor-profile\', null);
        }

        function openAdminStudentProfile(element) {
            var tr = element.closest(\'tr\');
            var name = tr.getAttribute(\'data-name\');
            var email = tr.getAttribute(\'data-email\');
            var since = tr.getAttribute(\'data-since\');
            var lastonline = tr.getAttribute(\'data-lastonline\');
            var coursesArr = JSON.parse(tr.getAttribute(\'data-courses\'));

            document.getElementById(\'adminProfStudentName\').innerText = name;
            document.getElementById(\'adminProfStudentCardName\').innerText = name;
            var emailEl = document.getElementById(\'adminProfStudentCardEmail\');
            emailEl.innerText = email;
            emailEl.href = \'mailto:\' + email;
            document.getElementById(\'adminProfStudentSince\').innerText = since;
            document.getElementById(\'adminProfStudentLastOnline\').innerText = lastonline;

            var coursesListEl = document.getElementById(\'adminProfStudentCoursesList\');
            coursesListEl.innerHTML = \'\';
            coursesArr.forEach(function(c) {
                var li = document.createElement(\'li\');
                li.innerHTML = \'<a href="\' + c.url + \'">\' + c.fullname + \'</a>\';
                coursesListEl.appendChild(li);
            });

            var classesBody = document.getElementById(\'adminStudentProfileClassesBody\');
            classesBody.innerHTML = \'\';
            coursesArr.forEach(function(c) {
                var row = document.createElement(\'tr\');
                row.innerHTML = \'<td><a href="\' + c.url + \'">\' + c.shortname + \'</a></td>\' +
                                \'<td>\' + c.fullname + \'</td>\' +
                                \'<td>\' + c.startdate + \' - \' + c.enddate + \'</td>\' +
                                \'<td><span class="status-badge complete">Complete</span></td>\' +
                                \'<td>98%</td>\';
                classesBody.appendChild(row);
            });

            switchAdminStudentSubTab(\'classes\');
            switchPearsonTab(\'view-admin-student-profile\', null);
        }

        function switchAdminStudentSubTab(subTab) {
            document.getElementById(\'adminStudentSubViewClasses\').style.display = (subTab === \'classes\') ? \'block\' : \'none\';
            document.getElementById(\'adminStudentSubViewProfile\').style.display = (subTab === \'profile\') ? \'block\' : \'none\';
            
            document.getElementById(\'tabBtnAdminStudentClasses\').style.borderBottom = (subTab === \'classes\') ? \'2px solid #124776\' : \'none\';
            document.getElementById(\'tabBtnAdminStudentClasses\').style.color = (subTab === \'classes\') ? \'#124776\' : \'#718096\';
            
            document.getElementById(\'tabBtnAdminStudentProfile\').style.borderBottom = (subTab === \'profile\') ? \'2px solid #124776\' : \'none\';
            document.getElementById(\'tabBtnAdminStudentProfile\').style.color = (subTab === \'profile\') ? \'#124776\' : \'#718096\';
        }

        function switchInstSubTab(subTab) {
            document.getElementById(\'instSubViewProfile\').style.display = \'none\';
            document.getElementById(\'instSubViewClasses\').style.display = \'none\';
            document.getElementById(\'instSubViewAvailability\').style.display = \'none\';

            document.getElementById(\'tabBtnProfile\').style.borderBottom = \'none\';
            document.getElementById(\'tabBtnProfile\').style.color = \'#718096\';
            document.getElementById(\'tabBtnClasses\').style.borderBottom = \'none\';
            document.getElementById(\'tabBtnClasses\').style.color = \'#718096\';
            document.getElementById(\'tabBtnAvailability\').style.borderBottom = \'none\';
            document.getElementById(\'tabBtnAvailability\').style.color = \'#718096\';

            if (subTab === \'profile\') {
                document.getElementById(\'instSubViewProfile\').style.display = \'block\';
                document.getElementById(\'tabBtnProfile\').style.borderBottom = \'2px solid #124776\';
                document.getElementById(\'tabBtnProfile\').style.color = \'#124776\';
            } else if (subTab === \'classes\') {
                document.getElementById(\'instSubViewClasses\').style.display = \'block\';
                document.getElementById(\'tabBtnClasses\').style.borderBottom = \'2px solid #124776\';
                document.getElementById(\'tabBtnClasses\').style.color = \'#124776\';
            } else if (subTab === \'availability\') {
                document.getElementById(\'instSubViewAvailability\').style.display = \'block\';
                document.getElementById(\'tabBtnAvailability\').style.borderBottom = \'2px solid #124776\';
                document.getElementById(\'tabBtnAvailability\').style.color = \'#124776\';
            }
        }
        </script>';

    } else if ($is_instructor) {
        // ==========================================
        // INSTRUCTOR SIDEBAR HTML
        // ==========================================
        $inst_fullname = s($USER->firstname . ' ' . $USER->lastname);
        $inst_email = s($USER->email);
        $inst_initials = strtoupper(substr($USER->firstname, 0, 1) . substr($USER->lastname, 0, 1));
        $instructor_since = !empty($USER->timecreated) ? date('m/d/y', $USER->timecreated) : '04/28/26';
        $last_online = !empty($USER->lastaccess) ? date('m/d/y', $USER->lastaccess) : date('m/d/y');

        $pearson_sidebar_html = '
        <style>
            body { padding-left: 270px !important; background-color: #f7fafc !important; transition: padding-left 0.3s ease; }
            body.sidebar-closed { padding-left: 0 !important; }
            
            /* Block drawer ko completely hide karne ke liye */
            .drawer-right-toggle, div.drawer-right-toggle, button[data-action="closedrawer"][aria-controls="block-region-side-pre"] {
                display: none !important;
            }
            body {
                padding-right: 0 !important;
            }

            .pearson-global-sidebar { position: fixed !important; top: 60px !important; left: 0 !important; width: 270px !important; height: calc(100vh - 60px) !important; background-color: #124776 !important; color: #ffffff !important; border-right: 1px solid #0f385c !important; z-index: 1000 !important; overflow-y: auto !important; padding-top: 10px !important; box-shadow: 2px 0 10px rgba(0,0,0,0.2) !important; font-family: \'Open Sans\', Arial, sans-serif !important; transition: transform 0.3s ease; }
            .pearson-global-sidebar.closed { transform: translateX(-100%); }
            .pearson-sidebar-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px; }
            .pearson-sidebar-title { font-size: 14px; font-weight: 700; color: #ffffff; letter-spacing: 0.5px; }
            .pearson-close-btn { background: transparent !important; border: none !important; color: #ffffff !important; font-size: 18px !important; cursor: pointer !important; padding: 2px 8px !important; border-radius: 4px; }
            .pearson-close-btn:hover { background: rgba(255,255,255,0.15) !important; }
            .pearson-open-btn { position: fixed !important; top: 70px !important; left: 15px !important; z-index: 1100 !important; background: #124776 !important; color: white !important; border: none !important; padding: 8px 12px !important; border-radius: 4px !important; cursor: pointer !important; display: none; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
            .pearson-open-btn.visible { display: block !important; }
            .pearson-menu { list-style: none !important; padding: 0 !important; margin: 0 !important; }
            .pearson-menu li a { display: flex !important; align-items: center !important; padding: 12px 24px !important; color: #ffffff !important; text-decoration: none !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.2s ease !important; cursor: pointer !important; }
            .pearson-menu li a svg { width: 18px !important; height: 18px !important; min-width: 18px !important; min-height: 18px !important; margin-right: 14px !important; fill: #ffffff !important; flex-shrink: 0 !important; }
            .pearson-menu li a:hover, .pearson-menu li.active a { background-color: #0f385c !important; color: #ffffff !important; }
            .sidebar-heading { font-size: 11px !important; font-weight: 700 !important; color: #cbd5e1 !important; padding: 24px 24px 8px 24px !important; letter-spacing: 0.5px !important; }

            .pearson-dynamic-view { display: none; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin: 40px 20px 25px 20px !important; width: auto !important; border: 1px solid #e2e8f0; box-sizing: border-box; }
            .pearson-dynamic-view.active-view { display: block !important; }
            .pearson-dynamic-view h2 { font-size: 24px; font-weight: 700; color: #1a202c; margin-bottom: 20px; }
            .p-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
            .p-table th { background: #f8fafc; color: #4a5568; text-align: left; padding: 12px 16px; font-weight: 700; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 11px; }
            .p-table td { padding: 12px 16px; border-bottom: 1px solid #edf2f7; color: #2d3748; }
            .p-table td a { color: #124776 !important; text-decoration: none !important; font-weight: 600; cursor: pointer; }
            .p-table td a:hover { text-decoration: underline !important; }
            .status-badge { background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }
            .status-badge.in-progress { background: #e9d8fd; color: #44337a; }
            .p-filter-box { display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; background: #fcfcfc; padding: 20px; border-radius: 6px; border: 1px solid #e2e8f0; }
            .radio-group { display: flex; gap: 20px; font-size: 13px; font-weight: 600; color: #2d3748; align-items: center; }
            .radio-group label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
            .p-filter-row { display: flex; gap: 15px; }
            .p-input, .p-select { padding: 9px 12px; border: 1px solid #cbd5e0; border-radius: 4px; width: 100%; font-size: 13px; background: #ffffff; }

            .instructor-tabs { display: flex; gap: 30px; border-bottom: 2px solid #e2e8f0; margin-bottom: 25px; }
            .instructor-tab-item { padding: 10px 0; font-size: 14px; font-weight: 600; color: #4a5568; cursor: pointer; position: relative; }
            .instructor-tab-item.active { color: #124776; border-bottom: 2px solid #124776; margin-bottom: -2px; }

            .instructor-profile-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin-top: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
            .instructor-profile-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
            .instructor-profile-name { font-size: 20px; font-weight: 700; color: #1a202c; }
        </style>

        <button id="sidebarOpenBtn" class="pearson-open-btn" onclick="togglePearsonSidebar()" title="Open Menu">☰</button>
        <div class="pearson-global-sidebar" id="pearsonSidebar">
            <div class="pearson-sidebar-header">
                <span class="pearson-sidebar-title">SSH INSTRUCTOR</span>
                <button class="pearson-close-btn" onclick="togglePearsonSidebar()" title="Close Sidebar">✕</button>
            </div>
            <ul class="pearson-menu">
                <li class="nav-item active" onclick="switchPearsonTab(\'view-home\', this)"><a><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>Home</a></li>
                <li class="nav-item" onclick="switchPearsonTab(\'view-students\', this)"><a><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>Students</a></li>
                <li class="nav-item" onclick="switchPearsonTab(\'view-classes\', this)"><a><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/></svg>Classes</a></li>
                <li class="nav-item" onclick="switchPearsonTab(\'view-profile\', this)"><a><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>My profile</a></li>
                <li><a href="' . $CFG->wwwroot . '/my/" target="_blank"><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>Skilling Administrator</a></li>
            </ul>
            <div class="sidebar-heading">HELP AND SUPPORT</div>
            <ul class="pearson-menu">
                <li><a href="https://www.pearsonvue.com" target="_blank"><svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7z"/></svg>Knowledge Center ↗</a></li>
            </ul>
        </div>

        <div id="pearsonDynamicContainer">
            <div id="view-students" class="pearson-dynamic-view">
                <h2>Students</h2>
                <div class="p-filter-box">
                    <div class="radio-group">
                        <label><input type="radio" name="instStudentSearchType" value="0" checked onchange="updateInstPlaceholder()"> Student name</label>
                        <label><input type="radio" name="instStudentSearchType" value="1" onchange="updateInstPlaceholder()"> Student email</label>
                    </div>
                    <div class="p-filter-row">
                        <input type="text" id="instStudentSearchInput" class="p-input" placeholder="Student Name" onkeyup="filterInstStudents()">
                    </div>
                </div>
                <table class="p-table" id="studentTable">
                    <thead><tr><th>STUDENT NAME</th><th>EMAIL</th><th>STATUS</th></tr></thead>
                    <tbody>';
                    foreach ($db_students as $s) {
                        $stname = s($s->firstname . ' ' . $s->lastname);
                        $semail = s($s->email);
                        $since = !empty($s->timecreated) ? date('m/d/y', $s->timecreated) : '08/04/26';
                        $lastonline = !empty($s->lastaccess) ? date('m/d/y', $s->lastaccess) : '11/06/26';
                        $pearson_sidebar_html .= '<tr data-name="' . $stname . '" data-email="' . $semail . '" data-since="' . $since . '" data-lastonline="' . $lastonline . '" data-json=\'' . htmlspecialchars(json_encode($s->courses_list), ENT_QUOTES, 'UTF-8') . '\'>
                            <td><a onclick="openStudentProfile(this)">' . $stname . '</a></td>
                            <td><a href="mailto:' . $semail . '">' . $semail . '</a></td>
                            <td><span class="status-badge">Active</span></td>
                        </tr>';
                    }
                    $pearson_sidebar_html .= '</tbody></table>
            </div>

            <div id="view-student-profile" class="pearson-dynamic-view">
                <div style="margin-bottom:15px;">
                    <a onclick="goBack()" style="cursor:pointer; color:#124776; font-weight:600; text-decoration:none;">← Back</a>
                </div>
                <h2 id="profStudentName" style="margin-bottom:15px;">Student Name</h2>

                <div style="display:flex; gap:20px; border-bottom:1px solid #e2e8f0; margin-bottom:20px; font-weight:600; font-size:14px;">
                    <span id="tabBtnStudentClasses" onclick="switchStudentSubTab(\'classes\')" style="padding-bottom:8px; border-bottom:2px solid #124776; color:#124776; cursor:pointer;">Classes and performance</span>
                    <span id="tabBtnStudentProfile" onclick="switchStudentSubTab(\'profile\')" style="padding-bottom:8px; color:#718096; cursor:pointer;">Profile</span>
                </div>

                <div id="studentSubViewClasses" class="student-sub-view">
                    <table class="p-table">
                        <thead>
                            <tr>
                                <th>CLASS</th>
                                <th>COURSE</th>
                                <th>START - END DATE</th>
                                <th>STATUS</th>
                                <th>CURRENT GRADE</th>
                            </tr>
                        </thead>
                        <tbody id="studentProfileClassesBody"></tbody>
                    </table>
                </div>

                <div id="studentSubViewProfile" class="student-sub-view" style="display:none;">
                    <div class="instructor-profile-card">
                        <div class="instructor-profile-header">
                            <div style="background:#edf2f7; width:45px; height:45px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#4a5568;">👤</div>
                            <div>
                                <div style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase;">Student</div>
                                <div class="instructor-profile-name" id="profStudentCardName">Student Name</div>
                            </div>
                        </div>
                        <div style="margin-bottom:25px;">
                            <a id="profStudentCardEmail" href="#" style="color:#124776; text-decoration:none; font-weight:500;">email@domain.com</a>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-top:1px solid #edf2f7; padding-top:20px;">
                            <div>
                                <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:8px;">Recent Courses Taken</p>
                                <ul id="profStudentCoursesList" style="padding-left:20px; color:#124776; font-size:13px; line-height:1.6; font-weight:600;"></ul>
                            </div>
                            <div style="display:flex; gap:40px; text-align:right;">
                                <div>
                                    <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:4px;">Student Since</p>
                                    <p id="profStudentSince" style="font-size:13px; font-weight:600; color:#2d3748;">08/04/26</p>
                                </div>
                                <div>
                                    <p style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; margin-bottom:4px;">Last Online</p>
                                    <p id="profStudentLastOnline" style="font-size:13px; font-weight:600; color:#2d3748;">11/06/26</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="view-classes" class="pearson-dynamic-view">
                <h2>Assigned Courses</h2>
                <table class="p-table">
                    <thead><tr><th>COURSE TITLE</th><th>SHORTNAME</th><th>START DATE</th><th>STATUS</th></tr></thead>
                    <tbody>';
                    foreach ($db_courses as $c) {
                        $cname = s($c->fullname);
                        $cshort = s($c->shortname);
                        $startdate = $c->startdate ? date('m/d/y', $c->startdate) : '05/07/26';
                        $enddate = $c->enddate ? date('m/d/y', $c->enddate) : '06/30/26';
                        $pearson_sidebar_html .= '<tr data-fullname="' . $cname . '" data-shortname="' . $cshort . '" data-start="' . $startdate . '" data-end="' . $enddate . '">
                            <td><a onclick="openClassDetails(this)">' . $cname . '</a></td>
                            <td>' . $cshort . '</td>
                            <td>' . $startdate . '</td>
                            <td><span class="status-badge">Visible</span></td>
                        </tr>';
                    }
                    $pearson_sidebar_html .= '</tbody></table>
            </div>

            <div id="view-class-details-page" class="pearson-dynamic-view">
                <div style="margin-bottom:15px;">
                    <a onclick="goBack()" style="cursor:pointer; color:#124776; font-weight:600; text-decoration:none;">← Back</a>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 id="classDetailsTitle" style="margin:0; font-size:22px; color:#1a202c;">Class details: Course Title</h2>
                    <a id="classDetailsExternalLink" href="#" target="_blank" class="pearson-main-btn" style="text-decoration:none;">View class ↗</a>
                </div>

                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:25px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div id="classDetailsHeaderName" style="font-weight:700; color:#1a202c; font-size:14px; text-transform:uppercase; margin-bottom:8px;">COURSE TITLE</div>
                    <div style="font-size:13px; color:#4a5568; margin-bottom:12px;">📅 <span id="classDetailsDateRange">MAY 7 - JUN 30, 2026</span></div>
                    <div style="font-size:12px; font-weight:600; color:#2d3748; margin-bottom:8px;">ENROLLMENT: minimum 1, enrolled <span id="classDetailsEnrolledCount">1</span>, maximum 50</div>
                    <div style="width:100%; background:#e2e8f0; height:6px; border-radius:3px; overflow:hidden;">
                        <div style="width:15%; background:#5b21b6; height:100%;"></div>
                    </div>
                </div>

                <table class="p-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>STUDENT</th>
                            <th>EMAIL</th>
                            <th>LAST LOGIN</th>
                            <th>LAST ACCESS</th>
                            <th>STATUS</th>
                            <th>CURRENT GRADE</th>
                            <th>EXAM READY</th>
                            <th>LAST EXAM DATE</th>
                            <th>EPP DAYS LEFT</th>
                        </tr>
                    </thead>
                    <tbody id="classDetailsStudentsBody"></tbody>
                </table>
                <div style="margin-top:15px; font-size:13px; color:#4a5568;" id="classDetailsShowingText">Showing 1 - 1 of 1.</div>
            </div>

            <div id="view-profile" class="pearson-dynamic-view">
                <h2 style="margin-bottom:20px;">' . $inst_fullname . '</h2>
                
                <div class="instructor-tabs">
                    <div class="instructor-tab-item active" onclick="switchProfileSubTab(\'profile-tab-content\', this)">Profile</div>
                    <div class="instructor-tab-item" onclick="switchProfileSubTab(\'classes-tab-content\', this)">Classes</div>
                    <div class="instructor-tab-item" onclick="switchProfileSubTab(\'availability-tab-content\', this)">Availability</div>
                </div>

                <div id="profile-tab-content" class="profile-sub-view">
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:25px; margin-bottom:25px;">
                        <div style="display:flex; align-items:center; gap:15px; margin-bottom:15px;">
                            <div style="background:#edf2f7; width:45px; height:45px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#4a5568; font-size:14px;">' . $inst_initials . '</div>
                            <div>
                                <div style="font-size:11px; font-weight:700; color:#718096; text-transform:uppercase;">Instructor</div>
                                <div style="font-size:16px; font-weight:700; color:#1a202c;">' . $inst_fullname . '</div>
                            </div>
                        </div>
                        <div style="margin-bottom:20px;">
                            <a href="mailto:' . $inst_email . '" style="color:#124776; text-decoration:none; font-weight:600; font-size:13px;">✉ ' . $inst_email . '</a>
                        </div>

                        <table class="p-table" style="margin-top:20px;">
                            <thead>
                                <tr>
                                    <th>COURSES TAUGHT</th>
                                    <th>INSTRUCTOR SINCE</th>
                                    <th>LAST ONLINE</th>
                                </tr>
                            </thead>
                            <tbody>';
                            foreach ($db_courses as $c) {
                                $cname = s($c->fullname);
                                $cshort = s($c->shortname);
                                $startdate = $c->startdate ? date('m/d/y', $c->startdate) : '05/07/26';
                                $enddate = $c->enddate ? date('m/d/y', $c->enddate) : '06/30/26';
                                $curl = $CFG->wwwroot . '/course/view.php?id=' . $c->id;
                                $pearson_sidebar_html .= '<tr data-fullname="' . $cname . '" data-shortname="' . $cshort . '" data-start="' . $startdate . '" data-end="' . $enddate . '" data-url="' . $curl . '">
                                    <td><a onclick="openClassDetailsFromProfile(this)">' . $cname . '</a></td>
                                    <td>' . $instructor_since . '</td>
                                    <td>' . $last_online . '</td>
                                </tr>';
                            }
                            $pearson_sidebar_html .= '</tbody>
                        </table>
                    </div>
                </div>

                <div id="classes-tab-content" class="profile-sub-view" style="display:none;">
                    <table class="p-table">
                        <thead>
                            <tr>
                                <th>COURSE TITLE</th>
                                <th>SHORTNAME</th>
                                <th>START DATE</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody>';
                        foreach ($db_courses as $c) {
                            $cname = s($c->fullname);
                            $cshort = s($c->shortname);
                            $startdate = $c->startdate ? date('m/d/y', $c->startdate) : '05/07/26';
                            $enddate = $c->enddate ? date('m/d/y', $c->enddate) : '06/30/26';
                            $pearson_sidebar_html .= '<tr data-fullname="' . $cname . '" data-shortname="' . $cshort . '" data-start="' . $startdate . '" data-end="' . $enddate . '">
                                <td><a onclick="openClassDetails(this)">' . $cname . '</a></td>
                                <td>' . $cshort . '</td>
                                <td>' . $startdate . '</td>
                                <td><span class="status-badge">Visible</span></td>
                            </tr>';
                        }
                        $pearson_sidebar_html .= '</tbody>
                    </table>
                </div>

                <div id="availability-tab-content" class="profile-sub-view" style="display:none;">
                    <div style="padding:20px; font-size:14px; color:#4a5568;">
                        Instructor availability hours: Monday - Friday (9:00 AM - 5:00 PKT).
                    </div>
                </div>
            </div>
        </div>
        <script>
        let navigationHistory = [];
        let studentsGlobalData = ' . json_encode(array_values(array_map(function($st) {
            return [
                'name' => $st->firstname . ' ' . $st->lastname,
                'email' => $st->email,
                'since' => !empty($st->timecreated) ? date('m/d/y', $st->timecreated) : '08/04/26',
                'lastonline' => !empty($st->lastaccess) ? date('m/d/y', $st->lastaccess) : '11/06/26',
                'courses' => isset($st->courses_list) ? $st->courses_list : []
            ];
        }, $db_students))) . ';

        function togglePearsonSidebar() {
            document.getElementById("pearsonSidebar").classList.toggle("closed");
            document.body.classList.toggle("sidebar-closed");
            document.getElementById("sidebarOpenBtn").classList.toggle("visible");
        }

        function switchPearsonTab(viewId, element) {
            document.querySelectorAll(\'.pearson-menu .nav-item\').forEach(i => i.classList.remove(\'active\'));
            if (element) element.classList.add(\'active\');
            document.querySelectorAll(\'.pearson-dynamic-view\').forEach(v => v.classList.remove(\'active-view\'));
            var mr = document.getElementById(\'region-main\'), ph = document.querySelector(\'#page-header\'), body = document.body;
            if (viewId === \'view-home\') {
                if (mr) mr.style.display = \'block\'; if (ph) ph.style.display = \'block\'; body.classList.remove(\'hide-block-drawer\');
            } else {
                if (mr) mr.style.display = \'none\'; if (ph) ph.style.display = \'none\'; body.classList.add(\'hide-block-drawer\');
                var t = document.getElementById(viewId); if (t) t.classList.add(\'active-view\');
            }
            navigationHistory = [viewId];
        }

        function navigateToView(viewId) {
            document.querySelectorAll(\'.pearson-dynamic-view\').forEach(v => v.classList.remove(\'active-view\'));
            var t = document.getElementById(viewId);
            if (t) t.classList.add(\'active-view\');
            navigationHistory.push(viewId);
        }

        function goBack() {
            if (navigationHistory.length > 1) {
                navigationHistory.pop();
                var prevView = navigationHistory[navigationHistory.length - 1];
                document.querySelectorAll(\'.pearson-dynamic-view\').forEach(v => v.classList.remove(\'active-view\'));
                var t = document.getElementById(prevView);
                if (t) t.classList.add(\'active-view\');
            } else {
                switchPearsonTab(\'view-students\', document.querySelectorAll(\'.pearson-menu .nav-item\')[3]);
            }
        }

        function updateInstPlaceholder() {
            var val = document.querySelector(\'input[name="instStudentSearchType"]:checked\').value;
            document.getElementById("instStudentSearchInput").placeholder = (val === "0") ? "Student Name" : "Student Email";
        }

        function filterInstStudents() {
            var type = document.querySelector(\'input[name="instStudentSearchType"]:checked\').value;
            var query = document.getElementById("instStudentSearchInput").value.toLowerCase();
            var trs = document.getElementById("studentTable").getElementsByTagName("tr");
            for (var i = 1; i < trs.length; i++) {
                var tds = trs[i].getElementsByTagName("td");
                var targetText = tds[parseInt(type)].innerText.toLowerCase();
                trs[i].style.display = (targetText.indexOf(query) > -1) ? "" : "none";
            }
        }

        function switchStudentSubTab(subTab) {
            document.getElementById(\'studentSubViewClasses\').style.display = (subTab === \'classes\') ? \'block\' : \'none\';
            document.getElementById(\'studentSubViewProfile\').style.display = (subTab === \'profile\') ? \'block\' : \'none\';
            
            document.getElementById(\'tabBtnStudentClasses\').style.borderBottom = (subTab === \'classes\') ? \'2px solid #124776\' : \'none\';
            document.getElementById(\'tabBtnStudentClasses\').style.color = (subTab === \'classes\') ? \'#124776\' : \'#718096\';
            
            document.getElementById(\'tabBtnStudentProfile\').style.borderBottom = (subTab === \'profile\') ? \'2px solid #124776\' : \'none\';
            document.getElementById(\'tabBtnStudentProfile\').style.color = (subTab === \'profile\') ? \'#124776\' : \'#718096\';
        }

        function switchProfileSubTab(tabId, element) {
            document.querySelectorAll(\'.instructor-tab-item\').forEach(i => i.classList.remove(\'active\'));
            element.classList.add(\'active\');
            document.querySelectorAll(\'.profile-sub-view\').forEach(v => v.style.display = \'none\');
            document.getElementById(tabId).style.display = \'block\';
        }

        function openStudentProfileByName(name) {
            var stObj = studentsGlobalData.find(s => s.name === name) || studentsGlobalData[0];
            if (!stObj) return;

            document.getElementById(\'profStudentName\').innerText = stObj.name;
            document.getElementById(\'profStudentCardName\').innerText = stObj.name;
            var emailEl = document.getElementById(\'profStudentCardEmail\');
            emailEl.innerText = stObj.email;
            emailEl.href = \'mailto:\' + stObj.email;
            document.getElementById(\'profStudentSince\').innerText = stObj.since;
            document.getElementById(\'profStudentLastOnline\').innerText = stObj.lastonline;

            var coursesListEl = document.getElementById(\'profStudentCoursesList\');
            coursesListEl.innerHTML = \'\';
            stObj.courses.forEach(function(c) {
                var li = document.createElement(\'li\');
                li.innerHTML = \'<a href="\' + c.url + \'">\' + c.fullname + \'</a>\';
                coursesListEl.appendChild(li);
            });

            var classesBody = document.getElementById(\'studentProfileClassesBody\');
            classesBody.innerHTML = \'\';
            stObj.courses.forEach(function(c) {
                var row = document.createElement(\'tr\');
                row.innerHTML = \'<td><a href="\' + c.url + \'">\' + c.shortname + \'</a></td>\' +
                                \'<td>\' + c.fullname + \'</td>\' +
                                \'<td>\' + c.startdate + \' - \' + c.enddate + \'</td>\' +
                                \'<td><span class="status-badge complete">Complete</span></td>\' +
                                \'<td>98%</td>\';
                classesBody.appendChild(row);
            });

            switchStudentSubTab(\'classes\');
            navigateToView(\'view-student-profile\');
        }

        function openStudentProfile(element) {
            var tr = element.closest(\'tr\');
            var name = tr.getAttribute(\'data-name\');
            openStudentProfileByName(name);
        }

        function openAdminStudentProfile(element) {
            var tr = element.closest(\'tr\');
            var name = tr.getAttribute(\'data-name\');
            var email = tr.getAttribute(\'data-email\');
            var since = tr.getAttribute(\'data-since\');
            var lastonline = tr.getAttribute(\'data-lastonline\');
            var coursesArr = JSON.parse(tr.getAttribute(\'data-courses\'));

            document.getElementById(\'adminProfStudentName\').innerText = name;
            document.getElementById(\'adminProfStudentCardName\').innerText = name;
            var emailEl = document.getElementById(\'adminProfStudentCardEmail\');
            emailEl.innerText = email;
            emailEl.href = \'mailto:\' + email;
            document.getElementById(\'adminProfStudentSince\').innerText = since;
            document.getElementById(\'adminProfStudentLastOnline\').innerText = lastonline;

            var coursesListEl = document.getElementById(\'adminProfStudentCoursesList\');
            coursesListEl.innerHTML = \'\';
            coursesArr.forEach(function(c) {
                var li = document.createElement(\'li\');
                li.innerHTML = \'<a href="\' + c.url + \'">\' + c.fullname + \'</a>\';
                coursesListEl.appendChild(li);
            });

            var classesBody = document.getElementById(\'adminStudentProfileClassesBody\');
            classesBody.innerHTML = \'\';
            coursesArr.forEach(function(c) {
                var row = document.createElement(\'tr\');
                row.innerHTML = \'<td><a href="\' + c.url + \'">\' + c.shortname + \'</a></td>\' +
                                \'<td>\' + c.fullname + \'</td>\' +
                                \'<td>\' + c.startdate + \' - \' + c.enddate + \'</td>\' +
                                \'<td><span class="status-badge complete">Complete</span></td>\' +
                                \'<td>98%</td>\';
                classesBody.appendChild(row);
            });

            switchAdminStudentSubTab(\'classes\');
            switchPearsonTab(\'view-admin-student-profile\', null);
        }

        function switchAdminStudentSubTab(subTab) {
            document.getElementById(\'adminStudentSubViewClasses\').style.display = (subTab === \'classes\') ? \'block\' : \'none\';
            document.getElementById(\'adminStudentSubViewProfile\').style.display = (subTab === \'profile\') ? \'block\' : \'none\';
            
            document.getElementById(\'tabBtnAdminStudentClasses\').style.borderBottom = (subTab === \'classes\') ? \'2px solid #124776\' : \'none\';
            document.getElementById(\'tabBtnAdminStudentClasses\').style.color = (subTab === \'classes\') ? \'#124776\' : \'#718096\';
            
            document.getElementById(\'tabBtnAdminStudentProfile\').style.borderBottom = (subTab === \'profile\') ? \'2px solid #124776\' : \'none\';
            document.getElementById(\'tabBtnAdminStudentProfile\').style.color = (subTab === \'profile\') ? \'#124776\' : \'#718096\';
        }

        function openClassDetails(element) {
            var tr = element.closest(\'tr\');
            var cname = tr.getAttribute(\'data-fullname\');
            var start = tr.getAttribute(\'data-start\');
            var end = tr.getAttribute(\'data-end\');

            document.getElementById(\'classDetailsTitle\').innerText = \'Class details: \' + cname;
            document.getElementById(\'classDetailsHeaderName\').innerText = cname.toUpperCase();
            document.getElementById(\'classDetailsDateRange\').innerText = start + \' - \' + end;
            document.getElementById(\'classDetailsEnrolledCount\').innerText = studentsGlobalData.length;

            var tbody = document.getElementById(\'classDetailsStudentsBody\');
            tbody.innerHTML = \'\';
            studentsGlobalData.forEach(function(st, idx) {
                var row = document.createElement(\'tr\');
                row.innerHTML = \'<td>...</td>\' +
                                \'<td><a onclick="openStudentProfileByName(\\\'\' + st.name + \'\\\')" style="cursor:pointer; color:#124776; font-weight:600;">\' + st.name + \'</a></td>\' +
                                \'<td><a href="mailto:\' + st.email + \'">\' + st.email + \'</a></td>\' +
                                \'<td>\' + st.lastonline + \'</td>\' +
                                \'<td>\' + st.lastonline + \'</td>\' +
                                \'<td><span class="status-badge">Active</span></td>\' +
                                \'<td>98%</td>\' +
                                \'<td>✅ 2</td>\' +
                                \'<td>\' + st.lastonline + \'</td>\' +
                                \'<td>--</td>\';
                tbody.appendChild(row);
            });
            document.getElementById(\'classDetailsShowingText\').innerText = \'Showing 1 - \' + studentsGlobalData.length + \' of \' + studentsGlobalData.length + \'.\';

            navigateToView(\'view-class-details-page\');
        }

        function openClassDetailsFromProfile(element) {
            openClassDetails(element);
        }
        </script>';
    }
}

$templatecontext = [
    'sitename' => 'SSH Skilling Administrator',
    'coursefullname' => $coursefullname,
    'courseurl' => $courseurl ? $courseurl->out(false) : null,
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton
];

echo $pearson_sidebar_html;
echo $OUTPUT->render_from_template('theme_boost/drawers', $templatecontext);