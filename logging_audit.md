# Logging System Audit and Resolution Plan

## Current Issues

### 1. Directory Structure Inconsistency
- Logger.php writes to: `/logs/type/`
- API expects: `/logs/type/date.log`
- Actual files have inconsistent naming: `access_2024-01-05.log` vs `2024-01-05.log`

### 2. JSON Formatting Issues
- Logger adds unnecessary formatting and newlines
- JSON entries should be one per line for proper parsing
- Current format makes it difficult for line-by-line reading

### 3. Data Structure Mismatch
- Logger output and API expectations don't align
- Nested fields are handled inconsistently
- Some fields are missing or in wrong location

## Resolution Plan

### 1. Standardize Directory Structure
- Update Logger.php to use consistent path format: `/logs/type/YYYY-MM-DD.log`
- Remove prefix from log filenames (e.g., "access_")
- Ensure all log types follow same structure

### 2. Fix JSON Formatting
- Modify Logger.php to write clean, single-line JSON
- Remove unnecessary pretty printing
- Ensure consistent newline handling

### 3. Standardize Data Structure
- Update Logger.php to match API expectations:
```json
{
    "timestamp": "YYYY-MM-DD HH:mm:ss",
    "type": "log_type",
    "user": "username",
    "message": "log message",
    "details": {
        "level": "INFO|ERROR|etc",
        "additional": "fields",
        "go": "here"
    }
}
```

### 4. Implementation Steps
1. Update Logger.php formatLogMessage method
2. Fix directory structure handling
3. Update API endpoint to handle standardized format
4. Add data validation on both write and read
5. Add error handling for malformed JSON

### 5. Testing Plan
1. Test log writing with various types
2. Verify file structure and naming
3. Test log reading via API
4. Verify DataTables display
5. Test search functionality
6. Verify export features

## Impact
These changes will ensure:
- Consistent log storage and retrieval
- Proper JSON formatting
- Reliable log display in admin interface
- Improved search and filter functionality
