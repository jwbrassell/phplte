<?php
require_once('includes/init.php');
require_once(realpath(dirname(__FILE__) . '/../shared/php/examples/datatable_list_view.php'));

// Debug logging
error_log("Loading examples datatable dictionary page");

// Check if user is logged in and has access
require_once 'includes/auth.php';
if (!check_access('e_datatable_from_json_dictionary.php')) {
    header('Location: 403.php');
    exit();
}

// Ensure static directory exists
$staticDir = dirname(__FILE__) . '/static/examples';
if (!is_dir($staticDir)) {
    error_log("Creating examples static directory");
    mkdir($staticDir, 0755, true);
}

error_log("Static files status:");
error_log("JS exists: " . (file_exists($staticDir . '/datatable.js') ? 'yes' : 'no'));

// Set page title and category
$page_title = "Examples - Datatable Dictionary";
$category = "Examples";

// Include header
include('header.php');

// Add required DataTables CSS
?>
<!-- DataTables -->
<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<?php

// Convert dictionary data to list format using Python script
$output = [];
$return_var = 0;
// Get the base directory (project root)
$base_dir = realpath(dirname(__FILE__) . '/..');
error_log("Project root: " . $base_dir);

$python_script = <<<EOT
import json
import sys
import os

base_dir = sys.argv[1]
print("Base directory:", base_dir, file=sys.stderr)

# Set up module path
module_path = os.path.join(base_dir, 'shared', 'scripts', 'modules')
print(f"Module path: {module_path}", file=sys.stderr)

if os.path.exists(module_path) and os.path.exists(os.path.join(module_path, 'examples')):
    print(f"Found valid module path: {module_path}", file=sys.stderr)
    sys.path.append(module_path)
    try:
        from examples.datatable_utils import convert_dictionary_to_list
        print("Successfully imported convert_dictionary_to_list", file=sys.stderr)
    except ImportError as e:
        print(f"Import failed: {str(e)}", file=sys.stderr)
        sys.exit(1)
else:
    print(f"Invalid module path: {module_path}", file=sys.stderr)
    sys.exit(1)

# Load JSON data
json_path = os.path.join(base_dir, 'shared', 'data', 'examples', 'datatable', 'example_datatable_report_from_dictionary.json')
print(f"JSON path: {json_path}", file=sys.stderr)

if not os.path.exists(json_path):
    print(f"JSON file not found: {json_path}", file=sys.stderr)
    sys.exit(1)

try:
    with open(json_path, 'r') as f:
        data = json.load(f)
except Exception as e:
    print(f"Error reading JSON: {str(e)}", file=sys.stderr)
    sys.exit(1)

# Convert the data
converted_data = convert_dictionary_to_list(data)

# Print the converted data as JSON
print(json.dumps(converted_data))
EOT;

// Save the Python script to a temporary file
$temp_script = tempnam(sys_get_temp_dir(), 'convert_') . '.py';
file_put_contents($temp_script, $python_script);

// Execute the Python script
error_log("Executing Python script...");
$descriptorspec = array(
    1 => array("pipe", "w"), // stdout
    2 => array("pipe", "w")  // stderr
);
$process = proc_open("python3 " . escapeshellarg($temp_script) . " " . escapeshellarg($base_dir), $descriptorspec, $pipes);

if (is_resource($process)) {
    // Get stdout and stderr
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    
    // Close pipes
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    // Get process exit code
    $return_var = proc_close($process);
    
    // Log stderr for debugging
    error_log("Python stderr: " . $stderr);
    error_log("Return value: " . $return_var);
    
    if ($return_var !== 0) {
        error_log("Error executing Python script: " . $stderr);
        die("Error converting data format");
    }
    
    // Use only stdout for JSON data
    $converted_json = $stdout;
} else {
    die("Failed to execute Python script");
}

// Clean up the temporary file
unlink($temp_script);
$decoded = json_decode($converted_json, true);
if ($decoded === null) {
    error_log("Failed to decode JSON: " . json_last_error_msg());
    error_log("Raw JSON: " . $converted_json);
    die("Error processing data format");
}

// Try to find the correct datatable directory
$possible_dirs = [
    $base_dir . '/shared/data/examples/datatable',
    $base_dir . '/shared/data/examples/datatable/',
    dirname($base_dir) . '/shared/data/examples/datatable'
];

$target_dir = null;
foreach ($possible_dirs as $dir) {
    if (is_dir($dir) || mkdir($dir, 0755, true)) {
        $target_dir = $dir;
        break;
    }
}

if (!$target_dir) {
    error_log("Failed to find or create datatable directory");
    die("Error saving temporary file");
}

// Save to temporary file
$temp_filename = 'temp_dictionary_' . uniqid() . '.json';
$json_path = $target_dir . '/' . $temp_filename;
file_put_contents($json_path, $converted_json);

// Debug logging
error_log("Target directory: " . $target_dir);
error_log("Temporary JSON file path: " . $json_path);
error_log("JSON file exists: " . (file_exists($json_path) ? 'yes' : 'no'));

// Render the datatable list with the converted data
echo render_datatable_list($temp_filename, $page_title, $category);

// Clean up temporary file after rendering
if (file_exists($json_path)) {
    unlink($json_path);
}

// Add required DataTables JS
?>
<!-- DataTables & Plugins -->
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<?php

// Include footer
include('footer.php');
