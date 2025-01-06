<?php
require_once 'config.php';
require_once 'header.php';

global $APP;

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

// Function to execute Python script and return JSON response
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
    
    // Clean and parse output
    $output = trim($output);
    if (empty($output)) {
        error_log("WebLinks Error: Empty output from Python script");
        return ['error' => 'Empty response from Python script'];
    }

    // Try to parse JSON with different cleanup steps
    $result = null;
    $attempts = [
        // First attempt: raw output
        function($out) { return $out; },
        // Second attempt: remove control characters
        function($out) { return preg_replace('/[\x00-\x1F\x7F]/', '', $out); },
        // Third attempt: remove newlines and extra whitespace
        function($out) { return preg_replace('/\s+/', ' ', $out); },
        // Fourth attempt: try to fix common JSON issues
        function($out) { 
            $out = str_replace(['}{', '}{', '}[', '[}'], ['},{', '},{', '},[', '[,{'], $out);
            return preg_replace('/(["\]}])(["\[{])/', '$1,$2', $out);
        }
    ];

    foreach ($attempts as $attempt) {
        $cleaned = $attempt($output);
        $result = json_decode($cleaned, true);
        if ($result !== null) {
            break;
        }
    }

    if ($result === null) {
        error_log("WebLinks JSON decode error: " . json_last_error_msg());
        error_log("WebLinks Raw output: " . bin2hex($output));
        return ['error' => 'Invalid response from Python script'];
    }

    // Return the result, handling different wrapper formats
    if (isset($result['items'])) {
        return $result['items'];
    } elseif (isset($result['error'])) {
        return ['error' => $result['error']];
    } elseif (isset($result['value'])) {
        return $result['value'];
    }

    return $result;
}

// Debug logging function
function debug_log($message) {
    error_log("WebLinks Debug: $message");
}

debug_log("Request URI: " . $_SERVER['REQUEST_URI']);
debug_log("Session user: " . ($_SESSION['user'] ?? 'not set'));
debug_log("Session roles: " . json_encode($_SESSION['roles'] ?? []));

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_links':
            $result = execute_weblinks_command('get_all_links');
            echo json_encode($result);
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
                $data['created_by'] = $_SESSION['user'];
                $result = execute_weblinks_command('create_link', $data);
                echo json_encode($result);
            }
            break;
            
        case 'update_link':
            if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                $id = $_GET['id'] ?? null;
                if ($id) {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $data['changed_by'] = $_SESSION['user'];
                    $result = execute_weblinks_command('update_link', [
                        'id' => intval($id),
                        'updates' => $data
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
            echo json_encode($result);
            break;
            
        case 'get_tags':
            $result = execute_weblinks_command('get_all_tags');
            echo json_encode($result);
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

        case 'download_template':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="weblinks_template.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['url', 'title', 'description', 'icon', 'tags']);
            fputcsv($output, ['https://example.com', 'Example Title', 'Description here', 'fas fa-link', 'tag1,tag2']);
            fclose($output);
            exit();
            break;

        case 'bulk_upload':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
                $file = $_FILES['file'];
                if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
                    echo json_encode(['success' => false, 'error' => 'File must be CSV']);
                    break;
                }

                $handle = fopen($file['tmp_name'], 'r');
                $header = fgetcsv($handle);
                $links = [];
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 2) {
                        $links[] = [
                            'url' => $data[0],
                            'title' => $data[1],
                            'description' => $data[2] ?? '',
                            'icon' => $data[3] ?? 'fas fa-link',
                            'tags' => isset($data[4]) ? array_map('trim', explode(',', $data[4])) : [],
                            'created_by' => $_SESSION['user']
                        ];
                    }
                }
                fclose($handle);

                $result = execute_weblinks_command('bulk_upload', ['links' => $links]);
                echo json_encode($result);
            }
            break;

        case 'bulk_tags':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data || !isset($data['tags'])) {
                    echo json_encode(['success' => false, 'error' => 'No tags provided']);
                    break;
                }
                $result = execute_weblinks_command('add_tags', ['tags' => $data['tags']]);
                echo json_encode(['success' => $result]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    exit();
}

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
<div class="modal" id="linkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="linkModalTitle">Add Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveLink()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- View Link Modal -->
<div class="modal" id="viewLinkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Link Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="linkDetails">
                    <!-- Populated dynamically -->
                </div>
                <div class="accordion mt-3">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#historyAccordion">
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
