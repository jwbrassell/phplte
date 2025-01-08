#!/bin/bash

# Domain configuration
DOMAIN="dogcrayons.com"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper function for logging
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1"
    exit 1
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "Please run as root"
fi

# Configuration
WEB_ROOT="/var/www/html"
APACHE_USER="apache"
APACHE_GROUP="apache"
NGINX_USER="nginx"
NGINX_GROUP="nginx"
SSL_DIR="/etc/nginx/ssl"
PHP_FPM_SOCK="/run/php-fpm/www.sock"

# Create SSL directory
log "Creating SSL directory..."
mkdir -p $SSL_DIR
chmod 700 $SSL_DIR

# Generate SSL certificate
log "Generating SSL certificate..."
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout $SSL_DIR/$DOMAIN.key \
    -out $SSL_DIR/$DOMAIN.crt \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=$DOMAIN"

chmod 600 $SSL_DIR/$DOMAIN.key
chmod 644 $SSL_DIR/$DOMAIN.crt

# Install required packages
log "Installing required packages..."
if command -v dnf >/dev/null 2>&1; then
    dnf install -y \
        nginx \
        php-fpm \
        php-cli \
        php-json \
        php-common \
        php-mysql \
        php-zip \
        php-gd \
        php-mbstring \
        php-curl \
        php-xml \
        php-bcmath \
        php-ldap \
        python3 \
        python3-pip \
        python3-devel \
        openldap-devel
fi

# Install Python packages
log "Installing Python packages..."
pip3 install --upgrade pip
pip3 install \
    python-ldap \
    hvac \
    requests \
    PyYAML \
    python-dateutil \
    pytz

# Verify users exist
if ! id -u $APACHE_USER >/dev/null 2>&1; then
    error "Apache user ($APACHE_USER) does not exist"
fi

if ! id -u $NGINX_USER >/dev/null 2>&1; then
    error "Nginx user ($NGINX_USER) does not exist"
fi

# Verify web root exists
if [ ! -d "$WEB_ROOT" ]; then
    error "Web root directory $WEB_ROOT does not exist"
fi

# Configure nginx
log "Configuring nginx..."
cat > /etc/nginx/conf.d/portal.conf << EOF
# HTTP server to redirect to HTTPS
server {
    listen 80;
    server_name $DOMAIN;
    return 301 https://\$server_name\$request_uri;
}

# HTTPS server
server {
    listen 443 ssl;
    server_name $DOMAIN;
    root $WEB_ROOT;
    index index.php;

    # Error logs
    access_log $WEB_ROOT/portal/logs/access/nginx_access.log;
    error_log $WEB_ROOT/portal/logs/errors/nginx_error.log debug;

    # SSL Configuration
    ssl_certificate $SSL_DIR/$DOMAIN.crt;
    ssl_certificate_key $SSL_DIR/$DOMAIN.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Default to portal directory
    location = / {
        return 301 /portal/;
    }

    # Handle portal directory
    location ^~ /portal/ {
        alias $WEB_ROOT/portal/;
        index index.php;
        try_files \$uri \$uri/ /portal/index.php?\$query_string;

        # Handle PHP files in portal directory
        location ~ \.php$ {
            if (!-f \$request_filename) {
                return 404;
            }
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $WEB_ROOT/portal\$fastcgi_script_name;
            fastcgi_param PATH_INFO \$fastcgi_path_info;
            fastcgi_buffers 16 16k;
            fastcgi_buffer_size 32k;
            fastcgi_intercept_errors on;
            fastcgi_param PHP_VALUE "error_log=$WEB_ROOT/portal/logs/errors/php_errors.log";
        }
    }

    # Handle includes from private directory
    location ~ ^/private/.*\.php$ {
        internal;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $WEB_ROOT\$fastcgi_script_name;
    }

    # Block direct access to private directory
    location ^~ /private {
        deny all;
        return 404;
    }

    # Handle shared directory
    location ^~ /shared {
        alias $WEB_ROOT/shared;
        try_files \$uri \$uri/ =404;
    }

    # Block access to dot files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Error pages
    error_page 404 /portal/404.php;
    error_page 403 /portal/403.php;
    error_page 500 502 503 504 /portal/50x.html;

    # Custom error handling
    location = /portal/50x.html {
        internal;
    }
}
EOF

# Configure PHP-FPM
log "Configuring PHP-FPM..."
cat > /etc/php-fpm.d/www.conf << EOF
[www]
user = $APACHE_USER
group = $APACHE_GROUP
listen = 127.0.0.1:9000
listen.owner = $NGINX_USER
listen.group = $NGINX_GROUP
listen.mode = 0660
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35

php_admin_value[error_log] = $WEB_ROOT/portal/logs/php_errors.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
php_admin_value[error_reporting] = E_ALL
php_admin_flag[display_startup_errors] = off
php_admin_flag[log_errors_max_len] = 4096
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/session
php_value[max_execution_time] = 300
php_value[memory_limit] = 128M
php_value[post_max_size] = 50M
php_value[upload_max_filesize] = 50M
php_value[include_path] = ".:/usr/share/php:$WEB_ROOT:$WEB_ROOT/portal:$WEB_ROOT/private"
php_value[catch_workers_output] = yes
php_value[decorate_workers_output] = no
EOF

# Create and configure PHP session directory
log "Setting up PHP session directory..."
mkdir -p /var/lib/php/session
chown apache:apache /var/lib/php/session
chmod 700 /var/lib/php/session

# Configure SELinux for PHP sessions
if command -v semanage >/dev/null 2>&1; then
    log "Configuring SELinux context for session directory..."
    semanage fcontext -a -t httpd_sess_t "/var/lib/php/session(/.*)?"
    restorecon -Rv /var/lib/php/session
fi

# Enable necessary SELinux booleans
if command -v setsebool >/dev/null 2>&1; then
    log "Setting SELinux booleans..."
    setsebool -P httpd_can_network_connect 1
    setsebool -P httpd_unified 1
    setsebool -P httpd_can_network_connect_db 1
fi

# Verify session directory permissions
if [ ! -w "/var/lib/php/session" ]; then
    warn "Session directory is not writable, applying emergency fix..."
    chmod 777 /var/lib/php/session
    warn "Please investigate SELinux or permission issues"
fi

# Create PHP-FPM socket directory
log "Creating PHP-FPM socket directory..."
mkdir -p $(dirname $PHP_FPM_SOCK)
chown $APACHE_USER:$NGINX_GROUP $(dirname $PHP_FPM_SOCK)
chmod 755 $(dirname $PHP_FPM_SOCK)

# Create symbolic links for required directories and files
log "Setting up directory structure..."
ln -sf $WEB_ROOT/shared $WEB_ROOT/portal/shared
ln -sf $WEB_ROOT/private $WEB_ROOT/portal/private
ln -sf $WEB_ROOT/public/auth/login.php $WEB_ROOT/portal/login.php

# Permanently disable SELinux
log "Permanently disabling SELinux..."

# Disable immediately if possible
if command -v setenforce >/dev/null 2>&1; then
    setenforce 0
    log "SELinux disabled for current session"
fi

# Update SELinux config file
if [ -f "/etc/selinux/config" ]; then
    # Backup original config
    cp /etc/selinux/config /etc/selinux/config.bak
    
    # Update config file
    sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config
    
    # Verify change
    if grep -q "^SELINUX=disabled" /etc/selinux/config; then
        log "SELinux permanently disabled in config"
    else
        error "Failed to update SELinux config"
    fi
    
    # Check if reboot needed
    if [ "$(getenforce)" != "Disabled" ]; then
        warn "System reboot required for SELinux changes to take full effect"
    fi
else
    warn "SELinux config file not found at /etc/selinux/config"
fi

# Set up vault configuration
log "Setting up vault configuration..."
VAULT_CONFIG_DIR="$WEB_ROOT/private/config"
VAULT_ENV="$VAULT_CONFIG_DIR/vault.env"
VAULT_TEMPLATE="$VAULT_CONFIG_DIR/vault.env.template"
OLD_VAULT_ENV="/etc/vault.env"

# Create vault config directory if it doesn't exist
if [ ! -d "$VAULT_CONFIG_DIR" ]; then
    install -d -m 750 -o root -g $APACHE_GROUP "$VAULT_CONFIG_DIR"
fi

# Handle vault.env setup
if [ -f "$OLD_VAULT_ENV" ]; then
    log "Found existing vault token at $OLD_VAULT_ENV"
    if [ ! -f "$VAULT_ENV" ]; then
        log "Migrating vault token to new location..."
        cp "$OLD_VAULT_ENV" "$VAULT_ENV"
        chown root:$APACHE_GROUP "$VAULT_ENV"
        chmod 640 "$VAULT_ENV"
        log "Vault token migrated successfully"
        warn "You can now safely remove $OLD_VAULT_ENV"
    else
        warn "New vault.env already exists, skipping migration"
    fi
elif [ ! -f "$VAULT_ENV" ] && [ -f "$VAULT_TEMPLATE" ]; then
    log "Creating vault.env from template..."
    cp "$VAULT_TEMPLATE" "$VAULT_ENV"
    chown root:$APACHE_GROUP "$VAULT_ENV"
    chmod 640 "$VAULT_ENV"
    warn "Please update $VAULT_ENV with your Vault credentials"
fi

# Set permissions for project directories
log "Setting directory permissions..."
find "$WEB_ROOT" -type d -exec chmod 755 {} \;
find "$WEB_ROOT" -type f -exec chmod 644 {} \;
find "$WEB_ROOT" -type f -name "*.py" -exec chmod 755 {} \;
find "$WEB_ROOT" -type f -name "*.php" -exec chmod 644 {} \;
chown -R $NGINX_USER:$NGINX_GROUP "$WEB_ROOT"

# Create log directories if they don't exist
log "Setting up log directories..."
mkdir -p "$WEB_ROOT/portal/logs"/{access,errors,client,python}
chown -R $NGINX_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs"
chmod -R 775 "$WEB_ROOT/portal/logs"

# Ensure PHP-FPM can read files
log "Setting PHP-FPM permissions..."
usermod -a -G $NGINX_GROUP $APACHE_USER

# Install Python dependencies
log "Installing Python dependencies..."
if [ -f "requirements.txt" ]; then
    pip3 install -r requirements.txt
else
    warn "requirements.txt not found"
fi

# Verify PHP-FPM is running
log "Verifying PHP-FPM..."
if ! systemctl is-active --quiet php-fpm; then
    log "Starting PHP-FPM..."
    systemctl start php-fpm
fi

# Verify nginx is running
log "Verifying nginx..."
if ! systemctl is-active --quiet nginx; then
    log "Starting nginx..."
    systemctl start nginx
fi

# Restart services
log "Restarting services..."
systemctl restart php-fpm nginx

log "Setup complete!"
echo "Please verify:"
echo "1. SELinux is disabled (getenforce)"
echo "2. Vault token is accessible"
echo "3. Web server is accessible"
echo "4. Python dependencies are installed"
echo "5. Reboot system if SELinux was previously enabled"

exit 0
