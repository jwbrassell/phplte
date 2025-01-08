#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Creating directory structure..."

# Create main directories with specific permissions
declare -A main_dirs=(
    ["$WEB_ROOT/portal"]="755:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared"]="755:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/private"]="750:$NGINX_USER:$NGINX_GROUP"
)

# Create log directories
declare -A log_dirs=(
    ["$WEB_ROOT/portal/logs/access"]="775:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/portal/logs/errors"]="775:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/portal/logs/client"]="775:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/portal/logs/python"]="775:$APACHE_USER:$NGINX_GROUP"
)

# Create portal subdirectories
declare -A portal_dirs=(
    ["$WEB_ROOT/portal/includes"]="755:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/portal/plugins"]="755:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/portal/static"]="755:$NGINX_USER:$NGINX_GROUP"
)

# Create shared subdirectories
declare -A shared_dirs=(
    ["$WEB_ROOT/shared/scripts/modules/logging"]="755:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/scripts/modules/vault"]="755:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/scripts/modules/ldap"]="755:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/scripts/modules/rbac"]="755:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/scripts/modules/utilities"]="755:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/scripts/modules/api"]="755:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/scripts/modules/data_processing"]="755:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/scripts/modules/oncall_calendar"]="755:$APACHE_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/data"]="755:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/shared/venv"]="755:$APACHE_USER:$NGINX_GROUP"
)

# Create private subdirectories
declare -A private_dirs=(
    ["$WEB_ROOT/private/config"]="750:root:$APACHE_GROUP"
    ["$WEB_ROOT/private/includes/logging"]="750:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/private/includes/auth"]="750:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/private/includes/calendar"]="750:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/private/includes/components/datatables"]="750:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/private/includes/components/forms"]="750:$NGINX_USER:$NGINX_GROUP"
    ["$WEB_ROOT/private/includes/components/widgets"]="750:$NGINX_USER:$NGINX_GROUP"
)

# Function to create directories with specified permissions
create_dirs() {
    local -n dirs=$1
    for dir in "${!dirs[@]}"; do
        IFS=: read -r perms owner group <<< "${dirs[$dir]}"
        ensure_dir "$dir" "$perms" "$owner" "$group"
        log "Created directory: $dir ($perms $owner:$group)"
    done
}

# Create all directories
create_dirs main_dirs
create_dirs log_dirs
create_dirs portal_dirs
create_dirs shared_dirs
create_dirs private_dirs

# Create PHP-FPM socket directory
ensure_dir "$(dirname $PHP_FPM_SOCK)" "755" "$APACHE_USER" "$NGINX_GROUP"

# Create SSL directory
ensure_dir "$SSL_DIR" "700" "root" "root"

# Create symbolic links
log "Creating symbolic links..."
ln -sf "$WEB_ROOT/shared" "$WEB_ROOT/portal/shared"
ln -sf "$WEB_ROOT/private" "$WEB_ROOT/portal/private"

log "Directory structure creation complete"
