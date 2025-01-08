#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Creating directory structure..."

# Create all required directories
log "Creating required directories..."

# Main directories
ensure_dir "$WEB_ROOT/portal" "755" "$NGINX_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared" "755" "$NGINX_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/private" "750" "$NGINX_USER" "$NGINX_GROUP"

# Log directories
ensure_dir "$WEB_ROOT/portal/logs/access" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/errors" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/client" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/python" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/error" "775" "$APACHE_USER" "$NGINX_GROUP"

# Python directories
ensure_dir "$WEB_ROOT/shared/scripts/modules" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/scripts/modules/logging" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/scripts/modules/vault" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/scripts/modules/ldap" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/scripts/modules/rbac" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/scripts/modules/utilities" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/scripts/modules/api" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/scripts/modules/data_processing" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/scripts/modules/oncall_calendar" "755" "$APACHE_USER" "$NGINX_GROUP"

# System directories
ensure_dir "$(dirname $PHP_FPM_SOCK)" "755" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$SSL_DIR" "700" "root" "root"

# Set permissions for main directories
log "Setting directory permissions..."
find "$WEB_ROOT" -type d -exec chmod 755 {} \;
find "$WEB_ROOT/private" -type d -exec chmod 750 {} \;

# Set file permissions
log "Setting file permissions..."
find "$WEB_ROOT" -type f -exec chmod 644 {} \;
find "$WEB_ROOT" -type f -name "*.sh" -exec chmod 755 {} \;
find "$WEB_ROOT" -type f -name "*.py" -exec chmod 755 {} \;

# Set ownership
log "Setting ownership..."
chown -R $NGINX_USER:$NGINX_GROUP "$WEB_ROOT"
chown -R $APACHE_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs"
chown -R root:$APACHE_GROUP "$WEB_ROOT/private/config"

# Ensure PHP-FPM can read files
log "Setting PHP-FPM permissions..."
usermod -a -G $NGINX_GROUP $APACHE_USER

log "Directory structure creation complete"
