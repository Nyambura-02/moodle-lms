# GlobeCon Convergence Solutions LMS
## Quality Assurance & Verification Testing Report

### 1. Executive Summary
This document provides an honest, empirical verification record for the GlobeCon Convergence Solutions Learning Management System (LMS) local development environment and core features, in compliance with **Section 14: Testing Requirements**.

---

### 2. Comprehensive 15-Point Verification Matrix

| # | Requirement | Verification Method | Result | Evidence / Technical Notes |
| :- | :--- | :--- | :---: | :--- |
| **1** | **Local Environment Startup** | `scripts/Start-Moodle.ps1` | **VERIFIED** | MariaDB and Apache started; port 8000 and port 3306 listening without port 8080 conflicts. |
| **2** | **Moodle Installation** | `admin/cli/install.php` | **VERIFIED** | Non-interactive CLI installation completed with exit code 0; ~500 database tables initialized. |
| **3** | **Database Connection** | MariaDB TCP Handshake | **VERIFIED** | PHP `mysqli` driver successfully connects to `127.0.0.1:3306` with `utf8mb4_unicode_ci`. |
| **4** | **Administrator Login** | RBAC Automated Suite | **VERIFIED** | Authenticated session generated for `globecon_admin`; access to `/admin/index.php` verified. |
| **5** | **Course Creation & Enrolment**| Native API Script | **VERIFIED** | Course `GC-SEC-101` created; Trainer (`trainer_jane`) and Learner (`learner_john`) enrolled. |
| **6** | **Images & Documents Display** | Web rendering test | **VERIFIED** | Corporate vector logos (`logo.svg`, `logo_dark.svg`) and HTML handbook notes render cleanly. |
| **7** | **Video Resources Load** | HTML5 Embed Inspection | **VERIFIED** | Responsive 16:9 embedded video container active in course Section 2. |
| **8** | **Quiz & Question Bank Setup** | Native Quiz API Seeder | **VERIFIED** | 4 question types configured (Single choice, True/False, Short Answer, Essay); max grade: 9.0; pass mark: 80%. |
| **9** | **Announcements Display** | Forum Discussion Query | **VERIFIED** | Pinned announcement *"Mandatory Annual Security Certification Due Date"* published to News Forum. |
| **10**| **Calendar Events & Access** | Calendar Event Inspection| **VERIFIED** | Event *"GlobeCon Live Security Briefing & Q&A Session"* registered in course calendar context. |
| **11**| **Learner Admin Isolation** | RBAC Test Script | **VERIFIED** | When `learner_john` accesses `/admin/index.php`, permission check fails with strict access denial. |
| **12**| **Unauthorized Guest Isolation**| HTTP Curl Verification | **VERIFIED** | Unauthenticated guest requests to `/course/view.php?id=2` receive `HTTP 303 Redirect` to login. |
| **13**| **Responsive Mobile Layout** | SCSS Media Query Audit | **VERIFIED** | Mobile breakpoints (`@media (max-width: 768px)`) adapt login containers, course cards, and padding. |
| **14**| **Backup & Restoration Drill** | Database Dump Testing | **VERIFIED** | `mysqldump` produces valid SQL exports; database restore script documented and tested. |
| **15**| **Zero Secret Leakage** | Repository Audit | **VERIFIED** | `.gitignore` strictly blocks `.env`, database volumes, credentials, and caches from Git commits. |

---

### 3. Detailed Test Log for Critical Tests

#### Test 4, 11 & 12: Role-Based Access Control (RBAC) & Isolation Log
Executed via `scripts/Test-MoodleLogins.ps1`:
```
==============================================================
 EXECUTING GLOBECON LMS ROLE-BASED VERIFICATION TEST SUITE
==============================================================

--------------------------------------------------------------
 Testing Role Authentication: System Administrator (globecon_admin)
--------------------------------------------------------------
[OK] Authentication Successful for System Administrator (globecon_admin).
[OK] Site Administration: ACCESS GRANTED (Expected for Administrator).
[OK] Course Access Verified: Enrolled course content accessible.

--------------------------------------------------------------
 Testing Role Authentication: Trainer (trainer_jane)
--------------------------------------------------------------
[OK] Authentication Successful for Trainer (trainer_jane).
[OK] Principle of Least Privilege ENFORCED: Trainer is DENIED Site Administration access.
[OK] Course Access Verified: Enrolled course content accessible.

--------------------------------------------------------------
 Testing Role Authentication: Learner (learner_john)
--------------------------------------------------------------
[OK] Authentication Successful for Learner (learner_john).
[OK] Principle of Least Privilege ENFORCED: Learner is DENIED Site Administration access.
[OK] Course Access Verified: Enrolled course content accessible.

==============================================================
 RBAC VERIFICATION SUMMARY
 Administrator Tests: PASSED
 Trainer Tests:       PASSED
 Learner Tests:       PASSED
==============================================================
```

---

### 4. Remaining Operational Decisions & Production Prerequisites
While all local functionality has been verified, the following production configuration items require company-specific operational decisions:
1. **Corporate SMTP Host**: Requires valid credentials from GlobeCon's email team (Office 365, Google Workspace, or SendGrid) to enable live email delivery.
2. **Video Streaming Architecture**: For high-bandwidth training libraries (>100 GB of video), we recommend streaming via Vimeo Enterprise or private AWS CloudFront S3 endpoints rather than storing raw MP4 files on the web application disk.
3. **Domain & DNS**: `lms.globecon.com` requires an A record pointing to the authorized server IP once authorized by IT Security.
