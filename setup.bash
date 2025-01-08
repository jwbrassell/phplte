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

# Permanently disable SELinux first
log "Permanently disabling SELinux..."
if command -v setenforce >/dev/null 2>&1; then
    setenforce 0
    log "SELinux disabled for current session"
fi

if [ -f "/etc/selinux/config" ]; then
    cp /etc/selinux/config /etc/selinux/config.bak
    sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config
    if grep -q "^SELINUX=disabled" /etc/selinux/config; then
        log "SELinux permanently disabled in config"
    else
        error "Failed to update SELinux config"
    fi
fi

# Enable necessary SELinux booleans while it's still active
if command -v setsebool >/dev/null 2>&1; then
    log "Setting SELinux booleans..."
    setsebool -P httpd_can_network_connect 1
    setsebool -P httpd_unified 1
    setsebool -P httpd_can_network_connect_db 1
fi

# Create all required directories first
log "Creating required directories..."
mkdir -p "$WEB_ROOT"/{portal,shared,private,public}
mkdir -p "$WEB_ROOT/portal/logs"/{access,errors,client,python}
mkdir -p "$WEB_ROOT/private"/{config,includes/{logging,auth,calendar,components/{datatables,forms,widgets}}}
mkdir -p $(dirname $PHP_FPM_SOCK)

# Set initial permissions early
log "Setting initial permissions..."
chown -R $NGINX_USER:$NGINX_GROUP "$WEB_ROOT"
chmod -R 755 "$WEB_ROOT"
chmod -R 775 "$WEB_ROOT/portal/logs"
chown -R $APACHE_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs"
chown $APACHE_USER:$NGINX_GROUP $(dirname $PHP_FPM_SOCK)
chmod 755 $(dirname $PHP_FPM_SOCK)

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
        php-mysqlnd \
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

# Set up Python virtual environment
log "Setting up Python virtual environment..."
python3 -m venv "$WEB_ROOT/shared/venv"
source "$WEB_ROOT/shared/venv/bin/activate"

# Install Python packages in virtual environment
log "Installing Python packages..."
"$WEB_ROOT/shared/venv/bin/pip3" install --upgrade pip
"$WEB_ROOT/shared/venv/bin/pip3" install \
    python-ldap \
    hvac \
    requests \
    PyYAML \
    python-dateutil \
    pytz

# Install requirements if they exist
if [ -f "requirements.txt" ]; then
    "$WEB_ROOT/shared/venv/bin/pip3" install -r requirements.txt
else
    warn "requirements.txt not found"
fi

# Deactivate virtual environment
deactivate

# Verify users exist
if ! id -u $APACHE_USER >/dev/null 2>&1; then
    error "Apache user ($APACHE_USER) does not exist"
fi

if ! id -u $NGINX_USER >/dev/null 2>&1; then
    error "Nginx user ($NGINX_USER) does not exist"
fi

# Create log files with proper permissions
log "Creating log files..."
touch "$WEB_ROOT/portal/logs/errors/php_errors.log"
touch "$WEB_ROOT/portal/logs/access/nginx_access.log"
touch "$WEB_ROOT/portal/logs/errors/nginx_error.log"

# Set log file permissions
chown $APACHE_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs/errors/php_errors.log"
chown $NGINX_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs/access/nginx_access.log"
chown $NGINX_USER:$NGINX_GROUP "$WEB_ROOT/portal/logs/errors/nginx_error.log"

chmod 664 "$WEB_ROOT/portal/logs/errors/php_errors.log"
chmod 664 "$WEB_ROOT/portal/logs/access/nginx_access.log"
chmod 664 "$WEB_ROOT/portal/logs/errors/nginx_error.log"

# Create symbolic links
log "Setting up symbolic links..."
ln -sf "$WEB_ROOT/shared" "$WEB_ROOT/portal/shared"
ln -sf "$WEB_ROOT/private" "$WEB_ROOT/portal/private"
ln -sf "$WEB_ROOT/public/auth/login.php" "$WEB_ROOT/portal/login.php"

# Configure nginx
log "Configuring nginx..."

# Clean up existing nginx configs
rm -f /etc/nginx/conf.d/*.conf

# Create main nginx config
cat > /etc/nginx/nginx.conf << EOF
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log notice;
pid /run/nginx.pid;

include /usr/share/nginx/modules/*.conf;

events {
    worker_connections 1024;
}

http {
    log_format  main  '\$remote_addr - \$remote_user [\$time_local] "\$request" '
                      '\$status \$body_bytes_sent "\$http_referer" '
                      '"\$http_user_agent" "\$http_x_forwarded_for"';

    access_log  /var/log/nginx/access.log  main;

    sendfile            on;
    tcp_nopush          on;
    tcp_nodelay         on;
    keepalive_timeout   65;
    types_hash_max_size 4096;

    include             /etc/nginx/mime.types;
    default_type        application/octet-stream;

    include /etc/nginx/conf.d/*.conf;
}
EOF

# Create portal config
cat > /etc/nginx/conf.d/portal.conf << EOF
# Default server block for non-matching requests
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    return 444;
}

server {
    listen 443 ssl default_server;
    listen [::]:443 ssl default_server;
    server_name _;
    ssl_certificate $SSL_DIR/$DOMAIN.crt;
    ssl_certificate_key $SSL_DIR/$DOMAIN.key;
    return 444;
}

# HTTP server block for domain
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    return 301 https://\$server_name\$request_uri;
}

# HTTPS server block for domain
server {
    listen 443 ssl;
    listen [::]:443 ssl;
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

    # Handle PHP files in portal directory first
    location ~ ^/portal/.*\.php$ {
        try_files \$uri =404;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_intercept_errors on;
        fastcgi_param PHP_VALUE "error_log=$WEB_ROOT/portal/logs/errors/php_errors.log";
    }

    # Handle portal directory
    location ^~ /portal/ {
        root $WEB_ROOT;
        index index.php;
        try_files \$uri \$uri/ /portal/index.php?\$query_string;
    }

    # Handle includes from private directory
    location ~ ^/private/.*\.php$ {
        internal;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
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

# Configure PHP settings
log "Configuring PHP settings..."
cat > /etc/php.d/99-custom.ini << EOF
; Error handling
display_errors = Off
log_errors = On
error_log = $WEB_ROOT/portal/logs/php_errors.log
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

; Include paths
include_path = ".:/usr/share/php:$WEB_ROOT:$WEB_ROOT/portal:$WEB_ROOT/private"

; Output handling
output_buffering = On
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

; Process Manager settings
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35

; Basic settings
clear_env = no
catch_workers_output = yes

; Environment variables
env[PYTHONPATH] = "$WEB_ROOT/shared/scripts"
EOF

# Create and configure PHP session directory
log "Setting up PHP session directory..."
mkdir -p /var/lib/php/session
chown $APACHE_USER:$APACHE_GROUP /var/lib/php/session
chmod 700 /var/lib/php/session

# Set up vault configuration
log "Setting up vault configuration..."
VAULT_CONFIG_DIR="$WEB_ROOT/private/config"
VAULT_ENV="$VAULT_CONFIG_DIR/vault.env"
VAULT_TEMPLATE="$VAULT_CONFIG_DIR/vault.env.template"
SYSTEM_VAULT_ENV="/etc/vault.env"

if [ ! -d "$VAULT_CONFIG_DIR" ]; then
    install -d -m 750 -o root -g $APACHE_GROUP "$VAULT_CONFIG_DIR"
fi

# Copy vault token from system vault env if it exists
if [ -f "$SYSTEM_VAULT_ENV" ]; then
    log "Copying vault token from system vault env..."
    cp "$SYSTEM_VAULT_ENV" "$VAULT_ENV"
    chown root:$APACHE_GROUP "$VAULT_ENV"
    chmod 640 "$VAULT_ENV"
elif [ -f "$VAULT_TEMPLATE" ]; then
    log "System vault env not found, using template..."
    cp "$VAULT_TEMPLATE" "$VAULT_ENV"
    chown root:$APACHE_GROUP "$VAULT_ENV"
    chmod 640 "$VAULT_ENV"
    warn "Please update $VAULT_ENV with your Vault credentials"
else
    error "Neither $SYSTEM_VAULT_ENV nor $VAULT_TEMPLATE found"
fi

# Copy required files
log "Copying files..."

# Create required directories
mkdir -p "$WEB_ROOT/shared/scripts/modules"/{logging,vault,ldap,rbac,utilities,api,data_processing,oncall_calendar}
mkdir -p "$WEB_ROOT/shared/data"

# Initialize Python logging module if it doesn't exist
if [ ! -f "$WEB_ROOT/shared/scripts/modules/logging/__init__.py" ]; then
    log "Initializing Python logging module..."
    cat > "$WEB_ROOT/shared/scripts/modules/logging/__init__.py" << 'EOF'
import logging
import os
from datetime import datetime

def setup_logger(name, log_file, level=logging.INFO):
    formatter = logging.Formatter('%(asctime)s %(levelname)s %(message)s')
    handler = logging.FileHandler(log_file)
    handler.setFormatter(formatter)
    
    logger = logging.getLogger(name)
    logger.setLevel(level)
    logger.addHandler(handler)
    
    return logger
EOF
fi

# Copy existing modules and files
cp -r private/includes/* "$WEB_ROOT/private/includes/" || warn "No private/includes files found"
cp -r portal/includes/* "$WEB_ROOT/portal/includes/" || warn "No portal/includes files found"
cp -r shared/scripts/modules/* "$WEB_ROOT/shared/scripts/modules/" || warn "No shared/scripts/modules files found"

# Copy all PHP files from portal directory
log "Copying PHP files..."
for file in portal/*.php; do
    if [ -f "$file" ]; then
        cp "$file" "$WEB_ROOT/portal/"
    fi
done

# Copy additional portal files
cp portal/.htaccess "$WEB_ROOT/portal/" || warn "No .htaccess file found"

# Copy static assets and plugins
if [ -d "portal/plugins" ]; then
    cp -r portal/plugins "$WEB_ROOT/portal/"
fi
if [ -d "portal/static" ]; then
    cp -r portal/static "$WEB_ROOT/portal/"
fi

# Create Python package structure
log "Setting up Python packages..."
for dir in "$WEB_ROOT/shared/scripts/modules"/*; do
    if [ -d "$dir" ]; then
        touch "$dir/__init__.py"
        chmod 755 "$dir"
    fi
done
touch "$WEB_ROOT/shared/scripts/modules/__init__.py"
chmod 755 "$WEB_ROOT/shared/scripts"
chmod 755 "$WEB_ROOT/shared/scripts/modules"

# Set final permissions
log "Setting final permissions..."
find "$WEB_ROOT" -type d -exec chmod 755 {} \;
find "$WEB_ROOT" -type f -exec chmod 644 {} \;
find "$WEB_ROOT" -type f -name "*.py" -exec chmod 755 {} \;

# Set ownership
log "Setting ownership..."
chown -R $APACHE_USER:$NGINX_GROUP "$WEB_ROOT/shared/scripts"
chown -R $NGINX_USER:$NGINX_GROUP "$WEB_ROOT"
chmod -R 775 "$WEB_ROOT/portal/logs"

# Ensure Python files are executable by PHP-FPM
log "Setting Python file permissions..."
find "$WEB_ROOT/shared/scripts" -type f -name "*.py" -exec chmod 755 {} \;
find "$WEB_ROOT/shared/scripts" -type d -exec chmod 755 {} \;

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

# Restart services
log "Restarting services..."
systemctl restart php-fpm nginx

log "Setup complete!"
echo -e "\nCurrent status:"
echo "1. SELinux status: $(getenforce)"
echo "2. Vault env: $([ -f "$VAULT_ENV" ] && echo "Created" || echo "Missing")"
echo "3. Python dependencies: $([ -f "requirements.txt" ] && echo "Installed" || echo "Missing requirements.txt")"
echo -e "\nNext steps:"
echo "1. Reboot system to fully apply SELinux changes"
echo "2. Verify web server is accessible: https://$DOMAIN"
echo "3. Check vault token accessibility"

exit 0
