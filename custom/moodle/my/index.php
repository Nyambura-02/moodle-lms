<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon LMS - Central Role-Adaptive Unified Dashboard
// The single authoritative Dashboard page (/my/) for Students, Trainers, and Administrators.

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once(__DIR__ . '/role_routing.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

redirect_if_major_upgrade_required();

// Require authenticated session
require_login();

// Guests are redirected to the dedicated login page
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

// 1. Authoritative Role Resolution
$user_role = globecon_get_user_role($USER->id);

if ($user_role === 'SUSPENDED') {
    redirect(new moodle_url('/my/account_status.php', ['status' => 'suspended']));
}
if ($user_role === 'UNRECOGNIZED') {
    redirect(new moodle_url('/my/account_status.php', ['status' => 'unrecognized']));
}

// 2. Handle Student Course Self-Registration and Age Update
$action = optional_param('action', '', PARAM_ALPHAEXT);
if ($action === 'enrol_course' && confirm_sesskey()) {
    $courseid = required_param('courseid', PARAM_INT);
    $selected_age = optional_param('student_age', 0, PARAM_INT);
    
    // Save/update student age in profile if provided
    if ($selected_age > 0) {
        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'age']);
        if ($fieldid) {
            $existing = $DB->get_record('user_info_data', ['userid' => $USER->id, 'fieldid' => $fieldid]);
            if ($existing) {
                $DB->set_field('user_info_data', 'data', (string)$selected_age, ['id' => $existing->id]);
            } else {
                $rec = new stdClass();
                $rec->userid = $USER->id;
                $rec->fieldid = $fieldid;
                $rec->data = (string)$selected_age;
                $rec->dataformat = 0;
                $DB->insert_record('user_info_data', $rec);
            }
        }
    }
    
    // Enrol user into course as student
    require_once($CFG->dirroot . '/enrol/manual/locallib.php');
    $enrol = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
    if ($instance) {
        $enrol->enrol_user($instance, $USER->id, 5); // 5 = student role
    }
    
    redirect(new moodle_url('/my/', ['enrolled' => $courseid]));
}

// 3. Role View Determination (with Admin Preview Switcher support)
$active_role = $user_role;
if ($user_role === 'ADMINISTRATOR') {
    $preview_role = optional_param('role', null, PARAM_ALPHA);
    if (!empty($preview_role)) {
        $preview_role = strtoupper($preview_role);
        if ($preview_role === 'ADMIN') {
            $preview_role = 'ADMINISTRATOR';
        }
        if (in_array($preview_role, ['ADMINISTRATOR', 'TRAINER', 'STUDENT'])) {
            $active_role = $preview_role;
        }
    }
}

// 3. Configure Moodle Page Properties
$PAGE->set_url(new moodle_url('/my/'));
$PAGE->set_pagelayout('mydashboard');

if ($active_role === 'ADMINISTRATOR') {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title('Administrator Dashboard | GlobeCon LMS');
    $PAGE->set_heading('GlobeCon Academy • Executive Administration Portal');
} else if ($active_role === 'TRAINER') {
    $PAGE->set_context(context_user::instance($USER->id));
    $PAGE->set_title('Trainer Dashboard | GlobeCon LMS');
    $PAGE->set_heading('GlobeCon Academy • Trainer Mentorship Portal');
} else {
    $PAGE->set_context(context_user::instance($USER->id));
    $PAGE->set_title('Student Dashboard | GlobeCon LMS');
    $PAGE->set_heading('GlobeCon Academy • Student Learning Portal');
}

echo $OUTPUT->header();

// 4. Role View Switcher for Administrators (enables 1-click preview of student/trainer dashboards)
if ($user_role === 'ADMINISTRATOR') {
    ?>
    <div class="alert alert-light border rounded-4 shadow-sm mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2 p-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger text-white rounded-pill px-3 py-1">Administrator</span>
            <span class="small fw-semibold text-dark">Unified Dashboard Active &bull; Role Preview Switcher:</span>
        </div>
        <div class="btn-group btn-group-sm" role="group">
            <a href="<?php echo $CFG->wwwroot; ?>/my/?role=admin" class="btn <?php echo ($active_role === 'ADMINISTRATOR') ? 'btn-danger text-white fw-bold shadow-sm' : 'btn-outline-danger'; ?>">
                <i class="fa fa-shield me-1"></i> Admin View
            </a>
            <a href="<?php echo $CFG->wwwroot; ?>/my/?role=trainer" class="btn <?php echo ($active_role === 'TRAINER') ? 'btn-info text-dark fw-bold shadow-sm' : 'btn-outline-info text-dark'; ?>">
                <i class="fa fa-chalkboard-teacher me-1"></i> Trainer View
            </a>
            <a href="<?php echo $CFG->wwwroot; ?>/my/?role=student" class="btn <?php echo ($active_role === 'STUDENT') ? 'btn-primary text-white fw-bold shadow-sm' : 'btn-outline-primary'; ?>">
                <i class="fa fa-graduation-cap me-1"></i> Student View
            </a>
        </div>
    </div>
    <?php
}

// 5. Render Authoritative Dashboard Component
if ($active_role === 'ADMINISTRATOR') {
    include(__DIR__ . '/views/view_admin.php');
} else if ($active_role === 'TRAINER') {
    include(__DIR__ . '/views/view_trainer.php');
} else {
    include(__DIR__ . '/views/view_student.php');
}

echo $OUTPUT->footer();
