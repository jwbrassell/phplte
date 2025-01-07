#!/bin/bash

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

# Verify apache user exists
if ! id -u $APACHE_USER >/dev/null 2>&1; then
    error "Apache user ($APACHE_USER) does not exist"
fi

# Verify web root exists
if [ ! -d "$WEB_ROOT" ]; then
    error "Web root directory $WEB_ROOT does not exist"
fi

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

# Set up critical directories
log "Setting up critical directories..."
CRITICAL_DIRS=(
    "$WEB_ROOT/portal"
    "$WEB_ROOT/portal/includes"
    "$WEB_ROOT/portal/static/css"
    "$WEB_ROOT/portal/static/js"
    "$WEB_ROOT/portal/plugins"
    "$WEB_ROOT/portal/logs"
    "$WEB_ROOT/shared"
    "$WEB_ROOT/shared/data"
    "$WEB_ROOT/shared/data/oncall_calendar"
    "$WEB_ROOT/private/config"
)

for dir in "${CRITICAL_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        install -d -m 775 -o root -g $APACHE_GROUP "$dir"
        log "Created directory: $dir"
    else
        chown root:$APACHE_GROUP "$dir"
        chmod 775 "$dir"
        log "Set permissions for $dir"
    fi
done

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

# Copy critical files from old to new locations
log "Copying critical files..."
declare -A FILE_MAPPINGS=(
    ["private/includes/auth/auth.php"]="portal/includes/auth.php"
    ["private/includes/init.php"]="portal/includes/init.php"
    ["private/includes/logging_bootstrap.php"]="portal/includes/logging_bootstrap.php"
    ["private/includes/logging/PythonLogger.php"]="portal/includes/PythonLogger.php"
    ["private/includes/header.php"]="portal/header.php"
    ["private/includes/footer.php"]="portal/footer.php"
)

for src in "${!FILE_MAPPINGS[@]}"; do
    dst="${FILE_MAPPINGS[$src]}"
    src_path="$WEB_ROOT/$src"
    dst_path="$WEB_ROOT/$dst"
    
    if [ -f "$src_path" ]; then
        log "Copying $src to $dst"
        cp "$src_path" "$dst_path"
        chown root:$APACHE_GROUP "$dst_path"
        chmod 644 "$dst_path"
    else
        warn "Source file not found: $src_path"
    fi
done

# Update environment configuration
log "Updating environment configuration..."
cat > "$WEB_ROOT/portal/includes/env.php" << 'EOF'
<?php
define('IS_PRODUCTION', true);
define('PROJECT_ROOT', '/var/www/html');
define('BASE_PATH', '/var/www/html');
define('SHARED_DIR', '/var/www/html/shared');
define('DATA_DIR', '/var/www/html/shared/data');
define('SCRIPTS_DIR', '/var/www/html/shared/scripts');
?>
EOF

chown root:$APACHE_GROUP "$WEB_ROOT/portal/includes/env.php"
chmod 644 "$WEB_ROOT/portal/includes/env.php"

# Fix symlinks
log "Setting up symlinks..."
if [ ! -L "$WEB_ROOT/portal/shared" ]; then
    ln -sf "$WEB_ROOT/shared" "$WEB_ROOT/portal/shared"
    log "Created shared symlink"
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

# Verify paths
log "Verifying critical paths..."
VERIFY_FILES=(
    "$WEB_ROOT/portal/includes/init.php"
    "$WEB_ROOT/portal/includes/env.php"
    "$WEB_ROOT/portal/header.php"
    "$WEB_ROOT/portal/footer.php"
    "$WEB_ROOT/portal/index.php"
)

for file in "${VERIFY_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        error "Critical file missing: $file"
    fi
    if ! sudo -u $APACHE_USER test -r "$file"; then
        error "Apache user cannot read: $file"
    fi
done

log "Setup complete!"
echo "Please verify:"
echo "1. SELinux is disabled (getenforce)"
echo "2. Vault token is accessible"
echo "3. Web server is accessible"
echo "4. Reboot system if SELinux was previously enabled"

exit 0
