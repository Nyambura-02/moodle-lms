# GlobeCon Convergence Solutions LMS
## Roles and Permissions Governance Framework

### 1. Overview and Principle of Least Privilege
In accordance with corporate governance and cybersecurity standards, access to the GlobeCon Convergence Solutions Learning Management System (LMS) is strictly governed by the **Principle of Least Privilege (PoLP)**. Users are granted only the minimum access rights and permissions necessary to perform their assigned learning or business duties.

---

### 2. Role Definitions & Access Matrices

| Capability / Action | System Administrator | Trainer / Course Creator | Learner / Employee |
| :--- | :---: | :---: | :---: |
| **Site Administration Settings** | **Full Access** | Denied | Denied |
| **User Account Provisioning** | **Full Access** | Denied | Denied |
| **Global Role Assignment** | **Full Access** | Denied | Denied |
| **Course Category Management** | **Full Access** | Denied | Denied |
| **Course Creation (Assigned Categories)** | **Full Access** | **Granted** | Denied |
| **Content Upload (PDFs, PPT, Videos)** | **Full Access** | **Granted (Own Courses)** | Denied |
| **Question Bank & Quiz Authoring** | **Full Access** | **Granted (Own Courses)** | Denied |
| **Course Announcements & Events** | **Full Access** | **Granted (Own Courses)** | View Only |
| **Grade Submissions & Manual Marking**| **Full Access** | **Granted (Own Courses)** | Denied |
| **View Other Learners' Grades** | **Full Access** | **Granted (Own Courses)** | **STRICTLY DENIED** |
| **Attempt Quizzes & Submit Work** | Testing Only | Testing Only | **Granted (Enrolled)** |
| **View Personal Progress & Grades** | Full Access | Full Access | **Granted (Self Only)** |
| **System Security & Audit Logs** | **Full Access** | Denied | Denied |

---

### 3. Detailed Role Responsibilities

#### A. System Administrator
- **Scope**: Platform-wide configuration and infrastructure health.
- **Responsibilities**:
  1. Configure global authentication, session timeouts, and password complexity rules.
  2. Provision user accounts and assign administrative or trainer privileges.
  3. Manage corporate course categories and global taxonomy.
  4. Monitor scheduled cron tasks, database health, and security audit logs.
  5. Manage theme settings (branding, logo, color palette).
  6. Oversee backup schedules and disaster recovery testing.
- **Security Safeguards**:
  - Requires complex multi-character passwords (minimum 12 characters).
  - Web sessions enforce strict idle timeouts (30 minutes).
  - Admin accounts must not be shared between team members.

#### B. Trainer / Course Creator (Instructor)
- **Scope**: Course-level instructional delivery and assessment management.
- **Responsibilities**:
  1. Structure learning modules into topical sections.
  2. Upload approved corporate training assets: PDF summaries, slide decks, and external streaming video links.
  3. Maintain and organize Question Banks categorized by subject domain.
  4. Author formative and summative quizzes with automated grading, time limits, and retake controls.
  5. Post official course announcements and schedule deadlines on the course calendar.
  6. Evaluate essay responses, provide constructive feedback, and review course completion reports.
- **Security Safeguards**:
  - Confined strictly to courses where explicitly enrolled with the `editingteacher` or `coursecreator` role.
  - Zero access to system administration, server logs, or global user passwords.
  - Must not disclose question banks or correct answer keys ahead of published review windows.

#### C. Learner / Employee
- **Scope**: Personal professional development and learning compliance.
- **Responsibilities**:
  1. Access enrolled orientation, security, and technical training courses.
  2. Review required documents, download study guides, and watch instructional videos.
  3. Attempt required quizzes and submit assignments prior to published deadlines.
  4. Monitor personal learning progress bars, completion checkmarks, and grade feedback.
  5. Check course announcements and upcoming corporate training calendar dates.
- **Security Safeguards**:
  - Enrolled courses are accessible; unenrolled or private courses are hidden or locked.
  - Gradebook visibility is isolated via Moodle's capability `moodle/grade:viewall` (revoked for learners). Learners can only ever see their own records.
  - Quiz answers and feedback are masked until the trainer's configured review period.

---

### 4. Moodle Capability Enforcement Matrix

To implement these controls within Moodle 4.5, verify the following core capabilities:

| Moodle Capability String | Administrator | Trainer (`editingteacher`) | Learner (`student`) |
| :--- | :---: | :---: | :---: |
| `moodle/site:config` | Allow | Prohibit | Prohibit |
| `moodle/user:create` | Allow | Prohibit | Prohibit |
| `moodle/course:create` | Allow | Allow (in assigned category) | Prohibit |
| `moodle/course:update` | Allow | Allow (within assigned course) | Prohibit |
| `moodle/question:add` | Allow | Allow (within course context) | Prohibit |
| `mod/quiz:viewreports` | Allow | Allow (within course context) | Prohibit |
| `mod/quiz:attempt` | Allow | Allow (for verification) | Allow |
| `moodle/grade:viewall` | Allow | Allow (within course context) | **Prohibit** |
| `moodle/grade:view` | Allow | Allow | Allow (Self Only) |
| `moodle/course:viewhiddenactivities` | Allow | Allow | Prohibit |

---

### 5. Role Setup Verification Procedure
1. Create a designated test Learner account (`test_learner`).
2. Log in as `test_learner`.
3. Verify that the Site Administration navigation link is completely absent.
4. Attempt direct navigation to `/admin/` and verify that access is blocked with an authorization exception.
5. Enter a test course and attempt a quiz; verify that answers for other participants or question bank editing controls cannot be accessed.
