#!/bin/bash

# PHP-FPM configuration
sudo tee /etc/php-fpm.d/www.conf << 'EOL'
[www]
user = nginx
group = nginx
listen = 127.0.0.1:9000
listen.allowed_clients = 127.0.0.1

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/session
php_value[session.gc_maxlifetime] = 1440
EOL

# Main nginx configuration
sudo tee /etc/nginx/nginx.conf << 'EOL'
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log;
pid /run/nginx.pid;

events {
    worker_connections 1024;
    multi_accept on;
}

http {
    log_format  main  '$remote_addr - $remote_user [$time_local] "$request" '
                      '$status $body_bytes_sent "$http_referer" '
                      '"$http_user_agent" "$http_x_forwarded_for"';

    access_log  /var/log/nginx/access.log  main;

    sendfile            on;
    tcp_nopush          on;
    tcp_nodelay         on;
    keepalive_timeout   65;
    types_hash_max_size 4096;

    include             /etc/nginx/mime.types;
    default_type        application/octet-stream;
    include             /etc/nginx/conf.d/*.conf;
}
EOL

# Server configuration
sudo tee /etc/nginx/conf.d/default.conf << 'EOL'
server {
    listen 80;
    listen [::]:80;
    server_name dogcrayons.com www.dogcrayons.com;
    
    root /var/www/html/portal;
    index index.php;

    client_max_body_size 20M;
    client_body_buffer_size 128k;
    keepalive_timeout 65;
    fastcgi_read_timeout 300;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffers 8 16k;
        fastcgi_buffer_size 32k;
        fastcgi_keep_conn on;
        fastcgi_socket_keepalive on;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico)$ {
        expires max;
        log_not_found off;
        access_log off;
    }
}
EOL

# System settings
sudo tee -a /etc/sysctl.conf << 'EOL'
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.ip_local_port_range = 1024 65535
net.ipv4.tcp_fin_timeout = 15
EOL

# Create session directory
sudo mkdir -p /var/lib/php/session
sudo chown -R nginx:nginx /var/lib/php/session
sudo chmod 770 /var/lib/php/session

# Apply changes
sudo sysctl -p
sudo systemctl restart php-fpm
sudo systemctl restart nginx

echo "Configuration complete. Check logs for any errors:"
echo "PHP-FPM: sudo tail -f /var/log/php-fpm/error.log"
echo "Nginx: sudo tail -f /var/log/nginx/error.log"
