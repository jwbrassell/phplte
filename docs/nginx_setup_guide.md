# Minimal Nginx Setup for PHP on Rocky Linux

This guide provides the minimal setup needed to run a PHP application with nginx on Rocky Linux.

## 1. Install Required Packages

```bash
# Install nginx and PHP-FPM
sudo dnf install nginx php-fpm
```

## 2. Configure PHP-FPM

Edit `/etc/php-fpm.d/www.conf`:
```ini
# Change the socket to TCP port
listen = 127.0.0.1:9000
```

## 3. Configure Nginx

Create `/etc/nginx/conf.d/default.conf`:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name dogcrayons.com www.dogcrayons.com;
    
    root /var/www/html/portal;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 4. Set Permissions

```bash
# Set ownership and permissions
sudo chown -R nginx:nginx /var/www/html
sudo chmod -R 755 /var/www/html
```

## 5. Start Services

```bash
# Start and enable services
sudo systemctl enable nginx
sudo systemctl enable php-fpm
sudo systemctl start php-fpm
sudo systemctl start nginx
```

## 6. Open Firewall

```bash
# Allow HTTP traffic
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload
```

## Verification

1. Test nginx config:
```bash
sudo nginx -t
```

2. Check services:
```bash
sudo systemctl status nginx
sudo systemctl status php-fpm
```

3. Verify PHP-FPM port:
```bash
sudo ss -tulpn | grep 9000
```

## Troubleshooting

1. Check nginx error logs:
```bash
sudo tail -f /var/log/nginx/error.log
```

2. Check PHP-FPM logs:
```bash
sudo tail -f /var/log/php-fpm/error.log
```

3. Test PHP processing:
```bash
curl -I http://dogcrayons.com/login.php
```

## Key Benefits Over Apache (httpd)

1. Simpler Configuration
   - Single config file vs multiple Apache config files
   - No complex module setup required
   - Straightforward PHP-FPM integration

2. No SELinux Complexity
   - Fewer permission issues
   - No context settings needed

3. Better Performance
   - Direct PHP-FPM communication
   - Lighter weight process management

4. Easier Troubleshooting
   - Centralized error logs
   - Clear error messages
   - Simple TCP-based PHP connection
