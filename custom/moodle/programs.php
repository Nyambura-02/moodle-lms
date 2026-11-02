<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon Convergence Solutions
// Official Enterprise & Technical Curriculum Catalog

require_once(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');

$PAGE->set_url(new moodle_url('/programs.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Curriculum & Technical Programs | GlobeCon Convergence Solutions');
$PAGE->set_heading('GlobeCon Convergence Solutions • Programs & Training Tracks');

$isguest = isguestuser();
$islogged = isloggedin() && !$isguest;

// Defined Official Course Catalog
$official_programs = [
    [
        'code' => 'CORP-SEC-001',
        'title' => 'GlobeCon Fundamentals: Corporate Security & Operational Excellence',
        'desc' => 'Comprehensive security certification covering acceptable device use, phishing detection, multi-factor authentication, data privacy, and rapid incident escalation protocols for GlobeCon Convergence Solutions employees.',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'Corporate Security'
    ],
    [
        'code' => 'JR-CODE-101',
        'title' => 'Creative Computing & Story Coding (Ages 6–10)',
        'desc' => 'Fun, visual block-based programming with Scratch-style puzzles, animated stories, and digital safety basics for young minds.',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'Computing Foundations'
    ],
    [
        'code' => 'MS-STEM-201',
        'title' => 'STEM Robotics & Web Makers (Ages 11–14)',
        'desc' => 'Hands-on introduction to building web pages, understanding algorithms, sensor mechanics, and collaborative digital projects.',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'STEM & Robotics'
    ],
    [
        'code' => 'HS-CS-301',
        'title' => 'Python Data Science & Cybersecurity Foundations (Ages 15–18)',
        'desc' => 'Comprehensive Python syntax, algorithmic problem solving, network defense, ethical hacking concepts, and digital portfolio building.',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'Data Science & Cyber'
    ],
    [
        'code' => 'UNI-DEV-401',
        'title' => 'Full-Stack Web Development & Cloud Systems (Ages 18–21)',
        'desc' => 'Modern API design, TypeScript, containerized cloud deployments, microservices, and industry capstone project preparation.',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'Cloud & Full-Stack'
    ],
    [
        'code' => 'A1, A2, A6, B1, B6, B7, C5, D6',
        'title' => 'Computational Thinking and Block Coding (Scratch)',
        'desc' => 'Hands-on block coding and algorithmic problem solving with Scratch. Modules: A1, A2, A6 (Ages 6–9), B1, B6, B7 (Ages 10–13), C5 (Ages 14–17), D6 (School Leavers).',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'Computational Thinking'
    ],
    [
        'code' => 'B2, C1, D5',
        'title' => 'Web Programming',
        'desc' => 'Frontend and full-stack web development with HTML5, CSS3, JavaScript, and backend integration. Modules: B2 (Ages 10–13), C1 (Ages 14–17), D5 (School Leavers).',
        'track' => 'Recommended For You',
        'track_class' => 'badge bg-warning text-dark fw-bold',
        'category' => 'Web Development'
    ],
    [
        'code' => 'C2',
        'title' => 'Text-Based Programming (Python)',
        'desc' => 'Core Python syntax, data structures, algorithms, and practical scripting for high school innovators. Module: C2.',
        'track' => 'Recommended For You',
        'track_class' => 'badge bg-warning text-dark fw-bold',
        'category' => 'Python Programming'
    ],
    [
        'code' => 'A3, B3, B8',
        'title' => 'Robotics and Physical Computing',
        'desc' => 'Interactive micro-controllers, sensors, robotics kits, and physical hardware logic. Modules: A3 (Ages 6–9), B3, B8 (Ages 10–13).',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'Hardware & Robotics'
    ],
    [
        'code' => 'A4, B4, C3, D3',
        'title' => 'Artificial Intelligence and Chatbots',
        'desc' => 'Machine learning principles, generative AI, LLM prompting, and practical chatbot deployment. Modules: A4 (Ages 6–9), B4 (Ages 10–13), C3 (Ages 14–17), D3 (School Leavers).',
        'track' => 'Recommended For You',
        'track_class' => 'badge bg-warning text-dark fw-bold',
        'category' => 'Artificial Intelligence'
    ],
    [
        'code' => 'A5, B5, C4, D4',
        'title' => 'Cyber Security and Online Safety',
        'desc' => 'Digital safety, defensive cybersecurity, encryption basics, ethical hacking, and threat mitigation. Modules: A5 (Ages 6–9), B5 (Ages 10–13), C4 (Ages 14–17), D4 (School Leavers).',
        'track' => 'Recommended For You',
        'track_class' => 'badge bg-warning text-dark fw-bold',
        'category' => 'Cybersecurity'
    ],
    [
        'code' => 'D1, D2',
        'title' => 'Digital and Workplace IT Skills',
        'desc' => 'Cloud workplace productivity, IT infrastructure, collaboration tools, and professional technical readiness. Modules: D1, D2.',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'Workplace IT'
    ],
    [
        'code' => 'cybersec',
        'title' => 'Cybersecurity',
        'desc' => 'Practical cybersecurity foundations, threat modeling, security governance, and network defense.',
        'track' => 'Standard Track',
        'track_class' => 'badge bg-primary text-white',
        'category' => 'Cybersecurity'
    ]
];

echo $OUTPUT->header();
?>

<div class="globecon-programs-standalone-page container-fluid py-3">

    <!-- Top Action & Navigation Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo $CFG->wwwroot; ?>/" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa fa-home me-1"></i> Home
            </a>
            <?php if ($islogged): ?>
                <a href="<?php echo $CFG->wwwroot; ?>/my/" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #6366f1 100%); border: none;">
                    <i class="fa fa-th-large me-1"></i> My Dashboard
                </a>
            <?php else: ?>
                <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #6366f1 100%); border: none;">
                    <i class="fa fa-sign-in me-1"></i> Sign In to Portal
                </a>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2 small">
            <span class="text-muted"><i class="fa fa-phone text-primary me-1"></i> For inquiries:</span>
            <a href="tel:+254116993399" class="fw-bold text-dark text-decoration-none">+254 116 993 399</a>
        </div>
    </div>

    <!-- Header Hero Banner (Blue & Violet Corporate Aesthetic) -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 text-white overflow-hidden" 
         style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 75%, #6366f1 100%);">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-20 px-3 py-1 rounded-pill mb-3">
                        <i class="fa fa-graduation-cap text-warning"></i>
                        <span class="small fw-semibold text-white">GlobeCon Convergence Solutions</span>
                    </div>

                    <h1 class="display-6 fw-bold mb-3 text-white">
                        Enterprise &amp; Technical Programs Catalog
                    </h1>

                    <p class="lead text-white-50 mb-4" style="font-size: 1.1rem; line-height: 1.6;">
                        Accredited technical pathways, corporate security modules, full-stack cloud engineering, and foundational computing skills designed and certified for GlobeCon Convergence Solutions learners and enterprise staff.
                    </p>

                    <div class="d-flex align-items-center gap-3 text-white-50 small flex-wrap">
                        <span><i class="fa fa-check-circle text-info me-1"></i> 13 Approved Curriculum Tracks</span>
                        <span>&bull;</span>
                        <span><i class="fa fa-shield text-warning me-1"></i> Industry Certified</span>
                        <span>&bull;</span>
                        <span><i class="fa fa-headphones text-success me-1"></i> Inquiries: +254 116 993 399</span>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <?php if (!$islogged): ?>
                        <div class="bg-white bg-opacity-10 p-4 rounded-3 border border-white border-opacity-20 text-start">
                            <h6 class="fw-bold text-white mb-2"><i class="fa fa-lock me-1 text-warning"></i> Ready to Access Courses?</h6>
                            <p class="small text-white-50 mb-3">Students log in with Registration Number; staff and trainers log in with company email.</p>
                            <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="btn btn-light fw-bold text-primary w-100 py-2 rounded-2 shadow-sm">
                                Go to Sign-In Portal <i class="fa fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="bg-white bg-opacity-10 p-4 rounded-3 border border-white border-opacity-20 text-start">
                            <h6 class="fw-bold text-white mb-1"><i class="fa fa-user-check me-1 text-success"></i> Authenticated Session</h6>
                            <p class="small text-white-50 mb-3"><?php echo s(fullname($USER)); ?> &bull; <?php echo s($USER->email); ?></p>
                            <a href="<?php echo $CFG->wwwroot; ?>/my/" class="btn btn-light fw-bold text-primary w-100 py-2 rounded-2 shadow-sm">
                                Open Dashboard <i class="fa fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Programs Catalog Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-dark mb-0">
                        <i class="fa fa-book text-primary me-2"></i> Official Curriculum Tracks &amp; Course Codes
                    </h4>
                    <p class="text-muted small mb-0">Approved courses, certifications, and technical tracks for GlobeCon Convergence Solutions</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-semibold">
                    13 Active Programs
                </span>
            </div>
        </div>
        <div class="card-body px-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 28%;">Course Title &amp; Domain</th>
                            <th style="width: 20%;">Course Code / Modules</th>
                            <th style="width: 36%;">Overview &amp; Scope</th>
                            <th style="width: 16%;" class="text-end">Program Track</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($official_programs as $prog): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo s($prog['title']); ?></div>
                                <span class="badge bg-light text-secondary border small mt-1"><?php echo s($prog['category']); ?></span>
                            </td>
                            <td>
                                <span class="font-monospace small px-2 py-1 bg-light rounded border text-primary fw-semibold">
                                    <?php echo s($prog['code']); ?>
                                </span>
                            </td>
                            <td>
                                <p class="text-muted small mb-0" style="line-height: 1.45;">
                                    <?php echo s($prog['desc']); ?>
                                </p>
                            </td>
                            <td class="text-end">
                                <span class="<?php echo $prog['track_class']; ?> rounded-pill px-3 py-1 small">
                                    <?php echo s($prog['track']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
echo $OUTPUT->footer();
