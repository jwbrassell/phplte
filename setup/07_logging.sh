#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Configuring logging system..."

# Create all required log directories
log "Creating log directories..."

# System log directories with proper ownership and permissions
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

# Portal log directories
ensure_dir "$WEB_ROOT/portal/logs" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/errors" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/access" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/python" "775" "$APACHE_USER" "$NGINX_GROUP"
ensure_dir "$WEB_ROOT/portal/logs/client" "775" "$APACHE_USER" "$NGINX_GROUP"

# Double-check permissions recursively
log "Setting recursive permissions..."
find "$WEB_ROOT/shared/data/logs" -type d -exec chmod 775 {} \;
find "$WEB_ROOT/shared/data/logs" -type f -exec chmod 664 {} \;
find "$WEB_ROOT/portal/logs" -type d -exec chmod 775 {} \;
find "$WEB_ROOT/portal/logs" -type f -exec chmod 664 {} \;

# Double-check ownership recursively
log "Setting recursive ownership..."
chown -R "$APACHE_USER:$NGINX_GROUP" "$WEB_ROOT/shared/data/logs"
chown -R "$APACHE_USER:$NGINX_GROUP" "$WEB_ROOT/portal/logs"

# Set up logrotate
log "Configuring logrotate..."
cp "$(dirname "$0")/config/phpadminlte.logrotate" "/etc/logrotate.d/phpadminlte"
chmod 644 "/etc/logrotate.d/phpadminlte"
chown root:root "/etc/logrotate.d/phpadminlte"

# Configure SELinux if enabled
if command -v selinuxenabled >/dev/null 2>&1 && selinuxenabled; then
    log "Configuring SELinux contexts..."
    
    # Set contexts for log directories
    semanage fcontext -a -t httpd_log_t "$WEB_ROOT/shared/data/logs/system(/.*)?"
    semanage fcontext -a -t httpd_log_t "$WEB_ROOT/portal/logs(/.*)?"
    
    # Apply contexts
    restorecon -R "$WEB_ROOT/shared/data/logs"
    restorecon -R "$WEB_ROOT/portal/logs"
    
    # Allow Apache to write to the log directories
    setsebool -P httpd_unified 1
    setsebool -P httpd_can_network_connect 1
fi

# Create API directory if it doesn't exist
ensure_dir "$WEB_ROOT/portal/api" "755" "$NGINX_USER" "$NGINX_GROUP"

# Verify nginx configuration includes API location
log "Checking nginx configuration..."
NGINX_CONF="/etc/nginx/conf.d/phpadminlte.conf"
if [ ! -f "$NGINX_CONF" ] || ! grep -q "location /portal/api" "$NGINX_CONF"; then
    warn "Please ensure your nginx configuration includes the API location block"
    echo "Example configuration:"
    echo "    location ~ ^/portal/includes/ {"
    echo "        deny all;"
    echo "        return 403;"
    echo "    }"
    echo ""
    echo "    location ~ ^/portal/api/ {"
    echo "        try_files \$uri =404;"
    echo "        fastcgi_pass unix:/run/php-fpm/www.sock;"
    echo "        include fastcgi_params;"
    echo "    }"
fi

log "Logging system configuration complete"
