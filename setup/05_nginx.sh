#!/bin/bash

# Source initialization script
source "$(dirname "$0")/00_init.sh"

log "Configuring Nginx..."

# Generate SSL certificate
log "Generating SSL certificate..."
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$SSL_DIR/$DOMAIN.key" \
    -out "$SSL_DIR/$DOMAIN.crt" \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=$DOMAIN"

chmod 600 "$SSL_DIR/$DOMAIN.key"
chmod 644 "$SSL_DIR/$DOMAIN.crt"

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

# Create portal config
log "Creating portal configuration..."
cat > /etc/nginx/conf.d/portal.conf << EOF
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

# HTTP server block for domain
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    return 301 https://\$server_name\$request_uri;
}

# HTTPS server block for domain
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name $DOMAIN;
    root $WEB_ROOT/portal;
    index index.php;

    # Error logs
    access_log $WEB_ROOT/portal/logs/access/nginx_access.log;
    error_log $WEB_ROOT/portal/logs/errors/nginx_error.log debug;

    # SSL Configuration
    ssl_certificate $SSL_DIR/$DOMAIN.crt;
    ssl_certificate_key $SSL_DIR/$DOMAIN.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Root redirect
    location = / {
        return 301 /portal/;
    }

    # PHP handling
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # Private directory protection
    location ^~ /private {
        deny all;
        return 404;
    }

    # Shared directory
    location ^~ /shared {
        alias $WEB_ROOT/shared;
        try_files \$uri \$uri/ =404;
    }

    # Block access to dot files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Error pages
    error_page 404 /404.php;
    error_page 403 /403.php;
    error_page 500 502 503 504 /50x.html;

    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
}
EOF

# Test nginx configuration
log "Testing Nginx configuration..."
if ! nginx -t; then
    error "Nginx configuration test failed"
fi

log "Nginx configuration complete"
