# GlobeCon Convergence Solutions Learning Management System (LMS)
## System Architecture & Environment Blueprint

### 1. Executive Summary
GlobeCon Convergence Solutions requires a secure, modern, highly accessible corporate Learning Management System (LMS) to train employees, manage learning pathways, track assessments, and host verified educational materials. 

To maintain enterprise-grade reliability, compliance, and long-term maintainability, the system is architected on **Moodle 4.5 LTS** (Long Term Support, supported through October 2027), utilizing Moodle's native core modules, question banks, course completion engines, role-based access control (RBAC), and custom theme extensions without modifying any Moodle core code.

---

### 2. High-Level Architecture Diagram

```
+---------------------------------------------------------------------------------+
|                                USER ACCESS TIER                                 |
|                                                                                 |
|   +------------------------+   +------------------------+   +---------------+   |
|   |  Learners / Employees  |   |  Trainers / Instructors|   | System Admins |   |
|   +------------------------+   +------------------------+   +---------------+   |
+---------------------------------------+-----------------------------------------+
                                        | HTTPS / TLS 1.3
                                        v
+---------------------------------------------------------------------------------+
|                        WEB & REVERSE PROXY TIER                                 |
|                                                                                 |
|   - Production: Nginx / Apache 2.4 Reverse Proxy with SSL Termination           |
|   - Local Dev: Apache 2.4 Windows / Docker Gateway (Port 8000)                  |
|   - Security: HSTS, CSP, X-Frame-Options, Rate Limiting                         |
+---------------------------------------+-----------------------------------------+
                                        | FastCGI / PHP-FPM
                                        v
+---------------------------------------------------------------------------------+
|                        APPLICATION RUNTIME TIER                                 |
|                                                                                 |
|   +-------------------------------------------------------------------------+   |
|   |                       Moodle 4.5 LTS Core Engine                        |   |
|   |   - Enrolment & RBAC Engine        - Gradebook & Question Bank Engine   |   |
|   |   - Course & Activity Modules      - Calendar & Announcement Engine     |   |
|   +-------------------------------------------------------------------------+   |
|   |                   GlobeCon Custom Extensions Layer                      |   |
|   |   - theme/globecon (Custom Child Theme based on Boost)                  |   |
|   |   - Brand Assets, Custom Scss, Corporate Navigation & Dashboard Styling |   |
|   +-------------------------------------------------------------------------+   |
|   |                   PHP 8.2+ Runtime & Extensions                         |   |
|   |   - OPcache (JIT enabled)          - mbstring, intl, curl, zip, gd      |   |
|   |   - sodium, xml, soap, exif        - max_input_vars=5000, memory=512M   |   |
|   +-------------------------------------------------------------------------+   |
+----------------------+----------------------------------+-----------------------+
                       |                                  |
      Direct File I/O  |                                  | TCP / UNIX Socket
      (Out of Web Root)|                                  v
+----------------------v--------------+  +----------------v-----------------------+
|          PERSISTENT FILE STORAGE    |  |            DATABASE TIER               |
|                                     |  |                                        |
|   Location: moodledata/             |  |   - MariaDB 10.11 LTS / MySQL 8.0      |
|   - User Uploads (PDF, PPT, DOCX)   |  |   - Charset: utf8mb4                   |
|   - Cached Theme SCSS & CSS Assets  |  |   - Collation: utf8mb4_unicode_ci      |
|   - Session Files (if file-based)   |  |   - InnoDB File-per-table: ON          |
|   - STRICTLY BLOCKED FROM DIRECT    |  |   - Strict SQL Mode Enabled            |
|     HTTP/WEB URL ACCESS             |  |   - Automated Backups (mysqldump/cron) |
+-------------------------------------+  +----------------------------------------+
```

---

### 3. Local Development vs. Production Infrastructure

| Architectural Dimension | Local Development (Current) | Production Server (Target) |
| :--- | :--- | :--- |
| **Operating System** | Windows 11 Pro | Linux (Ubuntu 22.04 LTS / RHEL 9 recommended) |
| **Orchestration** | Standalone Portable Apache/PHP/MariaDB / Docker | Docker Compose OR Managed Web Service (Nginx + PHP-FPM) |
| **Web Server** | Apache 2.4 (Port 8000 / 8085) | Nginx Reverse Proxy with Apache/PHP-FPM (Port 443 HTTPS) |
| **PHP Version** | PHP 8.2.x (CLI & Module) | PHP 8.2.x or 8.3.x (PHP-FPM) |
| **Database** | MariaDB 10.11 LTS | MariaDB 10.11 LTS or Managed MySQL 8.0 |
| **Base URL** | `http://localhost:8000` | `https://lms.globecon.com` |
| **Data Directory** | `.\server\moodledata` (isolated) | `/var/www/moodledata` (outside web root, 0770 permissions) |
| **Email Delivery** | Local log simulation / Mailpit | Secure Authenticated SMTP (TLS port 587) |
| **Cron Processing** | Manual CLI / Windows Task Scheduler | Systemd Timer / Crontab (`* * * * * php admin/cli/cron.php`) |

---

### 4. Compatibility & Environment Requirements

#### A. PHP 8.2+ Extensions Checklist
Moodle 4.5 requires the following extensions to be compiled and active in `php.ini`:
1. `intl` (Internationalization support - mandatory)
2. `mbstring` (Multi-byte string handling - mandatory)
3. `curl` (Network communication, repository access, updates - mandatory)
4. `zip` (Archive extraction and backup compression - mandatory)
5. `gd` (Image resizing, badges, user profile pictures - mandatory)
6. `soap` (Web services integration - mandatory)
7. `xml` / `xmlrpc` / `simplexml` (Data exchange and backup processing - mandatory)
8. `sodium` (Modern cryptography and token verification - mandatory)
9. `opcache` (PHP opcode caching for enterprise performance - mandatory)
10. `mysqli` or `pdo_mysql` (Database driver - mandatory)
11. `exif` (Media metadata extraction - recommended)

#### B. PHP Configuration Directives (`php.ini`)
```ini
max_execution_time = 300
max_input_time = 600
max_input_vars = 5000
memory_limit = 512M
post_max_size = 128M
upload_max_filesize = 100M
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
```

#### C. Database Configuration Requirements (MariaDB / MySQL)
```ini
[mysqld]
default_storage_engine = InnoDB
innodb_file_per_table = 1
innodb_large_prefix = 1
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

---

### 5. Extension Architecture (No Core File Modification)

To guarantee safe future upgrades and maintain cybersecurity integrity:
1. **GlobeCon Custom Theme (`theme/globecon`)**:
   - Inherits cleanly from Moodle's modern parent theme (`theme_boost`).
   - Uses standard Moodle SCSS variables and custom Sass partials (`scss/post.scss`).
   - Integrates GlobeCon brand identity (palette, responsive navigation, card layouts, accessible typography).
   - Configurable settings via Moodle's theme administration API (`settings.php`).
2. **Standard Core Modules**:
   - Course formats: Topics format with activity completion.
   - Assessments: Core `quiz` module and Question Bank with category hierarchies.
   - Content delivery: Core `resource` (PDF/Office files), `page`, `url` (video embeds), and `folder`.
   - Communication: Core `forum` (Announcements) and Core `calendar`.
   - Security: Core role definition overrides, capability configurations, and password policies.
