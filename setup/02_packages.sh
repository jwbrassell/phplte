#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Installing required packages..."

# Check package manager
if command_exists dnf; then
    PKG_MANAGER="dnf"
elif command_exists yum; then
    PKG_MANAGER="yum"
else
    error "No supported package manager found (dnf/yum)"
fi

# Array of required packages
declare -a packages=(
    # Web server
    "nginx"
    
    # PHP and extensions
    "php-fpm"
    "php-cli"
    "php-json"
    "php-common"
    "php-mysqlnd"
    "php-zip"
    "php-gd"
    "php-mbstring"
    "php-curl"
    "php-xml"
    "php-bcmath"
    "php-ldap"
    
    # Python and development tools
    "python3"
    "python3-pip"
    "python3-devel"
    "openldap-devel"
)

# Install packages
log "Using $PKG_MANAGER to install packages..."
if ! $PKG_MANAGER install -y "${packages[@]}"; then
    error "Failed to install packages"
fi

# Verify critical packages
log "Verifying critical packages..."
critical_commands=("nginx" "php-fpm" "python3" "pip3")
for cmd in "${critical_commands[@]}"; do
    if ! command_exists "$cmd"; then
        error "Critical command '$cmd' not found after installation"
    fi
done

# Enable services
log "Enabling services..."
systemctl enable nginx php-fpm

log "Package installation complete"
