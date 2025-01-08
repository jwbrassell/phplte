#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Creating directory structure..."

# Create runtime directories
log "Creating runtime directories..."

# Create log directories with proper permissions
ensure_dir "$WEB_ROOT/portal/logs/access" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/errors" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/client" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/python" "775" "$APACHE_USER" "$NGINX_GROUP"

# Create PHP-FPM socket directory
ensure_dir "$(dirname $PHP_FPM_SOCK)" "755" "$APACHE_USER" "$NGINX_GROUP"

# Create SSL directory
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
