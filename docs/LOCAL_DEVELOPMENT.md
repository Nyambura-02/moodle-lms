# GlobeCon Convergence Solutions LMS
## Local Development Environment Operations Manual

### 1. Introduction
This manual provides software developers, administrators, and contributors with step-by-step instructions for running, customizing, maintaining, and testing the GlobeCon Convergence Solutions Learning Portal in a local Windows development environment.

---

### 2. Architecture Overview
The local environment uses the official **Moodle 4.5.15+ LTS Windows Stack**, running self-contained in user space:
- **Web Tier**: Apache 2.4.56 listening on `http://localhost:8000` (and `https://localhost:8443` for SSL testing).
- **Application Tier**: PHP 8.2.4 (CLI & Apache module) with OPcache, `intl`, `mbstring`, `curl`, `gd`, `zip`, `xml`, `soap`, and `sodium`.
- **Database Tier**: MariaDB 10.4/10.11 on `127.0.0.1:3306`, configured with `utf8mb4_unicode_ci` and InnoDB file-per-table.
- **Storage Tier**: Persistent data directory located at `.\server\moodledata` (isolated from public web root).

---

### 3. Server Management Commands

#### A. Starting the Services
- **Option 1 (One-click)**: Double-click `start_moodle.bat` in the project root.
- **Option 2 (PowerShell)**:
  ```powershell
  cd "c:\Users\Chuchu\OneDrive\Desktop\moodle lms"
  powershell -ExecutionPolicy Bypass -File "scripts\Start-Moodle.ps1"
  ```
- **Option 3 (CLI Manual)**:
  ```powershell
  # Start MariaDB
  Start-Process "server\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=`"server\mysql\bin\my.ini`"", "--console" -WindowStyle Hidden
  # Start Apache
  Start-Process "server\apache\bin\httpd.exe" -WindowStyle Hidden
  ```

#### B. Stopping the Services
- **Option 1 (One-click)**: Double-click `stop_moodle.bat` in the project root.
- **Option 2 (PowerShell)**:
  ```powershell
  powershell -ExecutionPolicy Bypass -File "scripts\Stop-Moodle.ps1"
  ```

---

### 4. Essential Moodle CLI Maintenance Commands

All maintenance commands should be executed using the bundled PHP binary at `server\php\php.exe`:

#### A. Purging Moodle Caches
Whenever you edit theme SCSS files, language strings, or configuration settings, purge caches:
```powershell
& "server\php\php.exe" "server\moodle\admin\cli\purge_caches.php"
```

#### B. Reading & Updating Configuration Options (`cfg.php`)
```powershell
# View active theme:
& "server\php\php.exe" "server\moodle\admin\cli\cfg.php" --name=theme

# Set active theme to GlobeCon:
& "server\php\php.exe" "server\moodle\admin\cli\cfg.php" --name=theme --set=globecon

# Enable theme designer mode during active CSS development (disables caching):
& "server\php\php.exe" "server\moodle\admin\cli\cfg.php" --name=themedesignermode --set=1
```

#### C. Running Scheduled Background Tasks (Cron)
Moodle executes forum notifications, badge awards, completion tracking, and email queues via cron:
```powershell
& "server\php\php.exe" "server\moodle\admin\cli\cron.php"
```

---

### 5. Custom Theme Development (`theme/globecon`)

The GlobeCon theme is located in `theme/globecon/` and automatically deployed into `server/moodle/theme/globecon/`:

1. **SCSS Styling**: Edit `theme/globecon/scss/post.scss` to update component styles, card elevations, typography, and responsive media queries.
2. **Corporate Logos**:
   - `theme/globecon/pix/logo.svg`: High-resolution vector logo for dark backgrounds / navigation header.
   - `theme/globecon/pix/logo_dark.svg`: Vector logo for light backgrounds and printable certificates.
3. **Admin Settings**:
   - Navigate in browser to: **Site Administration -> Appearance -> Themes -> GlobeCon**.
   - Here, administrators can dynamically adjust the Primary Brand Color, Accent Color, Custom Logo, and Login Screen Background.

---

### 6. Automated Testing & Verification
Execute the automated Role-Based Access Control (RBAC) test suite:
```powershell
powershell -ExecutionPolicy Bypass -File "scripts\Test-MoodleLogins.ps1"
```
This tests:
- Administrator login & administrative capability verification.
- Trainer login & permission boundary verification (Site Admin blocked).
- Learner login & permission boundary verification (Site Admin blocked).
- Enrolled course accessibility for authorized participants.
