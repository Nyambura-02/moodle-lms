<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon LMS - Student Dashboard View Component (Unified /my/ Dashboard)
// Displays strictly:
// 1. My Courses (only courses registered/enrolled) with Trainer Name
// 2. If no courses registered: Age-filtered course selection with Trainer Name
// 3. Assignments & Coursework
// 4. Academic Calendar & Deadlines

defined('MOODLE_INTERNAL') || die();

global $DB, $USER, $CFG, $OUTPUT;

// 1. Retrieve Student Recorded Age
$user_age_str = $DB->get_field_sql("
    SELECT d.data
    FROM {user_info_data} d
    JOIN {user_info_field} f ON f.id = d.fieldid
    WHERE f.shortname = 'age' AND d.userid = :userid
", ['userid' => $USER->id]);

$user_age_num = 14; // Default if not yet recorded
$age_recorded = false;
if (!empty($user_age_str) && preg_match('/(\d+)/', $user_age_str, $matches)) {
    $user_age_num = (int)$matches[1];
    $age_recorded = true;
}

// 2. Retrieve Current Student's Enrolled Courses
$my_courses = enrol_get_my_courses(['id', 'fullname', 'shortname', 'idnumber', 'summary', 'category']);
$total_enrolled = count($my_courses);

// 3. If no courses enrolled yet, automatically allocate all units for student's age!
if ($total_enrolled === 0 && $user_age_num > 0) {
    require_once($CFG->dirroot . '/my/role_routing.php');
    globecon_auto_enrol_student_by_age($USER->id, $user_age_num);
    $my_courses = enrol_get_my_courses(['id', 'fullname', 'shortname', 'idnumber', 'summary', 'category']);
    $total_enrolled = count($my_courses);
}

// 4. Retrieve Assigned Trainer Name for Each Enrolled Course
$enrolled_trainers = [];
foreach ($my_courses as $c) {
    $context = context_course::instance($c->id);
    $trainers = get_role_users(3, $context, false, 'u.id, u.firstname, u.lastname, u.email'); // Editing teacher
    $tnames = [];
    foreach ($trainers as $t) {
        $tnames[] = fullname($t);
    }
    if (empty($tnames)) {
        $trainers4 = get_role_users(4, $context, false, 'u.id, u.firstname, u.lastname, u.email'); // Non-editing teacher
        foreach ($trainers4 as $t) {
            $tnames[] = fullname($t);
        }
    }
    $enrolled_trainers[$c->id] = empty($tnames) ? 'GlobeCon Certified Trainer' : implode(', ', array_unique($tnames));
}

// 4. Prepare All Available Courses & Trainers for Age-Based Registration
$all_courses_raw = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname, c.idnumber, c.summary 
    FROM {course} c 
    WHERE c.id > 1 AND c.visible = 1 
    ORDER BY c.id ASC
");

$catalog_by_code = [];
foreach ($all_courses_raw as $c) {
    $context = context_course::instance($c->id);
    $tr = get_role_users(3, $context, false, 'u.id, u.firstname, u.lastname');
    $tr_names = [];
    foreach ($tr as $t) {
        $tr_names[] = fullname($t);
    }
    if (empty($tr_names)) {
        $tr4 = get_role_users(4, $context, false, 'u.id, u.firstname, u.lastname');
        foreach ($tr4 as $t) {
            $tr_names[] = fullname($t);
        }
    }
    $catalog_by_code[$c->shortname] = [
        'id' => $c->id,
        'fullname' => $c->fullname,
        'shortname' => $c->shortname,
        'idnumber' => $c->idnumber,
        'summary' => $c->summary,
        'trainer' => empty($tr_names) ? 'GlobeCon Certified Trainer' : implode(', ', array_unique($tr_names)),
        'is_enrolled' => isset($my_courses[$c->id])
    ];
}

// 5. Age-to-Course Mapping Rules
$course_age_rules = [
    'tier1' => [
        'name' => 'Junior Explorers',
        'range' => 'Ages 6–10',
        'min' => 6,
        'max' => 10,
        'icon' => '🌟',
        'badge' => 'bg-warning text-dark',
        'codes' => ['JR-CODE-101', 'SCRATCH-CT', 'ROBOTICS-PC']
    ],
    'tier2' => [
        'name' => 'Middle School Innovators',
        'range' => 'Ages 11–14',
        'min' => 11,
        'max' => 14,
        'icon' => '🚀',
        'badge' => 'bg-info text-dark',
        'codes' => ['MS-STEM-201', 'WEB-PROG', 'ROBOTICS-PC', 'AI-CHATBOTS', 'SCRATCH-CT']
    ],
    'tier3' => [
        'name' => 'High School Scholars',
        'range' => 'Ages 15–18',
        'min' => 15,
        'max' => 18,
        'icon' => '⚙',
        'badge' => 'bg-primary text-white',
        'codes' => ['HS-CS-301', 'PYTHON-PROG', 'WEB-PROG', 'CYBER-SEC', 'AI-CHATBOTS']
    ],
    'tier4' => [
        'name' => 'Collegiate & Young Adult',
        'range' => 'Ages 18–21',
        'min' => 18,
        'max' => 21,
        'icon' => '💼',
        'badge' => 'bg-success text-white',
        'codes' => ['UNI-DEV-401', 'GC-SEC-101', 'cybersec', 'WORKPLACE-IT', 'WEB-PROG', 'CYBER-SEC']
    ]
];

// Determine active tier based on user age
$active_tier_key = 'tier3';
if ($user_age_num <= 10) {
    $active_tier_key = 'tier1';
} else if ($user_age_num <= 14) {
    $active_tier_key = 'tier2';
} else if ($user_age_num <= 18) {
    $active_tier_key = 'tier3';
} else {
    $active_tier_key = 'tier4';
}

// 6. Retrieve Student Assignments and Assessments
$student_assignments = [];
$now_ts = time();

if (!empty($my_courses)) {
    $courseids = array_keys($my_courses);
    list($in_sql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
    
    // Assignments (mod_assign)
    $params['assign_mod'] = 'assign';
    $assigns = $DB->get_records_sql("
        SELECT a.id, a.course, a.name, a.duedate, a.intro, c.fullname AS coursename, c.shortname AS courseshortname, cm.id AS cmid
        FROM {assign} a
        JOIN {course} c ON c.id = a.course
        JOIN {modules} m ON m.name = :assign_mod
        JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id AND cm.course = a.course
        WHERE a.course $in_sql AND cm.visible = 1
        ORDER BY a.duedate ASC
    ", $params);
    
    foreach ($assigns as $a) {
        $sub = $DB->get_record('assign_submission', ['assignment' => $a->id, 'userid' => $USER->id]);
        $is_submitted = ($sub && $sub->status === 'submitted');
        $is_overdue = (!$is_submitted && $a->duedate > 0 && $a->duedate < $now_ts);
        $is_due_soon = (!$is_submitted && $a->duedate > 0 && ($a->duedate - $now_ts) <= (7 * 86400));
        
        $student_assignments[] = [
            'type' => 'Assignment',
            'icon' => 'fa-file-text-o',
            'id' => $a->id,
            'cmid' => $a->cmid,
            'name' => $a->name,
            'coursename' => $a->coursename,
            'courseshortname' => $a->courseshortname,
            'duedate' => $a->duedate,
            'duedate_str' => ($a->duedate > 0) ? userdate($a->duedate, '%A, %d %B %Y, %I:%M %p') : 'No fixed due date',
            'is_submitted' => $is_submitted,
            'is_overdue' => $is_overdue,
            'is_due_soon' => $is_due_soon,
            'url' => new moodle_url('/mod/assign/view.php', ['id' => $a->cmid])
        ];
    }
    
    // Quizzes (mod_quiz)
    $params['quiz_mod'] = 'quiz';
    $quizzes = $DB->get_records_sql("
        SELECT q.id, q.course, q.name, q.timeclose AS duedate, q.intro, c.fullname AS coursename, c.shortname AS courseshortname, cm.id AS cmid
        FROM {quiz} q
        JOIN {course} c ON c.id = q.course
        JOIN {modules} m ON m.name = :quiz_mod
        JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = m.id AND cm.course = q.course
        WHERE q.course $in_sql AND cm.visible = 1
        ORDER BY q.timeclose ASC
    ", $params);
    
    foreach ($quizzes as $q) {
        $attempt = $DB->get_record('quiz_attempts', ['quiz' => $q->id, 'userid' => $USER->id, 'state' => 'finished']);
        $is_submitted = !empty($attempt);
        $is_overdue = (!$is_submitted && $q->duedate > 0 && $q->duedate < $now_ts);
        $is_due_soon = (!$is_submitted && $q->duedate > 0 && ($q->duedate - $now_ts) <= (7 * 86400));
        
        $student_assignments[] = [
            'type' => 'Quiz / Assessment',
            'icon' => 'fa-check-square-o',
            'id' => $q->id,
            'cmid' => $q->cmid,
            'name' => $q->name,
            'coursename' => $q->coursename,
            'courseshortname' => $q->courseshortname,
            'duedate' => $q->duedate,
            'duedate_str' => ($q->duedate > 0) ? userdate($q->duedate, '%A, %d %B %Y, %I:%M %p') : 'No closing date',
            'is_submitted' => $is_submitted,
            'is_overdue' => $is_overdue,
            'is_due_soon' => $is_due_soon,
            'url' => new moodle_url('/mod/quiz/view.php', ['id' => $q->cmid])
        ];
    }
}

// 7. Calendar Data & Upcoming Deadlines
$calendar_events = [];
if (!empty($my_courses)) {
    $courseids = array_keys($my_courses);
    list($in_sql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
    $params['userid'] = $USER->id;
    $params['cutoff'] = $now_ts - 86400;
    
    $calendar_events = $DB->get_records_sql("
        SELECT e.id, e.name, e.description, e.timestart, e.timeduration, e.eventtype, e.courseid,
               c.fullname AS coursename, c.shortname AS courseshortname
        FROM {event} e
        LEFT JOIN {course} c ON c.id = e.courseid
        WHERE (e.courseid $in_sql OR e.userid = :userid OR e.eventtype = 'site')
          AND (e.timestart + e.timeduration) >= :cutoff
        ORDER BY e.timestart ASC
        LIMIT 8
    ", $params);
}

// Calculate Current Month Calendar Grid
$cal_year = (int)date('Y', $now_ts);
$cal_month = (int)date('n', $now_ts);
$cal_today = (int)date('j', $now_ts);
$cal_first_day = mktime(0, 0, 0, $cal_month, 1, $cal_year);
$cal_days_count = (int)date('t', $cal_first_day);
$cal_start_wday = (int)date('w', $cal_first_day); // 0 (Sun) to 6 (Sat)
$cal_month_title = date('F Y', $cal_first_day);

// Mark calendar days that have events or assignment deadlines
$event_days = [];
foreach ($student_assignments as $item) {
    if ($item['duedate'] > 0 && date('n', $item['duedate']) == $cal_month && date('Y', $item['duedate']) == $cal_year) {
        $event_days[(int)date('j', $item['duedate'])] = true;
    }
}
foreach ($calendar_events as $ev) {
    if (date('n', $ev->timestart) == $cal_month && date('Y', $ev->timestart) == $cal_year) {
        $event_days[(int)date('j', $ev->timestart)] = true;
    }
}

// Check if user was just enrolled
$just_enrolled_id = optional_param('enrolled', 0, PARAM_INT);
$just_enrolled_name = '';
if ($just_enrolled_id > 0) {
    $just_enrolled_name = $DB->get_field('course', 'fullname', ['id' => $just_enrolled_id]);
}
$age_set_val = optional_param('age_set', 0, PARAM_INT);
?>

<div class="globecon-role-dashboard student-dashboard container-fluid py-2">

    <!-- Role Header Banner -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 text-white p-4" 
         style="background: linear-gradient(135deg, #1e1b4b 0%, #1e3a8a 50%, #2563eb 80%, #6366f1 100%);">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-20 px-3 py-1 rounded-pill mb-2">
                    <span class="badge bg-warning text-dark fw-bold rounded-pill">Student Portal</span>
                    <span class="small fw-semibold text-white">GlobeCon Convergence Solutions</span>
                </div>
                <h1 class="h3 fw-bold mb-1 text-white">
                    Welcome back, <?php echo s($USER->firstname); ?>!
                </h1>
                <p class="text-white-50 mb-0 small">
                    Registration Number: <strong class="text-white font-monospace px-2 py-0 bg-white bg-opacity-20 rounded"><?php echo s(!empty($USER->idnumber) ? $USER->idnumber : strtoupper($USER->username)); ?></strong> &bull; 
                    Email: <strong><?php echo s($USER->email); ?></strong> &bull; 
                    Age: <strong><?php echo $user_age_num > 0 ? "{$user_age_num} yrs" : "Not Set"; ?></strong>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo $CFG->wwwroot; ?>/user/profile.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="fa fa-user-circle me-1"></i> Profile
                </a>
                <a href="<?php echo $CFG->wwwroot; ?>/login/logout.php?sesskey=<?php echo sesskey(); ?>" class="btn btn-danger btn-sm rounded-pill px-3">
                    <i class="fa fa-sign-out me-1"></i> Log Out
                </a>
            </div>
        </div>
    </div>

    <!-- Enrollment Success Notification -->
    <?php if (!empty($just_enrolled_name)): ?>
        <div class="alert alert-success border-0 rounded-4 shadow-sm p-3 mb-4 d-flex align-items-center gap-3">
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="fa fa-check fs-5"></i>
            </div>
            <div>
                <div class="fw-bold text-success">Registration Successful!</div>
                <div class="small text-dark">You have been successfully enrolled in <strong><?php echo s($just_enrolled_name); ?></strong>. Your classroom and assignments are ready below.</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($age_set_val)): ?>
        <div class="alert alert-success border-0 rounded-4 shadow-sm p-3 mb-4 d-flex align-items-center gap-3">
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="fa fa-magic fs-5"></i>
            </div>
            <div>
                <div class="fw-bold text-success">Units Auto-Allocated for Age <?php echo $age_set_val; ?>!</div>
                <div class="small text-dark">Your dashboard has been automatically updated with all the prescribed learning units and assigned trainers for your age group.</div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 1. MY COURSES (ONLY REGISTERED COURSES) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fa fa-graduation-cap text-primary me-2"></i> My Registered Courses
                </h5>
                <p class="text-muted small mb-0">Courses currently registered to your student account</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo $total_enrolled; ?> Enrolled</span>
                <?php if ($total_enrolled > 0): ?>
                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" type="button" data-bs-toggle="collapse" data-bs-target="#registerCourseSection" aria-expanded="false" aria-controls="registerCourseSection">
                        <i class="fa fa-plus me-1"></i> Register for Another Course
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body px-4">
            <?php if ($total_enrolled === 0): ?>
                <div class="alert alert-warning border-0 rounded-4 p-4 mb-0">
                    <div class="d-flex align-items-start gap-3">
                        <div class="bg-warning bg-opacity-25 text-dark rounded-circle p-3 fs-3">
                            <i class="fa fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-1">No Registered Courses Yet</h5>
                            <p class="text-secondary small mb-3">
                                You are not currently registered for any courses. Select your age below to view the courses available for your age group and register with your dedicated trainer.
                            </p>
                            <a href="#registerCourseSection" class="btn btn-primary rounded-pill px-4 btn-sm fw-semibold">
                                <i class="fa fa-arrow-down me-1"></i> Select Your Age &amp; Register Below
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($my_courses as $c): ?>
                        <div class="col-md-6 col-lg-6">
                            <div class="card border rounded-4 p-3 h-100 shadow-none bg-light bg-opacity-50">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-primary text-white font-monospace">
                                        <?php echo !empty($c->idnumber) ? s($c->idnumber) : s($c->shortname); ?>
                                    </span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill">
                                        <i class="fa fa-check-circle me-1"></i> Registered
                                    </span>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">
                                    <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $c->id; ?>" class="text-decoration-none text-dark">
                                        <?php echo s($c->fullname); ?>
                                    </a>
                                </h6>
                                <!-- Trainer's Name Prominently Displayed -->
                                <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-white border mb-2 small text-dark">
                                    <i class="fa fa-user-circle text-primary fs-6"></i>
                                    <span>Trainer: <strong class="text-primary"><?php echo s($enrolled_trainers[$c->id] ?? 'GlobeCon Certified Trainer'); ?></strong></span>
                                </div>
                                <p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.82rem;">
                                    <?php echo !empty($c->summary) ? strip_tags($c->summary) : 'Trainer-led technical education with interactive labs and assessments.'; ?>
                                </p>
                                <div class="d-flex gap-2 pt-2 border-top">
                                    <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $c->id; ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                        Enter Classroom <i class="fa fa-arrow-right ms-1"></i>
                                    </a>
                                    <a href="<?php echo $CFG->wwwroot; ?>/grade/report/user/index.php?id=<?php echo $c->id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                        Grades
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. COURSE REGISTRATION & AGE FILTER (Shown when 0 courses OR toggled) -->
    <div class="collapse <?php echo ($total_enrolled === 0) ? 'show' : ''; ?> mb-4" id="registerCourseSection">
        <div class="card border-0 shadow-sm rounded-4 bg-white border-start border-4 border-primary">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa fa-filter text-primary me-2"></i> Register for a Course (Filtered by Age)
                        </h5>
                        <p class="text-muted small mb-0">Select your age to see only the courses designed for your age group and their trainers</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label for="ageDropdown" class="small fw-bold text-muted text-nowrap">Your Age:</label>
                        <select id="ageDropdown" class="form-select form-select-sm rounded-pill px-3" style="width: 140px;" onchange="globeconFilterAge(this.value)">
                            <?php for ($a = 6; $a <= 21; $a++): ?>
                                <option value="<?php echo $a; ?>" <?php echo ($a == $user_age_num) ? 'selected' : ''; ?>>
                                    <?php echo $a; ?> Years Old
                                </option>
                            <?php endfor; ?>
                        </select>
                        <a href="<?php echo $CFG->wwwroot; ?>/my/?action=set_age_and_enrol&student_age=<?php echo $user_age_num; ?>&sesskey=<?php echo sesskey(); ?>" id="autoEnrolAgeBtn" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm fw-semibold">
                            <i class="fa fa-bolt me-1"></i> Auto-Enrol All Units for Age <span id="autoAgeNum"><?php echo $user_age_num; ?></span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body px-4">
                <!-- Age Tier Selector Buttons -->
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php foreach ($course_age_rules as $k => $tier): ?>
                        <button type="button" 
                                class="btn btn-sm rounded-pill px-3 age-tier-btn <?php echo ($k === $active_tier_key) ? 'btn-primary text-white fw-bold shadow-sm' : 'btn-outline-secondary'; ?>" 
                                id="btn-tier-<?php echo $k; ?>"
                                onclick="globeconSelectTier('<?php echo $k; ?>', <?php echo $tier['min']; ?>)">
                            <?php echo $tier['icon']; ?> <?php echo $tier['name']; ?> (<?php echo $tier['range']; ?>)
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Course Cards for Each Tier (filtered dynamically by JS) -->
                <?php foreach ($course_age_rules as $k => $tier): ?>
                    <div class="tier-course-container" id="tier-container-<?php echo $k; ?>" style="<?php echo ($k === $active_tier_key) ? '' : 'display: none;'; ?>">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="fs-4"><?php echo $tier['icon']; ?></span>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?php echo $tier['name']; ?> &bull; <?php echo $tier['range']; ?></h6>
                                <p class="text-muted small mb-0"><?php echo $tier['desc']; ?></p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($tier['codes'] as $code): ?>
                                <?php 
                                    if (!isset($catalog_by_code[$code])) continue;
                                    $course = $catalog_by_code[$code];
                                    $is_enrolled = $course['is_enrolled'];
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card border rounded-4 p-3 h-100 shadow-sm bg-white">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-light text-primary border font-monospace">
                                                <?php echo !empty($course['idnumber']) ? s($course['idnumber']) : s($course['shortname']); ?>
                                            </span>
                                            <span class="badge <?php echo $tier['badge']; ?> rounded-pill small">
                                                <?php echo $tier['range']; ?>
                                            </span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">
                                            <?php echo s($course['fullname']); ?>
                                        </h6>
                                        <!-- Trainer's Name Prominently Shown -->
                                        <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light border mb-2 small text-dark">
                                            <i class="fa fa-user-circle text-primary fs-6"></i>
                                            <span>Trainer: <strong class="text-primary"><?php echo s($course['trainer']); ?></strong></span>
                                        </div>
                                        <p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.82rem;">
                                            <?php echo !empty($course['summary']) ? strip_tags($course['summary']) : 'Trainer-led technical practical course.'; ?>
                                        </p>
                                        <div class="pt-2 border-top">
                                            <?php if ($is_enrolled): ?>
                                                <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-success w-100 rounded-pill">
                                                    <i class="fa fa-check-circle me-1"></i> Already Registered
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo $CFG->wwwroot; ?>/my/?action=enrol_course&courseid=<?php echo $course['id']; ?>&student_age=<?php echo $user_age_num; ?>&sesskey=<?php echo sesskey(); ?>" 
                                                   class="btn btn-sm btn-primary w-100 rounded-pill fw-semibold shadow-sm enrol-action-link"
                                                   data-courseid="<?php echo $course['id']; ?>">
                                                    <i class="fa fa-plus-circle me-1"></i> Register for this Course
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- 3. MAIN DASHBOARD: ASSIGNMENTS & CALENDAR -->
    <div class="row g-4">
        
        <!-- LEFT COLUMN: ASSIGNMENTS & COURSEWORK -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa fa-tasks text-primary me-2"></i> Assignments &amp; Coursework Tasks
                        </h5>
                        <p class="text-muted small mb-0">Pending submissions, upcoming lab assignments, and assessments</p>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo count($student_assignments); ?> Active</span>
                </div>
                <div class="card-body px-4">
                    <?php if (empty($student_assignments)): ?>
                        <div class="text-center py-5">
                            <div class="bg-light rounded-circle d-inline-flex p-3 text-success mb-3 fs-2">
                                <i class="fa fa-check-circle"></i>
                            </div>
                            <h6 class="fw-bold text-dark">No Pending Assignments</h6>
                            <p class="text-muted small mb-0">You are all caught up on your assignments and assessments!</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush gap-3">
                            <?php foreach ($student_assignments as $task): ?>
                                <div class="list-group-item border rounded-4 p-3 bg-light bg-opacity-50">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary text-white font-monospace small">
                                                <?php echo s($task['courseshortname']); ?>
                                            </span>
                                            <span class="badge bg-light text-secondary border small">
                                                <i class="fa <?php echo $task['icon']; ?> me-1"></i> <?php echo s($task['type']); ?>
                                            </span>
                                        </div>
                                        <?php if ($task['is_submitted']): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-1">
                                                <i class="fa fa-check me-1"></i> Submitted
                                            </span>
                                        <?php elseif ($task['is_overdue']): ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-1">
                                                <i class="fa fa-clock-o me-1"></i> Overdue
                                            </span>
                                        <?php elseif ($task['is_due_soon']): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1">
                                                <i class="fa fa-exclamation me-1"></i> Due Soon
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark rounded-pill px-3 py-1">
                                                Pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">
                                        <a href="<?php echo $task['url']; ?>" class="text-dark text-decoration-none">
                                            <?php echo s($task['name']); ?>
                                        </a>
                                    </h6>
                                    <div class="small text-muted mb-3">
                                        <i class="fa fa-calendar-check-o text-primary me-1"></i> Due Date: <strong><?php echo s($task['duedate_str']); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <a href="<?php echo $task['url']; ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                            <?php echo $task['is_submitted'] ? 'View Submission' : 'Submit Assignment'; ?> <i class="fa fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: CALENDAR & DEADLINES -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa fa-calendar text-primary me-2"></i> Academic Calendar
                        </h5>
                        <p class="text-muted small mb-0"><?php echo s($cal_month_title); ?> (Africa / Nairobi)</p>
                    </div>
                    <a href="<?php echo $CFG->wwwroot; ?>/calendar/view.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        Full Calendar
                    </a>
                </div>
                <div class="card-body px-4">

                    <!-- Visual Monthly Calendar Grid -->
                    <div class="p-3 bg-light rounded-4 mb-4 border">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <strong class="text-dark"><?php echo s($cal_month_title); ?></strong>
                            <span class="badge bg-white text-primary border small">Today: <?php echo date('M j, Y', $now_ts); ?></span>
                        </div>
                        <table class="table table-borderless text-center mb-0 small" style="table-layout: fixed;">
                            <thead>
                                <tr class="text-muted text-uppercase" style="font-size: 0.72rem;">
                                    <th>Su</th>
                                    <th>Mo</th>
                                    <th>Tu</th>
                                    <th>We</th>
                                    <th>Th</th>
                                    <th>Fr</th>
                                    <th>Sa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $day_cursor = 1;
                                $row_started = false;
                                for ($week = 0; $week < 6; $week++) {
                                    echo "<tr>";
                                    for ($dow = 0; $dow < 7; $dow++) {
                                        if ($week === 0 && $dow < $cal_start_wday) {
                                            echo "<td class='text-muted' style='opacity: 0.3;'>&bull;</td>";
                                        } else if ($day_cursor <= $cal_days_count) {
                                            $is_today = ($day_cursor === $cal_today);
                                            $has_event = isset($event_days[$day_cursor]);
                                            
                                            $class = "py-1 ";
                                            $style = "width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 600;";
                                            
                                            if ($is_today) {
                                                $style .= " background-color: #2563eb; color: #ffffff;";
                                            } else if ($has_event) {
                                                $style .= " background-color: #fee2e2; color: #dc2626; border: 1px solid #f87171;";
                                            } else {
                                                $style .= " color: #334155;";
                                            }
                                            
                                            echo "<td><span style='{$style}'>{$day_cursor}</span></td>";
                                            $day_cursor++;
                                        } else {
                                            echo "<td class='text-muted' style='opacity: 0.3;'>&bull;</td>";
                                        }
                                    }
                                    echo "</tr>";
                                    if ($day_cursor > $cal_days_count) {
                                        break;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-center gap-3 mt-3 pt-2 border-top small" style="font-size: 0.78rem;">
                            <span><span class="badge rounded-circle p-1 me-1" style="background-color: #2563eb;">&nbsp;</span> Today</span>
                            <span><span class="badge rounded-circle p-1 me-1" style="background-color: #dc2626;">&nbsp;</span> Deadlines / Events</span>
                        </div>
                    </div>

                    <!-- Upcoming Schedule / Deadlines List -->
                    <h6 class="fw-bold text-dark mb-2 small text-uppercase">Upcoming Schedule &amp; Deadlines</h6>
                    <?php if (empty($calendar_events) && empty($student_assignments)): ?>
                        <p class="text-muted small mb-0">No upcoming calendar events scheduled.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush small">
                            <?php foreach ($student_assignments as $task): ?>
                                <?php if ($task['duedate'] > 0 && !$task['is_submitted']): ?>
                                    <a href="<?php echo $task['url']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 py-2">
                                        <div>
                                            <div class="fw-bold text-dark text-truncate" style="max-width: 240px;"><?php echo s($task['name']); ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo s($task['courseshortname']); ?> &bull; Due <?php echo date('M j, g:i A', $task['duedate']); ?></div>
                                        </div>
                                        <span class="badge bg-warning text-dark rounded-pill">Due</span>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php foreach ($calendar_events as $ev): ?>
                                <a href="<?php echo $CFG->wwwroot; ?>/calendar/view.php?view=event&id=<?php echo $ev->id; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 py-2">
                                    <div>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 240px;"><?php echo s($ev->name); ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo s($ev->courseshortname ?: 'GlobeCon Event'); ?> &bull; <?php echo date('M j, g:i A', $ev->timestart); ?></div>
                                    </div>
                                    <span class="badge bg-primary text-white rounded-pill">Event</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div>

</div>

<script>
// Dynamic Age and Tier Filtering for Student Dashboard
function globeconFilterAge(age) {
    age = parseInt(age, 10);
    var tier = 'tier3';
    if (age <= 10) {
        tier = 'tier1';
    } else if (age <= 14) {
        tier = 'tier2';
    } else if (age <= 18) {
        tier = 'tier3';
    } else {
        tier = 'tier4';
    }
    globeconSelectTier(tier, age);
}

function globeconSelectTier(tierKey, ageVal) {
    // 1. Update button styling
    var buttons = document.querySelectorAll('.age-tier-btn');
    buttons.forEach(function(btn) {
        btn.classList.remove('btn-primary', 'text-white', 'fw-bold', 'shadow-sm');
        btn.classList.add('btn-outline-secondary');
    });
    var activeBtn = document.getElementById('btn-tier-' + tierKey);
    if (activeBtn) {
        activeBtn.classList.remove('btn-outline-secondary');
        activeBtn.classList.add('btn-primary', 'text-white', 'fw-bold', 'shadow-sm');
    }

    // 2. Hide all tier course containers, show only active
    var containers = document.querySelectorAll('.tier-course-container');
    containers.forEach(function(c) {
        c.style.display = 'none';
    });
    var activeContainer = document.getElementById('tier-container-' + tierKey);
    if (activeContainer) {
        activeContainer.style.display = 'block';
    }

    // 3. Update age in dropdown if different
    if (ageVal) {
        var dropdown = document.getElementById('ageDropdown');
        if (dropdown && parseInt(dropdown.value, 10) !== ageVal) {
            dropdown.value = ageVal;
        }
    }

    // 4. Update the student_age parameter in enrol links
    var enrolLinks = document.querySelectorAll('.enrol-action-link');
    enrolLinks.forEach(function(link) {
        var href = link.getAttribute('href');
        if (href) {
            var updated = href.replace(/student_age=\d+/, 'student_age=' + (ageVal || 14));
            link.setAttribute('href', updated);
        }
    });

    // 5. Update auto-enrol all units button
    var autoAgeBtn = document.getElementById('autoEnrolAgeBtn');
    var autoAgeSpan = document.getElementById('autoAgeNum');
    if (autoAgeSpan) {
        autoAgeSpan.innerText = ageVal || 14;
    }
    if (autoAgeBtn) {
        var autoHref = autoAgeBtn.getAttribute('href');
        if (autoHref) {
            autoAgeBtn.setAttribute('href', autoHref.replace(/student_age=\d+/, 'student_age=' + (ageVal || 14)));
        }
    }
}
</script>
