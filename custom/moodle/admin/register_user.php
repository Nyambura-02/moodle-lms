<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon Convergence Solutions
// Direct Student & Staff Registration Tool (Zero Email Dependency)

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/my/role_routing.php');

// Require login
require_login();

$PAGE->set_url(new moodle_url('/admin/register_user.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Register Student or Staff | GlobeCon Convergence Solutions');
$PAGE->set_heading('GlobeCon Convergence Solutions • User Enrollment Portal');

$current_role = globecon_get_user_role($USER);
$is_admin = is_siteadmin($USER) || ($current_role === 'ADMINISTRATOR');
$is_trainer = ($current_role === 'TRAINER');

// Authorization check: only Admin and Trainer can access
if (!$is_admin && !$is_trainer) {
    throw new \moodle_exception('nopermissions', 'error', '', 'Only GlobeCon Administrators and Trainers can access the direct registration portal.');
}

// Calculate the next sequential Student Registration Number
$highest_id = $DB->get_field_sql("
    SELECT MAX(CAST(SUBSTRING(idnumber, 8) AS UNSIGNED)) 
    FROM {user} 
    WHERE idnumber LIKE 'GC-STU-%' AND deleted = 0
");
$next_seq = max(7, ((int)$highest_id + 1));
$suggested_student_id = sprintf('GC-STU-%05d', $next_seq);

// Fetch all available courses for quick enrollment
$all_courses = $DB->get_records_select('course', 'id > 1', null, 'id ASC', 'id, shortname, fullname, idnumber');

$success_user = null;
$error_message = '';

// Handle POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $account_role = clean_param($_POST['account_role'] ?? 'student', PARAM_ALPHA);
    $firstname    = clean_param(trim($_POST['firstname'] ?? ''), PARAM_TEXT);
    $lastname     = clean_param(trim($_POST['lastname'] ?? ''), PARAM_TEXT);
    $email        = clean_param(trim($_POST['email'] ?? ''), PARAM_EMAIL);
    $phone        = clean_param(trim($_POST['phone'] ?? ''), PARAM_TEXT);
    $password     = trim($_POST['password'] ?? '');
    $student_age  = clean_param($_POST['student_age'] ?? 14, PARAM_INT);
    $selected_courses = $_POST['courses'] ?? [];
    
    if ($account_role === 'student') {
        $regno = strtoupper(clean_param(trim($_POST['regno'] ?? $suggested_student_id), PARAM_TEXT));
        $username = strtolower($regno);
    } else {
        $regno = clean_param(trim($_POST['staff_id'] ?? ''), PARAM_TEXT);
        if (empty($regno)) {
            $regno = 'EMP-TRN-' . sprintf('%04d', $DB->count_records('user') + 1);
        }
        $username = clean_param(trim($_POST['username'] ?? strtolower(explode('@', $email)[0])), PARAM_USERNAME);
        if (empty($username)) {
            $username = strtolower($regno);
        }
    }

    // Validation
    if (empty($firstname) || empty($lastname)) {
        $error_message = 'Please provide both First Name and Last Name.';
    } else if (empty($email) || !validate_email($email)) {
        $error_message = 'Please provide a valid email address.';
    } else if (empty($password) || strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else if ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])) {
        $error_message = "A user with the identifier/username '{$username}' already exists. Please choose a different registration number or username.";
    } else if ($DB->record_exists('user', ['email' => $email, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])) {
        $error_message = "A user with the email address '{$email}' already exists in the system.";
    } else if (!empty($regno) && $DB->record_exists('user', ['idnumber' => $regno, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])) {
        $error_message = "A user with the Registration Number '{$regno}' already exists. Please select another number.";
    }

    if (empty($error_message)) {
        try {
            $newuser = new stdClass();
            $newuser->username     = $username;
            $newuser->password     = hash_internal_user_password($password);
            $newuser->idnumber     = $regno;
            $newuser->firstname    = $firstname;
            $newuser->lastname     = $lastname;
            $newuser->email        = $email;
            $newuser->phone1       = $phone;
            $newuser->confirmed    = 1; // Instant activation (Zero email dependency!)
            $newuser->auth         = 'manual';
            $newuser->mnethostid   = $CFG->mnet_localhost_id;
            $newuser->lang         = 'en';
            $newuser->calendartype = 'gregorian';
            $newuser->timecreated  = time();
            $newuser->timemodified = time();

            $userid = user_create_user($newuser, false, false);

            // Assign Moodle Role
            $syscontext = context_system::instance();
            $role_shortname = ($account_role === 'trainer') ? 'editingteacher' : 'student';
            $roleid = $DB->get_field('role', 'id', ['shortname' => $role_shortname]);
            
            if ($roleid) {
                role_assign($roleid, $userid, $syscontext->id);
            }

            $enrolled_course_names = [];
            $enrol_plugin = enrol_get_plugin('manual');

            // If Student: Automatically allocate all units corresponding to their age!
            if ($account_role === 'student' && !empty($student_age)) {
                $auto_units = globecon_auto_enrol_student_by_age($userid, $student_age);
                foreach ($auto_units as $unit) {
                    $enrolled_course_names[] = $unit['shortname'] . ' - ' . $unit['fullname'];
                }
            }

            // Also enrol any additional selected courses
            if (!empty($selected_courses) && is_array($selected_courses)) {
                foreach ($selected_courses as $cid) {
                    $cid = (int)$cid;
                    $c_obj = $DB->get_record('course', ['id' => $cid]);
                    if ($c_obj && $enrol_plugin) {
                        $instances = enrol_get_instances($cid, true);
                        foreach ($instances as $instance) {
                            if ($instance->enrol === 'manual') {
                                $enrol_plugin->enrol_user($instance, $userid, $roleid);
                                $c_display = $c_obj->shortname . ' - ' . $c_obj->fullname;
                                if (!in_array($c_display, $enrolled_course_names)) {
                                    $enrolled_course_names[] = $c_display;
                                }
                                break;
                            }
                        }
                    }
                }
            }

            // Populate success response
            $success_user = [
                'id'            => $userid,
                'role'          => ($account_role === 'trainer') ? 'Trainer / Faculty Mentor' : 'Student',
                'account_role'  => $account_role,
                'age'           => ($account_role === 'student') ? $student_age : null,
                'fullname'      => $firstname . ' ' . $lastname,
                'regno'         => $regno,
                'username'      => $username,
                'email'         => $email,
                'phone'         => $phone,
                'password'      => $password,
                'courses'       => $enrolled_course_names,
                'loginurl'      => $CFG->wwwroot . '/login/index.php'
            ];

            // Recalculate next student seq
            $highest_id = $DB->get_field_sql("SELECT MAX(CAST(SUBSTRING(idnumber, 8) AS UNSIGNED)) FROM {user} WHERE idnumber LIKE 'GC-STU-%' AND deleted = 0");
            $suggested_student_id = sprintf('GC-STU-%05d', max(7, ((int)$highest_id + 1)));

        } catch (Exception $e) {
            $error_message = 'Registration failed: ' . $e->getMessage();
        }
    }
}

// Fetch recent 10 registered accounts
$recent_registrations = $DB->get_records_sql("
    SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.idnumber, u.timecreated, u.auth, u.confirmed,
           (SELECT r.shortname FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid WHERE ra.userid = u.id ORDER BY r.sortorder ASC LIMIT 1) as mainrole
    FROM {user} u
    WHERE u.deleted = 0 AND u.id > 1
    ORDER BY u.id DESC
    LIMIT 10
");

echo $OUTPUT->header();
?>

<div class="globecon-register-portal container-fluid py-3" style="max-width: 1140px;">

    <!-- Top Action Breadcrumb Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo $CFG->wwwroot; ?>/my/" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa fa-arrow-left me-1"></i> Return to Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="small fw-semibold text-dark">
                <i class="fa fa-shield text-primary me-1"></i> Authorized Registrar: <strong><?php echo s(fullname($USER)); ?></strong> (<?php echo s($current_role); ?>)
            </span>
        </div>
        <div class="small">
            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold">
                <i class="fa fa-bolt me-1"></i> Instant Activation (Zero Email Delay)
            </span>
        </div>
    </div>

    <!-- Hero Header Banner -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 text-white p-4" 
         style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 35%, #2563eb 70%, #6366f1 100%);">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-20 px-3 py-1 rounded-pill mb-2">
                    <span class="badge bg-light text-primary fw-bold rounded-pill">GlobeCon Convergence Solutions</span>
                    <span class="small fw-semibold text-white">Enrollment Engine</span>
                </div>
                <h1 class="h3 fw-bold mb-1 text-white">
                    Direct Student &amp; Staff Registration
                </h1>
                <p class="text-white-50 mb-0 small">
                    Create active learner or trainer accounts with immediate credentials. Accounts are activated instantly without email verification delays.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-primary shadow-sm" target="_blank">
                    <i class="fa fa-external-link me-1"></i> Test Sign-In Page
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2">
            <i class="fa fa-exclamation-triangle fs-5"></i>
            <div><?php echo s($error_message); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success_user): ?>
        <!-- Instant Credential Slip Card -->
        <div class="card border-0 shadow-lg rounded-4 mb-4 overflow-hidden border-top border-success border-4" id="credentialSlip">
            <div class="card-header bg-success text-white py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-4"></i>
                    <div>
                        <h5 class="mb-0 fw-bold">Account Created &amp; Activated Successfully!</h5>
                        <div class="small text-white-50">This user can sign in immediately at the portal login page</div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-light fw-bold rounded-pill px-3 shadow-sm" onclick="window.print()">
                    <i class="fa fa-print me-1"></i> Print Credential Slip
                </button>
            </div>
            <div class="card-body p-4 bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border">
                            <span class="text-muted small text-uppercase fw-semibold">Assigned Role &amp; Full Name</span>
                            <div class="fs-5 fw-bold text-dark mt-1"><?php echo s($success_user['fullname']); ?></div>
                            <span class="badge bg-primary text-white mt-1"><?php echo s($success_user['role']); ?></span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border">
                            <span class="text-muted small text-uppercase fw-semibold">
                                <?php echo ($success_user['account_role'] === 'student') ? 'Student Registration Number (Login Identifier)' : 'Staff ID / Username'; ?>
                            </span>
                            <div class="fs-5 fw-bold text-primary font-monospace mt-1"><?php echo s($success_user['regno']); ?></div>
                            <small class="text-muted">Username: <code><?php echo s($success_user['username']); ?></code></small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border">
                            <span class="text-muted small text-uppercase fw-semibold">Email Address</span>
                            <div class="fw-bold text-dark mt-1"><?php echo s($success_user['email']); ?></div>
                            <?php if (!empty($success_user['phone'])): ?>
                                <small class="text-muted">Phone: <?php echo s($success_user['phone']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border border-warning bg-warning bg-opacity-10">
                            <span class="text-dark small text-uppercase fw-bold"><i class="fa fa-key me-1 text-warning"></i> Login Password</span>
                            <div class="fs-5 fw-bold text-dark font-monospace mt-1"><?php echo s($success_user['password']); ?></div>
                            <small class="text-muted">Status: <strong class="text-success">Active &bull; Immediate Login Allowed</strong></small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($success_user['courses'])): ?>
                    <div class="mt-3 p-3 bg-white rounded-3 border">
                        <span class="text-muted small text-uppercase fw-semibold"><i class="fa fa-book text-primary me-1"></i> Auto-Enrolled Courses:</span>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <?php foreach ($success_user['courses'] as $cname): ?>
                                <span class="badge bg-light text-dark border px-2 py-1"><i class="fa fa-check text-success me-1"></i> <?php echo s($cname); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="copyCredentials()">
                        <i class="fa fa-copy me-1"></i> Copy Credentials to Clipboard
                    </button>
                    <a href="<?php echo $CFG->wwwroot; ?>/admin/register_user.php" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #6366f1 100%); border: none;">
                        <i class="fa fa-plus me-1"></i> Register Another User
                    </a>
                </div>
            </div>
        </div>

        <script>
        function copyCredentials() {
            var text = "GlobeCon Convergence Solutions Credentials:\n" +
                       "Name: <?php echo addslashes($success_user['fullname']); ?>\n" +
                       "Role: <?php echo addslashes($success_user['role']); ?>\n" +
                       "Registration Number / Username: <?php echo addslashes($success_user['regno']); ?>\n" +
                       "Email: <?php echo addslashes($success_user['email']); ?>\n" +
                       "Password: <?php echo addslashes($success_user['password']); ?>\n" +
                       "Login URL: <?php echo $CFG->wwwroot; ?>/login/index.php";
            navigator.clipboard.writeText(text).then(function() {
                alert("Credentials copied to clipboard!");
            });
        }
        </script>
    <?php endif; ?>

    <!-- Main Registration Form Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
            <h4 class="fw-bold text-dark mb-0">
                <i class="fa fa-user-plus text-primary me-2"></i> User Enrollment Form
            </h4>
            <p class="text-muted small mb-0">Fill in the details below to provision a student or staff account with immediate portal access.</p>
        </div>
        <div class="card-body p-4">
            <form action="<?php echo $CFG->wwwroot; ?>/admin/register_user.php" method="post" id="regForm">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

                <!-- Role Selector -->
                <div class="mb-4 p-3 bg-light rounded-3 border">
                    <label class="form-label fw-bold text-dark mb-2">Account Type / Portal Role <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="account_role" id="role_student" value="student" checked onchange="toggleRoleFields('student')">
                            <label class="form-check-label fw-semibold text-dark" for="role_student">
                                <span class="fs-5 me-1">🎓</span> Student (Learner)
                            </label>
                        </div>
                        <?php if ($is_admin): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="account_role" id="role_trainer" value="trainer" onchange="toggleRoleFields('trainer')">
                            <label class="form-check-label fw-semibold text-dark" for="role_trainer">
                                <span class="fs-5 me-1">👩‍🏫</span> Trainer / Faculty Mentor
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Identity / Registration Number Section -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6" id="student_reg_box">
                        <label for="regno" class="form-label fw-semibold text-dark small mb-1">
                            Student Registration Number <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa fa-id-card"></i></span>
                            <input type="text" class="form-control font-monospace fw-bold text-primary" id="regno" name="regno" value="<?php echo s($suggested_student_id); ?>" required>
                        </div>
                        <div class="form-text small text-muted">Auto-generated next sequence. The student will use this exact number to log in.</div>
                    </div>

                    <div class="col-md-6 d-none" id="trainer_id_box">
                        <label for="staff_id" class="form-label fw-semibold text-dark small mb-1">
                            Staff / Trainer Employee ID
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa fa-id-badge"></i></span>
                            <input type="text" class="form-control font-monospace" id="staff_id" name="staff_id" placeholder="e.g. EMP-TRN-0004">
                        </div>
                        <div class="form-text small text-muted">Official company identification code.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold text-dark small mb-1">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="name@domain.com or student@globecon.local" required>
                        </div>
                        <div class="form-text small text-muted">Users can also use their email address to sign in.</div>
                    </div>
                </div>

                <!-- Personal Name & Age Details -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label for="firstname" class="form-label fw-semibold text-dark small mb-1">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="firstname" name="firstname" placeholder="First Name" required>
                    </div>
                    <div class="col-md-3">
                        <label for="lastname" class="form-label fw-semibold text-dark small mb-1">Last Name / Surname <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Last Name" required>
                    </div>
                    <div class="col-md-3" id="student_age_box">
                        <label for="student_age" class="form-label fw-semibold text-dark small mb-1">
                            Student Age (6–21) <span class="text-danger">*</span>
                        </label>
                        <select class="form-select fw-bold text-primary" id="student_age" name="student_age" onchange="autoHighlightUnits(this.value)">
                            <?php for ($a = 6; $a <= 21; $a++): ?>
                                <option value="<?php echo $a; ?>" <?php echo ($a == 14) ? 'selected' : ''; ?>>
                                    <?php echo $a; ?> Years Old
                                </option>
                            <?php endfor; ?>
                        </select>
                        <div class="form-text small text-primary fw-semibold"><i class="fa fa-magic me-1"></i> Units for this age auto-allocated</div>
                    </div>
                    <div class="col-md-3">
                        <label for="phone" class="form-label fw-semibold text-dark small mb-1">Phone Number (Optional)</label>
                        <input type="text" class="form-control" id="phone" name="phone" placeholder="+254 7XX XXX XXX">
                    </div>
                </div>

                <!-- Password Setup (Direct, Zero Email) -->
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label for="password" class="form-label fw-semibold text-dark small mb-1">
                            Login Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa fa-key"></i></span>
                            <input type="password" class="form-control border-end-0 font-monospace" id="password" name="password" value="Learner@2026!Pass" required>
                            <button class="btn btn-outline-secondary bg-white border-start-0 border-end-0" type="button" onclick="togglePassVisibility()">
                                <i class="fa fa-eye" id="passEye"></i>
                            </button>
                            <button class="btn btn-outline-primary" type="button" onclick="generateRandomPass()">
                                <i class="fa fa-random me-1"></i> Generate
                            </button>
                        </div>
                        <div class="form-text small text-success fw-semibold">
                            <i class="fa fa-check-circle me-1"></i> This password will be active immediately. No email confirmation link is needed!
                        </div>
                    </div>
                </div>

                <!-- Course Enrollment Selection -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold text-dark mb-0">Course Enrollment(s)</label>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" onclick="selectAllCourses()">Select All</button>
                    </div>
                    <div class="p-3 bg-light rounded-3 border" style="max-height: 240px; overflow-y: auto;">
                        <div class="row g-2">
                            <?php foreach ($all_courses as $c): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input course-checkbox" type="checkbox" name="courses[]" value="<?php echo $c->id; ?>" id="course_<?php echo $c->id; ?>" <?php echo ($c->id == 2) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small text-dark" for="course_<?php echo $c->id; ?>">
                                            <strong><?php echo s($c->shortname); ?></strong> &bull; <?php echo s($c->fullname); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="<?php echo $CFG->wwwroot; ?>/my/" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #6366f1 100%); border: none;">
                        <i class="fa fa-user-check me-1"></i> Create &amp; Activate Account Immediately
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Recent Registrations Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">
                <i class="fa fa-history text-primary me-2"></i> Recent Account Registrations
            </h5>
            <span class="badge bg-light text-muted border small"><?php echo count($recent_registrations); ?> displayed</span>
        </div>
        <div class="card-body px-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead class="table-light text-muted text-uppercase">
                        <tr>
                            <th>User / Name</th>
                            <th>Registration No / ID</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_registrations as $ru): ?>
                        <tr>
                            <td>
                                <strong><?php echo s($ru->firstname . ' ' . $ru->lastname); ?></strong>
                                <div class="text-muted" style="font-size: 0.75rem;">Username: <?php echo s($ru->username); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary font-monospace px-2 py-1">
                                    <?php echo s($ru->idnumber ?: 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <a href="mailto:<?php echo s($ru->email); ?>" class="text-decoration-none text-dark">
                                    <?php echo s($ru->email); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($ru->mainrole === 'editingteacher' || $ru->mainrole === 'teacher'): ?>
                                    <span class="badge bg-info text-dark">Trainer</span>
                                <?php elseif ($ru->id == 2 || is_siteadmin($ru->id)): ?>
                                    <span class="badge bg-danger text-white">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-success text-white">Student</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-success bg-opacity-10 text-success">
                                    <i class="fa fa-check-circle me-1"></i> Active
                                </span>
                            </td>
                            <td class="text-muted">
                                <?php echo date('d M Y, H:i', $ru->timecreated); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function toggleRoleFields(role) {
    var studentBox = document.getElementById('student_reg_box');
    var trainerBox = document.getElementById('trainer_id_box');
    var ageBox = document.getElementById('student_age_box');
    var passField = document.getElementById('password');
    if (role === 'trainer') {
        if (studentBox) studentBox.classList.add('d-none');
        if (trainerBox) trainerBox.classList.remove('d-none');
        if (ageBox) ageBox.classList.add('d-none');
        if (passField) passField.value = 'Trainer@2026!Pass';
    } else {
        if (studentBox) studentBox.classList.remove('d-none');
        if (trainerBox) trainerBox.classList.add('d-none');
        if (ageBox) ageBox.classList.remove('d-none');
        if (passField) passField.value = 'Learner@2026!Pass';
    }
}

function togglePassVisibility() {
    var p = document.getElementById('password');
    var eye = document.getElementById('passEye');
    if (p.type === 'password') {
        p.type = 'text';
        if (eye) { eye.classList.remove('fa-eye'); eye.classList.add('fa-eye-slash'); }
    } else {
        p.type = 'password';
        if (eye) { eye.classList.remove('fa-eye-slash'); eye.classList.add('fa-eye'); }
    }
}

function generateRandomPass() {
    var chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%";
    var pass = "";
    for (var i = 0; i < 12; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    pass += "!Pass";
    var p = document.getElementById('password');
    if (p) p.value = pass;
}

function selectAllCourses() {
    var boxes = document.querySelectorAll('.course-checkbox');
    boxes.forEach(function(b) { b.checked = true; });
}
</script>

<?php
echo $OUTPUT->footer();

