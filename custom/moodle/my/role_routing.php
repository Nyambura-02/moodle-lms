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
            return $CFG->wwwroot . '/my/account_status.php?status=suspended';
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
        redirect(new moodle_url('/my/account_status.php', ['status' => 'suspended']));
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
