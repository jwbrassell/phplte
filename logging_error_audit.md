# Logging and Authentication Error Audit

## Current Issues

1. Authentication Failure
   - Works when run from command line as Apache
   - Fails when run through web interface
   - Suggests permission/ownership issues with Python execution

2. log_error.php Failures
   - Console shows failures accessing log_error.php
   - Likely causing cascade failures in authentication process
   - May be preventing proper session handling

## Investigation Plan

### 1. Logging System Audit
```bash
# Check log file permissions and ownership
ls -la portal/logs/
ls -la portal/logs/access/
ls -la portal/logs/python/

# Verify Apache user permissions
ps aux | grep apache
id apache  # or www-data on some systems

# Check Python script permissions
ls -la shared/scripts/modules/ldap/ldapcheck.py
ls -la shared/venv/bin/python
```

### 2. log_error.php Investigation
- Check if file exists
- Verify permissions
- Review error handling implementation
- Consider temporarily disabling to isolate authentication issues

### 3. Python Environment Check
```bash
# Verify Python path and permissions
ls -la shared/venv/
ls -la shared/venv/bin/python

# Check LDAP module installation
shared/venv/bin/pip list | grep ldap
```

### 4. File Permission Fixes

1. Log Directory Permissions:
```bash
# Create logs directory if it doesn't exist
mkdir -p portal/logs/access portal/logs/python

# Set proper ownership
chown -R apache:apache portal/logs/  # or www-data:www-data

# Set proper permissions
chmod -R 755 portal/logs/
chmod -R 775 portal/logs/access portal/logs/python
```

2. Python Script Permissions:
```bash
chmod 755 shared/scripts/modules/ldap/ldapcheck.py
chmod 755 shared/venv/bin/python
```

3. Verify SELinux Context (if applicable):
```bash
ls -Z portal/logs/
semanage fcontext -a -t httpd_log_t "portal/logs(/.*)?"
restorecon -R portal/logs/
```

## Implementation Steps

1. Disable log_error.php temporarily:
   - Rename file to log_error.php.bak
   - Create simple placeholder that always returns success
   - Test authentication without logging

2. Fix Log Directory Structure:
   - Create all required directories
   - Set proper ownership and permissions
   - Verify Apache can write to logs

3. Update Python Script Permissions:
   - Ensure executable permissions
   - Verify proper ownership
   - Test execution as Apache user

4. Implement Proper Error Handling:
   - Add error logging to files
   - Implement fallback for logging failures
   - Add debug logging for authentication process

## Verification Steps

1. Test Authentication Flow:
```bash
# Test as Apache user
sudo -u apache shared/venv/bin/python shared/scripts/modules/ldap/ldapcheck.py test test123 APP

# Check log files
tail -f portal/logs/python/ldap_debug.log
tail -f portal/logs/access/YYYYMMDD_access.log
```

2. Monitor Web Server Logs:
```bash
tail -f /var/log/apache2/error.log  # or appropriate path
```

3. Check File Permissions:
```bash
namei -l portal/logs/access/current.log
namei -l shared/scripts/modules/ldap/ldapcheck.py
```

## Rollback Plan

1. Keep backup of original log_error.php
2. Document all permission changes
3. Maintain list of modified files
4. Create backup of current log files

## Success Criteria

1. Authentication works through web interface
2. No log_error.php errors in console
3. Proper logging to all required files
4. Correct error messages displayed to users
5. All processes running with correct permissions

## Next Steps

1. Implement fixes in order of least to most invasive
2. Test each change individually
3. Monitor logs for new errors
4. Document all changes made
5. Update deployment documentation with new requirements
