#!/bin/bash

# setup.bash
# Sets up proper permissions and ownership for the portal application
# Must be run as root

# Exit on any error
set -e

# Disable SELinux immediately
log "Disabling SELinux at startup..."
if command -v setenforce >/dev/null 2>&1; then
    setenforce 0
    log "SELinux disabled for current session"
fi

if [ -f "/etc/selinux/config" ]; then
    log "Permanently disabling SELinux..."
    sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config
    log "SELinux permanently disabled (requires reboot to take full effect)"
fi

# Configuration
WEB_ROOT="/var/www/html"
APACHE_USER="apache"
APACHE_GROUP="apache"
PYTHON_VENV="$WEB_ROOT/shared/venv"

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

# Verify apache user exists
if ! id -u $APACHE_USER >/dev/null 2>&1; then
    error "Apache user ($APACHE_USER) does not exist"
fi

log "Starting permission and ownership setup..."

# Detect environment
IS_PRODUCTION=false
if [ "$WEB_ROOT" = "/var/www/html" ]; then
    IS_PRODUCTION=true
    log "Running in production environment"
else
    log "Running in development environment"
fi

# Create and set permissions for required directories
log "Creating and setting up directories..."
CALENDAR_DATA="$WEB_ROOT/shared/data/oncall_calendar"

# Create directories with proper ownership from the start
install -d -m 775 -o $APACHE_USER -g $APACHE_GROUP "$WEB_ROOT/portal/logs/"{access,errors,client,python}
install -d -m 775 -o $APACHE_USER -g $APACHE_GROUP "$WEB_ROOT/shared/venv"
install -d -m 775 -o $APACHE_USER -g $APACHE_GROUP "$CALENDAR_DATA/"{uploads,backups}
install -d -m 775 -o $APACHE_USER -g $APACHE_GROUP "$WEB_ROOT/shared/scripts/modules/oncall_calendar"

# Set up calendar data files
log "Setting up calendar data..."
TEAMS_JSON="$CALENDAR_DATA/teams.json"
ROTATIONS_JSON="$CALENDAR_DATA/rotations.json"

# Create initial JSON files with proper ownership
for file in "$TEAMS_JSON" "$ROTATIONS_JSON"; do
    if [ ! -f "$file" ]; then
        content='{"'$(basename "$file" .json)'"":[]}'
        # Create file with proper ownership and permissions from the start
        install -m 664 -o $APACHE_USER -g $APACHE_GROUP /dev/null "$file"
        echo "$content" > "$file"
    fi
done

# Double-check permissions recursively
log "Verifying directory permissions..."
chown -R $APACHE_USER:$APACHE_GROUP "$CALENDAR_DATA"
chmod -R u+rwX,g+rwX,o+rX "$CALENDAR_DATA"

# Set up permissions based on environment
log "Setting up remaining permissions..."
if [ "$IS_PRODUCTION" = true ]; then
    # Production permissions
    log "Applying production permissions..."
    
    # Set base permissions (more restrictive)
    find $WEB_ROOT -type d -exec chmod 755 {} \;
    find $WEB_ROOT -type f -exec chmod 644 {} \;
    
    # Set specific permissions for writable directories
    find "$WEB_ROOT/portal/logs" -type d -exec chmod 775 {} \;
    find "$CALENDAR_DATA" -type d -exec chmod 775 {} \;
    
    # Set Python script permissions
    find "$WEB_ROOT" -name "*.py" -type f -exec chmod 755 {} \;
    
    # Verify apache user can write to critical directories
    sudo -u $APACHE_USER mkdir -p "$CALENDAR_DATA/test_write"
    if [ $? -eq 0 ]; then
        rm -rf "$CALENDAR_DATA/test_write"
        log "Write test successful"
    else
        error "Apache user cannot write to calendar directory"
    fi
else
    # Development permissions
    log "Applying development permissions..."
    
    # More permissive permissions for development
    chmod -R 777 "$CALENDAR_DATA"
    chmod -R 777 $WEB_ROOT/portal/logs
    find "$WEB_ROOT" -name "*.py" -type f -exec chmod 755 {} \;
    
    # Create Python virtual environment if it doesn't exist
    if [ ! -d "$WEB_ROOT/shared/venv" ]; then
        log "Creating development virtual environment..."
        python3 -m venv "$WEB_ROOT/shared/venv"
        source "$WEB_ROOT/shared/venv/bin/activate"
        pip install -r requirements.txt
        deactivate
    fi
fi

# Create initial Python module structure if needed
log "Setting up Python module structure..."
for file in "__init__.py" "calendar_api.py" "csv_handler.py"; do
    module_file="$WEB_ROOT/shared/scripts/modules/oncall_calendar/$file"
    if [ ! -f "$module_file" ]; then
        touch "$module_file"
        chmod +x "$module_file"
    fi
done

# Make Python scripts executable
log "Setting Python script permissions..."
find $WEB_ROOT -name "*.py" -type f -exec chmod +x {} \;

# Special handling for sensitive files
log "Setting up sensitive file permissions..."

# Vault environment file
VAULT_ENV="/etc/vault.env"
if [ -f "$VAULT_ENV" ]; then
    log "Setting vault.env permissions..."
    chown root:$APACHE_GROUP $VAULT_ENV
    chmod 640 $VAULT_ENV
else
    warn "vault.env not found at $VAULT_ENV"
fi

# Disable SELinux
log "Disabling SELinux..."
if command -v setenforce >/dev/null 2>&1; then
    setenforce 0
    log "SELinux disabled for current session"
fi

# Permanently disable SELinux
if [ -f "/etc/selinux/config" ]; then
    log "Permanently disabling SELinux..."
    sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config
    log "SELinux permanently disabled (requires reboot to take full effect)"
else
    warn "SELinux config file not found at /etc/selinux/config"
fi

# Install Python 3.11 and dependencies
log "Installing Python 3.11 and dependencies..."
if command -v dnf >/dev/null 2>&1; then
    log "Installing Python 3.11 and development packages..."
    dnf install -y python3.11 python3.11-devel
    
    # Create Python virtual environment
    log "Creating Python virtual environment..."
    python3.11 -m venv $PYTHON_VENV
    
    # Activate virtual environment and install packages
    source $PYTHON_VENV/bin/activate
    
    log "Upgrading pip..."
    python3.11 -m pip install --upgrade pip
    
    # Set CFLAGS for python-ldap installation
    export CFLAGS="-I/usr/include/python3.11"
    log "Installing python-ldap with custom flags..."
    python3.11 -m pip install python-ldap
    
    log "Installing remaining Python dependencies..."
    python3.11 -m pip install -r requirements.txt
    
    deactivate
else
    error "DNF package manager not found. Please install python3.11 and python3.11-devel manually."
fi

# Ensure proper permissions after package installation
log "Resetting permissions after package installation..."
chmod -R 777 "$WEB_ROOT/shared/data/oncall_calendar"
chown -R $APACHE_USER:$APACHE_GROUP "$WEB_ROOT/shared/data/oncall_calendar"

# Double-check SELinux is disabled
log "Final SELinux check and disable..."
if command -v setenforce >/dev/null 2>&1; then
    setenforce 0
    log "SELinux confirmed disabled for current session"
fi

if [ -f "/etc/selinux/config" ]; then
    log "Confirming SELinux permanently disabled..."
    sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config
fi

# Verify critical files and directories
log "Verifying setup..."

# Check Python scripts
LDAP_CHECK_SCRIPT="$WEB_ROOT/shared/scripts/modules/ldap/ldapcheck.py"
if [ -f "$LDAP_CHECK_SCRIPT" ]; then
    if [ ! -x "$LDAP_CHECK_SCRIPT" ]; then
        error "LDAP check script is not executable"
    fi
else
    warn "LDAP check script not found at $LDAP_CHECK_SCRIPT"
fi

# Check log directories
for dir in access errors client python; do
    LOG_DIR="$WEB_ROOT/portal/logs/$dir"
    if [ ! -d "$LOG_DIR" ]; then
        error "Log directory $LOG_DIR does not exist"
    fi
    if [ ! -w "$LOG_DIR" ]; then
        error "Log directory $LOG_DIR is not writable by apache"
    fi
done

# Final verification of apache access
log "Testing apache user access..."
sudo -u $APACHE_USER test -w $WEB_ROOT/portal/logs/access || error "Apache user cannot write to logs"
sudo -u $APACHE_USER test -x $LDAP_CHECK_SCRIPT || warn "Apache user cannot execute LDAP check script"

# Verify OnCall Calendar directory permissions and paths
log "Verifying OnCall Calendar setup..."

# Debug path resolution
PHP_TEST_SCRIPT=$(mktemp)
cat > "$PHP_TEST_SCRIPT" << 'EOF'
<?php
$currentDir = __DIR__;
$sharedDir = dirname(dirname(dirname($currentDir))) . '/shared';
$dataPath = $sharedDir . '/data/oncall_calendar';
$uploadPath = $dataPath . '/uploads';

echo "Current Directory: $currentDir\n";
echo "Resolved Shared Directory: $sharedDir\n";
echo "Data Path: $dataPath\n";
echo "Upload Path: $uploadPath\n";
EOF

log "Testing PHP path resolution..."
sudo -u $APACHE_USER php "$PHP_TEST_SCRIPT"
rm "$PHP_TEST_SCRIPT"

# Create symlink to ensure correct path resolution
log "Setting up shared directory symlink..."
if [ ! -L "$WEB_ROOT/portal/shared" ]; then
    ln -s "$WEB_ROOT/shared" "$WEB_ROOT/portal/shared"
fi

# Verify directory permissions
log "Verifying directory permissions..."
sudo -u $APACHE_USER test -w "$CALENDAR_DATA" || error "Apache user cannot write to oncall_calendar directory"
sudo -u $APACHE_USER test -w "$CALENDAR_DATA/uploads" || error "Apache user cannot write to oncall_calendar uploads directory"
sudo -u $APACHE_USER test -w "$CALENDAR_DATA/backups" || error "Apache user cannot write to oncall_calendar backups directory"

# Verify file permissions
log "Verifying calendar file permissions..."
sudo -u $APACHE_USER test -w "$TEAMS_JSON" || error "Apache user cannot write to teams.json"
sudo -u $APACHE_USER test -w "$ROTATIONS_JSON" || error "Apache user cannot write to rotations.json"

# Make Python scripts executable
log "Setting calendar script permissions..."
chmod +x "$WEB_ROOT/shared/scripts/modules/oncall_calendar/"*.py

# Verify file permissions
log "Verifying file permissions..."
sudo -u $APACHE_USER test -w "$WEB_ROOT/shared/data/oncall_calendar/teams.json" || error "Apache user cannot write to teams.json"
sudo -u $APACHE_USER test -w "$WEB_ROOT/shared/data/oncall_calendar/rotations.json" || error "Apache user cannot write to rotations.json"

log "Setup complete! Please verify the following manually:"
echo "1. Test LDAP authentication"
echo "2. Verify log file creation"
echo "3. Check vault token access"
echo "4. Restart Apache service if needed"

exit 0
