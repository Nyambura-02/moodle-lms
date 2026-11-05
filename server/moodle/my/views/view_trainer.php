<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon LMS - Trainer Dashboard View Component (Unified /my/ Dashboard)

defined('MOODLE_INTERNAL') || die();

global $DB, $USER, $CFG, $OUTPUT;

// Security rule: Retrieve ONLY courses where this trainer is explicitly authorized (editingteacher / teacher)
$trainer_courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname, c.idnumber, c.summary, r.shortname as trainerrole
    FROM {course} c
    JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
    JOIN {role_assignments} ra ON ra.contextid = ctx.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE ra.userid = :userid AND r.shortname IN ('editingteacher', 'teacher') AND c.id > 1
    ORDER BY c.fullname ASC
", ['userid' => $USER->id]);

$total_teaching_courses = count($trainer_courses);

// Count total students enrolled across trainer's authorized courses
$total_students = 0;
if (!empty($trainer_courses)) {
    $course_ids = array_keys($trainer_courses);
    list($insql, $inparams) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED);
    $total_students = $DB->count_records_sql("
        SELECT COUNT(DISTINCT ue.userid)
        FROM {user_enrolments} ue
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE e.courseid $insql AND ue.status = 0
    ", $inparams);
}
?>

<div class="globecon-role-dashboard trainer-dashboard container-fluid py-2">

    <!-- Role Header Banner -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 text-white p-4" 
         style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 30%, #0369a1 75%, #0284c7 100%);">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-20 px-3 py-1 rounded-pill mb-2">
                    <span class="badge bg-info text-dark fw-bold rounded-pill">Trainer Portal</span>
                    <span class="small fw-semibold text-white">Instruction &amp; Course Management</span>
                </div>
                <h1 class="h3 fw-bold mb-1 text-white">
                    Welcome, Instructor <?php echo s($USER->firstname); ?>!
                </h1>
                <p class="text-white-50 mb-0 small">
                    Verified Trainer ID: <strong>TRN-<?php echo str_pad($USER->id, 4, '0', STR_PAD_LEFT); ?></strong> &bull; Company Email: <strong><?php echo s($USER->email); ?></strong> &bull; Faculty Mentor
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?php echo $CFG->wwwroot; ?>/admin/register_user.php?role=student" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #6366f1 100%); border: none;">
                    <i class="fa fa-user-plus me-1"></i> + Enroll New Student
                </a>
                <a href="<?php echo $CFG->wwwroot; ?>/user/profile.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="fa fa-user-circle me-1"></i> Trainer Profile
                </a>
                <a href="<?php echo $CFG->wwwroot; ?>/login/logout.php?sesskey=<?php echo sesskey(); ?>" class="btn btn-danger btn-sm rounded-pill px-3">
                    <i class="fa fa-sign-out me-1"></i> Log Out
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-info border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Assigned Courses</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo $total_teaching_courses; ?></div>
                        <small class="text-info">Authorized Cohorts</small>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-3 fs-4">
                        <i class="fa fa-book-open"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Active Enrolled Students</div>
                        <div class="fs-3 fw-bold text-primary mt-1"><?php echo $total_students; ?></div>
                        <small class="text-primary">Under Mentorship</small>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 fs-4">
                        <i class="fa fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-success border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Course Authoring</div>
                        <div class="fs-3 fw-bold text-success mt-1">Ready</div>
                        <small class="text-success">Quizzes, Notes, Videos</small>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 fs-4">
                        <i class="fa fa-plus-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-warning border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Accreditation</div>
                        <div class="fs-3 fw-bold text-dark mt-1">NITA</div>
                        <small class="text-warning">Compliant Standard</small>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 fs-4">
                        <i class="fa fa-shield"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Content Creator Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white border-start border-4 border-success">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="fa fa-bolt text-warning me-2"></i> 1-Click Course Content &amp; Assessment Tools
                    </h5>
                    <p class="text-muted small mb-0">Quickly add quizzes, upload notes/PDFs, embed video lectures, and access student registers</p>
                </div>
            </div>

            <?php if (!empty($trainer_courses)): ?>
                <div class="row g-2 align-items-center bg-light p-3 rounded-3 border">
                    <div class="col-md-4">
                        <label class="small fw-semibold text-secondary mb-1">Select Assigned Course:</label>
                        <select id="quickCourseSelect" class="form-select form-select-sm" onchange="updateQuickLinks()">
                            <?php foreach ($trainer_courses as $c): ?>
                                <option value="<?php echo $c->id; ?>"><?php echo s($c->fullname); ?> (<?php echo s($c->idnumber ?: $c->shortname); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex flex-wrap gap-2 align-items-end pt-md-4">
                        <a id="btnQuickQuiz" href="#" class="btn btn-sm btn-success text-white shadow-sm">
                            <i class="fa fa-question-circle me-1"></i> + Add Quiz
                        </a>
                        <a id="btnQuickNotes" href="#" class="btn btn-sm btn-primary shadow-sm">
                            <i class="fa fa-file-pdf-o me-1"></i> + Upload Notes / PDF
                        </a>
                        <a id="btnQuickVideo" href="#" class="btn btn-sm btn-info text-dark shadow-sm">
                            <i class="fa fa-video-camera me-1"></i> + Add Video Link
                        </a>
                        <a id="btnQuickAssign" href="#" class="btn btn-sm btn-warning text-dark shadow-sm">
                            <i class="fa fa-pencil me-1"></i> + New Assignment
                        </a>
                        <a id="btnQuickRegister" href="#" class="btn btn-sm btn-dark shadow-sm">
                            <i class="fa fa-users me-1"></i> Student Register
                        </a>
                        <a id="btnQuickEdit" href="#" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-pencil-square-o me-1"></i> Edit Mode ON
                        </a>
                    </div>
                </div>

                <script>
                function updateQuickLinks() {
                    var sel = document.getElementById('quickCourseSelect');
                    if (!sel) return;
                    var cid = sel.value;
                    var base = '<?php echo $CFG->wwwroot; ?>';
                    document.getElementById('btnQuickQuiz').href = base + '/course/modedit.php?add=quiz&type=&course=' + cid + '&section=0&return=0&sr=0';
                    document.getElementById('btnQuickNotes').href = base + '/course/modedit.php?add=resource&type=&course=' + cid + '&section=0&return=0&sr=0';
                    document.getElementById('btnQuickVideo').href = base + '/course/modedit.php?add=url&type=&course=' + cid + '&section=0&return=0&sr=0';
                    document.getElementById('btnQuickAssign').href = base + '/course/modedit.php?add=assign&type=&course=' + cid + '&section=0&return=0&sr=0';
                    document.getElementById('btnQuickRegister').href = base + '/user/index.php?id=' + cid;
                    document.getElementById('btnQuickEdit').href = base + '/course/view.php?id=' + cid + '&edit=on';
                }
                document.addEventListener('DOMContentLoaded', updateQuickLinks);
                </script>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Assigned Courses List -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fa fa-chalkboard-teacher text-primary me-2"></i> Authorized Teaching Cohorts &amp; Course Management
                        </h5>
                        <p class="text-muted small mb-0">Direct 1-click authoring of quizzes, learning materials, assignments, and student rosters</p>
                    </div>
                    <span class="badge bg-info text-dark rounded-pill">Faculty Access</span>
                </div>
                <div class="card-body px-4">
                    <?php if (empty($trainer_courses)): ?>
                        <div class="alert alert-warning border-0 rounded-3 p-4">
                            <i class="fa fa-exclamation-circle me-1"></i> You currently have no courses assigned to your trainer profile. Please contact the site administrator to be allocated to your teaching cohorts.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($trainer_courses as $c): ?>
                                <?php
                                $student_count = $DB->count_records_sql("
                                    SELECT COUNT(DISTINCT ue.userid)
                                    FROM {user_enrolments} ue
                                    JOIN {enrol} e ON e.id = ue.enrolid
                                    WHERE e.courseid = :cid AND ue.status = 0
                                ", ['cid' => $c->id]);
                                ?>
                                <div class="card border rounded-4 p-4 shadow-none bg-light bg-opacity-50">
                                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                                        <div>
                                            <span class="badge bg-primary text-white font-monospace me-2">
                                                Code: <?php echo !empty($c->idnumber) ? s($c->idnumber) : s($c->shortname); ?>
                                            </span>
                                            <span class="badge bg-secondary-subtle text-dark">
                                                <?php echo s($c->trainerrole); ?>
                                            </span>
                                        </div>
                                        <a href="<?php echo $CFG->wwwroot; ?>/user/index.php?id=<?php echo $c->id; ?>" class="badge bg-success bg-opacity-10 text-success text-decoration-none fw-bold p-2">
                                            <i class="fa fa-users me-1"></i> <?php echo $student_count; ?> Enrolled Students (View Register)
                                        </a>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-1">
                                        <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $c->id; ?>" class="text-decoration-none text-dark">
                                            <?php echo s($c->fullname); ?>
                                        </a>
                                    </h5>
                                    <p class="text-muted small mb-3">
                                        <?php echo !empty($c->summary) ? strip_tags($c->summary) : 'Authorized course content, video lectures, notes, assignments, and gradebook.'; ?>
                                    </p>
                                    <div class="d-flex gap-2 flex-wrap pt-2 border-top">
                                        <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $c->id; ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                            <i class="fa fa-folder-open me-1"></i> Open Course
                                        </a>
                                        <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $c->id; ?>&edit=on" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                            <i class="fa fa-pencil me-1"></i> Edit Mode
                                        </a>
                                        <a href="<?php echo $CFG->wwwroot; ?>/grade/report/grader/index.php?id=<?php echo $c->id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                            <i class="fa fa-table me-1"></i> Gradebook
                                        </a>
                                        <a href="<?php echo $CFG->wwwroot; ?>/user/index.php?id=<?php echo $c->id; ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3">
                                            <i class="fa fa-users me-1"></i> Enrolled Students
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions & Shortcuts -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="fa fa-bookmark text-primary me-2"></i> Faculty Quick Navigation
                </h6>
                <div class="list-group list-group-flush small">
                    <a href="<?php echo $CFG->wwwroot; ?>/my/" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0 fw-semibold text-primary">
                        <span><i class="fa fa-th-large text-primary me-2"></i> Trainer Dashboard Home</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 0.7rem;"></i>
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/calendar/view.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0">
                        <span><i class="fa fa-calendar text-info me-2"></i> Cohort Calendar &amp; Deadlines</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 0.7rem;"></i>
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/my/courses.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0">
                        <span><i class="fa fa-book text-success me-2"></i> All Teaching Courses</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 0.7rem;"></i>
                    </a>
                </div>
            </div>

            <!-- Teaching Guidelines -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-light">
                <h6 class="fw-bold text-secondary mb-2 small text-uppercase">NITA Trainer Standards</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li>Use the <strong>+ Upload Notes / PDF</strong> button to upload lesson notes and cheat sheets.</li>
                    <li>Use the <strong>+ Add Quiz</strong> button to create graded multiple-choice and coding tests.</li>
                    <li>Click <strong>Student Register</strong> to view student attendance and enrolled learners.</li>
                    <li>Toggle <strong>Turn Edit Mode ON</strong> to drag-and-drop materials directly into the course.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

