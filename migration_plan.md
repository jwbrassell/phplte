# Migration Documentation Plan

## 1. Core Authentication System

### Current Location
- portal/includes/auth.php
- portal/login.php
- shared/scripts/modules/ldap/ldapcheck.py

### Migration Steps
1. Create directories:
   ```bash
   mkdir -p private/scripts/modules/ldap
   mkdir -p private/includes/auth
   mkdir -p public/auth
   ```

2. Move files:
   ```bash
   cp portal/includes/auth.php private/includes/auth/
   cp portal/login.php public/auth/
   cp shared/scripts/modules/ldap/ldapcheck.py private/scripts/modules/ldap/
   ```

3. Update paths in auth.php:
   - Update LDAP module path
   - Update session handling paths
   - Update include paths

4. Verification Steps:
   - Test LDAP connection
   - Verify login flow
   - Check session handling
   - Test logout functionality

## 2. RBAC System

### Current Location
- shared/scripts/modules/rbac/verifyuser.php

### Migration Steps
1. Create directory:
   ```bash
   mkdir -p private/scripts/modules/rbac
   ```

2. Move files:
   ```bash
   cp shared/scripts/modules/rbac/verifyuser.php private/scripts/modules/rbac/
   ```

3. Update in verifyuser.php:
   - Update include paths
   - Update permission checks
   - Update user verification logic

4. Verification Steps:
   - Test role assignments
   - Verify access controls
   - Test permission inheritance

## 3. Logging System

### Current Location
- portal/includes/PythonLogger.php
- shared/scripts/modules/logging/logger.py
- portal/log_error.php

### Migration Steps
1. Create directories:
   ```bash
   mkdir -p private/scripts/modules/logging
   mkdir -p private/includes/logging
   mkdir -p logs
   ```

2. Move files:
   ```bash
   cp portal/includes/PythonLogger.php private/includes/logging/
   cp shared/scripts/modules/logging/logger.py private/scripts/modules/logging/
   cp portal/log_error.php private/includes/logging/
   ```

3. Update configurations:
   - Update log file paths
   - Update Python logger imports
   - Update error handling paths

4. Verification Steps:
   - Test error logging
   - Verify log rotation
   - Check log permissions

## 4. OnCall Calendar System

### Current Location
- portal/includes/OnCallCalendar.php
- portal/test_calendar.php
- shared/data/oncall_calendar/*.json

### Migration Steps
1. Create directories:
   ```bash
   mkdir -p private/includes/calendar
   mkdir -p private/data/oncall
   mkdir -p public/calendar
   ```

2. Move files:
   ```bash
   cp portal/includes/OnCallCalendar.php private/includes/calendar/
   cp portal/test_calendar.php public/calendar/
   cp shared/data/oncall_calendar/*.json private/data/oncall/
   ```

3. Update configurations:
   - Update calendar paths
   - Update JSON data locations
   - Update include paths

4. Verification Steps:
   - Test calendar display
   - Verify rotation data
   - Check team assignments

## 5. DataTables Components

### Current Location
- portal/e_datatable_from_json_dictionary.php
- portal/e_datatable_from_json_list_of_lists.php

### Migration Steps
1. Create directories:
   ```bash
   mkdir -p private/scripts/modules/data_processing
   mkdir -p private/includes/components/datatables
   mkdir -p public/components
   ```

2. Create Python Data Processing Module:
   ```python
   # private/scripts/modules/data_processing/table_processor.py
   class TableDataProcessor:
       def process_json_dictionary(self, data):
           # Convert dictionary data to table format
           pass
           
       def process_json_list(self, data):
           # Convert list data to table format
           pass
           
       def apply_filters(self, data, filters):
           # Apply data filters
           pass
           
       def sort_data(self, data, sort_key, direction):
           # Sort data
           pass
   ```

3. Create Reusable PHP Component:
   ```php
   // private/includes/components/datatables/DataTableComponent.php
   class DataTableComponent {
       private $pythonProcessor;
       
       public function renderTable($data, $config) {
           // Call Python processor
           // Render table with processed data
       }
       
       public function getJsonResponse($data) {
           // Handle AJAX requests
       }
   }
   ```

4. Move and Update Files:
   ```bash
   # Convert existing datatables to use new components
   cp portal/e_datatable_from_json_dictionary.php public/components/dictionary_table.php
   cp portal/e_datatable_from_json_list_of_lists.php public/components/list_table.php
   ```

5. Verification Steps:
   - Test Python data processing
   - Verify table rendering
   - Check AJAX functionality
   - Test sorting and filtering
   - Verify component reusability

## 6. Admin Interfaces

### Current Location
- portal/weblinks_admin.php
- portal/user_admin.php
- portal/admin_logs.php

### Migration Steps
1. Create directory:
   ```bash
   mkdir -p public/admin
   ```

2. Move files:
   ```bash
   cp portal/weblinks_admin.php public/admin/weblinks.php
   cp portal/user_admin.php public/admin/users.php
   cp portal/admin_logs.php public/admin/logs.php
   ```

3. Update in each file:
   - Update include paths
   - Update authentication checks
   - Update RBAC verifications

4. Verification Steps:
   - Test admin access
   - Verify CRUD operations
   - Check permission levels

## 7. Final Verification

### Component Reusability Checks
- Verify DataTable component usage across pages
- Test Python data processor with different data sources
- Check component configuration flexibility
- Verify error handling consistency


### Security Checks
- Verify private directory protection
- Check file permissions
- Test authentication flow
- Verify RBAC enforcement

### Functionality Checks
- Test all admin interfaces
- Verify calendar operations
- Check logging system
- Test user management

### Performance Checks
- Monitor load times
- Check log sizes
- Verify caching

**Note:** Each component should be migrated and tested independently. Keep the original files until the migration is verified successful. Document any issues or special cases encountered during migration.

## Python-First Architecture Guidelines

1. Data Processing Principles:
   - Move all data manipulation to Python modules
   - Use PHP only for presentation logic
   - Implement caching for processed data
   - Create standardized data interchange formats

2. Reusable Components Structure:
   ```
   private/
   ├── scripts/
   │   └── modules/
   │       ├── data_processing/    # Python data processors
   │       ├── api/               # Python API handlers
   │       └── utilities/         # Shared Python utilities
   └── includes/
       └── components/           # PHP UI components
           ├── datatables/
           ├── forms/
           └── widgets/
   ```

3. Component Development Guidelines:
   - Create Python classes for all data operations
   - Build PHP wrapper classes for UI components
   - Implement standardized interfaces
   - Document component configurations
   - Include usage examples

4. Performance Optimization:
   - Implement Python-based data caching
   - Use background processing for heavy operations
   - Optimize data transfer formats
   - Monitor memory usage in Python processes
