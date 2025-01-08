# Logging System Comprehensive Audit & Implementation Plan

## Current System Analysis

### Components
1. Client-Side (JavaScript)
   - Global error handler (window.onerror)
   - AJAX error handler (jQuery ajaxError)
   - Currently POSTing to /portal/includes/logging/log_error.php (404 error)

2. PHP Components
   - PythonLogger class (portal/includes/PythonLogger.php)
   - ErrorLogger class (portal/includes/logging/log_error.php)
   - Logging bootstrap (portal/includes/logging_bootstrap.php)
   - Duplicate PythonLogger in private/includes/logging/

3. Python Component
   - Logger script (shared/scripts/modules/logging/logger.py)
   - Creates JSON log files with timestamps
   - Handles directory creation

4. Directory Structure
   - /var/www/html/shared/data/logs/system/{error,access,audit,etc}
   - /var/www/html/portal/logs/{errors,access,python,client}

## Issues Identified

1. Permission Issues
   - Directory ownership conflicts (nginx vs apache)
   - SELinux context missing for log directories
   - Inconsistent permissions (755 vs 775)

2. Code Organization Issues
   - Duplicate PythonLogger class definitions
   - Direct access to includes directory attempted
   - Missing proper API endpoint for client logging

3. Error Handling Issues
   - No proper error feedback to client
   - Missing logging rotation
   - No size limits on log files
   - No cleanup strategy

## Implementation Plan

### 1. Directory Structure & Permissions

```bash
/var/www/html/
├── portal/
│   ├── api/
│   │   └── log.php           # New API endpoint (775 apache:nginx)
│   └── logs/                 # Application logs (775 apache:nginx)
└── shared/
    └── data/
        └── logs/
            └── system/       # System logs (775 apache:nginx)
                ├── error/    # Error logs (775 apache:nginx)
                ├── access/   # Access logs (775 apache:nginx)
                └── ...       # Other log directories (775 apache:nginx)
```

### 2. Code Reorganization

1. Remove duplicate PythonLogger
   - Delete /private/includes/logging/PythonLogger.php
   - Update all references to use portal version

2. Create proper API endpoint
   - Implement /portal/api/log.php
   - Add proper error handling and responses
   - Add rate limiting
   - Add request validation

3. Update JavaScript
   - Point to new API endpoint
   - Add retry logic
   - Add offline queuing
   - Improve error context collection

### 3. Security Hardening

1. SELinux Configuration
   ```bash
   # Set context for log directories
   semanage fcontext -a -t httpd_log_t "/var/www/html/shared/data/logs/system(/.*)?"
   semanage fcontext -a -t httpd_log_t "/var/www/html/portal/logs(/.*)?"
   restorecon -R /var/www/html/shared/data/logs/system
   restorecon -R /var/www/html/portal/logs
   ```

2. File Permissions
   ```bash
   # Set ownership and permissions
   chown -R apache:nginx /var/www/html/shared/data/logs
   chown -R apache:nginx /var/www/html/portal/logs
   chmod -R 775 /var/www/html/shared/data/logs
   chmod -R 775 /var/www/html/portal/logs
   ```

3. Nginx Configuration
   ```nginx
   # Protect includes directory
   location ~ ^/portal/includes/ {
       deny all;
       return 403;
   }
   
   # Allow API access
   location ~ ^/portal/api/ {
       try_files $uri =404;
       fastcgi_pass unix:/run/php-fpm/www.sock;
       include fastcgi_params;
   }
   ```

### 4. Log Management

1. Implement Log Rotation
   ```bash
   # /etc/logrotate.d/phpadminlte
   /var/www/html/shared/data/logs/system/*/*.json {
       daily
       rotate 7
       compress
       delaycompress
       missingok
       notifempty
       create 664 apache nginx
   }
   ```

2. Add Log Cleanup Script
   ```python
   # Add to logger.py
   def cleanup_old_logs(directory, days_to_keep=7):
       """Remove log files older than specified days"""
       current_time = time.time()
       for root, _, files in os.walk(directory):
           for file in files:
               file_path = os.path.join(root, file)
               if os.path.getmtime(file_path) < current_time - (days_to_keep * 86400):
                   os.remove(file_path)
   ```

## Implementation Steps

1. Backup
   ```bash
   tar -czf /root/logs_backup_$(date +%Y%m%d).tar.gz /var/www/html/shared/data/logs
   ```

2. Directory Setup
   - Run updated setup script with new directory structure
   - Verify permissions and ownership
   - Configure SELinux contexts

3. Code Deployment
   - Deploy new API endpoint
   - Update JavaScript code
   - Remove duplicate PythonLogger
   - Add log rotation configuration

4. Testing
   - Verify client-side error logging
   - Check log file creation and permissions
   - Test log rotation
   - Validate API endpoint security

5. Monitoring
   - Add log size monitoring
   - Set up alerts for logging failures
   - Monitor disk space usage

## Rollback Plan

1. Keep backup of original files
2. Document current permissions
3. Create restore script:
   ```bash
   #!/bin/bash
   # Restore from backup
   tar -xzf /root/logs_backup_*.tar.gz -C /
   # Restore permissions
   chown -R apache:nginx /var/www/html/shared/data/logs
   chmod -R 775 /var/www/html/shared/data/logs
   # Restore SELinux contexts
   restorecon -R /var/www/html/shared/data/logs
   ```

## Success Criteria

1. No permission errors in logs
2. Client-side errors successfully logged
3. Log rotation working
4. SELinux not blocking operations
5. Proper error feedback to client
6. No duplicate class errors
7. Protected includes directory
8. Manageable log sizes
