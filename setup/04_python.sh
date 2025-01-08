#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Setting up Python environment..."

VENV_DIR="$WEB_ROOT/shared/venv"
REQUIREMENTS_FILE="requirements.txt"

# Create virtual environment
log "Creating virtual environment..."
if ! python3 -m venv "$VENV_DIR"; then
    error "Failed to create virtual environment"
fi

# Activate virtual environment
log "Activating virtual environment..."
source "$VENV_DIR/bin/activate"

if [ "$VIRTUAL_ENV" != "$VENV_DIR" ]; then
    error "Failed to activate virtual environment"
fi

# Upgrade pip
log "Upgrading pip..."
"$VENV_DIR/bin/pip3" install --upgrade pip

# Install base packages
log "Installing base Python packages..."
base_packages=(
    "python-ldap"
    "hvac"
    "requests"
    "PyYAML"
    "python-dateutil"
    "pytz"
)

for package in "${base_packages[@]}"; do
    log "Installing $package..."
    if ! "$VENV_DIR/bin/pip3" install "$package"; then
        warn "Failed to install $package"
    fi
done

# Install requirements if file exists
if [ -f "$REQUIREMENTS_FILE" ]; then
    log "Installing packages from $REQUIREMENTS_FILE..."
    if ! "$VENV_DIR/bin/pip3" install -r "$REQUIREMENTS_FILE"; then
        warn "Failed to install some packages from $REQUIREMENTS_FILE"
    fi
else
    warn "$REQUIREMENTS_FILE not found"
fi

# Create basic logging module
log "Creating basic logging module..."
LOGGING_MODULE="$WEB_ROOT/shared/scripts/modules/logging/__init__.py"

cat > "$LOGGING_MODULE" << 'EOF'
import logging
import os
from datetime import datetime

def setup_logger(name, log_file, level=logging.INFO):
    """Set up a new logger instance.
    
    Args:
        name (str): Name of the logger
        log_file (str): Path to the log file
        level (int): Logging level (default: logging.INFO)
    
    Returns:
        logging.Logger: Configured logger instance
    """
    formatter = logging.Formatter('%(asctime)s %(levelname)s %(message)s')
    
    handler = logging.FileHandler(log_file)
    handler.setFormatter(formatter)
    
    logger = logging.getLogger(name)
    logger.setLevel(level)
    logger.addHandler(handler)
    
    return logger
EOF

# Set proper permissions for logging module
chmod 644 "$LOGGING_MODULE"
chown "$APACHE_USER:$NGINX_GROUP" "$LOGGING_MODULE"

# Create __init__.py files for all module directories
log "Creating __init__.py files for all modules..."
find "$WEB_ROOT/shared/scripts/modules" -type d -exec touch {}/__init__.py \;
find "$WEB_ROOT/shared/scripts/modules" -name "__init__.py" -exec chmod 644 {} \;
find "$WEB_ROOT/shared/scripts/modules" -name "__init__.py" -exec chown "$APACHE_USER:$NGINX_GROUP" {} \;

# Deactivate virtual environment
deactivate

log "Python environment setup complete"
