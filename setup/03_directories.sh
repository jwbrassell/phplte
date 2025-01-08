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

# System log directories
ensure_dir "$WEB_ROOT/shared/data/logs" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/error" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/access" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/audit" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/client" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/errors" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/general" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/performance" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/rbac" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/shared/data/logs/system/test" "775" "$APACHE_USER" "$NGINX_GROUP"

# Ensure proper ownership of all log directories
chown -R "$APACHE_USER:$NGINX_GROUP" "$WEB_ROOT/shared/data/logs"
chmod -R 775 "$WEB_ROOT/shared/data/logs"

# Set SELinux context if enabled
if command -v selinuxenabled >/dev/null 2>&1 && selinuxenabled; then
    restorecon -R "$WEB_ROOT/shared/data/logs"
fi

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

# Set base permissions
log "Setting base permissions..."
find "$WEB_ROOT" -type d -exec chmod 755 {} \;
find "$WEB_ROOT" -type f -exec chmod 644 {} \;
find "$WEB_ROOT" -type f -name "*.sh" -exec chmod 755 {} \;
find "$WEB_ROOT" -type f -name "*.py" -exec chmod 755 {} \;

# Set base ownership
log "Setting base ownership..."
chown -R $NGINX_USER:$NGINX_GROUP "$WEB_ROOT"

# Set specific directory permissions and ownership
log "Setting specific permissions..."
find "$WEB_ROOT/private" -type d -exec chmod 750 {} \;
find "$WEB_ROOT/shared/data/logs" -type d -exec chmod 775 {} \;

# Set specific ownership
log "Setting specific ownership..."
chown -R $APACHE_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs"
chown -R $APACHE_USER:$NGINX_GROUP "$WEB_ROOT/shared/data/logs"
chown -R root:$APACHE_GROUP "$WEB_ROOT/private/config"

# Double-check system logs permissions and ownership
log "Ensuring system logs permissions..."
find "$WEB_ROOT/shared/data/logs/system" -type d -exec chmod 775 {} \;
chown -R $APACHE_USER:$NGINX_GROUP "$WEB_ROOT/shared/data/logs/system"

# Set SELinux context if enabled
if command -v selinuxenabled >/dev/null 2>&1 && selinuxenabled; then
    log "Setting SELinux context..."
    semanage fcontext -a -t httpd_log_t "$WEB_ROOT/shared/data/logs/system(/.*)?"
    restorecon -R "$WEB_ROOT/shared/data/logs/system"
fi

# Ensure PHP-FPM can read files
log "Setting PHP-FPM permissions..."
usermod -a -G $NGINX_GROUP $APACHE_USER

log "Directory structure creation complete"
