#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Configuring Nginx..."

# Create SSL directory
log "Creating SSL directory..."
mkdir -p "$SSL_DIR"
chmod 700 "$SSL_DIR"
chown root:root "$SSL_DIR"

# Generate SSL certificate
log "Generating SSL certificate..."
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$SSL_DIR/$DOMAIN.key" \
    -out "$SSL_DIR/$DOMAIN.crt" \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=$DOMAIN"

chmod 600 "$SSL_DIR/$DOMAIN.key"
chmod 644 "$SSL_DIR/$DOMAIN.crt"
chown root:root "$SSL_DIR/$DOMAIN.key" "$SSL_DIR/$DOMAIN.crt"

# Create main nginx config
log "Creating main Nginx configuration..."
cat > /etc/nginx/nginx.conf << EOF
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log notice;
pid /run/nginx.pid;

include /usr/share/nginx/modules/*.conf;

events {
    worker_connections 1024;
}

http {
    log_format  main  '\$remote_addr - \$remote_user [\$time_local] "\$request" '
                      '\$status \$body_bytes_sent "\$http_referer" '
                      '"\$http_user_agent" "\$http_x_forwarded_for"';

    access_log  /var/log/nginx/access.log  main;

    sendfile            on;
    tcp_nopush          on;
    tcp_nodelay         on;
    keepalive_timeout   65;
    types_hash_max_size 4096;

    include             /etc/nginx/mime.types;
    default_type        application/octet-stream;

    include /etc/nginx/conf.d/*.conf;
}
EOF

# Clean up existing configs
rm -f /etc/nginx/conf.d/*.conf

# Install our nginx configuration with variable substitution
log "Installing nginx configuration..."
sed "s|\$PHP_FPM_SOCK|$PHP_FPM_SOCK|g" "$(dirname "$0")/config/phpadminlte.conf" > "/etc/nginx/conf.d/phpadminlte.conf"
chmod 644 "/etc/nginx/conf.d/phpadminlte.conf"
chown root:root "/etc/nginx/conf.d/phpadminlte.conf"

# Create default server block for non-matching requests
log "Creating default server configuration..."
cat > /etc/nginx/conf.d/default.conf << EOF
# Default server block for non-matching requests
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    return 444;
}

server {
    listen 443 ssl default_server;
    listen [::]:443 ssl default_server;
    server_name _;
    ssl_certificate $SSL_DIR/$DOMAIN.crt;
    ssl_certificate_key $SSL_DIR/$DOMAIN.key;
    return 444;
}
EOF

# Test nginx configuration
log "Testing Nginx configuration..."
if ! nginx -t; then
    error "Nginx configuration test failed"
fi

log "Nginx configuration complete"
