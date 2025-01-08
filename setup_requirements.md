# Setup.bash Requirements

## Domain Configuration
- Domain: dogcrayons.com
- Self-signed SSL certificate requirements:
  - Location: /etc/nginx/ssl/
  - Files: dogcrayons.com.key and dogcrayons.com.crt
  - Validity: 365 days
  - Key type: RSA 2048 bit
  - Certificate subject: /C=US/ST=State/L=City/O=Organization/CN=dogcrayons.com

## Directory Structure
```
/var/www/html/
├── portal/
│   ├── logs/
│   │   ├── access/
│   │   ├── errors/
│   │   ├── client/
│   │   └── python/
│   ├── includes/
│   ├── plugins/
│   └── static/
├── shared/
│   ├── scripts/
│   │   └── modules/
│   │       ├── logging/
│   │       ├── vault/
│   │       ├── ldap/
│   │       ├── rbac/
│   │       ├── utilities/
│   │       ├── api/
│   │       ├── data_processing/
│   │       └── oncall_calendar/
│   ├── data/
│   └── venv/
└── private/
    ├── config/
    └── includes/
        ├── logging/
        ├── auth/
        ├── calendar/
        └── components/
            ├── datatables/
            ├── forms/
            └── widgets/
```

## Nginx Configuration
1. Server Blocks:
   - Default server (port 80/443): Return 444
   - HTTP server: Redirect to HTTPS
   - HTTPS server: Main application

2. Main HTTPS Server Configuration:
   - Root: /var/www/html/portal
   - Index: index.php
   - SSL: TLSv1.2 and TLSv1.3
   - SSL Ciphers: HIGH:!aNULL:!MD5

3. Location Blocks:
   ```nginx
   # Root redirect
   location = / {
       return 301 /portal/;
   }

   # PHP handling
   location ~ \.php$ {
       fastcgi_pass 127.0.0.1:9000;
       include fastcgi_params;
       fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
   }

   # Private directory protection
   location ^~ /private {
       deny all;
       return 404;
   }

   # Shared directory
   location ^~ /shared {
       alias /var/www/html/shared;
       try_files $uri $uri/ =404;
   }

   # Main location
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

4. Error Pages:
   - 404: /404.php
   - 403: /403.php
   - 500,502,503,504: /50x.html

## PHP-FPM Configuration
1. Process Manager:
   - Dynamic
   - max_children = 50
   - start_servers = 5
   - min_spare_servers = 5
   - max_spare_servers = 35

2. PHP Settings:
   - memory_limit = 128M
   - max_execution_time = 300
   - post_max_size = 50M
   - upload_max_filesize = 50M
   - error_log = /var/www/html/portal/logs/errors/php_errors.log

3. User/Group:
   - User: apache
   - Group: apache
   - Listen: 127.0.0.1:9000

## Python Environment
1. Virtual Environment:
   - Location: /var/www/html/shared/venv
   - Python version: 3.x

2. Required Packages:
   - python-ldap
   - hvac
   - requests
   - PyYAML
   - python-dateutil
   - pytz
   - Additional packages from requirements.txt

## File Permissions
1. Directories:
   - Default: 755
   - Logs: 775

2. Files:
   - Default: 644
   - Python scripts: 755
   - SSL key: 600
   - SSL cert: 644

3. Ownership:
   - Web files: nginx:nginx
   - Log files: apache:nginx
   - Python files: apache:nginx

## SELinux Configuration
- Disabled for initial setup
- Booleans (while active):
  - httpd_can_network_connect = 1
  - httpd_unified = 1
  - httpd_can_network_connect_db = 1

## Required Packages
- nginx
- php-fpm
- php-cli
- php-json
- php-common
- php-mysqlnd
- php-zip
- php-gd
- php-mbstring
- php-curl
- php-xml
- php-bcmath
- php-ldap
- python3
- python3-pip
- python3-devel
- openldap-devel

## Vault Configuration
- Config directory: /var/www/html/private/config
- Environment file: vault.env
- Permissions: 640
- Owner: root:apache
