#!/bin/bash

# Domain configuration
DOMAIN="exampledomain.com"

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
    root $WEB_ROOT/portal;
    index index.php;

    ssl_certificate $SSL_DIR/$DOMAIN.crt;
    ssl_certificate_key $SSL_DIR/$DOMAIN.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location /shared {
        alias $WEB_ROOT/shared;
        try_files \$uri \$uri/ =404;
    }

    location ~ /\.ht {
        deny all;
    }

    error_page 404 /404.php;
    error_page 403 /403.php;
}
EOF

# Configure PHP-FPM
log "Configuring PHP-FPM..."
cat > /etc/php-fpm.d/www.conf << EOF
[www]
user = $APACHE_USER
group = $APACHE_GROUP
listen = /var/run/php-fpm/www.sock
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
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/session
php_value[max_execution_time] = 300
php_value[memory_limit] = 128M
php_value[post_max_size] = 50M
php_value[upload_max_filesize] = 50M
EOF

# Create PHP session directory
log "Creating PHP session directory..."
mkdir -p /var/lib/php/session
chown $APACHE_USER:$NGINX_GROUP /var/lib/php/session
chmod 770 /var/lib/php/session

# Create PHP-FPM socket directory
log "Creating PHP-FPM socket directory..."
mkdir -p /var/run/php-fpm
chown $APACHE_USER:$NGINX_GROUP /var/run/php-fpm
chmod 755 /var/run/php-fpm

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
find "$WEB_ROOT" -type d -exec chmod 775 {} \;
find "$WEB_ROOT" -type f -exec chmod 664 {} \;
find "$WEB_ROOT" -type f -name "*.py" -exec chmod 775 {} \;
chown -R root:$APACHE_GROUP "$WEB_ROOT"

# Create log directories if they don't exist
log "Setting up log directories..."
mkdir -p "$WEB_ROOT/portal/logs"/{access,errors,client,python}
chown -R root:$APACHE_GROUP "$WEB_ROOT/portal/logs"
chmod -R 775 "$WEB_ROOT/portal/logs"

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
