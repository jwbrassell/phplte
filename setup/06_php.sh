#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Configuring PHP and PHP-FPM..."

# Configure PHP settings
log "Creating PHP configuration..."
cat > /etc/php.d/99-custom.ini << EOF
; Error handling
display_errors = Off
log_errors = On
error_log = $WEB_ROOT/portal/logs/errors/php_errors.log
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_startup_errors = Off

; Resource limits
memory_limit = 128M
max_execution_time = 300
max_input_time = 300
post_max_size = 50M
upload_max_filesize = 50M

; Session handling
session.save_handler = files
session.save_path = /var/lib/php/session
session.cookie_secure = On
session.cookie_httponly = On
session.use_only_cookies = On

; Include paths
include_path = ".:/usr/share/php:$WEB_ROOT:$WEB_ROOT/portal:$WEB_ROOT/private"

; Output handling
output_buffering = On

; Security - permissive for initial setup
expose_php = On
allow_url_fopen = On
allow_url_include = On

; SOAP settings
soap.wsdl_cache_dir = /var/lib/php/wsdlcache
EOF

# Configure PHP-FPM
log "Creating PHP-FPM configuration..."
cat > /etc/php-fpm.d/www.conf << EOF
[www]
; User and group
user = $NGINX_USER
group = $NGINX_GROUP
listen = 127.0.0.1:9000
listen.owner = $NGINX_USER
listen.group = $NGINX_GROUP
listen.mode = 0660

; Process Manager settings
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; Basic settings
clear_env = no
catch_workers_output = yes
php_flag[display_errors] = on
php_admin_flag[log_errors] = on

; PHP settings
php_admin_value[error_log] = $WEB_ROOT/portal/logs/errors/php_errors.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/session

; Environment variables
env[PYTHONPATH] = "$WEB_ROOT/shared/scripts"

; Logging
access.log = $WEB_ROOT/portal/logs/access/php-fpm.access.log
access.format = "%R - %u %t \"%m %r%Q%q\" %s %f %{mili}d %{kilo}M %C%%"
EOF

# Create and configure PHP session directory
log "Setting up PHP session directory..."
mkdir -p /var/lib/php/session
chown $NGINX_USER:$NGINX_GROUP /var/lib/php/session
chmod 700 /var/lib/php/session

# Create and configure WSDL cache directory
log "Setting up WSDL cache directory..."
mkdir -p /var/lib/php/wsdlcache
chown $NGINX_USER:$NGINX_GROUP /var/lib/php/wsdlcache
chmod 700 /var/lib/php/wsdlcache

# Create log files
log "Creating PHP log files..."
touch "$WEB_ROOT/portal/logs/errors/php_errors.log"
touch "$WEB_ROOT/portal/logs/access/php-fpm.access.log"

# Set log file permissions
chown $NGINX_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs/errors/php_errors.log"
chown $NGINX_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs/access/php-fpm.access.log"
chmod 664 "$WEB_ROOT/portal/logs/errors/php_errors.log"
chmod 664 "$WEB_ROOT/portal/logs/access/php-fpm.access.log"

# Test PHP-FPM configuration
log "Testing PHP-FPM configuration..."
if ! php-fpm -t; then
    error "PHP-FPM configuration test failed"
fi

log "PHP and PHP-FPM configuration complete"
