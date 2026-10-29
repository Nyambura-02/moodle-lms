# GlobeCon Convergence Solutions - Learning Management System (LMS)

[![Moodle Version](https://img.shields.io/badge/Moodle-4.5%20LTS-orange.svg)](https://moodle.org)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.11%20LTS-teal.svg)](https://mariadb.org)
[![License](https://img.shields.io/badge/License-GPLv3-green.svg)](LICENSE)

An enterprise corporate learning portal developed for **GlobeCon Convergence Solutions**, built natively on **Moodle 4.5 LTS (Long Term Support)** with custom corporate branding, role-based access control, secure assessment workflows, and production deployment playbooks.

---

## Quick Navigation
- [System Architecture](docs/ARCHITECTURE.md)
- [Local Installation & Startup](docs/LOCAL_DEVELOPMENT.md)
- [Production Deployment Guide](docs/PRODUCTION_DEPLOYMENT.md)
- [Roles & Permissions Governance](docs/ROLES_PERMISSIONS.md)
- [Sample Course Curriculum](docs/SAMPLE_COURSE_SPEC.md)
- [Cybersecurity Hardening & Checklist](docs/SECURITY.md)
- [Backup & Disaster Recovery](docs/BACKUP_RECOVERY.md)
- [Quality Assurance & Testing Report](docs/TESTING_REPORT.md)
- [Git & Repository Management](docs/GIT_SETUP.md)

---

## 1. Quick Start (Local Development)

### Prerequisites
- Windows 11 (or 10) 64-bit
- Web browser (Chrome, Edge, Firefox)

### Starting the LMS
To start the Apache web server and MariaDB database services:
1. Double-click `start_moodle.bat` in the project root (or run `powershell scripts\Start-Moodle.ps1`).
2. Open your web browser and navigate to:
   ```
   http://localhost:8000
   ```

### Default Credentials (Local Test Environment)
| Role | Username | Password | Purpose |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `globecon_admin` | `GlobeCon@2026!Secure` | Full platform configuration, user management, and audit logs. |
| **Trainer / Instructor** | `trainer_jane` | `Trainer@2026!Pass` | Course authoring, content upload, question bank, and grading. |
| **Learner / Employee** | `learner_john` | `Learner@2026!Pass` | Course navigation, viewing notes/videos, quiz attempts, progress tracking. |

### Stopping the LMS
Double-click `stop_moodle.bat` in the project root (or run `powershell scripts\Stop-Moodle.ps1`).

---

## 2. Core Features Implemented

- **GlobeCon Corporate Theme (`theme_globecon`)**: Clean corporate navy and cyan aesthetic, responsive card-based layout, branded login portal, accessible typography, and configurable admin color pickers.
- **Sample Demonstration Course (`GC-SEC-101`)**: "GlobeCon Fundamentals: Corporate Security & Operational Excellence" featuring:
  - Information Security Policy Handbook (Document resource).
  - Phishing & Social Engineering Defense (Responsive video player).
  - Knowledge Assessment Quiz (Single choice, True/False, Short Answer, Essay).
  - Pinned announcement in the News Forum.
  - Corporate training calendar event with automated reminders.
- **Strict Role-Based Access Control (RBAC)**: Least-privilege separation between Admins, Trainers, and Learners. Gradebooks, admin dashboards, and student assessment answers are strictly isolated.
- **Containerized Production Stack**: `docker-compose.yml` pre-configured for MariaDB 10.11 and Moodle 4.5 with persistent volumes, network isolation, and healthchecks.

---

## 3. Project Directory Structure

```
moodle-lms/
├── .env.example                  # Environment configuration template
├── .gitignore                    # Version control exclusion rules
├── docker-compose.yml            # Containerized deployment blueprint
├── README.md                     # Main project guide
├── start_moodle.bat              # One-click Windows server launcher
├── stop_moodle.bat               # One-click Windows server stop script
├── docs/                         # Comprehensive technical documentation
│   ├── ARCHITECTURE.md           # Architecture blueprint and tier diagrams
│   ├── BACKUP_RECOVERY.md        # Backup routines and restoration drill
│   ├── GIT_SETUP.md              # Git repository management and secrets protection
│   ├── LOCAL_DEVELOPMENT.md      # Local environment operations manual
│   ├── PRODUCTION_DEPLOYMENT.md  # Production server prerequisites, HTTPS & Nginx
│   ├── ROLES_PERMISSIONS.md      # RBAC matrices and capability enforcement
│   ├── SAMPLE_COURSE_SPEC.md     # Demonstration course & quiz curriculum
│   ├── SECURITY.md               # Cybersecurity hardening and compliance checklist
│   └── TESTING_REPORT.md         # 15-point verification and test log
├── scripts/                      # Automation and maintenance scripts
│   ├── seed_globecon_demo.php    # Automated course, user, quiz, and event seeder
│   ├── Start-Moodle.ps1          # PowerShell service startup script
│   ├── Stop-Moodle.ps1           # PowerShell service shutdown script
│   └── Test-MoodleLogins.ps1     # Automated RBAC verification test suite
├── theme/
│   └── globecon/                 # Custom GlobeCon corporate theme
│       ├── classes/              # Theme PHP classes
│       ├── lang/en/              # Language strings
│       ├── pix/                  # Corporate SVG logos and icons
│       ├── scss/                 # Custom SCSS post-stylesheet
│       ├── config.php            # Theme inheritance from Boost
│       ├── lib.php               # Theme SCSS injection & asset handlers
│       ├── settings.php          # Admin color pickers and asset uploaders
│       └── version.php           # Moodle theme metadata
└── server/                       # Local portable runtime (Moodle core, Apache, DB, PHP)
```
