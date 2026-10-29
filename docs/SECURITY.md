# GlobeCon Convergence Solutions LMS
## Cybersecurity Hardening & Governance Specification

### 1. Security Baseline & Threat Model
The GlobeCon Convergence Solutions Learning Management System (LMS) processes employee professional records, proprietary corporate training materials, and compliance certifications. Cybersecurity is enforced as a foundational operational requirement across application runtime, data storage, network traffic, and access governance.

---

### 2. Core Security Controls Implemented

#### A. Data Directory Isolation (Out of Web Root)
- **Vulnerability Mitigated**: Direct URL retrieval of uploaded user files, sensitive exam answer sheets, and internal session payloads.
- **Implementation**: The Moodle data directory (`moodledata/`) is situated strictly outside the web server's `DocumentRoot`.
  - In local development: `C:\Users\Chuchu\OneDrive\Desktop\moodle lms\server\moodledata` is isolated from `server\moodle`.
  - In production: `/var/www/moodledata` is separated from `/var/www/html` with permissions `0770 www-data:www-data`.
- **Verification**: Direct HTTP navigation to `/moodledata/` yields HTTP 404/403. Files are served exclusively through Moodle's authenticated `pluginfile.php` dispatcher after permission checking.

#### B. Cryptographic Password Handling & Authentication
- **Vulnerability Mitigated**: Credential stuffing, brute-force attacks, and rainbow-table compromises.
- **Implementation**:
  - Passwords are encrypted using native PHP `password_hash()` utilizing salted Argon2id / Bcrypt algorithms.
  - Plaintext passwords are never recorded in database tables, debug logs, or session tokens.
  - Password complexity policy: Minimum 12 characters, at least 1 uppercase letter, 1 lowercase letter, 1 digit, and 1 non-alphanumeric character.
  - Account lockout policy: Automatically locks accounts for 30 minutes after 5 consecutive failed login attempts.

#### C. Cross-Site Request Forgery (CSRF) Mitigation
- **Vulnerability Mitigated**: Unauthorized action execution via forged authenticated browser requests.
- **Implementation**:
  - Moodle's native session key architecture (`$USER->sesskey` / `confirm_sesskey()`) is strictly enforced on all state-changing POST requests.
  - Login forms require unique, single-use `logintoken` parameters.
  - HTTP cookies are configured with `HttpOnly`, `SameSite=Lax`, and `Secure` (under HTTPS) flags.

#### D. Cross-Site Scripting (XSS) & Input Validation
- **Vulnerability Mitigated**: Injected malicious scripts stealing sessions or defacing corporate portals.
- **Implementation**:
  - All database writes pass through Moodle's data validation abstraction (`clean_param()`, `PARAM_TEXT`, `PARAM_INT`).
  - All rendered content is escaped using `format_text()`, `s()`, or `p()`.
  - Content Security Policy (CSP) headers applied via reverse proxy disallow unauthorized inline execution.

#### E. Strict Role-Based Access Control (RBAC) & Isolation
- **Vulnerability Mitigated**: Privilege escalation, unauthorized access to question banks, and grade tampering.
- **Implementation**:
  - System Administration (`moodle/site:config`) is restricted to certified platform administrators.
  - Instructors can only manage assigned courses and cannot inspect other departments' private offerings.
  - Learners can only review their own grades and feedback; the capability `moodle/grade:viewall` is prohibited for student roles.
  - Correct assessment answers are masked until the instructor-configured review date passes.

#### F. Secret Management & Version Control Hygiene
- **Vulnerability Mitigated**: Exposure of database passwords, API tokens, and private keys in Git.
- **Implementation**:
  - Environment templates (`.env.example`) contain only placeholders.
  - Repository `.gitignore` excludes `.env`, `*.sql`, `moodledata/`, `secrets/`, and runtime logs.
  - Production credentials must be injected dynamically via environment variables or protected server configuration files (`config.php`).

---

### 3. Production Security Checklist

| Control Item | Audit Verification Method | Status |
| :--- | :--- | :---: |
| **HTTPS Enforced** | Check for HTTP 301 redirect to HTTPS on port 443 | Required |
| **TLS 1.2+ Only** | Scan using SSL Labs or `testssl.sh` to confirm SSLv3/TLS1.0/1.1 disabled | Required |
| **HSTS Active** | Verify `Strict-Transport-Security: max-age=31536000` header | Required |
| **moodledata Isolated** | Confirm moodledata path is outside Nginx/Apache DocumentRoot | **Verified** |
| **Database Remote Denied** | Verify MariaDB binds only to `127.0.0.1` or internal VPC socket | **Verified** |
| **No Default Passwords** | Verify admin, trainer, and database root passwords are randomized | **Verified** |
| **Git Exclusions** | Check `git status` to ensure `.env` and `config.php` are untracked | **Verified** |
| **Automated Backups** | Verify daily cron runs mysqldump and moodledata sync with encryption | Required |
| **Security Patching** | Track Moodle security advisories; apply minor point updates quarterly | Required |
| **Fail2ban Active** | Configure jail for failed SSH and web authentication spikes | Required |
