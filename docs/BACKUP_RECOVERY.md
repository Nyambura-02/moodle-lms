# GlobeCon Convergence Solutions LMS
## Backup and Disaster Recovery Procedure

### 1. Overview and Recovery Objectives
To guarantee business continuity and prevent catastrophic loss of learning records, the GlobeCon Convergence Solutions Learning Portal requires scheduled, automated backups of three critical state components:

1. **Database Dump**: Complete MariaDB/MySQL SQL dump (`moodle` database) including all user records, enrollments, course definitions, quiz attempts, and grades.
2. **Moodle Data Directory (`moodledata/`)**: All uploaded course resources, user profile pictures, submitted assignment files, and cached theme assets.
3. **Application & Extension Code**: `config.php`, custom theme (`theme/globecon`), and installed local plugins.

#### Key Recovery Metrics:
- **Recovery Point Objective (RPO)**: < 24 Hours (Daily full backup, optional hourly database binlog replication).
- **Recovery Time Objective (RTO)**: < 2 Hours (Complete restore from disaster snapshot).

---

### 2. Backup Procedures

#### A. Database Backup (Production Command)
```bash
#!/usr/bin/env bash
BACKUP_DIR="/var/backups/globecon_lms"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
mkdir -p "$BACKUP_DIR"

# Perform consistent InnoDB dump with single transaction
mysqldump -u globecon_db_user -p'SECRET_PASSWORD' \
    --single-transaction \
    --quick \
    --default-character-set=utf8mb4 \
    moodle | gzip > "$BACKUP_DIR/moodle_db_${TIMESTAMP}.sql.gz"

echo "Database backup completed: $BACKUP_DIR/moodle_db_${TIMESTAMP}.sql.gz"
```

#### B. Moodle Data Directory Backup
```bash
# Tar and compress moodledata excluding transient cache directories
tar -czf "$BACKUP_DIR/moodledata_${TIMESTAMP}.tar.gz" \
    --exclude='cache' \
    --exclude='localcache' \
    --exclude='temp' \
    --exclude='sessions' \
    /var/www/moodledata
```

#### C. Local Windows Development Backup Script (`scripts/backup_local.bat`)
In the local development environment, use the bundled mysqldump:
```bat
@echo off
set TIMESTAMP=%date:~10,4%%date:~4,2%%date:~7,2%_%time:~0,2%%time:~3,2%
set TIMESTAMP=%TIMESTAMP: =0%
set BACKUP_FILE=backup_moodle_%TIMESTAMP%.sql

echo Backing up local Moodle database to %BACKUP_FILE%...
server\mysql\bin\mysqldump.exe -u root --single-transaction --default-character-set=utf8mb4 moodle > %BACKUP_FILE%
echo Backup completed successfully.
```

---

### 3. Step-by-Step Restoration Drill

In the event of database corruption, server failure, or migration to a new host, follow this verified restoration sequence:

#### Step 1: Put System in Maintenance Mode (if web server is online)
```bash
php /var/www/lms.globecon.com/html/admin/cli/maintenance.php --enable
```

#### Step 2: Restore Database
```bash
# Decompress and import database dump into clean database
gunzip < "$BACKUP_DIR/moodle_db_20261029.sql.gz" | mysql -u globecon_db_user -p'SECRET_PASSWORD' moodle
```

#### Step 3: Restore Moodle Data Directory
```bash
# Unpack data directory to /var/www/moodledata
tar -xzf "$BACKUP_DIR/moodledata_20261029.tar.gz" -C /
sudo chown -R www-data:www-data /var/www/moodledata
sudo chmod -R 0770 /var/www/moodledata
```

#### Step 4: Verify `config.php` Parameters
Verify that `/var/www/lms.globecon.com/html/config.php` has the correct `dbhost`, `dbname`, `dbuser`, `dbpass`, `wwwroot`, and `dataroot`.

#### Step 5: Purge All Cached Assets
```bash
php /var/www/lms.globecon.com/html/admin/cli/purge_caches.php
```

#### Step 6: Disable Maintenance Mode
```bash
php /var/www/lms.globecon.com/html/admin/cli/maintenance.php --disable
```

#### Step 7: Post-Restoration Verification
1. Access the web portal in browser.
2. Confirm user accounts can authenticate.
3. Verify that course activities, enrolled students, and past grades match the restored snapshot.
