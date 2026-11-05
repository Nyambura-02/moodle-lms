<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon LMS - Central Role Identification & Dynamic Dashboard Routing Library

defined('MOODLE_INTERNAL') || die();

/**
 * Accurately determines the user's authoritative role directly from Moodle RBAC.
 * Strict Rule: Never determine privileges from username, email, display name, course code or user input.
 *
 * @param int|stdClass $userorid
 * @return string 'ADMINISTRATOR' | 'TRAINER' | 'STUDENT' | 'SUSPENDED' | 'GUEST' | 'UNRECOGNIZED'
 */
function globecon_get_user_role($userorid = null) {
    global $DB, $USER;

    if ($userorid === null) {
        $userid = $USER->id ?? 0;
    } else if (is_object($userorid)) {
        $userid = $userorid->id;
    } else {
        $userid = (int)$userorid;
    }

    if (empty($userid) || isguestuser($userid)) {
        return 'GUEST';
    }

    $user = ($userid == $USER->id) ? $USER : $DB->get_record('user', ['id' => $userid]);
    if (!$user || !empty($user->deleted)) {
        return 'UNRECOGNIZED';
    }

    if (!empty($user->suspended)) {
        return 'SUSPENDED';
    }

    // 1. Check Site Administrator (highest authority)
    if (is_siteadmin($userid) || has_capability('moodle/site:config', context_system::instance(), $userid)) {
        return 'ADMINISTRATOR';
    }

    // 2. Check Trainer / Instructor
    // User has editingteacher or teacher role in at least one course/category or coursecreator
    $is_trainer = $DB->record_exists_sql("
        SELECT 1 FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        WHERE ra.userid = :userid AND r.shortname IN ('editingteacher', 'teacher', 'coursecreator')
    ", ['userid' => $userid]);

    if ($is_trainer) {
        return 'TRAINER';
    }

    // 3. Check Student / Enrolled Learner
    // Check if user has student role or is confirmed active registered user
    if (!empty($user->confirmed)) {
        return 'STUDENT';
    }

    return 'UNRECOGNIZED';
}

/**
 * Returns the authoritative dashboard URL for a given role.
 */
function globecon_get_role_dashboard_url($role) {
    global $CFG;
    switch ($role) {
        case 'ADMINISTRATOR':
        case 'TRAINER':
        case 'STUDENT':
            return $CFG->wwwroot . '/my/';
        case 'SUSPENDED':
            return $CFG->wwwroot . '/my/account_status.php?status=deactivated';
        case 'UNRECOGNIZED':
        default:
            return $CFG->wwwroot . '/my/account_status.php?status=unrecognized';
    }
}

/**
 * Backend enforcement check on dashboards.
 * Blocks access if the authenticated user's actual role does not match the allowed roles.
 */
function globecon_enforce_dashboard_access($allowed_roles) {
    global $CFG, $USER;

    require_login();

    $actual_role = globecon_get_user_role($USER->id);

    if ($actual_role === 'SUSPENDED') {
        redirect(new moodle_url('/my/account_status.php', ['status' => 'deactivated']));
        exit;
    }

    if ($actual_role === 'UNRECOGNIZED') {
        redirect(new moodle_url('/my/account_status.php', ['status' => 'unrecognized']));
        exit;
    }

    $allowed = (array)$allowed_roles;
    if (!in_array($actual_role, $allowed)) {
        // Backend authorization block!
        \core\notification::error("Access Denied: You do not have permission to access the requested dashboard. You have been redirected to your authorized " . ucfirst(strtolower($actual_role)) . " Dashboard.");
        redirect(globecon_get_role_dashboard_url($actual_role));
        exit;
    }

    return $actual_role;
}

/**
 * Returns the official course shortnames corresponding to a student's age (Ages 6 to 21).
 *
 * @param int $age
 * @return array
 */
function globecon_get_unit_codes_for_age($age) {
    $age = (int)$age;
    if ($age <= 10) {
        // Ages 6–10 (Junior Explorers)
        return ['JR-CODE-101', 'SCRATCH-CT', 'ROBOTICS-PC'];
    } else if ($age <= 14) {
        // Ages 11–14 (Middle School Innovators)
        return ['MS-STEM-201', 'WEB-PROG', 'ROBOTICS-PC', 'AI-CHATBOTS', 'SCRATCH-CT'];
    } else if ($age <= 18) {
        // Ages 15–18 (High School Scholars)
        return ['HS-CS-301', 'PYTHON-PROG', 'WEB-PROG', 'CYBER-SEC', 'AI-CHATBOTS'];
    } else {
        // Ages 18–21+ (Collegiate, Young Adult & Professional)
        return ['UNI-DEV-401', 'GC-SEC-101', 'cybersec', 'WORKPLACE-IT', 'WEB-PROG', 'CYBER-SEC'];
    }
}

/**
 * Automatically saves a student's age and enrolls them into all official curriculum units for that age.
 *
 * @param int $userid
 * @param int $age
 * @return array List of enrolled course fullnames
 */
function globecon_auto_enrol_student_by_age($userid, $age) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/enrollib.php');
    require_once($CFG->dirroot . '/enrol/manual/locallib.php');

    $age = (int)$age;
    if ($age < 6) $age = 6;
    if ($age > 21) $age = 21;

    // 1. Save age into user_info_data
    $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'age']);
    if ($fieldid) {
        $field_param1 = $DB->get_field('user_info_field', 'param1', ['id' => $fieldid]);
        $age_label = "{$age} years old";
        if (!empty($field_param1)) {
            $lines = explode("\n", str_replace("\r", "", $field_param1));
            foreach ($lines as $line) {
                $trimline = trim($line);
                if (preg_match('/^' . $age . '\s+years?\s+old/i', $trimline)) {
                    $age_label = $trimline;
                    break;
                }
            }
        }
        $existing = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);
        if ($existing) {
            $DB->set_field('user_info_data', 'data', $age_label, ['id' => $existing->id]);
        } else {
            $rec = new stdClass();
            $rec->userid = $userid;
            $rec->fieldid = $fieldid;
            $rec->data = $age_label;
            $rec->dataformat = 0;
            $DB->insert_record('user_info_data', $rec);
        }
    }

    // 2. Ensure user has student role in system context
    $student_role_id = $DB->get_field('role', 'id', ['shortname' => 'student']);
    if ($student_role_id) {
        $syscontext = context_system::instance();
        if (!$DB->record_exists('role_assignments', ['roleid' => $student_role_id, 'userid' => $userid, 'contextid' => $syscontext->id])) {
            role_assign($student_role_id, $userid, $syscontext->id);
        }
    }

    $enrol_plugin = enrol_get_plugin('manual');

    // If student is under 18, ensure they are not accidentally enrolled in Course 2 (Corporate Security)
    if ($age < 18 && $enrol_plugin) {
        $inst2 = $DB->get_record('enrol', ['courseid' => 2, 'enrol' => 'manual']);
        if ($inst2) {
            $enrol_plugin->unenrol_user($inst2, $userid);
        }
    }

    // 3. Retrieve courses for this age
    $codes = globecon_get_unit_codes_for_age($age);
    list($in_sql, $params) = $DB->get_in_or_equal($codes);
    $courses = $DB->get_records_sql("SELECT c.id, c.shortname, c.fullname, c.idnumber FROM {course} c WHERE c.id > 1 AND c.shortname $in_sql ORDER BY c.id ASC", $params);
    $enrolled_courses = [];

    foreach ($courses as $c) {
        $instances = enrol_get_instances($c->id, true);
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual' && $enrol_plugin && $student_role_id) {
                $enrol_plugin->enrol_user($instance, $userid, $student_role_id);
                $enrolled_courses[] = [
                    'id'        => $c->id,
                    'shortname' => $c->shortname,
                    'fullname'  => $c->fullname,
                    'idnumber'  => $c->idnumber
                ];
                break;
            }
        }
    }

    return $enrolled_courses;
}

