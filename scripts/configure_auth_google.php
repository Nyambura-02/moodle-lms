<?php
// ==============================================================================
// GlobeCon LMS - Configure Email Login, Google OAuth2 & Self-Registration
// ==============================================================================

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../server/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/authlib.php');

cli_heading("Configuring Authentication: Email Login, Google OAuth2, and Self-Registration");

// 1. Enable login by email address
set_config('authloginviaemail', 1);
set_config('auth_allowemail', 1);
cli_writeln("[✓] Enabled: Login via Email address (authloginviaemail = 1)");

// 2. Enable self-registration via email
set_config('registerauth', 'email');
cli_writeln("[✓] Enabled: Self-Registration / Create Account (registerauth = email)");

// 3. Ensure auth plugins list includes manual, email, oauth2
$current_auth = get_config('core', 'auth');
$auth_array = explode(',', $current_auth);
foreach (['manual', 'email', 'oauth2'] as $plugin) {
    if (!in_array($plugin, $auth_array)) {
        $auth_array[] = $plugin;
    }
}
$new_auth = implode(',', array_filter($auth_array));
set_config('auth', $new_auth);
cli_writeln("[✓] Enabled Auth Plugins: {$new_auth}");

// 4. Configure Google OAuth2 Issuer
\core\cron::setup_user();
$issuers = \core\oauth2\api::get_all_issuers();
$google_issuer = null;
foreach ($issuers as $issuer) {
    if (stripos($issuer->get('name'), 'Google') !== false) {
        $google_issuer = $issuer;
        break;
    }
}

if (!$google_issuer) {
    try {
        $google_issuer = \core\oauth2\api::create_standard_issuer('google');
        $google_issuer->set('enabled', 1);
        $google_issuer->set('showonloginpage', 1);
        $google_issuer->update();
        cli_writeln("[✓] Created Standard Google OAuth2 Issuer (ID: {$google_issuer->get('id')})");
    } catch (\Exception $e) {
        cli_writeln("[!] Error creating Google issuer: " . $e->getMessage());
    }
} else {
    $google_issuer->set('enabled', 1);
    $google_issuer->set('showonloginpage', 1);
    $google_issuer->update();
    cli_writeln("[✓] Google OAuth2 Issuer configured & enabled on login page (ID: {$google_issuer->get('id')})");
}

// 5. Purge caches so changes take effect immediately
purge_all_caches();
cli_writeln("[✓] Purged all Moodle caches.");

cli_writeln("\nAuthentication configuration completed successfully.");
