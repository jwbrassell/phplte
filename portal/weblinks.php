<?php
// Suppress deprecation warnings for clean JSON output
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

session_start();
require_once 'config.php';
global $APP;

// Function definitions
function execute_weblinks_command($command, $args = []) {
    $python_script = realpath(dirname(__FILE__) . "/../shared/scripts/modules/weblinks/weblinks.py");
    if (!file_exists($python_script)) {
        error_log("WebLinks Error: Python script not found at $python_script");
        return ['error' => 'Python script not found'];
    }
    
    $args_json = escapeshellarg(json_encode($args));
    $cmd = "python3 " . escapeshellarg($python_script) . " $command $args_json";
    
    // Log command details
    error_log("WebLinks Command: $cmd");
    error_log("WebLinks Working Directory: " . getcwd());
    error_log("WebLinks Script Path: $python_script");
    
    // Execute command and capture output
    $output = shell_exec($cmd . " 2>&1");
    
    // Log raw output for debugging
    error_log("WebLinks Raw Output: " . bin2hex($output));
    
    // Clean output
    $output = trim($output);
    if (empty($output)) {
        error_log("WebLinks Error: Empty output from Python script");
        return ['error' => 'Empty response from Python script'];
    }

    // Handle hex-encoded output
    if (ctype_xdigit($output)) {
        $output = hex2bin($output);
    }

    // Parse JSON response
    $result = json_decode($output, true);
    if ($result === null) {
        error_log("WebLinks Error decoding JSON: " . json_last_error_msg());
        error_log("WebLinks Raw output: " . bin2hex($output));
        return ['error' => 'Invalid response from Python script'];
    }

    return $result;
}

function debug_log($message) {
    error_log("WebLinks Debug: $message");
}

// Handle AJAX requests first, before any HTML output
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    // Process AJAX request
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_links':
            $result = execute_weblinks_command('get_all_links');
            // Ensure we return an array for DataTable
            if (isset($result['items'])) {
                echo json_encode($result['items']);
            } else {
                echo json_encode([]);
            }
            break;
            
        case 'get_link':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $result = execute_weblinks_command('get_link', ['id' => intval($id)]);
                echo json_encode($result);
            }
            break;
            
        case 'create_link':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $data['created_by'] = $_SESSION[$APP."_user_name"];
                $result = execute_weblinks_command('create_link', $data);
                echo json_encode($result);
            }
            break;
            
        case 'update_link':
            if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                $id = $_GET['id'] ?? null;
                if ($id) {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $data['changed_by'] = $_SESSION[$APP."_user_name"];
                    $result = execute_weblinks_command('update_link', [
                        'id' => intval($id),
                        'updates' => $data
                    ]);
                    echo json_encode($result);
                }
            }
            break;
            
        case 'delete_link':
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                $id = $_GET['id'] ?? null;
                if ($id) {
                    $result = execute_weblinks_command('delete_link', [
                        'id' => intval($id),
                        'deleted_by' => $_SESSION[$APP."_user_name"]
                    ]);
                    echo json_encode($result);
                }
            }
            break;
            
        case 'record_click':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $result = execute_weblinks_command('record_click', ['id' => intval($id)]);
                echo json_encode(['success' => $result]);
            }
            break;
            
        case 'get_common_links':
            $result = execute_weblinks_command('get_common_links');
            // Ensure we return an array for common links
            if (isset($result['items'])) {
                echo json_encode($result['items']);
            } else {
                echo json_encode([]);
            }
            break;
            
        case 'get_tags':
            $result = execute_weblinks_command('get_all_tags');
            // Ensure we return an array for Select2
            if (isset($result['items'])) {
                echo json_encode($result['items']);
            } else {
                echo json_encode([]);
            }
            break;
            
        case 'add_tags':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $result = execute_weblinks_command('add_tags', ['tags' => $data['tags']]);
                echo json_encode(['success' => $result]);
            }
            break;
            
        case 'get_stats':
            $result = execute_weblinks_command('get_stats');
            echo json_encode($result);
            break;
            
        case 'bulk_upload':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No file uploaded or upload error']);
                    break;
                }

                $file = $_FILES['file'];
                if ($file['type'] !== 'text/csv') {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid file type. Please upload a CSV file']);
                    break;
                }

                // Parse CSV file
                $handle = fopen($file['tmp_name'], 'r');
                if (!$handle) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Could not read CSV file']);
                    break;
                }

                // Get headers
                $headers = fgetcsv($handle, 0, ",", '"', "\\");
                if (!$headers) {
                    fclose($handle);
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid CSV format']);
                    break;
                }

                // Convert headers to lowercase for case-insensitive matching
                $headers = array_map('strtolower', $headers);
                
                // Required columns
                $required = ['url', 'title'];
                foreach ($required as $field) {
                    if (!in_array($field, $headers)) {
                        fclose($handle);
                        http_response_code(400);
                        echo json_encode(['error' => "Missing required column: $field"]);
                        break 2;
                    }
                }

                $links = [];
                while (($data = fgetcsv($handle, 0, ",", '"', "\\")) !== FALSE) {
                    $row = array_combine($headers, $data);
                    
                    // Handle tags column (comma-separated list)
                    if (isset($row['tags'])) {
                        $row['tags'] = array_map('trim', explode(',', $row['tags']));
                    } else {
                        $row['tags'] = [];
                    }

                    // Add metadata
                    $row['created_by'] = $_SESSION[$APP."_user_name"];
                    
                    $links[] = $row;
                }
                fclose($handle);

                $result = execute_weblinks_command('bulk_upload', [
                    'links' => $links
                ]);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    exit();
}

// Include header for non-AJAX requests
require_once 'header.php';

// Debug request and session state
debug_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
debug_log("Request URI: " . $_SERVER['REQUEST_URI']);
debug_log("Script Name: " . $_SERVER['SCRIPT_NAME']);
debug_log("PHP Self: " . $_SERVER['PHP_SELF']);
debug_log("Document Root: " . $_SERVER['DOCUMENT_ROOT']);
debug_log("Current Directory: " . getcwd());
debug_log("Session state: " . print_r($_SESSION, true));

// Verify file locations
$script_path = realpath(dirname(__FILE__) . "/../shared/scripts/modules/weblinks/weblinks.py");
$data_path = realpath(dirname(__FILE__) . "/../shared/data/weblinks/weblinks.json");
debug_log("Python Script Path: " . ($script_path ?: 'not found'));
debug_log("Data File Path: " . ($data_path ?: 'not found'));

// Check if user is logged in and has access
require_once 'includes/auth.php';
if (!check_access('weblinks')) {
    debug_log("Access denied to weblinks");
    header('Location: 403.php');
    exit();
}

$_SESSION['user'] = $_SESSION[$APP."_user_name"];
debug_log("User authenticated: " . $_SESSION['user']);

// Check if accessing admin page
if (isset($_GET['admin']) && $_GET['admin'] === 'true') {
    if (!isset($_SESSION['roles']) || !in_array('admin', $_SESSION['roles'])) {
        header('Location: weblinks.php');
        exit();
    }
    require_once 'weblinks_admin.php';
    exit();
}

// Ensure static directory exists
$staticDir = dirname(__FILE__) . '/static/weblinks';
if (!is_dir($staticDir)) {
    debug_log("Creating static directory");
    mkdir($staticDir, 0755, true);
}

debug_log("Static files status:");
debug_log("CSS exists: " . (file_exists($staticDir . '/weblinks.css') ? 'yes' : 'no'));
debug_log("JS exists: " . (file_exists($staticDir . '/weblinks.js') ? 'yes' : 'no'));

// Create data directory if it doesn't exist
$dataDir = dirname(__FILE__) . '/../shared/data/weblinks';
if (!is_dir($dataDir)) {
    debug_log("Creating data directory");
    mkdir($dataDir, 0755, true);
}

// Main page content
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>WebLinks</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Common Links -->
            <div class="common-links">
                <h5>Common Links</h5>
                <div id="commonLinks" class="d-flex flex-wrap">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Main Links -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Links</h5>
                    <button class="btn btn-primary" onclick="showAddLinkModal()">
                        <i class="fas fa-plus"></i> Add Link
                    </button>
                </div>
                <div class="card-body">
                    <table id="linksTable" class="table">
                        <thead>
                            <tr>
                                <th>Icon</th>
                                <th>Title</th>
                                <th>URL</th>
                                <th>Tags</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add/Edit Link Modal -->
<div class="modal" id="linkModal" tabindex="-1" data-backdrop="static" role="dialog" aria-labelledby="linkModalTitle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="linkModalTitle">Add Link</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="linkForm">
                    <input type="hidden" id="linkId">
                    <div class="mb-3">
                        <label class="form-label" for="linkTitle">Title</label>
                        <input type="text" class="form-control" id="linkTitle" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="linkUrl">URL</label>
                        <input type="url" class="form-control" id="linkUrl" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="linkDescription">Description</label>
                        <textarea class="form-control" id="linkDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="linkIcon">Icon</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i id="iconPreview" class="fas fa-link"></i>
                            </span>
                            <select class="form-control" id="linkIcon">
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="linkTags">Tags</label>
                        <select class="form-control" id="linkTags" multiple>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveLink()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- View Link Modal -->
<div class="modal" id="viewLinkModal" tabindex="-1" data-backdrop="static" role="dialog" aria-labelledby="viewLinkModalTitle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Link Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="linkDetails">
                    <!-- Populated dynamically -->
                </div>
                <div class="accordion mt-3">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-toggle="collapse" data-target="#historyAccordion">
                                Change History
                            </button>
                        </h2>
                        <div id="historyAccordion" class="accordion-collapse collapse">
                            <div class="accordion-body history-scroll">
                                <div id="linkHistory">
                                    <!-- Populated dynamically -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<script src="plugins/select2/js/select2.full.min.js"></script>

<link rel="stylesheet" href="static/weblinks/weblinks.css">
<script src="static/weblinks/weblinks.js"></script>

<?php require_once 'footer.php'; ?>
