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

# 3. Run Intelligent Cloud Configuration Engine (resolves DB & generates config.php)
if [ -f "/var/www/html/scripts/configure_moodle.php" ]; then
    php /var/www/html/scripts/configure_moodle.php
fi

echo "=========================================================="
echo " Initialization Complete. Launching Apache Web Server...  "
echo "=========================================================="

exec "$@"
