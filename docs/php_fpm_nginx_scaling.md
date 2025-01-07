# Scaling PHP-FPM with Nginx for Multiple Sessions

This guide explains how to configure PHP-FPM and Nginx to handle multiple concurrent sessions efficiently.

## 1. PHP-FPM Configuration

Edit `/etc/php-fpm.d/www.conf`:
```ini
; Process manager settings
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; Session handling
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/session
php_value[session.gc_maxlifetime] = 1440
```

## 2. Nginx Configuration

Edit `/etc/nginx/conf.d/default.conf`:
```nginx
# Worker connections
events {
    worker_connections 1024;
    multi_accept on;
}

# HTTP server configuration
server {
    listen 80;
    listen [::]:80;
    server_name dogcrayons.com www.dogcrayons.com;
    
    root /var/www/html/portal;
    index index.php;

    # Client body settings
    client_max_body_size 20M;
    client_body_buffer_size 128k;

    # Timeouts
    keepalive_timeout 65;
    fastcgi_read_timeout 300;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    # PHP handling with optimized settings
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # FastCGI caching settings
        fastcgi_buffers 8 16k;
        fastcgi_buffer_size 32k;
        
        # FastCGI performance settings
        fastcgi_keep_conn on;
        fastcgi_socket_keepalive on;
    }

    # Static file handling
    location ~* \.(js|css|png|jpg|jpeg|gif|ico)$ {
        expires max;
        log_not_found off;
        access_log off;
    }
}
```

## 3. System Settings

Add to `/etc/sysctl.conf`:
```
# Network tuning
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.ip_local_port_range = 1024 65535
net.ipv4.tcp_fin_timeout = 15
```

## 4. Session Directory Setup

```bash
# Create session directory
sudo mkdir -p /var/lib/php/session

# Set permissions
sudo chown -R nginx:nginx /var/lib/php/session
sudo chmod 770 /var/lib/php/session
```

## 5. Apply Changes

```bash
# Apply sysctl changes
sudo sysctl -p

# Restart services
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

## 6. Monitoring

Monitor PHP-FPM status:
```bash
# Check PHP-FPM processes
ps aux | grep php-fpm

# Monitor PHP-FPM status
sudo tail -f /var/log/php-fpm/error.log
```

Monitor Nginx status:
```bash
# Check Nginx processes
ps aux | grep nginx

# Monitor access logs
sudo tail -f /var/log/nginx/access.log

# Monitor error logs
sudo tail -f /var/log/nginx/error.log
```

## Performance Testing

You can test the setup using Apache Bench:
```bash
# Install Apache Bench
sudo dnf install httpd-tools

# Test with 100 concurrent users, 1000 requests
ab -n 1000 -c 100 http://dogcrayons.com/login.php
```

This configuration provides:
- Dynamic PHP-FPM process management
- Efficient session handling
- Static file caching
- Optimized nginx settings for multiple connections
- System-level network optimization
