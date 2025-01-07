#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
    exit 1
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "Please run as root"
fi

# Base directory
BASE_DIR="/var/www/html"
cd $BASE_DIR || error "Could not change to $BASE_DIR"

log "Starting post-deployment permission setup..."

# Set base ownership to root:apache
log "Setting base ownership to root:apache..."
chown -R root:apache .

# Set base permissions
log "Setting base permissions..."
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Make Python scripts executable
log "Making Python scripts executable..."
find . -name "*.py" -type f -exec chmod 755 {} \;

# Set up log directories
log "Setting up log directories..."
LOG_DIRS=(
    "portal/logs/access"
    "portal/logs/errors"
    "portal/logs/client"
    "portal/logs/python"
)

for dir in "${LOG_DIRS[@]}"; do
    log "Setting up $dir..."
    
    # Create directory if it doesn't exist
    mkdir -p "$dir"
    
    # Set ownership and permissions
    chown root:apache "$dir"
    chmod 2775 "$dir"  # setgid + rwxrwxr-x
    
    # Set default ACL
    setfacl -d -m g::rwx "$dir"
    setfacl -d -m o::r-x "$dir"
    
    # Set ACL on existing files
    setfacl -R -m g:apache:rwx "$dir"
done

# Set up data directories
log "Setting up data directories..."
DATA_DIRS=(
    "shared/data/oncall_calendar/uploads"
    "shared/data/oncall_calendar/backups"
)

for dir in "${DATA_DIRS[@]}"; do
    log "Setting up $dir..."
    
    # Create directory if it doesn't exist
    mkdir -p "$dir"
    
    # Set ownership and permissions
    chown root:apache "$dir"
    chmod 2775 "$dir"
    
    # Set default ACL
    setfacl -d -m g::rwx "$dir"
    setfacl -d -m o::r-x "$dir"
    
    # Set ACL on existing files
    setfacl -R -m g:apache:rwx "$dir"
done

# Verify critical files
log "Verifying critical files..."
CRITICAL_FILES=(
    "shared/scripts/modules/ldap/ldapcheck.py"
    "shared/scripts/modules/vault/vault_utility.py"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        log "Verifying $file..."
        chmod 755 "$file"
        chown root:apache "$file"
    else
        error "Critical file missing: $file"
    fi
done

# Verify Python virtual environment
log "Verifying Python virtual environment..."
VENV_DIR="shared/venv"
if [ -d "$VENV_DIR" ]; then
    chown -R root:apache "$VENV_DIR"
    chmod -R 755 "$VENV_DIR"
else
    error "Python virtual environment not found at $VENV_DIR"
fi

# Verify vault.env permissions
log "Verifying vault.env permissions..."
VAULT_ENV="/etc/vault.env"
if [ -f "$VAULT_ENV" ]; then
    chown root:apache "$VAULT_ENV"
    chmod 640 "$VAULT_ENV"
else
    error "vault.env not found at $VAULT_ENV"
fi

log "Post-deployment setup complete!"
log "Please verify the following manually:"
echo "1. Test LDAP authentication"
echo "2. Verify log file creation"
echo "3. Check vault token access"
echo "4. Verify Python script execution"
