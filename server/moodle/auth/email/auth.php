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

/**
 * Authentication Plugin: Email Authentication
 *
 * @author Martin Dougiamas
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package auth_email
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');

/**
 * Email authentication plugin.
 */
class auth_plugin_email extends auth_plugin_base {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'email';
        $this->config = get_config('auth_email');
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function auth_plugin_email() {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct();
    }

    /**
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    function user_login($username, $password) {
        global $CFG, $DB;
        if ($user = $DB->get_record('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id))) {
            return validate_internal_user_password($user, $password);
        }
        return false;
    }

    /**
     * Updates the user's password.
     *
     * called when the user password is updated.
     *
     * @param  object  $user        User table object  (with system magic quotes)
     * @param  string  $newpassword Plaintext password (with system magic quotes)
     * @return boolean result
     *
     */
    function user_update_password($user, $newpassword) {
        $user = get_complete_user_data('id', $user->id);
        // This will also update the stored hash to the latest algorithm
        // if the existing hash is using an out-of-date algorithm (or the
        // legacy md5 algorithm).
        return update_internal_user_password($user, $newpassword);
    }

    function can_signup() {
        return true;
    }

    /**
     * Sign up a new user ready for confirmation.
     * Password is passed in plaintext.
     *
     * @param object $user new user object
     * @param boolean $notify print notice with link and terminate
     */
    function user_signup($user, $notify=true) {
        // Standard signup, without custom confirmatinurl.
        return $this->user_signup_with_confirmation($user, $notify);
    }

    /**
     * Sign up a new user ready for confirmation.
     *
     * Password is passed in plaintext.
     * A custom confirmationurl could be used.
     *
     * @param object $user new user object
     * @param boolean $notify print notice with link and terminate
     * @param string $confirmationurl user confirmation URL
     * @return boolean true if everything well ok and $notify is set to true
     * @throws moodle_exception
     * @since Moodle 3.2
     */
    public function user_signup_with_confirmation($user, $notify=true, $confirmationurl = null) {
        global $CFG, $DB, $SESSION;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $plainpassword = $user->password;
        $user->password = hash_internal_user_password($user->password);
        if (empty($user->calendartype)) {
            $user->calendartype = $CFG->calendartype;
        }

        $user->id = user_create_user($user, false, false);

        user_add_password_history($user->id, $plainpassword);

        // Save any custom profile field information.
        profile_save_data($user);

        // Save wantsurl against user's profile, so we can return them there upon confirmation.
        if (!empty($SESSION->wantsurl)) {
            set_user_preference('auth_email_wantsurl', $SESSION->wantsurl, $user);
        }

        // Trigger event.
        \core\event\user_created::create_from_userid($user->id)->trigger();

        // Auto-assign official Student Registration Number (GC-STU-XXXXX)
        if (empty($user->idnumber) || strpos($user->idnumber, 'GC-STU-') !== 0) {
            $highest_id = $DB->get_field_sql("SELECT MAX(CAST(SUBSTRING(idnumber, 8) AS UNSIGNED)) FROM {user} WHERE idnumber LIKE 'GC-STU-%' AND deleted = 0");
            $next_seq = max(7, ((int)$highest_id + 1));
            $user->idnumber = sprintf('GC-STU-%05d', $next_seq);
            $DB->set_field('user', 'idnumber', $user->idnumber, array('id' => $user->id));
        }

        // Auto-assign Student role in system context
        $student_role_id = $DB->get_field('role', 'id', ['shortname' => 'student']);
        if ($student_role_id) {
            role_assign($student_role_id, $user->id, context_system::instance()->id);
        }

        // Determine student age and automatically enroll them into all units for that age
        require_once($CFG->dirroot . '/my/role_routing.php');
        $student_age = 14;
        $age_str = $DB->get_field_sql("
            SELECT d.data 
            FROM {user_info_data} d 
            JOIN {user_info_field} f ON f.id = d.fieldid 
            WHERE f.shortname = 'age' AND d.userid = :userid
        ", ['userid' => $user->id]);
        if (!empty($age_str) && preg_match('/(\d+)/', $age_str, $m)) {
            $student_age = (int)$m[1];
        }

        $allocated_units = globecon_auto_enrol_student_by_age($user->id, $student_age);

        // Auto-confirm registered users immediately (Zero email dependency)
        $DB->set_field('user', 'confirmed', 1, array('id' => $user->id));
        $DB->set_field('user', 'secret', '', array('id' => $user->id));

        // Attempt sending confirmation email if mail service is available, but don't block registration
        @send_confirmation_email($user, $confirmationurl);

        if ($notify) {
            global $CFG, $PAGE, $OUTPUT;
            $PAGE->navbar->add('Account Verified');
            $PAGE->set_title('Account Created | GlobeCon Convergence Solutions');
            $PAGE->set_heading('GlobeCon Convergence Solutions');
            echo $OUTPUT->header();
            
            echo '<div class="container py-4 my-auto" style="max-width: 680px;">';
            echo '  <div class="card border-0 shadow-lg rounded-4 overflow-hidden border-top border-primary border-4 text-center p-4 p-md-5 bg-white">';
            echo '      <div class="mb-3 text-success fs-1"><i class="fa fa-check-circle"></i></div>';
            echo '      <h3 class="fw-bold text-dark mb-1">Registration Complete &amp; Account Activated!</h3>';
            echo '      <p class="text-muted small mb-4">Welcome to GlobeCon Convergence Solutions. Your student profile is active immediately.</p>';
            echo '      <div class="p-3 bg-light rounded-3 border text-start mb-4">';
            echo '          <div class="mb-2"><span class="text-muted small text-uppercase fw-semibold">Your Student Registration Number:</span>';
            echo '              <div class="fs-4 fw-bold text-primary font-monospace">' . s($user->idnumber) . '</div>';
            echo '          </div>';
            echo '          <div class="mb-2"><span class="text-muted small text-uppercase fw-semibold">Registered Age:</span>';
            echo '              <div class="fw-bold text-dark">' . s($student_age) . ' Years Old</div>';
            echo '          </div>';
            echo '          <div class="mb-3"><span class="text-muted small text-uppercase fw-semibold">Automatically Allocated Units for Age ' . s($student_age) . ':</span>';
            echo '              <div class="mt-1">';
            foreach ($allocated_units as $unit) {
                echo '          <div class="badge bg-white text-dark border p-2 mb-1 me-1 text-wrap text-start d-block font-monospace"><i class="fa fa-book text-primary me-1"></i> ' . s($unit['shortname']) . ' - ' . s($unit['fullname']) . '</div>';
            }
            echo '              </div>';
            echo '          </div>';
            echo '          <div class="mb-2"><span class="text-muted small text-uppercase fw-semibold">Registered Email Address:</span>';
            echo '              <div class="fw-bold text-dark">' . s($user->email) . '</div>';
            echo '          </div>';
            echo '          <div><span class="text-muted small text-uppercase fw-semibold">Login Instructions:</span>';
            echo '              <div class="small text-secondary">Sign in using your <strong>Student Registration Number (' . s($user->idnumber) . ')</strong> or <strong>Email</strong> with your password to access your allocated units immediately.</div>';
            echo '          </div>';
            echo '      </div>';
            echo '      <div class="d-grid gap-2">';
            echo '          <a href="' . $CFG->wwwroot . '/login/index.php" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #6366f1 100%); border: none;">';
            echo '              <i class="fa fa-sign-in me-1"></i> Proceed to Login Portal';
            echo '          </a>';
            echo '      </div>';
            echo '  </div>';
            echo '</div>';
            
            echo $OUTPUT->footer();
        } else {
            return true;
        }
    }

    /**
     * Returns true if plugin allows confirming of new users.
     *
     * @return bool
     */
    function can_confirm() {
        return true;
    }

    /**
     * Confirm the new user as registered.
     *
     * @param string $username
     * @param string $confirmsecret
     */
    function user_confirm($username, $confirmsecret) {
        global $DB, $SESSION;
        $user = get_complete_user_data('username', $username);

        if (!empty($user)) {
            if ($user->auth != $this->authtype) {
                return AUTH_CONFIRM_ERROR;

            } else if ($user->secret === $confirmsecret && $user->confirmed) {
                return AUTH_CONFIRM_ALREADY;

            } else if ($user->secret === $confirmsecret) {   // They have provided the secret key to get in
                $DB->set_field("user", "confirmed", 1, array("id"=>$user->id));

                if ($wantsurl = get_user_preferences('auth_email_wantsurl', false, $user)) {
                    // Ensure user gets returned to page they were trying to access before signing up.
                    $SESSION->wantsurl = $wantsurl;
                    unset_user_preference('auth_email_wantsurl', $user);
                }

                return AUTH_CONFIRM_OK;
            }
        } else {
            return AUTH_CONFIRM_ERROR;
        }
    }

    function prevent_local_passwords() {
        return false;
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return true;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return true;
    }

    /**
     * Returns the URL for changing the user's pw, or empty if the default can
     * be used.
     *
     * @return moodle_url
     */
    function change_password_url() {
        return null; // use default internal method
    }

    /**
     * Returns true if plugin allows resetting of internal password.
     *
     * @return bool
     */
    function can_reset_password() {
        return true;
    }

    /**
     * Returns true if plugin can be manually set.
     *
     * @return bool
     */
    function can_be_manually_set() {
        return true;
    }

    /**
     * Returns whether or not the captcha element is enabled.
     * @return bool
     */
    function is_captcha_enabled() {
        return get_config("auth_{$this->authtype}", 'recaptcha');
    }

}
