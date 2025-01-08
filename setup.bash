#!/bin/bash

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="/var/www/html"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1"
    exit 1
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "Please run as root"
fi

# Array of setup scripts in order
declare -a setup_scripts=(
    "00_init.sh"
    "01_selinux.sh"
    "02_packages.sh"
    "03_directories.sh"
    "04_python.sh"
    "05_nginx.sh"
    "06_php.sh"
    "07_logging.sh"
)

# Create setup directory in web root and copy scripts
log "Copying setup scripts to web root..."
mkdir -p "$WEB_ROOT/setup"
chmod 755 "$WEB_ROOT/setup"
chown root:root "$WEB_ROOT/setup"
cp -r "$SCRIPT_DIR"/setup/* "$WEB_ROOT/setup/"

# Make all setup scripts executable in web root
log "Making setup scripts executable..."
if ! chmod +x "$WEB_ROOT"/setup/*.sh; then
    error "Failed to make setup scripts executable. Please check permissions."
fi

# Verify scripts are executable
for script in "${setup_scripts[@]}"; do
    if [ ! -x "$WEB_ROOT/setup/$script" ]; then
        error "Script $script is not executable. Please check permissions."
    fi
done

# Change to web root directory
cd "$WEB_ROOT" || error "Failed to change to web root directory"

# Run each setup script
for script in "${setup_scripts[@]}"; do
    log "Running $script..."
    if ! "./setup/$script"; then
        error "Failed to run $script"
    fi
    log "Completed $script"
done

# Backup existing nginx config if it exists
if [ -f "/etc/nginx/nginx.conf" ]; then
    cp "/etc/nginx/nginx.conf" "/etc/nginx/nginx.conf.bak"
fi

# Restart services in correct order
log "Restarting services..."
systemctl restart php-fpm
systemctl restart nginx

log "Setup complete!"
echo -e "\nNext steps:"
echo "1. Reboot system to fully apply SELinux changes"
echo "2. Verify web server is accessible: https://dogcrayons.com"
echo "3. Check logs for any errors:"
echo "   - Nginx: /var/www/html/portal/logs/errors/nginx_error.log"
echo "   - PHP: /var/www/html/portal/logs/errors/php_errors.log"
echo "   - PHP-FPM: /var/www/html/portal/logs/access/php-fpm.access.log"

exit 0
