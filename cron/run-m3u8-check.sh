#!/bin/bash
# Wrapper script for M3U8 stream checker
# This ensures the PHP script runs with the correct PHP interpreter

# Try common PHP paths
PHP_PATHS=(
    "/usr/bin/php"
    "/usr/local/php/bin/php"
    "/usr/local/php74/bin/php"
    "/usr/local/php80/bin/php"
    "/usr/local/php81/bin/php"
    "/usr/local/php82/bin/php"
    "/usr/local/php83/bin/php"
)

# Script directory
SCRIPT_DIR="/www/wwwroot/streampanel.live"
PHP_SCRIPT="$SCRIPT_DIR/cron/check-m3u8-streams.php"

# Find working PHP
PHP_CMD=""
for php_path in "${PHP_PATHS[@]}"; do
    if [ -f "$php_path" ] && [ -x "$php_path" ]; then
        PHP_CMD="$php_path"
        break
    fi
done

# If no PHP found, try which
if [ -z "$PHP_CMD" ]; then
    PHP_CMD=$(which php 2>/dev/null)
fi

# If still no PHP, exit with error
if [ -z "$PHP_CMD" ]; then
    echo "Error: PHP not found. Please install PHP or specify PHP path."
    exit 1
fi

# Run the PHP script
"$PHP_CMD" "$PHP_SCRIPT"

exit $?