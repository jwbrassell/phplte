#!/bin/bash

# Domain configuration
DOMAIN="dogcrayons.com"

# Directory paths
WEB_ROOT="/var/www/html"
SSL_DIR="/etc/nginx/ssl"
PHP_FPM_SOCK="/run/php-fpm/www.sock"

# User/Group configuration
APACHE_USER="apache"
APACHE_GROUP="apache"
NGINX_USER="nginx"
NGINX_GROUP="nginx"

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

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

# Check if script is run as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        error "Please run as root"
    fi
}

# Verify user exists
verify_user() {
    local user=$1
    if ! id -u "$user" >/dev/null 2>&1; then
        error "$user user does not exist"
    fi
}

# Create directory if it doesn't exist
ensure_dir() {
    local dir=$1
    local mode=${2:-755}
    local owner=${3:-$NGINX_USER}
    local group=${4:-$NGINX_GROUP}

    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        chmod "$mode" "$dir"
        chown "$owner:$group" "$dir"
    fi
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Export all variables
export DOMAIN WEB_ROOT SSL_DIR PHP_FPM_SOCK
export APACHE_USER APACHE_GROUP NGINX_USER NGINX_GROUP
export RED GREEN YELLOW NC
