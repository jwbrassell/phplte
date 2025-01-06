#!/bin/bash

# RBAC System Setup Script

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

# Check if running with sudo/root
if [ "$EUID" -ne 0 ]; then 
    error "Please run as root or with sudo"
    exit 1
fi

# Create backup directory
BACKUP_DIR="./backups/rbac/$(date +'%Y%m%d_%H%M%S')"
mkdir -p "$BACKUP_DIR"
log "Created backup directory: $BACKUP_DIR"

# Backup existing configuration
backup_configs() {
    log "Backing up existing configuration files..."
    
    # List of files to backup
    local files=(
        "portal/config/rbac.json"
        "portal/config/menu-bar.json"
    )
    
    for file in "${files[@]}"; do
        if [ -f "$file" ]; then
            cp "$file" "$BACKUP_DIR/$(basename "$file")"
            log "Backed up $file"
        else
            warn "File $file not found, skipping backup"
        fi
    done
}

# Create log directories
setup_logging() {
    log "Setting up logging directories..."
    
    # Create log directories in shared/data/logs/system
    mkdir -p shared/data/logs/system/rbac
    mkdir -p shared/data/logs/system/rbac/reports
    
    # Set permissions
    chmod 755 shared/data/logs/system/rbac
    chmod 755 shared/data/logs/system/rbac/reports
    
    # Create initial log files
    touch shared/data/logs/system/rbac/role_changes.log
    touch shared/data/logs/system/rbac/sync_events.log
    touch shared/data/logs/system/rbac/validation.log
    
    log "Created logging directories with proper permissions"
}

# Update RBAC configuration
update_rbac_config() {
    log "Updating RBAC configuration..."
    
    # Create role_definitions in rbac.json if it doesn't exist
    local rbac_file="portal/config/rbac.json"
    if [ -f "$rbac_file" ]; then
        # Create temporary file
        local temp_file=$(mktemp)
        
        # Add role_definitions if not present
        jq '. + {role_definitions: {admin: {description: "Full system access", inherits: ["user"]}, user: {description: "Basic user access", inherits: []}}}' "$rbac_file" > "$temp_file"
        
        # Replace original file
        mv "$temp_file" "$rbac_file"
        log "Updated rbac.json with role definitions"
    else
        error "rbac.json not found"
        return 1
    fi
}

# Synchronize roles across files
sync_roles() {
    log "Synchronizing roles across configuration files..."
    
    # Extract and update roles directly using jq
    if [ -f "portal/config/rbac.json" ]; then
        # Create initial JSON structure if file is empty
        if [ ! -s "portal/config/rbac.json" ]; then
            echo '{
                "adom_groups": [],
                "category_list": ["Dashboard", "Admin", "Reports", "Settings"],
                "icon_list": [
                    "fas fa-tachometer-alt",
                    "fas fa-users-cog",
                    "fas fa-chart-bar",
                    "fas fa-cog",
                    "fas fa-file",
                    "fas fa-folder",
                    "fas fa-list"
                ],
                "role_definitions": {
                    "admin": {
                        "description": "Full system access",
                        "inherits": ["user"]
                    },
                    "user": {
                        "description": "Basic user access",
                        "inherits": []
                    }
                }
            }' > "portal/config/rbac.json"
        fi
        
        # Extract unique roles from menu-bar.json and update rbac.json in one step
        local temp_file=$(mktemp)
        jq -s '
          .[0] * {
            "adom_groups": (
              .[1] | 
              with_entries(select(.value | type == "object" and has("urls"))) |
              [.[].urls | to_entries[].value.roles[]] | 
              flatten | 
              unique
            )
          }' \
          "portal/config/rbac.json" \
          "portal/config/menu-bar.json" > "$temp_file"
        mv "$temp_file" "portal/config/rbac.json"
        log "Updated roles in rbac.json"
    fi
}

# Main setup process
main() {
    log "Starting RBAC system setup..."
    
    # Execute setup steps
    backup_configs || exit 1
    setup_logging || exit 1
    update_rbac_config || exit 1
    sync_roles || exit 1
    
    log "RBAC system setup completed successfully"
    
    # Display next steps
    echo -e "\n${GREEN}Setup completed. Next steps:${NC}"
    echo "1. Review the backup files in $BACKUP_DIR"
    echo "2. Verify the updated configuration files"
    echo "3. Check shared/data/logs/system/rbac for logging setup"
    echo "4. Test the RBAC system with sample roles"
    echo "5. Review implementation_plan.md for full implementation details"
    echo "6. Check logging_audit.md for logging system details"
    echo "7. Review login_flow.md for authentication flow"
}

# Execute main function
main
