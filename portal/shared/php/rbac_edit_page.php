<?php
// Get the request payload and validate JSON
$raw_input = file_get_contents('php://input');
if (empty($raw_input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    error_log("Empty request body received");
    exit;
}

$request = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    error_log("JSON decode error: " . json_last_error_msg() . "\nRaw input: " . $raw_input);
    exit;
}

// Validate request structure
if (!isset($request['request_payload']) || !isset($request['request_payload']['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required request structure']);
    error_log("Invalid request structure: " . print_r($request, true));
    exit;
}

// Get configuration file
$config_dir = __DIR__ . '/../../config/';
$config_file = $config_dir . 'rbac_config.json';

// Load and validate configuration
if (!file_exists($config_file)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found']);
    error_log("Missing configuration file: " . $config_file);
    exit;
}

$config_data = json_decode(file_get_contents($config_file), true);

if (!$config_data || !isset($config_data['rbac_settings']) || !isset($config_data['menu_structure'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid configuration structure']);
    error_log("Invalid configuration structure: " . print_r($config_data, true));
    exit;
}

$rbac_data = $config_data['rbac_settings'];
$menu_data = $config_data['menu_structure'];

if (!isset($rbac_data['adom_groups']) || !isset($rbac_data['icon_list']) || !isset($rbac_data['category_list'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid RBAC settings structure']);
    error_log("Invalid RBAC settings structure: " . print_r($rbac_data, true));
    exit;
}

if (!is_array($menu_data)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid menu structure']);
    error_log("Invalid menu structure: " . print_r($menu_data, true));
    exit;
}

// Extract and validate request data
$data = $request['request_payload']['data'];
$required_fields = ['page_name', 'page_file_name', 'action'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
    error_log("Missing required fields: " . implode(', ', $missing_fields));
    exit;
}

$page_name = $data['page_name'];
$page_file = $data['page_file_name'];
$action = $data['action'];

// Get available groups and icons
$adom_groups = $rbac_data['adom_groups'];
$icons = $rbac_data['icon_list'];
$categories = $rbac_data['category_list'];

// Find existing page configuration if it exists
$existing_config = null;
foreach ($menu_data as $key => $value) {
    // Skip non-array values and special keys
    if (!is_array($value) || in_array($key, ['description', 'summary'])) {
        continue;
    }
    
    // Validate required structure
    if (!isset($value['type']) || !isset($value['urls']) || !is_array($value['urls'])) {
        error_log("Invalid menu item structure for key '$key': " . print_r($value, true));
        continue;
    }
    
    // Handle single type
    if ($value['type'] === 'single' && is_array($value['urls'])) {
        foreach ($value['urls'] as $title => $info) {
            if (is_array($info) && isset($info['url']) && $info['url'] === $page_file) {
                $existing_config = [
                    'name' => $title,
                    'type' => 'single',
                    'category' => $key,
                    'icon' => isset($value['img']) ? $value['img'] : 'fas fa-file',
                    'roles' => isset($info['roles']) ? $info['roles'] : []
                ];
                break 2;
            }
        }
    }
    // Handle category type
    elseif ($value['type'] === 'category' && is_array($value['urls'])) {
        foreach ($value['urls'] as $title => $info) {
            if (is_array($info) && isset($info['url']) && $info['url'] === $page_file) {
                $existing_config = [
                    'name' => $title,
                    'type' => 'category',
                    'category' => $key,
                    'icon' => isset($value['img']) ? $value['img'] : 'fas fa-folder',
                    'roles' => isset($info['roles']) ? $info['roles'] : []
                ];
                break 2;
            }
        }
    }
}

// Log the result for debugging
error_log("Page file: $page_file");
error_log("Existing config: " . ($existing_config ? json_encode($existing_config) : 'null'));

// Generate the edit form HTML
?>
<div class="card card-info">
    <div class="card-header">
        <h3 class="card-title">Edit Page RBAC</h3>
    </div>
    <div class="card-body">
        <form id="rbac_form">
            <div class="form-group">
                <label>Page Name</label>
                <input type="text" class="form-control" id="link_name" value="<?php echo $existing_config ? $existing_config['name'] : ucfirst($page_name); ?>">
            </div>

            <div class="form-group">
                <label>Link Type</label>
                <select class="form-control" id="link_type">
                    <option value="single" <?php echo $existing_config && $existing_config['type'] === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="category" <?php echo $existing_config && $existing_config['type'] === 'category' ? 'selected' : ''; ?>>Category</option>
                </select>
            </div>

            <div class="form-group <?php echo $existing_config && $existing_config['type'] === 'category' ? '' : 'd-none'; ?>" id="category_dropdown">
                <label>Category</label>
                <select class="form-control" id="category_options">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category; ?>" <?php echo $existing_config && $existing_config['category'] === $category ? 'selected' : ''; ?>><?php echo $category; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Icon</label>
                <select class="form-control" id="icon_options">
                    <?php foreach ($icons as $icon): ?>
                        <option value="<?php echo $icon; ?>" <?php echo $existing_config && $existing_config['icon'] === $icon ? 'selected' : ''; ?>><?php echo $icon; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>ADOM Groups</label>
                <select class="form-control" id="adom_groups" multiple>
                    <?php foreach ($adom_groups as $group): ?>
                        <option value="<?php echo $group; ?>" <?php echo $existing_config && in_array($group, $existing_config['roles']) ? 'selected' : ''; ?>><?php echo $group; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="current_adom_groups" value="<?php echo $existing_config ? implode(',', $existing_config['roles']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Filename</label>
                <div id="filename"><?php echo $page_file; ?></div>
            </div>

            <button type="button" class="btn btn-primary" onclick="sendRequest()">Save</button>
        </form>
    </div>
</div>
