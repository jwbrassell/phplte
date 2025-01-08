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

    # Redirect HTTP to HTTPS
    server {
        listen 80;
        listen [::]:80;
        server_name dogcrayons.com;
        return 301 https://\$server_name\$request_uri;
    }

    # HTTPS server
    server {
        listen 443 ssl http2;
        listen [::]:443 ssl http2;
        server_name dogcrayons.com;

        # SSL configuration
        ssl_certificate /etc/nginx/ssl/dogcrayons.com.crt;
        ssl_certificate_key /etc/nginx/ssl/dogcrayons.com.key;
        ssl_session_timeout 1d;
        ssl_session_cache shared:SSL:50m;
        ssl_session_tickets off;

        # Modern SSL configuration
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
        ssl_prefer_server_ciphers off;

        # HSTS
        add_header Strict-Transport-Security "max-age=63072000" always;
        root /var/www/html;
        index index.php index.html;

        # Access and error logs
        access_log /var/www/html/portal/logs/access/nginx.access.log combined;
        error_log /var/www/html/portal/logs/errors/nginx_error.log;

        # Root location
        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        # Protect includes directory
        location ~ ^/portal/includes/ {
            deny all;
            return 403;
        }

        # API endpoints
        location ~ ^/portal/api/ {
            try_files \$uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)\$;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
            
            # Security headers
            add_header X-Frame-Options "SAMEORIGIN" always;
            add_header X-XSS-Protection "1; mode=block" always;
            add_header X-Content-Type-Options "nosniff" always;
            add_header Referrer-Policy "no-referrer-when-downgrade" always;
            add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
            add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
        }

        # PHP handling for portal
        location ~ \.php\$ {
            try_files \$uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)\$;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_param HTTPS on;
        }

        # Deny access to . files
        location ~ /\. {
            deny all;
            access_log off;
            log_not_found off;
        }

        # Handle static files
        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
            expires max;
            log_not_found off;
            access_log off;
        }
    }

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
}
EOF

# Test nginx configuration
log "Testing Nginx configuration..."
if ! nginx -t; then
    error "Nginx configuration test failed"
fi

log "Nginx configuration complete"
