<?php
// ==============================================================================
// GlobeCon LMS - Demonstration Course, Question Bank & User Seeding Script
// Uses Moodle 4.5 native internal APIs without modifying core code.
// ==============================================================================

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../server/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/page/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/questionlib.php');

global $DB, $CFG, $USER;

// Run with administrator context
\core\cron::setup_user();

cli_heading("GlobeCon LMS - Automated Course, Quiz & User Provisioning");

// ------------------------------------------------------------------------------
// 1. Create or Find Category: "Corporate Training & Compliance"
// ------------------------------------------------------------------------------
$catname = 'Corporate Training & Compliance';
$category = $DB->get_record('course_categories', ['name' => $catname]);
if (!$category) {
    $category = core_course_category::create([
        'name' => $catname,
        'description' => 'Official training programs, onboarding tracks, and compliance certifications for GlobeCon Convergence Solutions.',
        'descriptionformat' => FORMAT_HTML,
    ]);
    cli_writeln("[+] Created Course Category: {$catname}");
} else {
    cli_writeln("[✓] Existing Category Found: {$catname}");
}

// ------------------------------------------------------------------------------
// 2. Create or Find Course: "GlobeCon Fundamentals: Corporate Security"
// ------------------------------------------------------------------------------
$coursename = 'GlobeCon Fundamentals: Corporate Security & Operational Excellence';
$shortname = 'GC-SEC-101';
$course = $DB->get_record('course', ['shortname' => $shortname]);
if (!$course) {
    $coursedata = new stdClass();
    $coursedata->fullname = $coursename;
    $coursedata->shortname = $shortname;
    $coursedata->category = $category->id;
    $coursedata->format = 'topics';
    $coursedata->numsections = 3;
    $coursedata->summary = '<p>Comprehensive security certification covering acceptable device use, phishing detection, multi-factor authentication, data privacy, and rapid incident escalation protocols for GlobeCon Convergence Solutions employees.</p>';
    $coursedata->summaryformat = FORMAT_HTML;
    $coursedata->showgrades = 1;
    $coursedata->enablecompletion = 1;
    $coursedata->startdate = time();
    $coursedata->enddate = time() + (90 * 86400); // 90 days validity

    $course = create_course($coursedata);
    cli_writeln("[+] Created Course: {$coursename} ({$shortname}) [ID: {$course->id}]");
} else {
    cli_writeln("[✓] Existing Course Found: {$coursename} [ID: {$course->id}]");
}

// ------------------------------------------------------------------------------
// 3. Create Trainer & Learner Accounts
// ------------------------------------------------------------------------------
function create_or_get_user($username, $password, $firstname, $lastname, $email) {
    global $DB, $CFG;
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$user) {
        $userdata = new stdClass();
        $userdata->auth = 'manual';
        $userdata->confirmed = 1;
        $userdata->username = $username;
        $userdata->password = hash_internal_user_password($password);
        $userdata->firstname = $firstname;
        $userdata->lastname = $lastname;
        $userdata->email = $email;
        $userdata->city = 'Nairobi';
        $userdata->country = 'KE';
        $userdata->lang = 'en';
        $userdata->timezone = 'Africa/Nairobi';
        $userdata->mnethostid = $CFG->mnet_localhost_id;
        $userdata->timecreated = time();
        $userid = user_create_user($userdata, false, false);
        cli_writeln("[+] Created User: {$username} ({$email})");
        return $DB->get_record('user', ['id' => $userid]);
    }
    cli_writeln("[✓] User Found: {$username}");
    return $user;
}

$trainer = create_or_get_user('trainer_jane', 'Trainer@2026!Pass', 'Jane', 'Doe', 'jane.trainer@globecon.local');
$learner = create_or_get_user('learner_john', 'Learner@2026!Pass', 'John', 'Smith', 'john.smith@globecon.local');

// ------------------------------------------------------------------------------
// 4. Enrol Trainer (editingteacher) and Learner (student)
// ------------------------------------------------------------------------------
$enrolplugin = enrol_get_plugin('manual');
$enrolinstances = enrol_get_instances($course->id, true);
$manualinstance = null;
foreach ($enrolinstances as $instance) {
    if ($instance->enrol === 'manual') {
        $manualinstance = $instance;
        break;
    }
}
if (!$manualinstance) {
    $manualid = $enrolplugin->add_instance($course);
    $manualinstance = $DB->get_record('enrol', ['id' => $manualid]);
}

$context = context_course::instance($course->id);
$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
$studentrole = $DB->get_record('role', ['shortname' => 'student']);

// Enrol Trainer
$enrolplugin->enrol_user($manualinstance, $trainer->id, $teacherrole->id);
cli_writeln("[+] Enrolled {$trainer->username} as Trainer (editingteacher).");

// Enrol Learner
$enrolplugin->enrol_user($manualinstance, $learner->id, $studentrole->id);
cli_writeln("[+] Enrolled {$learner->username} as Learner (student).");

// ------------------------------------------------------------------------------
// 5. Populate Course Sections & Activities
// ------------------------------------------------------------------------------
// Module: Page 1 (Handbook)
$existingPage = $DB->get_record('page', ['course' => $course->id, 'name' => 'GlobeCon Information Security Policy Handbook']);
if (!$existingPage) {
    $page = new stdClass();
    $page->course = $course->id;
    $page->name = 'GlobeCon Information Security Policy Handbook';
    $page->intro = '<p>Mandatory corporate security policies for all personnel.</p>';
    $page->introformat = FORMAT_HTML;
    $page->content = '<h3>GlobeCon Corporate Security & Acceptable Use Policy</h3>
<p><strong>Effective Date:</strong> 2026-2027<br><strong>Classification:</strong> Confidential - Internal Corporate Use</p>
<hr>
<h4>1. Device Protection & Clear Desk Standard</h4>
<ul>
  <li>All laptops, mobile terminals, and workstations must be locked whenever left unattended (Win + L).</li>
  <li>Confidential customer documents must not be left on desks overnight or in unsecured conference rooms.</li>
  <li>Removable USB drives from unknown origins are strictly prohibited without prior IT Security sanitization.</li>
</ul>
<h4>2. Password Complexity & MFA Enforcement</h4>
<ul>
  <li>Minimum length: 12 characters combining upper, lower, numbers, and symbols.</li>
  <li>Multi-Factor Authentication (MFA) is mandatory across all enterprise tools, email, and VPN endpoints.</li>
  <li>Never share passwords via chat, email, or post-it notes.</li>
</ul>';
    $page->contentformat = FORMAT_HTML;
    $page->timemodified = time();
    $page->id = $DB->insert_record('page', $page);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'page']);
    $cm->instance = $page->id;
    $cm->section = 1;
    $cm->idnumber = 'SEC-POLICY-DOC';
    $cm->added = time();
    $cm->id = $DB->insert_record('course_modules', $cm);
    
    // Update section sequence
    course_add_cm_to_section($course, $cm->id, 1);
    cli_writeln("[+] Added Activity: Policy Handbook (Page)");
}

// Module: Video Resource (Page with HTML5 responsive video & guide)
$existingVideo = $DB->get_record('page', ['course' => $course->id, 'name' => 'Video Walkthrough: Recognizing Phishing & Social Engineering']);
if (!$existingVideo) {
    $videoPage = new stdClass();
    $videoPage->course = $course->id;
    $videoPage->name = 'Video Walkthrough: Recognizing Phishing & Social Engineering';
    $videoPage->intro = '<p>Interactive video training on identifying simulated and real phishing vectors.</p>';
    $videoPage->introformat = FORMAT_HTML;
    $videoPage->content = '<div class="alert alert-info" role="alert">
  <h4 class="alert-heading">Training Video Demonstration</h4>
  <p>Watch this practical overview illustrating real-world social engineering tactics and email indicators.</p>
</div>
<div class="video-container text-center my-4" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #0f2942; border-radius: 8px;">
  <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #ffffff;">
    <svg width="64" height="64" viewBox="0 0 24 24" fill="#38bdf8"><path d="M8 5v14l11-7z"/></svg>
    <p class="mt-2 font-weight-bold">Interactive Educational Video Stream</p>
    <p class="small text-light">Simulated Phishing Analysis & Defense Walkthrough (Duration: 8m 30s)</p>
  </div>
</div>
<h4>Key Takeaways:</h4>
<ol>
  <li>Inspect email sender domains carefully for typosquatting (e.g., <code>@g1obecon.com</code> instead of <code>@globecon.com</code>).</li>
  <li>Avoid clicking embedded password reset links from unexpected notifications.</li>
  <li>Report suspicious messages immediately using the internal IT Security Incident button.</li>
</ol>';
    $videoPage->contentformat = FORMAT_HTML;
    $videoPage->timemodified = time();
    $videoPage->id = $DB->insert_record('page', $videoPage);

    $cm2 = new stdClass();
    $cm2->course = $course->id;
    $cm2->module = $DB->get_field('modules', 'id', ['name' => 'page']);
    $cm2->instance = $videoPage->id;
    $cm2->section = 2;
    $cm2->idnumber = 'SEC-VIDEO-01';
    $cm2->added = time();
    $cm2->id = $DB->insert_record('course_modules', $cm2);
    course_add_cm_to_section($course, $cm2->id, 2);
    cli_writeln("[+] Added Activity: Video Walkthrough (Responsive Video Stream)");
}

// ------------------------------------------------------------------------------
// 6. Create Quiz & Questions (Question Bank)
// ------------------------------------------------------------------------------
$existingQuiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => 'GlobeCon Security & Compliance Final Assessment']);
if (!$existingQuiz) {
    $quiz = new stdClass();
    $quiz->course = $course->id;
    $quiz->name = 'GlobeCon Security & Compliance Final Assessment';
    $quiz->intro = '<p>Evaluate your knowledge of GlobeCon Convergence Solutions information security standards, device rules, and incident reporting. Pass mark: 80%.</p>';
    $quiz->introformat = FORMAT_HTML;
    $quiz->timeopen = time() - 3600;
    $quiz->timeclose = time() + (30 * 86400); // Open for 30 days
    $quiz->timelimit = 1200; // 20 minutes
    $quiz->attempts = 2;
    $quiz->grademethod = QUIZ_GRADEHIGHEST;
    $quiz->grade = 10.0;
    $quiz->sumgrades = 9.0;
    $quiz->preferredbehaviour = 'deferredfeedback';
    $quiz->canredoquestions = 0;
    $quiz->attempts = 2;
    $quiz->overduehandling = 'autosubmit';
    $quiz->timecreated = time();
    $quiz->timemodified = time();
    $quiz->id = $DB->insert_record('quiz', $quiz);

    $cmQuiz = new stdClass();
    $cmQuiz->course = $course->id;
    $cmQuiz->module = $DB->get_field('modules', 'id', ['name' => 'quiz']);
    $cmQuiz->instance = $quiz->id;
    $cmQuiz->section = 3;
    $cmQuiz->idnumber = 'SEC-FINAL-QUIZ';
    $cmQuiz->added = time();
    $cmQuiz->id = $DB->insert_record('course_modules', $cmQuiz);
    course_add_cm_to_section($course, $cmQuiz->id, 3);
    cli_writeln("[+] Created Quiz: {$quiz->name}");

    // Create Question Category in Course Context
    $catdata = new stdClass();
    $catdata->name = 'Security Fundamentals Bank';
    $catdata->contextid = $context->id;
    $catdata->info = 'Corporate security and acceptable use questions.';
    $catdata->infoformat = FORMAT_HTML;
    $catdata->parent = 0;
    $catdata->sortorder = 999;
    $catdata->stamp = make_unique_id_code();
    $catdata->id = $DB->insert_record('question_categories', $catdata);

    // Helper to insert question
    function insert_q($catid, $name, $qtype, $text, $answers, $defaultmark = 1.0) {
        global $DB;
        $q = new stdClass();
        $q->category = $catid;
        $q->name = $name;
        $q->questiontext = $text;
        $q->questiontextformat = FORMAT_HTML;
        $q->generalfeedback = 'Review the GlobeCon Information Security Policy Handbook for further guidance.';
        $q->generalfeedbackformat = FORMAT_HTML;
        $q->defaultmark = $defaultmark;
        $q->qtype = $qtype;
        $q->stamp = make_unique_id_code();
        $q->version = make_unique_id_code();
        $q->timecreated = time();
        $q->timemodified = time();
        $qid = $DB->insert_record('question', $q);

        // Insert answers
        foreach ($answers as $ans) {
            $a = new stdClass();
            $a->question = $qid;
            $a->answer = $ans['text'];
            $a->answerformat = FORMAT_HTML;
            $a->fraction = $ans['fraction'];
            $a->feedback = $ans['feedback'];
            $a->feedbackformat = FORMAT_HTML;
            $DB->insert_record('question_answers', $a);
        }
        return $qid;
    }

    // Q1: Multiple Choice (Single)
    $q1_answers = [
        ['text' => 'An urgent email requesting password verification from a domain with a subtle spelling mistake', 'fraction' => 1.0, 'feedback' => 'Correct! Typosquatting and urgency are key signs of phishing.'],
        ['text' => 'An internal newsletter sent through standard corporate channels', 'fraction' => 0.0, 'feedback' => 'Incorrect. Legitimate internal communication is standard.'],
        ['text' => 'A calendar invite from your scheduled supervisor meeting', 'fraction' => 0.0, 'feedback' => 'Incorrect.'],
    ];
    $q1_id = insert_q($catdata->id, 'Q1: Phishing Indicator', 'multichoice', '<p>Which of the following represents the strongest indicator of a potential phishing email?</p>', $q1_answers, 1.0);

    // Q2: True / False
    $q2_answers = [
        ['text' => 'True', 'fraction' => 0.0, 'feedback' => 'Incorrect. Passwords and MFA tokens must never be shared.'],
        ['text' => 'False', 'fraction' => 1.0, 'feedback' => 'Correct! Sharing VPN or system credentials violates corporate policy.'],
    ];
    $q2_id = insert_q($catdata->id, 'Q2: Credential Sharing', 'truefalse', '<p>True or False: It is acceptable to share temporary corporate VPN credentials with a teammate over instant messaging if approved verbally.</p>', $q2_answers, 1.0);

    // Q3: Short Answer
    $q3_answers = [
        ['text' => 'PoLP', 'fraction' => 1.0, 'feedback' => 'Correct!'],
        ['text' => 'Principle of Least Privilege', 'fraction' => 1.0, 'feedback' => 'Correct!'],
        ['text' => 'Least Privilege', 'fraction' => 1.0, 'feedback' => 'Correct!'],
    ];
    $q3_id = insert_q($catdata->id, 'Q3: PoLP Acronym', 'shortanswer', '<p>What is the recognized acronym for the security policy that grants users only the minimum access rights necessary to perform their jobs?</p>', $q3_answers, 1.0);

    // Q4: Essay
    $q4_answers = [];
    $q4_id = insert_q($catdata->id, 'Q4: Incident Escalation Steps', 'essay', '<p>Briefly describe the three immediate actions an employee must take upon suspecting that ransomware or malware has infected their workstation.</p>', $q4_answers, 5.0);

    // Link Questions to Quiz using quiz_slots
    $slotnum = 1;
    foreach ([$q1_id, $q2_id, $q3_id, $q4_id] as $qid) {
        $slot = new stdClass();
        $slot->quizid = $quiz->id;
        $slot->slot = $slotnum++;
        $slot->page = 1;
        $slot->requireprevious = 0;
        $slot->maxmark = ($qid === $q4_id) ? 5.0 : 1.0;
        $slot->id = $DB->insert_record('quiz_slots', $slot);

        // question reference
        $qref = new stdClass();
        $qref->usingcontextid = $context->id;
        $qref->component = 'mod_quiz';
        $qref->questionarea = 'slot';
        $qref->itemid = $slot->id;
        $qref->questionbankentryid = $qid;
        $qref->version = null;
        $DB->insert_record('question_references', $qref);
    }
    cli_writeln("[+] Added 4 Sample Questions to Quiz slots with Question Bank mapping.");
}

// ------------------------------------------------------------------------------
// 7. Create Course Calendar Event
// ------------------------------------------------------------------------------
$eventdata = new stdClass();
$eventdata->name = 'GlobeCon Live Security Briefing & Q&A Session';
$eventdata->description = '<p>Live webinar for all employees on emerging cybersecurity threats, safe browsing, and corporate VPN best practices.</p>';
$eventdata->format = FORMAT_HTML;
$eventdata->courseid = $course->id;
$eventdata->userid = $trainer->id;
$eventdata->eventtype = 'course';
$eventdata->timestart = time() + (3 * 86400); // 3 days from now
$eventdata->timeduration = 3600; // 1 hour
$event = calendar_event::create($eventdata, false);
cli_writeln("[+] Created Course Calendar Event: {$eventdata->name}");

// ------------------------------------------------------------------------------
// 8. Create Course Announcement
// ------------------------------------------------------------------------------
require_once($CFG->dirroot . '/mod/forum/lib.php');
$newsforum = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news']);
if ($newsforum) {
    $existingDiscussion = $DB->get_record('forum_discussions', ['forum' => $newsforum->id, 'name' => 'Mandatory Annual Security Certification Due Date']);
    if (!$existingDiscussion) {
        $discussion = new stdClass();
        $discussion->course = $course->id;
        $discussion->forum = $newsforum->id;
        $discussion->name = 'Mandatory Annual Security Certification Due Date';
        $discussion->intro = '<p>Welcome to GlobeCon Fundamentals. All active employees must complete the training modules and achieve an 80% passing score on the final assessment by the scheduled deadline. For technical inquiries or questions, contact the Training & Development team.</p>';
        $discussion->introformat = FORMAT_HTML;
        $discussion->userid = $trainer->id;
        $discussion->groupid = -1;
        $discussion->timemodified = time();
        $discussion->usermodified = $trainer->id;
        $discussion->pinned = FORUM_DISCUSSION_PINNED;
        $discid = forum_add_discussion($discussion);
        cli_writeln("[+] Published Announcement in News Forum (ID: {$discid})");
    }
}

cli_writeln("\n==========================================================");
cli_writeln(" Demonstration Data Seeding Completed Successfully! ");
cli_writeln("==========================================================");
