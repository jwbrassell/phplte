# Setup Requirements

## Overview
The application is cloned directly into /var/www/html and configured to serve files from the portal directory without exposing the directory structure in URLs.

## Installation
1. Clone repository:
   ```bash
   cd /var/www
   git clone [repo-url] html
   ```
2. Run setup:
   ```bash
   cd html
   sudo ./setup.bash
   ```

## Domain Configuration
- Domain: dogcrayons.com
- Self-signed SSL certificate:
  - Location: /etc/nginx/ssl/
  - Files: dogcrayons.com.key and dogcrayons.com.crt
  - Validity: 365 days
  - Key type: RSA 2048 bit
  - Certificate subject: /C=US/ST=State/L=City/O=Organization/CN=dogcrayons.com

## Directory Structure
Repository is cloned directly into /var/www/html with the following structure:
```
/var/www/html/
├── portal/              # Served as root URL (/)
│   ├── logs/           # Runtime logs
│   ├── includes/       # PHP includes
│   ├── plugins/        # Frontend libraries
│   └── static/         # Static assets
├── shared/
│   ├── scripts/        # Python scripts
│   │   └── modules/    # Python modules
│   ├── data/          # Shared data
│   └── venv/          # Python virtual environment
└── private/           # Protected files
    ├── config/        # Configuration files
    └── includes/      # Private includes
```

## Nginx Configuration
1. URL Structure:
   - Clean URLs without /portal/ prefix
   - Example: https://dogcrayons.com/login.php
   - Files served directly from portal directory

2. Server Configuration:
   - Root: /var/www/html/portal
   - Index: login.php, index.php
   - SSL: TLSv1.2 and TLSv1.3
   - Default response: 444 for unknown hosts

3. Location Blocks:
   ```nginx
   # PHP handling
   location ~ \.php$ {
       try_files $uri =404;
       fastcgi_pass 127.0.0.1:9000;
       include fastcgi_params;
       fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       fastcgi_param PATH_INFO $fastcgi_path_info;
   }

   # Main location - default to login.php
   location / {
       try_files $uri $uri/ /login.php?$query_string;
   }
   ```

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
   - display_errors = On (initial setup)
   - allow_url_fopen = On (initial setup)

3. User/Group:
   - User: apache
   - Group: apache
   - Listen: 127.0.0.1:9000

## Python Environment
1. Virtual Environment:
   - Location: /var/www/html/shared/venv
   - Python version: 3.x
   - Created during setup

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
   - Private: 750
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
   - Private config: root:apache

## SELinux Configuration
- Disabled for initial setup
- Booleans set (while active):
  - httpd_can_network_connect = 1
  - httpd_unified = 1
  - httpd_can_network_connect_db = 1

## Required Packages
1. Web Server:
   - nginx
   - php-fpm

2. PHP Extensions:
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

3. Python:
   - python3
   - python3-pip
   - python3-devel
   - openldap-devel

## Vault Configuration
- Config directory: /var/www/html/private/config
- Environment file: vault.env
- Permissions: 640
- Owner: root:apache
