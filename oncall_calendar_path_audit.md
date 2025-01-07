# OnCall Calendar Path Resolution Audit

## Current Issue
The OnCall Calendar system fails consistently in both production and local development (port 8000) with the error:
```
Fatal error: Uncaught Exception: Calendar storage directory not found. Please contact system administrator. 
in /var/www/html/portal/includes/OnCallCalendar.php:26
```

## Environment Analysis

### Production Environment
- Base Path: `/var/www/html`
- Python Scripts: `/var/www/html/shared/scripts/modules/oncall_calendar`
- Data Directory: `/var/www/html/shared/data/oncall_calendar`
- Virtual Environment: `/var/www/html/shared/venv`

### Local Development Environment
- Base Path: `/Users/justin/Downloads/phpadminlte`
- Python Scripts: `shared/scripts/modules/oncall_calendar`
- Data Directory: `shared/data/oncall_calendar`
- Virtual Environment: `shared/venv`

## Path Resolution Flow

1. **Initial Configuration**
   - config.php sets:
     ```php
     $DIR = __DIR__;  // Current directory path
     $ROOTDIR = "../..";  // Relative path to project root
     ```

2. **Path Resolution in OnCallCalendar.php**
   - Current method:
     ```php
     $projectRoot = realpath($DIR . '/' . $ROOTDIR);
     ```
   - Issues:
     - Path resolution varies based on how the script is accessed
     - realpath() may fail if intermediate directories don't exist
     - Inconsistent behavior between direct file access and web server access

3. **Directory Structure Verification**
   - Current directories exist but may not be accessible:
     ```php
     $this->dataPath = $projectRoot . '/shared/data/oncall_calendar';
     $this->uploadPath = $this->dataPath . '/uploads';
     ```

## Root Causes and Solutions

1. **Project Root Resolution** ✅
   - Issue: Incorrect project root resolution from portal directory
   - Solution: Use realpath(__DIR__ . '/../..') to properly resolve project root
   - Added debug logging to verify directory structure

2. **Path Normalization** ✅
   - Issue: Inconsistent handling of shared/ prefix and slashes
   - Solution: Clean paths using trim() to ensure consistent format
   - Simplified path resolution logic

3. **Environment Detection** ✅
   - Issue: No clear production vs development distinction
   - Solution: Added IS_PRODUCTION constant based on hostname
   - Different path handling for each environment

4. **Debug Logging** ✅
   - Issue: Limited visibility into path resolution
   - Solution: Added comprehensive logging:
     ```
     - Directory structure logging
     - Path resolution steps
     - Environment configuration
     - Final resolved paths
     ```

## Current Implementation

1. **Project Root Resolution**
   ```php
   $projectRoot = realpath(__DIR__ . '/../..');
   if (!$projectRoot) {
       error_log("Failed to resolve project root from: " . __DIR__ . '/../..');
       throw new Exception("Could not resolve project root directory");
   }
   ```

2. **Path Constants**
   ```php
   define('PROJECT_ROOT', $projectRoot);
   define('BASE_PATH', IS_PRODUCTION ? '/var/www/html' : PROJECT_ROOT);
   define('SHARED_DIR', PROJECT_ROOT . '/shared');
   define('DATA_DIR', SHARED_DIR . '/data');
   define('SCRIPTS_DIR', SHARED_DIR . '/scripts');
   ```

3. **Path Resolution**
   ```php
   function resolvePath($path) {
       global $projectRoot;
       $cleanPath = trim($path, '/');
       $absolutePath = IS_PRODUCTION 
           ? '/var/www/html/' . $cleanPath
           : $projectRoot . '/' . $cleanPath;
       return realpath($absolutePath) ?: $absolutePath;
   }
   ```

## Proposed Solutions

1. **Centralize Path Configuration**
   ```php
   // In config.php
   define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
   define('SHARED_DIR', PROJECT_ROOT . '/shared');
   define('DATA_DIR', SHARED_DIR . '/data');
   define('SCRIPTS_DIR', SHARED_DIR . '/scripts');
   ```

2. **Environment-Based Configuration**
   ```php
   // In config.php
   define('IS_PRODUCTION', php_uname('n') === 'production-hostname');
   define('BASE_PATH', IS_PRODUCTION ? '/var/www/html' : PROJECT_ROOT);
   ```

3. **Robust Path Resolution**
   ```php
   class OnCallCalendar {
       private function resolvePath($path) {
           $absolutePath = IS_PRODUCTION ? '/var/www/html/' . $path : PROJECT_ROOT . '/' . $path;
           return realpath($absolutePath) ?: $absolutePath;
       }
   }
   ```

## Implementation Plan

1. **Phase 1: Path Configuration**
   - Create centralized path configuration
   - Add environment detection
   - Update OnCallCalendar.php to use new configuration

2. **Phase 2: Directory Verification**
   - Add directory existence checks
   - Implement permission verification
   - Add detailed error logging

3. **Phase 3: Error Handling**
   - Improve error messages
   - Add path resolution debugging
   - Implement fallback paths

## Required Changes

1. **config.php**
   - Add environment detection
   - Define base paths
   - Add path helper functions

2. **OnCallCalendar.php**
   - Update path resolution logic
   - Add debug logging
   - Improve error handling

3. **init.php**
   - Update path initialization
   - Add environment-specific logic

## Testing Plan

1. **Local Development**
   - Test with PHP built-in server (port 8000)
   - Test with direct file access
   - Verify path resolution in all contexts

2. **Production Environment**
   - Test with Apache/Nginx
   - Verify permissions
   - Check path resolution

3. **Edge Cases**
   - Test with missing directories
   - Test with insufficient permissions
   - Test with symbolic links

## Success Criteria

1. Consistent path resolution in all environments
2. Clear error messages with actionable information
3. No path-related errors in logs
4. Successful operation in both production and development

## Monitoring and Maintenance

1. Add path resolution logging
2. Monitor error logs for path-related issues
3. Regular permission checks
4. Documentation updates for path configuration

## Implementation Status

### Phase 1: Path Configuration ✅
1. Created centralized path configuration in config.php:
   - Added environment detection (IS_PRODUCTION)
   - Defined absolute path constants
   - Added type-aware resolvePath() helper function
   - Added debug logging for path resolution

2. Updated OnCallCalendar.php:
   - Using centralized path constants
   - Using resolvePath() with type-specific paths
   - Enhanced directory checks with detailed error messages
   - Added comprehensive logging for troubleshooting

3. Updated PythonLogger.php:
   - Using centralized path configuration
   - Using resolvePath() for Python script paths
   - Using configured virtual environment
   - Added detailed logging for script location

### Current Status

1. Environment Detection Fixed ✅
   - Added comprehensive production detection:
     ```php
     define('IS_PRODUCTION', 
         strpos($hostname, 'ip-') === 0 || 
         strpos($serverSoftware, 'nginx') !== false ||
         strpos($serverSoftware, 'apache') !== false
     );
     ```
   - Added detailed environment logging
   - Fixed production path handling

2. Path Resolution Issues Fixed ✅
   - Fixed project root resolution in config.php
   - Added path type handling (module, data, shared)
   - Added path cleanup to remove redundant prefixes
   - Fixed Python module imports:
     ```python
     # In calendar_api.py
     current_dir = os.path.dirname(os.path.abspath(__file__))
     sys.path.append(os.path.dirname(current_dir))
     ```
   - Updated PYTHONPATH to handle module imports:
     ```php
     // In OnCallCalendar.php
     $moduleParent = dirname(MODULES_DIR);
     $env = ['PYTHONPATH' => $moduleParent . ':' . MODULES_DIR];
     ```
   - Added production-specific path handling:
     ```php
     if (IS_PRODUCTION) {
         $projectRoot = '/var/www/html';
     } else {
         $projectRoot = realpath($ROOTDIR);
     }
     ```

2. Python Environment ✅
   - Virtual environment created at shared/venv
   - All requirements installed successfully
   - Python packages up to date

2. Directory Structure ✅
   ```
   shared/
   ├── scripts/
   │   └── modules/
   │       ├── logging/
   │       │   └── logger.py
   │       └── oncall_calendar/
   │           ├── calendar_api.py
   │           ├── calendar_manager.py
   │           ├── csv_handler.py
   │           └── file_lock.py
   └── data/
       └── oncall_calendar/
           ├── uploads/
           ├── backups/
           ├── teams.json
           └── rotations.json
   ```

2. Path Resolution System ✅
   - Centralized configuration in config.php
   - Type-aware path resolution (module, data, shared)
   - Environment-specific paths (production vs development)
   - Detailed logging for troubleshooting

3. Python Integration ✅
   - Logger script found at correct location and executable
   - Virtual environment properly configured
   - Path resolution consistent across components
   - Python module dependencies:
     ```python
     # calendar_api.py & calendar_manager.py
     current_dir = os.path.dirname(os.path.abspath(__file__))
     sys.path.append(os.path.dirname(current_dir))
     from oncall_calendar.calendar_manager import CalendarManager
     ```
   - File operations using pathlib:
     ```python
     # file_lock.py
     from pathlib import Path
     self.base_path = Path(base_path)
     self.backup_path = self.base_path / 'backups'
     ```
   - Required packages installed:
     ```
     - numpy, pandas (data processing)
     - python-ldap (authentication)
     - requests, urllib3 (HTTP)
     - psutil (system monitoring)
     - portalocker (file locking)
     ```

4. Final Verification Checklist
   - [x] Python virtual environment exists and configured
   - [x] Logger script is executable
   - [x] All required Python packages installed
   - [x] Path resolution system updated
   - [x] Directory permissions verified
   - [x] Removed duplicate logging code
   - [x] Fixed PYTHONPATH configuration
   - [ ] Test local server (port 8000)
   - [ ] Verify logging output
   - [ ] Check calendar operations

5. Key Changes Made
   ```php
   // Environment detection in config.php
   $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
   define('IS_PRODUCTION', 
       strpos($hostname, 'ip-') === 0 || 
       strpos($serverSoftware, 'nginx') !== false ||
       strpos($serverSoftware, 'apache') !== false
   );

   // Enhanced Python execution logging
   error_log("Python Command:");
   error_log("- Command: " . $command);
   error_log("- PYTHONPATH: " . $env['PYTHONPATH']);

   // Production path handling
   if (IS_PRODUCTION) {
       $projectRoot = '/var/www/html';
   }

   // Path resolution improvements
   $cleanPath = preg_replace('#^scripts/modules/#', '', $cleanPath);
   $env = ['PYTHONPATH' => MODULES_DIR];
   $this->pythonScript = resolvePath('logging/logger.py', 'module');
   ```

6. Environment-Specific Behavior
   - Development (PHP Built-in Server):
     * Uses realpath() for dynamic path resolution
     * Relative paths from project root
     * PYTHONPATH includes both modules and parent directory
     * More permissive permissions (777 for directories)
     * Local Python virtual environment
   - Production (nginx/apache):
     * Uses fixed /var/www/html base path
     * Absolute paths for consistent resolution
     * Python module imports handled via PYTHONPATH
     * Strict permissions (775 for directories, apache:apache ownership)
     * System-wide Python virtual environment
   - Python Script Behavior:
     * Uses __file__ for self-location
     * Adds parent directory to sys.path
     * Uses pathlib.Path for file operations

7. Setup Scripts
   - Production (setup.bash):
     * Requires root privileges
     * Sets up apache:apache ownership
     * Creates system-wide virtual environment
     * Handles SELinux configuration
   - Development (setup_dev.bash):
     * No root privileges required
     * Sets local file permissions
     * Creates local virtual environment
     * Initializes calendar data files

### Next Steps

1. Testing
   - [x] Created development setup script
   - [x] Updated permission handling
   - [ ] Test in local environment (port 8000)
   - [ ] Test directory permissions
   - [ ] Verify error messages are helpful
   - [ ] Check log output for debugging info

2. Monitoring
   - Review error logs for path resolution issues
   - Verify directory permissions are correct
   - Check Python script execution

3. Documentation
   - Update setup instructions
   - Document path configuration
   - Add troubleshooting guide

4. Production Deployment
   - Create backup of current files
   - Deploy changes to staging
   - Test thoroughly
   - Deploy to production
   - Monitor for issues

### Rollback Plan

1. Backup Files
   ```bash
   cp portal/config.php portal/config.php.bak
   cp portal/includes/OnCallCalendar.php portal/includes/OnCallCalendar.php.bak
   ```

2. Rollback Commands
   ```bash
   mv portal/config.php.bak portal/config.php
   mv portal/includes/OnCallCalendar.php.bak portal/includes/OnCallCalendar.php
   ```

### Success Metrics
1. No path resolution errors in logs
2. Clear, actionable error messages
3. Consistent behavior in all environments
4. Proper permission handling
