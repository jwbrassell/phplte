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

# Set SELinux contexts if SELinux is enabled
if command -v semanage >/dev/null 2>&1 && command -v getenforce >/dev/null 2>&1; then
    if [ "$(getenforce)" != "Disabled" ]; then
        log "Setting SELinux contexts..."
        semanage fcontext -a -t httpd_sys_content_t "$WEB_ROOT(/.*)?"
        semanage fcontext -a -t httpd_sys_rw_content_t "$WEB_ROOT/portal/logs(/.*)?"
        restorecon -Rv $WEB_ROOT
    fi
fi

# Install LDAP dependencies and Python packages
log "Installing LDAP and Python dependencies..."
if command -v dnf >/dev/null 2>&1; then
    log "Installing python3.11-devel..."
    dnf install -y python3.11-devel
else
    error "DNF package manager not found. Please install python3.11-devel manually."
fi

if [ -f "$PYTHON_VENV/bin/pip3" ]; then
    # Set CFLAGS for python-ldap installation
    export CFLAGS="-I/usr/include/python3.11"
    log "Installing python-ldap with custom flags..."
    $PYTHON_VENV/bin/pip3 install python-ldap
    
    log "Installing remaining Python dependencies..."
    $PYTHON_VENV/bin/pip3 install -r requirements.txt
else
    warn "Python virtual environment not found at $PYTHON_VENV. Please set up virtual environment manually."
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
