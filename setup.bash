#!/bin/bash

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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

# Make all setup scripts executable
log "Making setup scripts executable..."
chmod +x "$SCRIPT_DIR"/setup/*.sh

# Array of setup scripts in order
declare -a setup_scripts=(
    "00_init.sh"
    "01_selinux.sh"
    "02_packages.sh"
    "03_directories.sh"
    "04_python.sh"
    "05_nginx.sh"
    "06_php.sh"
)

# Run each setup script
for script in "${setup_scripts[@]}"; do
    log "Running $script..."
    if ! "$SCRIPT_DIR/setup/$script"; then
        error "Failed to run $script"
    fi
    log "Completed $script"
done

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
