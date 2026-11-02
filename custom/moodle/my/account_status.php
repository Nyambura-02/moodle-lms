<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon LMS - Account Status & Access Denied Notice

require_once(__DIR__ . '/../config.php');

$status = optional_param('status', 'unrecognized', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/my/account_status.php', ['status' => $status]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Account Status | GlobeCon LMS');
$PAGE->set_heading('GlobeCon Convergence Solutions');

echo $OUTPUT->header();
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0 rounded-4 p-4 text-center">
                <div class="mb-3">
                    <?php if ($status === 'suspended'): ?>
                        <div class="display-4 text-danger mb-2"><i class="fa fa-ban"></i></div>
                        <h3 class="fw-bold text-danger">Account Suspended</h3>
                        <p class="text-secondary mt-3">
                            Your GlobeCon LMS account has been temporarily suspended or deactivated by an administrator.
                        </p>
                    <?php else: ?>
                        <div class="display-4 text-warning mb-2"><i class="fa fa-exclamation-triangle"></i></div>
                        <h3 class="fw-bold text-dark">Access Restricted</h3>
                        <p class="text-secondary mt-3">
                            Your account is authenticated, but no recognized role (Student, Trainer, or Administrator) is currently assigned to your profile in the GlobeCon LMS.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="alert alert-light border rounded-3 p-3 text-start small mb-4">
                    <strong>Next Steps:</strong>
                    <ul class="mb-0 ps-3 mt-1 text-muted">
                        <li>Ensure tuition or training registration is up to date with GlobeCon Admissions.</li>
                        <li>Contact the platform administrator to verify role permissions.</li>
                    </ul>
                </div>

                <div class="card p-3 bg-light border-0 rounded-3 mb-4 text-start small">
                    <div class="fw-bold text-secondary mb-1"><i class="fa fa-envelope-o me-1"></i> Contact GlobeCon Support:</div>
                    <div>Email: <a href="mailto:support@globeconcs.com" class="fw-semibold">support@globeconcs.com</a></div>
                    <div>Phone / WhatsApp: <strong>+254 116 993 399</strong> / <strong>+254 755 101 101</strong></div>
                    <div>Location: Juja Professional Centre, 4th Floor, Juja, Kiambu, Kenya</div>
                </div>

                <div class="d-flex justify-content-center gap-2">
                    <a href="<?php echo $CFG->wwwroot; ?>/login/logout.php?sesskey=<?php echo sesskey(); ?>" class="btn btn-outline-danger">
                        <i class="fa fa-sign-out me-1"></i> Log Out
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/" class="btn btn-primary">
                        <i class="fa fa-home me-1"></i> Home Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
echo $OUTPUT->footer();
