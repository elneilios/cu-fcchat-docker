#!/bin/bash
set -e

PHPBB_DIR="/var/www/html"

# ---------------------------
# 0. Handle config.php
# ---------------------------
if [ -f "/config/docker.config.php" ]; then
    echo "Copying Docker-specific config.php..."
    cp /config/docker.config.php "$PHPBB_DIR/config.php"
    chown www-data:www-data "$PHPBB_DIR/config.php"
    chmod 644 "$PHPBB_DIR/config.php"
fi

# ---------------------------
# 1. Fix permissions for writable directories
# ---------------------------
for dir in cache files store images/avatars/upload; do
    if [ -d "$PHPBB_DIR/$dir" ]; then
        echo "Setting permissions for $dir..."
        chown -R www-data:www-data "$PHPBB_DIR/$dir"
        chmod -R 777 "$PHPBB_DIR/$dir"
    fi
done

# ---------------------------
# 2. Clear phpBB cache safely
# ---------------------------
CACHE_DIR="$PHPBB_DIR/cache"
if [ -d "$CACHE_DIR" ]; then
    echo "Clearing phpBB cache..."
    find "$CACHE_DIR" -mindepth 1 ! -name "index.htm" ! -name ".htaccess" -exec rm -rf {} +
fi

# ---------------------------
# 3. Clear old session files
# ---------------------------
if [ -d "$PHPBB_DIR/store" ]; then
    echo "Clearing old session files..."
    find "$PHPBB_DIR/store" -type f -name 'sess_*' -exec rm -f {} +
fi

# ---------------------------
# 4. Fix permissions again after cleanup
# ---------------------------
for dir in cache files store images/avatars/upload; do
    if [ -d "$PHPBB_DIR/$dir" ]; then
        chown -R www-data:www-data "$PHPBB_DIR/$dir"
        chmod -R 777 "$PHPBB_DIR/$dir"
    fi
done

# ---------------------------
# 5. Force environment variables for phpBB cookies
# ---------------------------
export HTTP_HOST=${APACHE_SERVER_NAME:-localhost}
export SERVER_PORT=${APACHE_SERVER_PORT:-80}

# ---------------------------
# 6. Configure PHP for phpBB sessions, logging, and timezone
# ---------------------------
# Ensure error log exists
touch /var/log/php_errors.log
chown www-data:www-data /var/log/php_errors.log
chmod 664 /var/log/php_errors.log

cat <<EOL > /usr/local/etc/php/conf.d/phpbb.ini
; phpBB session settings
session.save_handler = files
session.save_path = /var/www/html/store

; Error reporting
error_reporting = E_ALL & ~E_STRICT & ~E_DEPRECATED & ~E_NOTICE
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Set default timezone
date.timezone = Europe/London

; Make environment variables available to PHP
variables_order = "EGPCS"
EOL

# ---------------------------
# 7. Configure Apache to pass environment variables to PHP
# ---------------------------
echo "Configuring Apache to pass environment variables..."
cat <<'EOL' >> /etc/apache2/conf-available/env-vars.conf
PassEnv DB_HOST
PassEnv DB_PORT
PassEnv DB_NAME
PassEnv DB_USER
PassEnv DB_PASSWORD
EOL
a2enconf env-vars 2>/dev/null || true

# ---------------------------
# 7. Start Apache
# ---------------------------
echo "Starting Apache..."
exec "$@"
