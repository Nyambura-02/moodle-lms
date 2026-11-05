#!/usr/bin/env bash
set -e

# ==============================================================================
# GlobeCon LMS - Cloud Container Entrypoint Script
# Supports: Railway, Render, Fly.io, Cloud VPS
# ==============================================================================

echo "=========================================================="
echo " Starting GlobeCon LMS - Cloud Container Initialization "
echo "=========================================================="

# 1. Dynamic Port Binding for Cloud PaaS (Render: 10000, Railway: $PORT)
TARGET_PORT="${PORT:-80}"
echo "[*] Configuring Apache to listen on port ${TARGET_PORT}..."
sed -i "s/Listen [0-9]*/Listen ${TARGET_PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${TARGET_PORT}>/g" /etc/apache2/sites-available/000-default.conf

# 2. Setup Moodledata and Presets
mkdir -p /var/www/moodledata/lang
if [ -d "/var/www/moodledata_preset_lang" ]; then
    cp -rn /var/www/moodledata_preset_lang/* /var/www/moodledata/lang/ 2>/dev/null || true
fi
chown -R www-data:www-data /var/www/moodledata
chmod -R 0777 /var/www/moodledata

# 3. Resolve Public Site URL
SITE_URL="${MOODLE_URL}"
if [ -z "${SITE_URL}" ]; then
    if [ -n "${RENDER_EXTERNAL_URL}" ]; then
        SITE_URL="${RENDER_EXTERNAL_URL}"
    elif [ -n "${RAILWAY_PUBLIC_DOMAIN}" ]; then
        SITE_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
    elif [ -n "${RAILWAY_STATIC_URL}" ]; then
        SITE_URL="https://${RAILWAY_STATIC_URL}"
    else
        SITE_URL="http://localhost:${TARGET_PORT}"
    fi
fi
# Remove trailing slash
SITE_URL="${SITE_URL%/}"
echo "[*] Resolved Moodle Public URL: ${SITE_URL}"

# 4. Resolve Database Connection Settings
# Support DATABASE_URL (Standard URI format from Railway/Render)
DB_DRIVER="${DB_TYPE:-mariadb}"
DB_SERVER="${DB_HOST:-${MYSQLHOST:-database}}"
DB_PORT_NUM="${DB_PORT:-${MYSQLPORT:-3306}}"
DB_DATABASE="${DB_NAME:-${MYSQLDATABASE:-moodle}}"
DB_USERNAME="${DB_USER:-${MYSQLUSER:-moodleuser}}"
DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-}}"
DB_PREFIX="${DB_PREFIX:-mdl_}"

if [ -n "${DATABASE_URL}" ]; then
    echo "[*] Parsing DATABASE_URL..."
    # Format: protocol://user:password@host:port/database
    PROTO="$(echo "${DATABASE_URL}" | grep :// | sed -e's,^\(.*://\).*,\1,g')"
    URL="${DATABASE_URL/$PROTO/}"
    
    USER_PASS="$(echo "${URL}" | grep / | cut -d/ -f1 | grep @ | cut -d@ -f1)"
    if [ -n "${USER_PASS}" ]; then
        DB_USERNAME="$(echo "${USER_PASS}" | cut -d: -f1)"
        DB_PASSWORD="$(echo "${USER_PASS}" | cut -d: -f2)"
    fi
    
    HOST_PORT="$(echo "${URL}" | grep / | cut -d/ -f1 | sed -e 's/.*@//')"
    DB_SERVER="$(echo "${HOST_PORT}" | cut -d: -f1)"
    if echo "${HOST_PORT}" | grep -q ":"; then
        DB_PORT_NUM="$(echo "${HOST_PORT}" | cut -d: -f2)"
    fi
    
    DB_DATABASE="$(echo "${URL}" | grep / | cut -d/ -f2 | cut -d? -f1)"
    
    if [[ "${PROTO}" =~ postgres ]]; then
        DB_DRIVER="pgsql"
        if [ "${DB_PORT_NUM}" = "3306" ]; then DB_PORT_NUM="5432"; fi
    else
        DB_DRIVER="mariadb"
    fi
fi

# Fallback driver mapping
if [ "${DB_DRIVER}" = "mysql" ]; then
    DB_DRIVER="mysqli"
fi

echo "[*] Database Driver: ${DB_DRIVER}"
echo "[*] Database Host: ${DB_SERVER}:${DB_PORT_NUM}"
echo "[*] Database Name: ${DB_DATABASE}"
echo "[*] Database User: ${DB_USERNAME}"

# 5. Generate /var/www/html/config.php if not provided or when dynamically managed
if [ ! -f /var/www/html/config.php ] || [ "${FORCE_CONFIG_REGEN:-false}" = "true" ]; then
    echo "[*] Generating production config.php..."
    cat <<EOF > /var/www/html/config.php
<?php  // Moodle configuration file
unset(\$CFG);
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = '${DB_DRIVER}';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = '${DB_SERVER}';
\$CFG->dbname    = '${DB_DATABASE}';
\$CFG->dbuser    = '${DB_USERNAME}';
\$CFG->dbpass    = '${DB_PASSWORD}';
\$CFG->prefix    = '${DB_PREFIX}';
\$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => ${DB_PORT_NUM},
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

\$CFG->wwwroot   = '${SITE_URL}';
\$CFG->dataroot  = '/var/www/moodledata';
\$CFG->admin     = 'admin';

\$CFG->directorypermissions = 0777;
\$CFG->sslproxy  = true;

\$CFG->timezone = 'Africa/Nairobi';
\$CFG->country  = 'KE';

// Performance caching flags
\$CFG->themedesignermode = false;

require_once(__DIR__ . '/lib/setup.php');
EOF
    chown www-data:www-data /var/www/html/config.php
    chmod 0644 /var/www/html/config.php
    echo "[✓] config.php generated successfully."
fi

# 6. Ensure theme_globecon default theme selection
if [ -n "${DEFAULT_THEME:-}" ]; then
    export MOODLE_THEME="${DEFAULT_THEME}"
fi

echo "=========================================================="
echo " Initialization Complete. Launching Apache Web Server...  "
echo "=========================================================="

exec "$@"
