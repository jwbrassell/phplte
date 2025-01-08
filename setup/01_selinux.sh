#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Configuring SELinux..."

# Disable SELinux for current session
if command_exists setenforce; then
    log "Disabling SELinux for current session..."
    setenforce 0
    log "SELinux disabled for current session"
fi

# Permanently disable SELinux
if [ -f "/etc/selinux/config" ]; then
    log "Permanently disabling SELinux..."
    cp /etc/selinux/config /etc/selinux/config.bak
    sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config
    if grep -q "^SELINUX=disabled" /etc/selinux/config; then
        log "SELinux permanently disabled in config"
    else
        error "Failed to update SELinux config"
    fi
fi

# Set SELinux booleans while it's still active
if command_exists setsebool; then
    log "Setting SELinux booleans..."
    
    # Array of booleans to set
    declare -a booleans=(
        "httpd_can_network_connect"
        "httpd_unified"
        "httpd_can_network_connect_db"
    )

    # Set each boolean
    for boolean in "${booleans[@]}"; do
        log "Setting $boolean..."
        setsebool -P "$boolean" 1
        if [ $? -ne 0 ]; then
            warn "Failed to set $boolean"
        fi
    done
fi

log "SELinux configuration complete"
