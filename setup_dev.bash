#!/bin/bash

# setup_dev.bash
# Sets up development environment for the portal application
# Does not require root privileges

# Exit on any error
set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

# Get project root
PROJECT_ROOT="$(pwd)"
log "Project root: $PROJECT_ROOT"

# Create necessary directories
log "Creating required directories..."
mkdir -p portal/logs/{access,errors,client,python}
mkdir -p shared/venv
mkdir -p shared/data/oncall_calendar/{uploads,backups}
mkdir -p shared/scripts/modules/oncall_calendar

# Set up calendar data
log "Setting up calendar data..."
CALENDAR_DATA="shared/data/oncall_calendar"
TEAMS_JSON="$CALENDAR_DATA/teams.json"
ROTATIONS_JSON="$CALENDAR_DATA/rotations.json"

# Create initial JSON files if they don't exist
for file in "$TEAMS_JSON" "$ROTATIONS_JSON"; do
    if [ ! -f "$file" ]; then
        content='{"'$(basename "$file" .json)'"":[]}'
        echo "$content" > "$file"
        chmod 666 "$file"
    fi
done

# Set development permissions
log "Setting development permissions..."
chmod -R 777 "$CALENDAR_DATA"
chmod -R 777 portal/logs
find . -name "*.py" -type f -exec chmod 755 {} \;

# Set up Python environment
log "Setting up Python environment..."
if [ ! -d "shared/venv" ]; then
    python3 -m venv shared/venv
    source shared/venv/bin/activate
    pip install --upgrade pip
    pip install -r requirements.txt
    deactivate
    log "Python virtual environment created and packages installed"
else
    log "Python virtual environment already exists"
fi

# Create initial Python module structure
log "Setting up Python module structure..."
for file in "__init__.py" "calendar_api.py" "csv_handler.py"; do
    module_file="shared/scripts/modules/oncall_calendar/$file"
    if [ ! -f "$module_file" ]; then
        touch "$module_file"
        chmod +x "$module_file"
    fi
done

# Verify setup
log "Verifying setup..."

# Check directory permissions
for dir in "$CALENDAR_DATA" "$CALENDAR_DATA/uploads" "$CALENDAR_DATA/backups" "portal/logs"; do
    if [ ! -w "$dir" ]; then
        warn "Directory not writable: $dir"
    fi
done

# Check file permissions
for file in "$TEAMS_JSON" "$ROTATIONS_JSON"; do
    if [ ! -w "$file" ]; then
        warn "File not writable: $file"
    fi
done

# Check Python environment
if [ -f "shared/venv/bin/python3" ]; then
    log "Python virtual environment found"
else
    warn "Python virtual environment not found"
fi

log "Development setup complete!"
log "You can now run: php -S localhost:8000 -t portal/"

exit 0
