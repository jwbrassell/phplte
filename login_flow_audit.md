# Login Flow Audit

## Current Flow
1. User submits login form (login.php)
2. Form submission handled in auth.php
3. verifyuser.php executes ldapcheck.py for authentication
4. On successful LDAP auth, session variables are set and user is redirected

## Issues Identified

### 1. Session Initialization Timing
- init.php starts the session before authentication
- verifyuser.php starts another session (potential conflict)
- Multiple session_start() calls could cause session data loss

### 2. Session Variable Consistency
- Session variables set in verifyuser.php:
  ```php
  $_SESSION[$APP . "_user_session"] = $uname;
  $_SESSION[$APP . "_user_num"] = $employee_num;
  $_SESSION[$APP . "_user_name"] = $employee_name;
  $_SESSION[$APP . "_user_vzid"] = $vzid;
  $_SESSION[$APP . "_user_email"] = $employee_email;
  $_SESSION[$APP . "_adom_groups"] = $adom_groups;
  ```
- But auth.php expects additional variables that aren't set

### 3. Redirection Path Issue
- verifyuser.php uses absolute path: `header("Location: /index.php");`
- This might not work with different base paths set in init.php

### 4. Domain Access Flow
- When accessing the domain directly, users should be:
  - Redirected to login.php if not authenticated
  - Redirected to their originally requested page after login
  - Redirected to index.php if no specific page was requested
- Current implementation doesn't properly handle this flow

## Solution Plan

1. Fix Session Management:
```php
// Remove session_start() from verifyuser.php since init.php already handles it
// In verifyuser.php, remove:
session_start();
```

2. Standardize Session Variables:
```php
// In verifyuser.php, add:
$_SESSION[$APP . "_is_admin"] = in_array('admin', explode(',', $adom_groups));
```

3. Fix Redirection Paths:
```php
// In verifyuser.php, change:
header("Location: /index.php");
// To:
header("Location: " . $basePath . "/index.php");
```

4. Implement Proper Domain Access Flow:
```php
// Add to init.php:
if (!isAuthenticated() && $PAGE !== 'login.php') {
    $requested_page = urlencode($_SERVER['REQUEST_URI']);
    header("Location: " . $basePath . "/login.php?next=" . $requested_page);
    exit;
}

// Update auth.php login success redirect:
if (isset($_GET['next'])) {
    $next_url = filter_var($_GET['next'], FILTER_SANITIZE_URL);
    header("Location: " . $next_url);
} else {
    header("Location: " . $basePath . "/index.php");
}
```

## Implementation Steps

1. Remove duplicate session_start() from verifyuser.php
2. Add missing session variables
3. Fix redirection paths to use proper base path
4. Add session debugging
5. Implement domain access flow changes:
   - Add authentication check in init.php
   - Update login redirect logic
   - Add next page parameter handling
6. Test login flow with:
   - Different base paths
   - Various user roles
   - Multiple consecutive login attempts
   - Direct domain access scenarios

## Verification Steps

1. Check ldap_debug.log for authentication success
2. Verify session variables in PHP error log
3. Confirm proper redirection after login:
   - Direct domain access → login → requested page
   - Login page direct access → index.php
4. Test session persistence across pages
5. Verify redirection maintains original request parameters

## Testing Scenarios

1. Direct Domain Access:
   - Access domain.com/some-page.php (unauthenticated)
   - Should redirect to login.php with next=some-page.php
   - After login, should redirect to some-page.php

2. Already Authenticated:
   - Access domain.com/some-page.php (authenticated)
   - Should directly access page without login redirect

3. Login Page Direct Access:
   - Access domain.com/login.php
   - After login, should redirect to index.php
   - If already authenticated, should redirect to index.php immediately
