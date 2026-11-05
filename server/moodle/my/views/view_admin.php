<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon LMS - Administrator Dashboard View Component (Unified /my/ Dashboard)

defined('MOODLE_INTERNAL') || die();

global $DB, $USER, $CFG, $OUTPUT;

// 1. Gather System Statistics
$total_users = $DB->count_records('user', ['deleted' => 0]);
$total_suspended = $DB->count_records('user', ['deleted' => 0, 'suspended' => 1]);
$total_courses = $DB->count_records_select('course', 'id > 1');

// Count trainers (users assigned editingteacher or teacher role)
$total_trainers = $DB->count_records_sql("
    SELECT COUNT(DISTINCT ra.userid)
    FROM {role_assignments} ra
    JOIN {role} r ON r.id = ra.roleid
    JOIN {user} u ON u.id = ra.userid
    WHERE r.shortname IN ('editingteacher', 'teacher') AND u.deleted = 0
");

// Count students (users assigned student role)
$total_students = $DB->count_records_sql("
    SELECT COUNT(DISTINCT ra.userid)
    FROM {role_assignments} ra
    JOIN {role} r ON r.id = ra.roleid
    JOIN {user} u ON u.id = ra.userid
    WHERE r.shortname = 'student' AND u.deleted = 0
");

// Count admins
$total_admins = count(get_admins());

// 2. Fetch User Directory (Limit 20 most recent)
$recent_users = $DB->get_records_sql("
    SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.idnumber, u.lastaccess, u.suspended, u.confirmed, u.auth
    FROM {user} u
    WHERE u.deleted = 0 AND u.id > 1
    ORDER BY u.id DESC
    LIMIT 20
");

// 3. Fetch Learning Paths & Curriculum Matrix
$curriculum_tracks = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname, c.idnumber, c.visible,
           (SELECT COUNT(DISTINCT ue.userid) 
            FROM {user_enrolments} ue 
            JOIN {enrol} e ON e.id = ue.enrolid 
            WHERE e.courseid = c.id AND ue.status = 0) as student_count
    FROM {course} c
    WHERE c.id > 1
    ORDER BY c.id ASC
");
?>

<div class="globecon-role-dashboard admin-dashboard container-fluid py-2">

    <!-- Role Header Banner -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 text-white p-4" 
         style="background: linear-gradient(135deg, #090d16 0%, #1e1b4b 35%, #312e81 70%, #1d4ed8 100%);">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-20 px-3 py-1 rounded-pill mb-2">
                    <span class="badge bg-danger text-white fw-bold rounded-pill">Executive Administrator</span>
                    <span class="small fw-semibold text-white">Full Platform Governance</span>
                </div>
                <h1 class="h3 fw-bold mb-1 text-white">
                    GlobeCon Administrator Control Center
                </h1>
                <p class="text-white-50 mb-0 small">
                    Signed in as <strong><?php echo s($USER->firstname . ' ' . $USER->lastname); ?></strong> &bull; Company Email: <strong><?php echo s($USER->email); ?></strong>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?php echo $CFG->wwwroot; ?>/admin/register_user.php" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm fw-bold">
                    <i class="fa fa-user-plus me-1"></i> + Register Student / Staff
                </a>
                <a href="<?php echo $CFG->wwwroot; ?>/admin/index.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                    <i class="fa fa-cogs me-1"></i> Site Administration
                </a>
                <a href="<?php echo $CFG->wwwroot; ?>/login/logout.php?sesskey=<?php echo sesskey(); ?>" class="btn btn-danger btn-sm rounded-pill px-3">
                    <i class="fa fa-sign-out me-1"></i> Log Out
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Students</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo $total_students; ?></div>
                        <small class="text-success"><i class="fa fa-graduation-cap"></i> Enrolled Learners</small>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 fs-4">
                        <i class="fa fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-info border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Active Faculty</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo $total_trainers; ?></div>
                        <small class="text-info"><i class="fa fa-user-tie"></i> Authorized Mentors</small>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-3 fs-4">
                        <i class="fa fa-user-tie"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-purple border-4" style="border-left-color: #7c3aed !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Learning Paths</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo $total_courses; ?> Active</div>
                        <small class="text-muted">Curriculum Learning Paths</small>
                    </div>
                    <div class="bg-purple bg-opacity-10 text-purple rounded-circle p-3 fs-4" style="background-color: #f3e8ff; color: #7c3aed;">
                        <i class="fa fa-book-open"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-warning border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Security &amp; Health</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo $total_admins; ?> Admins</div>
                        <small class="text-warning"><?php echo $total_suspended; ?> Deactivated</small>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 fs-4">
                        <i class="fa fa-shield"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Admin Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?php echo $CFG->wwwroot; ?>/user/editadvanced.php?id=-1" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                    <i class="fa fa-user-plus me-1"></i> Add New User
                </a>
                <a href="<?php echo $CFG->wwwroot; ?>/course/edit.php?category=1" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm">
                    <i class="fa fa-plus-circle me-1"></i> Create Learning Path
                </a>
                <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=themesettingglobecon" class="btn btn-sm btn-info text-dark rounded-pill px-3 shadow-sm">
                    <i class="fa fa-paint-brush me-1"></i> Theme &amp; Brand Settings
                </a>
                <a href="<?php echo $CFG->wwwroot; ?>/admin/purgecaches.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="fa fa-refresh me-1"></i> Purge Caches
                </a>
            </div>
            <div class="text-muted small">
                Moodle 4.5 LTS &bull; PHP 8.2 &bull; MariaDB 10.11
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-4 shadow-sm border" id="adminDashboardTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active rounded-pill px-4 fw-semibold" id="tab-courses-btn" onclick="switchAdminTab('courses')">
                <i class="fa fa-road me-1"></i> Learning Paths (<?php echo $total_courses; ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 fw-semibold" id="tab-users-btn" onclick="switchAdminTab('users')">
                <i class="fa fa-users me-1"></i> User Register Directory (<?php echo $total_users; ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 fw-semibold" id="tab-gov-btn" onclick="switchAdminTab('gov')">
                <i class="fa fa-shield me-1"></i> System Health &amp; Governance
            </button>
        </li>
    </ul>

    <!-- Tab 1: Curriculum Tracks -->
    <div id="admin-tab-courses" class="admin-tab-pane">
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Active Learning Paths &amp; Enrollment Matrix</h5>
                    <p class="text-muted small mb-0">Learning paths currently published on GlobeCon LMS with syllabus codes and enrollment tallies</p>
                </div>
                <a href="<?php echo $CFG->wwwroot; ?>/course/edit.php?category=1" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="fa fa-plus me-1"></i> New Learning Path
                </a>
            </div>
            <div class="card-body px-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted text-uppercase">
                                <th>Code</th>
                                <th>Learning Path Title</th>
                                <th>Enrollment</th>
                                <th>Status</th>
                                <th class="text-end">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($curriculum_tracks as $track): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace px-2 py-1">
                                            <?php echo !empty($track->idnumber) ? s($track->idnumber) : s($track->shortname); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo s($track->fullname); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">
                                            <i class="fa fa-users me-1"></i> <?php echo $track->student_count; ?> Learners
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($track->visible): ?>
                                            <span class="badge bg-success rounded-pill px-2 py-1">Published</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill px-2 py-1">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $track->id; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1">
                                            View
                                        </a>
                                        <a href="<?php echo $CFG->wwwroot; ?>/course/edit.php?id=<?php echo $track->id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: User Register Directory -->
    <div id="admin-tab-users" class="admin-tab-pane d-none">
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Recent User Register Directory</h5>
                    <p class="text-muted small mb-0">Showing students with registration numbers, faculty mentors with emails, and administrators</p>
                </div>
                <a href="<?php echo $CFG->wwwroot; ?>/admin/user.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="fa fa-users me-1"></i> Full User List
                </a>
            </div>
            <div class="card-body px-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted text-uppercase">
                                <th>Name</th>
                                <th>Reg Number / Username</th>
                                <th>Email</th>
                                <th>Role Category</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $u): ?>
                                <?php 
                                    $urole = globecon_get_user_role($u->id);
                                    $badge_class = 'bg-secondary';
                                    if ($urole === 'ADMINISTRATOR') $badge_class = 'bg-danger';
                                    else if ($urole === 'TRAINER') $badge_class = 'bg-info text-dark';
                                    else if ($urole === 'STUDENT') $badge_class = 'bg-primary';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo s($u->firstname . ' ' . $u->lastname); ?></strong>
                                    </td>
                                    <td>
                                        <span class="font-monospace small px-2 py-1 bg-light border rounded">
                                            <?php echo s(!empty($u->idnumber) ? $u->idnumber : $u->username); ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo s($u->email); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?> rounded-pill px-3 py-1">
                                            <?php echo $urole; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u->suspended): ?>
                                            <span class="badge bg-warning text-dark rounded-pill">Deactivated</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo $CFG->wwwroot; ?>/user/editadvanced.php?id=<?php echo $u->id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <a href="<?php echo $CFG->wwwroot; ?>/user/profile.php?id=<?php echo $u->id; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2">
                                            <i class="fa fa-user"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 3: System Health & Governance -->
    <div id="admin-tab-gov" class="admin-tab-pane d-none">
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3">
                        <i class="fa fa-server text-primary me-2"></i> Infrastructure &amp; Runtime Status
                    </h5>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Moodle Version</span>
                            <span class="badge bg-primary rounded-pill font-monospace">4.5.15+ LTS</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>PHP Runtime</span>
                            <span class="badge bg-info text-dark rounded-pill font-monospace"><?php echo phpversion(); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Database Server</span>
                            <span class="badge bg-secondary rounded-pill font-monospace">MariaDB 10.11 / utf8mb4</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Active Theme</span>
                            <span class="badge bg-success rounded-pill font-monospace">theme_globecon (Boost Child)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>OPcache Status</span>
                            <span class="badge bg-success rounded-pill">Active &amp; Accelerated</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3">
                        <i class="fa fa-shield text-danger me-2"></i> Security Safeguards &amp; RBAC
                    </h5>
                    <div class="list-group list-group-flush small">
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/roles/manage.php" class="list-group-item list-group-item-action px-0 d-flex justify-content-between align-items-center">
                            <span><i class="fa fa-users-cog text-primary me-2"></i> Role Definitions &amp; Capabilities</span>
                            <i class="fa fa-chevron-right text-muted"></i>
                        </a>
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=manageauths" class="list-group-item list-group-item-action px-0 d-flex justify-content-between align-items-center">
                            <span><i class="fa fa-lock text-info me-2"></i> Authentication &amp; Self-Registration Plugins</span>
                            <i class="fa fa-chevron-right text-muted"></i>
                        </a>
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/purgecaches.php" class="list-group-item list-group-item-action px-0 d-flex justify-content-between align-items-center">
                            <span><i class="fa fa-refresh text-success me-2"></i> Purge All Moodle Caches</span>
                            <i class="fa fa-chevron-right text-muted"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function switchAdminTab(tabKey) {
    var tabs = ['courses', 'users', 'gov'];
    for (var i = 0; i < tabs.length; i++) {
        var k = tabs[i];
        var pane = document.getElementById('admin-tab-' + k);
        var btn = document.getElementById('tab-' + k + '-btn');
        if (k === tabKey) {
            if (pane) pane.classList.remove('d-none');
            if (btn) btn.classList.add('active');
        } else {
            if (pane) pane.classList.add('d-none');
            if (btn) btn.classList.remove('active');
        }
    }
}
</script>

