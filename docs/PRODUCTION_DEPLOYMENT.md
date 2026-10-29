# GlobeCon Convergence Solutions LMS
## Production Deployment Runbook & Server Architecture

### 1. Scope and Deployment Governance
This runbook details the end-to-end procedure for deploying the GlobeCon Convergence Solutions Learning Management System (LMS) to an authorized company server.

> [!WARNING]
> **Production Safety Gate**: Do NOT publish this application to live company DNS, expose server ports to the public internet, or deploy to GlobeCon's production server without explicit authorization from the GlobeCon Infrastructure & Security Steering Committee.

---

### 2. Recommended Production Server Prerequisites

| Component | Minimum Specification | Recommended Production Specification |
| :--- | :--- | :--- |
| **Operating System** | Ubuntu 22.04 LTS / RHEL 9 | Ubuntu 24.04 LTS or Debian 12 |
| **CPU Architecture** | 4 Cores (x86_64) | 8+ Cores (for 500+ concurrent learners) |
| **System Memory (RAM)**| 8 GB | 16 GB - 32 GB |
| **Storage (Disk)** | 100 GB NVMe SSD | 250+ GB Enterprise NVMe SSD (RAID-1/10) |
| **Web Server** | Nginx 1.24+ (Reverse Proxy) | Nginx with TLS 1.3 Termination |
| **PHP Runtime** | PHP 8.2 or 8.3 FPM | PHP 8.3-FPM with OPcache & JIT Enabled |
| **Database** | MariaDB 10.11 LTS | MariaDB 10.11 Enterprise or AWS RDS MariaDB |
| **Domain & SSL** | Valid FQDN (`lms.globecon.com`) | Valid Commercial / Let's Encrypt Wildcard SSL |

---

### 3. Production Architecture Tiering

```
[ User Browser / Mobile App ]
             │
             ▼ HTTPS (Port 443)
[ Cloudflare / Corporate Edge WAF ]
             │
             ▼ Protected Origin Traffic
[ Nginx Reverse Proxy (SSL Termination + Security Headers) ]
             │
             ▼ FastCGI (Unix Socket / 127.0.0.1:9000)
[ PHP 8.3-FPM + Moodle 4.5 Core Engine ]
    ├── Theme: theme/globecon (Custom Extensions)
    ├── Persistent Storage: /var/www/moodledata (0770 www-data:www-data)
    └── Cron Daemon: systemd timer running admin/cli/cron.php every 1 min
             │
             ▼ TCP / UNIX Socket (Port 3306)
[ MariaDB 10.11 LTS Database ]
    ├── Character Set: utf8mb4 / utf8mb4_unicode_ci
    ├── Dedicated user: moodleuser (least-privilege permissions)
    └── Automated Daily Snapshots (mysqldump to encrypted backup volume)
```

---

### 4. Step-by-Step Server Provisioning Procedure

#### Step 1: Operating System & Dependency Installation (Ubuntu Example)
```bash
# Update package repositories
sudo apt update && sudo apt upgrade -y

# Install Nginx, MariaDB, and utilities
sudo apt install -y nginx mariadb-server curl git ufw fail2ban unzip

# Install PHP 8.2/8.3 and all mandatory Moodle extensions
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-gd \
    php8.3-intl php8.3-mbstring php8.3-xml php8.3-xmlrpc php8.3-soap \
    php8.3-zip php8.3-opcache php8.3-sodium php8.3-bcmath php8.3-exif
```

#### Step 2: Database Hardening & User Setup
```sql
-- Execute in MariaDB shell:
CREATE DATABASE moodle DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'globecon_db_user'@'localhost' IDENTIFIED BY 'STRONG_PRODUCTION_SECRET_GENERATED_AT_SETUP';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES 
ON moodle.* TO 'globecon_db_user'@'localhost';

FLUSH PRIVILEGES;
```

#### Step 3: File System Structure & Isolation
```bash
# Create application root and data directory
sudo mkdir -p /var/www/lms.globecon.com/html
sudo mkdir -p /var/www/moodledata

# Restrict data directory out of web root:
sudo chown -R www-data:www-data /var/www/moodledata
sudo chmod -R 0770 /var/www/moodledata

# Deploy Moodle 4.5 LTS core and GlobeCon Theme
sudo git clone -b MOODLE_405_STABLE https://github.com/moodle/moodle.git /var/www/lms.globecon.com/html
sudo cp -r /path/to/repo/theme/globecon /var/www/lms.globecon.com/html/theme/globecon
sudo chown -R www-data:www-data /var/www/lms.globecon.com/html
```

#### Step 4: Production `config.php` Configuration
Create `/var/www/lms.globecon.com/html/config.php` using environment-specific secrets (NEVER committed to Git):
```php
<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'globecon_db_user';
$CFG->dbpass    = 'STRONG_PRODUCTION_SECRET_GENERATED_AT_SETUP';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => 3306,
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'https://lms.globecon.com';
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02770;
$CFG->sslproxy  = true; // If behind SSL-terminating reverse proxy

require_once(__DIR__ . '/lib/setup.php');
```

#### Step 5: Nginx Reverse Proxy & TLS Configuration
Create `/etc/nginx/sites-available/lms.globecon.com`:
```nginx
server {
    listen 80;
    server_name lms.globecon.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name lms.globecon.com;

    ssl_certificate /etc/letsencrypt/live/lms.globecon.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/lms.globecon.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root /var/www/lms.globecon.com/html;
    index index.php index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ [^/]\.php(/|$) {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_read_timeout 300;
    }

    # Deny direct access to data files, git, and sensitive documents
    location ~ (/vendor/|/node_modules/|\.git|\.env) {
        deny all;
        return 404;
    }
}
```

#### Step 6: Systemd Scheduled Cron Service
Create `/etc/systemd/system/moodle-cron.service`:
```ini
[Unit]
Description=Moodle Scheduled Background Tasks Runner
After=network.target

[Service]
Type=oneshot
User=www-data
ExecStart=/usr/bin/php /var/www/lms.globecon.com/html/admin/cli/cron.php
```

Create `/etc/systemd/system/moodle-cron.timer`:
```ini
[Unit]
Description=Runs Moodle Cron every 1 minute

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min
Unit=moodle-cron.service

[Install]
WantedBy=timers.target
```
Enable and start the timer:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now moodle-cron.timer
```

---

### 5. Post-Deployment Verification Checklist

- [ ] HTTPS enforces TLS 1.2/1.3 with valid certificate.
- [ ] Direct HTTP requests automatically redirect (301) to HTTPS.
- [ ] `moodledata` is located strictly outside web root and inaccessible via browser.
- [ ] Administrator login functions with strict password.
- [ ] Theme `globecon` is active and responsive across desktop, tablet, and mobile.
- [ ] System email delivers notifications via corporate authenticated SMTP (TLS 587).
- [ ] Scheduled cron runs every 1 minute without failures (`systemctl status moodle-cron.timer`).
- [ ] Quiz attempts and grading operate without errors.
- [ ] Automated backup scripts are active in daily crontab.
- [ ] Firewall (UFW) permits only ports 80, 443, and secured SSH.
