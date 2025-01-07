<?php
/**
 * Migration Verification Script
 * Tests all migrated components and their functionality
 */

// Initialize results array
$results = [
    'success' => [],
    'failure' => []
];

function log_result($component, $test, $success, $message = '') {
    global $results;
    $result = [
        'component' => $component,
        'test' => $test,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($success) {
        $results['success'][] = $result;
    } else {
        $results['failure'][] = $result;
    }
}

// Test directory structure
function verify_directory_structure() {
    $required_dirs = [
        'private/scripts/modules/ldap',
        'private/scripts/modules/rbac',
        'private/scripts/modules/logging',
        'private/scripts/modules/data_processing',
        'private/includes/auth',
        'private/includes/logging',
        'private/includes/calendar',
        'private/includes/components/datatables',
        'private/data/oncall',
        'public/auth',
        'public/calendar',
        'public/components',
        'public/admin',
        'logs',
        'logs/system'
    ];
    
    foreach ($required_dirs as $dir) {
        if (is_dir($dir)) {
            log_result('Directory Structure', $dir, true, 'Directory exists');
        } else {
            log_result('Directory Structure', $dir, false, 'Directory missing');
        }
    }
}

// Test file existence
function verify_file_existence() {
    $required_files = [
        'private/includes/auth/auth.php',
        'private/scripts/modules/ldap/ldapcheck.py',
        'private/scripts/modules/rbac/verifyuser.php',
        'private/scripts/modules/logging/logger.py',
        'private/includes/logging/PythonLogger.php',
        'private/includes/logging/log_error.php',
        'private/includes/calendar/OnCallCalendar.php',
        'private/scripts/modules/data_processing/table_processor.py',
        'private/includes/components/datatables/DataTableComponent.php',
        'public/auth/login.php',
        'public/calendar/test_calendar.php',
        'public/components/dictionary_table.php',
        'public/components/list_table.php',
        'public/admin/weblinks.php',
        'public/admin/users.php',
        'public/admin/logs.php'
    ];
    
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            log_result('File Existence', $file, true, 'File exists');
        } else {
            log_result('File Existence', $file, false, 'File missing');
        }
    }
}

// Test file permissions
function verify_file_permissions() {
    $log_dirs = [
        'logs',
        'logs/system',
        'logs/access',
        'logs/python'
    ];
    
    foreach ($log_dirs as $dir) {
        if (is_dir($dir)) {
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
            if ($perms == '0775') {
                log_result('File Permissions', $dir, true, 'Correct permissions (0775)');
            } else {
                log_result('File Permissions', $dir, false, "Incorrect permissions ($perms)");
            }
        }
    }
}

// Test Python modules
function verify_python_modules() {
    $required_modules = ['ldap', 'hvac'];
    foreach ($required_modules as $module) {
        $cmd = "python3 -c 'import $module'";
        exec($cmd, $output, $return_var);
        if ($return_var === 0) {
            log_result('Python Modules', $module, true, 'Module available');
        } else {
            log_result('Python Modules', $module, false, 'Module missing');
        }
    }
}

// Run all verifications
verify_directory_structure();
verify_file_existence();
verify_file_permissions();
verify_python_modules();

// Output results
echo "\nVerification Results:\n";
echo "===================\n\n";

echo "Successful Tests:\n";
echo "----------------\n";
foreach ($results['success'] as $result) {
    echo "[{$result['timestamp']}] {$result['component']} - {$result['test']}: {$result['message']}\n";
}

echo "\nFailed Tests:\n";
echo "------------\n";
foreach ($results['failure'] as $result) {
    echo "[{$result['timestamp']}] {$result['component']} - {$result['test']}: {$result['message']}\n";
}

// Calculate summary
$total_tests = count($results['success']) + count($results['failure']);
$success_rate = ($total_tests > 0) ? (count($results['success']) / $total_tests) * 100 : 0;

echo "\nSummary:\n";
echo "--------\n";
echo "Total Tests: $total_tests\n";
echo "Successful: " . count($results['success']) . "\n";
echo "Failed: " . count($results['failure']) . "\n";
echo "Success Rate: " . number_format($success_rate, 2) . "%\n";
?>
