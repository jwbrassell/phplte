# Apache (httpd) Setup Guide for Rocky Linux 9.5

This guide provides step-by-step instructions for setting up Apache (httpd) with PHP and Python support on Rocky Linux 9.5.

## Prerequisites

- Rocky Linux 9.5
- Root access
- Internet connectivity

## 1. Install Required Packages
```bash
# Install Apache, PHP, and required modules
dnf install httpd php php-cli php-ldap mod_ssl php-json php-xml php-mbstring php-mysqlnd php-gd

# Ensure SSL module is loaded
ln -s /etc/httpd/conf.modules.d/00-ssl.conf /etc/httpd/conf.modules.d/ssl.conf

# Install Python 3.11 and development tools
dnf install python3.11 python3.11-devel

# Install LDAP development packages (required for compiling python-ldap)
dnf install openldap-devel python3-devel gcc

# Install Git
dnf install git
```

## 2. Set Up Web Directory with Git
```bash
# Navigate to web directory
cd /var/www

# Remove default content
rm -rf html

# Clone repository (weblinks branch)
git clone --branch weblinks https://github.com/jwbrassell/phplte.git html
cd html

# Set permissions
chown -R apache:apache /var/www/html
chmod -R 755 /var/www/html
```

## 3. Create SSL Certificate
```bash
# Create directory for SSL certificates
mkdir -p /etc/httpd/ssl

# Generate self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
-keyout /etc/httpd/ssl/selfsigned.key \
-out /etc/httpd/ssl/selfsigned.crt
```
When prompted, use your actual domain name for the Common Name field. Replace all instances of 'dogcrayons.com' in the Apache configuration with your actual domain name (or use 'localhost' for local development).

## 4. Set Up Python Virtual Environment
```bash
# Create virtual environment directory in project
mkdir -p shared/venv

# Create virtual environment
python3.11 -m venv shared/venv

# Set permissions
chown -R apache:apache shared/venv
chmod -R 755 shared/venv

# Install requirements
source shared/venv/bin/activate
pip install -r requirements.txt
deactivate
```

Note: The Python virtual environment is created within the project directory to maintain portability across different environments.

## 5. Create Log Directories
```bash
# Create log directories
mkdir -p /var/www/html/shared/data/logs/system/{access,errors,client,audit,performance,rbac}

# Set permissions
chown -R apache:apache /var/www/html/shared/data/logs
chmod -R 755 /var/www/html/shared/data/logs
```

## 6. Configure SELinux
```bash
# Install SELinux tools
dnf install policycoreutils-python-utils

# Set contexts
semanage fcontext -a -t httpd_sys_content_t "/var/www/html(/.*)?"
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/shared/data/logs(/.*)?"
restorecon -Rv /var/www/html
```

## 7. Configure Apache
```bash
# Backup original configs
cp /etc/httpd/conf/httpd.conf /etc/httpd/conf/httpd.conf.bak
cp /etc/httpd/conf.d/ssl.conf /etc/httpd/conf.d/ssl.conf.bak

# Remove default SSL config to avoid port conflicts
mv /etc/httpd/conf.d/ssl.conf /etc/httpd/conf.d/ssl.conf.disabled

# Create main config (replace dogcrayons.com with your actual domain)
cat > /etc/httpd/conf/httpd.conf << 'EOL'
ServerRoot "/etc/httpd"
Listen 443 https
Include conf.modules.d/*.conf

# User and Group Settings
User apache
Group apache

ServerAdmin root@localhost

# Default Directory Settings
<Directory />
    AllowOverride None
    Require all denied
</Directory>

ServerName dogcrayons.com
DocumentRoot "/var/www/html"

<Directory "/var/www">
    AllowOverride None
    Require all granted
</Directory>

<Directory "/var/www/html">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

<IfModule dir_module>
    DirectoryIndex index.php index.html
</IfModule>

# PHP Configuration
<IfModule php_module>
    AddHandler application/x-httpd-php .php
    DirectoryIndex index.php
    php_value session.cookie_httponly 1
    php_value session.cookie_secure 1
</IfModule>

# SSL Configuration
SSLPassPhraseDialog exec:/usr/libexec/httpd-ssl-pass-dialog
SSLSessionCache shmcb:/run/httpd/sslcache(512000)
SSLSessionCacheTimeout 300
SSLCryptoDevice builtin

# HTTP to HTTPS Redirection
Listen 80
<VirtualHost *:80>
    ServerName dogcrayons.com
    Redirect permanent / https://dogcrayons.com/
</VirtualHost>

# HTTPS Virtual Host Configuration
<VirtualHost *:443>
    ServerName dogcrayons.com
    DocumentRoot "/var/www/html"
    
    SSLEngine on
    SSLCertificateFile /etc/httpd/ssl/selfsigned.crt
    SSLCertificateKeyFile /etc/httpd/ssl/selfsigned.key
    SSLHonorCipherOrder on
    SSLCipherSuite PROFILE=SYSTEM
    SSLProxyCipherSuite PROFILE=SYSTEM

    <Directory "/var/www/html">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch "\.(cgi|shtml|phtml|php)$">
        SSLOptions +StdEnvVars
    </FilesMatch>

    BrowserMatch "MSIE [2-5]" \
        nokeepalive ssl-unclean-shutdown \
        downgrade-1.0 force-response-1.0

    CustomLog logs/ssl_request_log \
        "%t %h %{SSL_PROTOCOL}x %{SSL_CIPHER}x \"%r\" %b"
</VirtualHost>

# Files Configuration
<Files ".ht*">
    Require all granted
</Files>

# Logging Configuration
ErrorLog "logs/error_log"
LogLevel warn

<IfModule log_config_module>
    LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
    LogFormat "%h %l %u %t \"%r\" %>s %b" common

    <IfModule logio_module>
        LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %I %O" combinedio
    </IfModule>

    CustomLog "logs/access_log" combined
</IfModule>

# MIME Configuration
<IfModule mime_module>
    TypesConfig /etc/mime.types
    AddType application/x-compress .Z
    AddType application/x-gzip .gz .tgz
    AddType text/html .shtml
    AddOutputFilter INCLUDES .shtml
</IfModule>

AddDefaultCharset UTF-8

<IfModule mime_magic_module>
    MIMEMagicFile conf/magic
</IfModule>

EnableSendfile on
Include conf.d/*.conf
EOL
```

# Create portal-specific config
cat > /etc/httpd/conf.d/portal.conf << 'EOL'
<Directory "/var/www/html/portal">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Allow following symlinks for dist and plugins directories
<Directory "/var/www/html/dist">
    Options FollowSymLinks
    Require all granted
</Directory>

<Directory "/var/www/html/plugins">
    Options FollowSymLinks
    Require all granted
</Directory>
EOL
```

## 8. Install Python Requirements
```bash
# Activate virtual environment and install packages
source shared/venv/bin/activate
pip install -r requirements.txt
deactivate
```

Note: Make sure you're in the project root directory when running these commands.

## 9. Test Configuration
```bash
# Test Apache config
apachectl configtest

# If successful, start and enable Apache
systemctl start httpd
systemctl enable httpd
```

## 10. Configure Firewall
(Skip this step if running in a development environment without firewall requirements)
```bash
# Install firewalld if not already installed
dnf install firewalld

# Start and enable firewalld
systemctl start firewalld
systemctl enable firewalld

# Allow HTTPS traffic
firewall-cmd --permanent --add-service=https
firewall-cmd --permanent --add-service=http
firewall-cmd --reload
```

## 11. Test Access
```bash
# Check service status
systemctl status httpd

# Test PHP
echo "<?php phpinfo(); ?>" > /var/www/html/test.php
# Access https://dogcrayons.com/test.php
# Remove test file after verification
rm /var/www/html/test.php
```

## Troubleshooting

### PHP Not Working
If only the test page works but other PHP pages don't, you may need to update your Apache configuration with proper PHP handling. Add or update the following sections in `/etc/httpd/conf/httpd.conf`:

1. Enable PHP module and secure sessions:
```apache
<IfModule php_module>
    AddHandler php-script .php
    AddType text/html .php
    DirectoryIndex index.php
    php_value session.cookie_httponly 1
    php_value session.cookie_secure 1
</IfModule>
```

2. Update DirectoryIndex to prioritize PHP:
```apache
<IfModule dir_module>
    DirectoryIndex index.php index.html
</IfModule>
```

3. Ensure proper DocumentRoot and Directory settings in SSL VirtualHost:
```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot "/var/www/html"
    
    <Directory "/var/www/html">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    # ... rest of SSL configuration ...
</VirtualHost>
```

After making these changes:
```bash
# Test the configuration
apachectl configtest

# If test passes, restart Apache
systemctl restart httpd
```

### Common Commands
```bash
# Check SELinux status
sestatus

# Check Apache error log
tail -f /var/log/httpd/error_log

# Check PHP error log
tail -f /var/log/php-fpm/www-error.log

# Test Python script execution
sudo -u apache shared/venv/bin/python shared/scripts/modules/logging/logger.py test "Test message" "{}"

# Check permissions
ls -la /var/www/html
ls -la shared/venv
ls -la shared/data/logs/system
```

### Common Issues

1. **SELinux Blocking Access**
   - Check SELinux logs: `ausearch -m AVC -ts recent`
   - Consider using `sealert -a /var/log/audit/audit.log`

2. **Permission Issues**
   - Verify Apache user ownership: `ls -l /var/www/html`
   - Check log directory permissions: `ls -l /var/www/html/shared/data/logs`

3. **SSL Certificate Issues**
   - Verify certificate exists: `ls -l /etc/httpd/ssl/`
   - Check certificate validity: `openssl x509 -in /etc/httpd/ssl/selfsigned.crt -text`

## Accessing the Application

After completing the setup, you should be able to access the application at:
```
https://dogcrayons.com/portal/login.php
```
(Remember to replace dogcrayons.com with your actual domain name or 'localhost' for local development)

The default test credentials are:
- Username: test
- Password: test123
