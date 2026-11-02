<?php
// ==============================================================================
// GlobeCon Academy - Age 6-21 Curriculum & Theme Configuration Seeding Script
// ==============================================================================

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../server/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/page/locallib.php');

global $DB, $CFG;

\core\cron::setup_user();

cli_heading("GlobeCon Academy - Seeding Ages 6-21 Curriculum & Theme Branding");

// 1. Update Site Fullname and Shortname
$sitecourse = $DB->get_record('course', ['id' => SITEID]);
if ($sitecourse) {
    $sitecourse->fullname = 'GlobeCon Academy Learning Portal (Ages 6–21)';
    $sitecourse->shortname = 'GlobeCon Academy';
    $sitecourse->summary = '<p>A modern, interactive learning management system designed for students, educators, and young professionals aged 6 to 21.</p>';
    $DB->update_record('course', $sitecourse);
    cli_writeln("[✓] Updated Site Name: {$sitecourse->fullname}");
}

// 2. Set Theme Brand Colors (Royal Blue #2563eb, Sky Cyan #0ea5e9)
set_config('brandcolor', '#2563eb', 'theme_globecon');
set_config('accentcolor', '#0ea5e9', 'theme_globecon');
cli_writeln("[✓] Set Theme Colors: Brand=#2563eb, Accent=#0ea5e9");

// 3. Ensure Auth Settings: Email login, Self-registration, OAuth2
set_config('auth_allowemail', 1);
set_config('registerauth', 'email');
$current_auth = get_config('core', 'auth');
$auth_array = explode(',', $current_auth);
foreach (['manual', 'email', 'oauth2'] as $p) {
    if (!in_array($p, $auth_array)) {
        $auth_array[] = $p;
    }
}
set_config('auth', implode(',', array_filter($auth_array)));
cli_writeln("[✓] Auth configured: AllowEmail=1, RegisterAuth=email, AuthPlugins=" . get_config('core', 'auth'));

// 4. Create Age-Bracket Categories & Courses
$age_tracks = [
    [
        'category' => 'Junior Explorers (Ages 6–10)',
        'cat_desc' => 'Visual programming, digital storytelling, math adventures, and safe online exploration for elementary school learners.',
        'coursename' => 'Creative Computing & Story Coding (Ages 6–10)',
        'shortname' => 'JR-CODE-101',
        'summary' => '<p><span class="badge bg-warning text-dark me-2">Ages 6–10</span> Fun, visual block-based programming with Scratch-style puzzles, animated stories, and digital safety basics for young minds.</p>',
    ],
    [
        'category' => 'Middle School Innovators (Ages 11–14)',
        'cat_desc' => 'Robotics, game logic, HTML/CSS website foundations, and science inquiry for intermediate students.',
        'coursename' => 'STEM Robotics & Web Makers (Ages 11–14)',
        'shortname' => 'MS-STEM-201',
        'summary' => '<p><span class="badge bg-info text-dark me-2">Ages 11–14</span> Hands-on introduction to building web pages, understanding algorithms, sensor mechanics, and collaborative digital projects.</p>',
    ],
    [
        'category' => 'Senior Scholars & High School (Ages 15–18)',
        'cat_desc' => 'Computer science, Python programming, cybersecurity, data literacy, and college prep for high school students.',
        'coursename' => 'Python Data Science & Cybersecurity Foundations (Ages 15–18)',
        'shortname' => 'HS-CS-301',
        'summary' => '<p><span class="badge bg-primary text-white me-2">Ages 15–18</span> Comprehensive Python syntax, algorithmic problem solving, network defense, ethical hacking concepts, and digital portfolio building.</p>',
    ],
    [
        'category' => 'Collegiate & Young Adult (Ages 18–21)',
        'cat_desc' => 'Advanced software engineering, cloud architectures, DevOps, professional certifications, and career launchpads.',
        'coursename' => 'Full-Stack Web Development & Cloud Systems (Ages 18–21)',
        'shortname' => 'UNI-DEV-401',
        'summary' => '<p><span class="badge bg-dark text-white me-2">Ages 18–21</span> Modern API design, TypeScript, containerized cloud deployments, microservices, and industry capstone project preparation.</p>',
    ],
];

foreach ($age_tracks as $track) {
    // Check or create category
    $cat = $DB->get_record('course_categories', ['name' => $track['category']]);
    if (!$cat) {
        $cat = core_course_category::create([
            'name' => $track['category'],
            'description' => $track['cat_desc'],
            'descriptionformat' => FORMAT_HTML,
        ]);
        cli_writeln("[+] Created Category: {$track['category']}");
    }

    // Check or create course
    $c = $DB->get_record('course', ['shortname' => $track['shortname']]);
    if (!$c) {
        $cdata = new stdClass();
        $cdata->fullname = $track['coursename'];
        $cdata->shortname = $track['shortname'];
        $cdata->category = $cat->id;
        $cdata->format = 'topics';
        $cdata->numsections = 3;
        $cdata->summary = $track['summary'];
        $cdata->summaryformat = FORMAT_HTML;
        $cdata->showgrades = 1;
        $cdata->enablecompletion = 1;
        $cdata->startdate = time();
        $cdata->enddate = time() + (180 * 86400);

        $newcourse = create_course($cdata);
        cli_writeln("[+] Created Course: {$track['coursename']} ({$track['shortname']})");
    } else {
        cli_writeln("[✓] Existing Course: {$track['shortname']}");
    }
}

// 5. Purge Caches
purge_all_caches();
cli_writeln("[✓] Purged all Moodle caches.");
cli_writeln("\nGlobeCon Academy Ages 6-21 Seeding Completed Successfully.");
