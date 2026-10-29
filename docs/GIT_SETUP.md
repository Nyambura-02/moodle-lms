# GlobeCon Convergence Solutions LMS
## Git Repository Setup & Version Control Management

### 1. Repository Purpose & Architecture
The GlobeCon LMS repository is architected to track custom extensions, infrastructure definitions, automation scripts, and technical documentation without committing large core vendor runtimes, user uploads, caches, or credentials.

---

### 2. What Is Tracked vs. What Is Ignored

| Category | Tracked in Git | Ignored via `.gitignore` | Rationale |
| :--- | :--- | :--- | :--- |
| **Custom Theme** | `theme/globecon/` (SCSS, PHP, SVG logos) | None | Core intellectual property and corporate branding. |
| **Documentation** | `docs/*.md`, `README.md` | None | Architecture, security, and deployment runbooks. |
| **Automation** | `scripts/*.ps1`, `scripts/*.bat`, `scripts/*.php` | None | Repeatable seeding, testing, and deployment scripts. |
| **Docker Config** | `docker-compose.yml`, `.env.example` | `.env`, `.env.local` | Secrets protection; only templates with placeholders are tracked. |
| **Moodle Core** | Not tracked | `server/moodle/` | Upstream open-source code; pulled from official tags in CI/CD. |
| **Moodle Data** | Not tracked | `moodledata/`, `server/moodledata/` | Contains confidential user uploads, private sessions, and caches. |
| **Database** | Not tracked | `*.sql`, `mariadb_data/`, `mysql_data/` | Protects employee assessment records and password hashes. |

---

### 3. Step-by-Step: Pushing to a Company Private Repository

When authorized to push the codebase to GlobeCon's approved private Git hosting service (e.g. GitHub Enterprise, GitLab, or Bitbucket):

#### Step 1: Create a Clean Private Repository
Create a new private repository on your company Git server (e.g., `globecon/lms-portal`). Ensure the repository is marked **Private**.

#### Step 2: Verify Local Git Status & Secrets Check
Before adding remotes or pushing, verify that no secret files are staged:
```powershell
git status
```
Ensure that `.env`, database dump files (`*.sql`), and `moodledata` are listed as ignored or untracked.

#### Step 3: Add the Private Remote
```powershell
# Replace with your company private repository URL:
git remote add origin https://github.com/globecon-solutions/lms-portal.git
```

#### Step 4: Push to Main Branch
```powershell
git branch -M main
git push -u origin main
```

---

### 4. Continuous Integration & Production Server Deployment
When deploying to the live GlobeCon server:
1. Clone the private repository to the server:
   ```bash
   git clone https://github.com/globecon-solutions/lms-portal.git /var/www/lms-deploy
   ```
2. Copy the custom theme into the server's Moodle installation:
   ```bash
   cp -r /var/www/lms-deploy/theme/globecon /var/www/lms.globecon.com/html/theme/globecon
   ```
3. Purge Moodle caches to compile the latest SCSS changes:
   ```bash
   php /var/www/lms.globecon.com/html/admin/cli/purge_caches.php
   ```
