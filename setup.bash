#!/bin/bash

# setup.bash
# Sets up proper permissions and ownership for the portal application
# Must be run as root

# Exit on any error
set -e

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

# Create necessary directories if they don't exist
log "Creating required directories..."
mkdir -p $WEB_ROOT/portal/logs/{access,errors,client,python}
mkdir -p $WEB_ROOT/shared/venv
mkdir -p $WEB_ROOT/shared/data/oncall_calendar/uploads

# Set specific permissions for oncall calendar directories
log "Setting oncall calendar directory permissions..."
chown -R $APACHE_USER:$APACHE_GROUP "$WEB_ROOT/shared/data/oncall_calendar"
chmod -R 775 "$WEB_ROOT/shared/data/oncall_calendar"

# Set base ownership and permissions
log "Setting base ownership and permissions..."
chown -R $APACHE_USER:$APACHE_GROUP $WEB_ROOT
find $WEB_ROOT -type d -exec chmod 755 {} \;
find $WEB_ROOT -type f -exec chmod 644 {} \;

# Set specific permissions for log directories
log "Setting log directory permissions..."
chmod -R 775 $WEB_ROOT/portal/logs
chown -R $APACHE_USER:$APACHE_GROUP $WEB_ROOT/portal/logs

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

# Install LDAP dependencies and Python packages
log "Installing LDAP and Python dependencies..."
if command -v dnf >/dev/null 2>&1; then
    log "Installing python3.11-devel..."
    dnf install -y python3.11-devel
else
    error "DNF package manager not found. Please install python3.11-devel manually."
fi

# Create Python virtual environment
log "Creating Python virtual environment..."
if command -v python3.11 >/dev/null 2>&1; then
    python3.11 -m venv $PYTHON_VENV
    source $PYTHON_VENV/bin/activate
    
    log "Upgrading pip..."
    python -m pip install --upgrade pip

    # Set CFLAGS for python-ldap installation
    export CFLAGS="-I/usr/include/python3.11"
    log "Installing python-ldap with custom flags..."
    pip install python-ldap
    
    log "Installing remaining Python dependencies..."
    pip install -r requirements.txt
    
    deactivate
else
    error "Python 3.11 not found. Please install Python 3.11 before running this script."
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

log "Setup complete! Please verify the following manually:"
echo "1. Test LDAP authentication"
echo "2. Verify log file creation"
echo "3. Check vault token access"
echo "4. Restart Apache service if needed"

exit 0
