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

$data = $request['request_payload']['data'];
$required_fields = ['link_name', 'link_type', 'filename', 'new_adom_groups', 'image'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || ($field === 'new_adom_groups' && !is_array($data[$field]))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
    error_log("Missing required fields: " . implode(', ', $missing_fields));
    exit;
}

// Get configuration file
$config_dir = __DIR__ . '/../../config/';
$config_file = $config_dir . 'rbac_config.json';

// Load and validate configuration
if (!file_exists($config_file)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found']);
    error_log("Missing configuration file");
    exit;
}

$config_data = json_decode(file_get_contents($config_file), true);

if (!$config_data || !isset($config_data['menu_structure']) || !isset($config_data['rbac_settings'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid configuration structure']);
    exit;
}

$menu_data = $config_data['menu_structure'];
$rbac_data = $config_data['rbac_settings'];

if (!is_array($menu_data)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid menu structure']);
    exit;
}

// Find and update or create the page entry
$page_updated = false;
$section_key = $data['link_type'] === 'category' ? $data['category'] : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $data['link_name']));

// Remove existing page entry if it exists
foreach ($menu_data as $key => &$section) {
    if (is_array($section) && isset($section['urls'])) {
        foreach ($section['urls'] as $name => $page) {
            if ($page['url'] === $data['filename']) {
                unset($section['urls'][$name]);
                if (empty($section['urls'])) {
                    unset($menu_data[$key]);
                }
                break 2;
            }
        }
    }
}

// Create or update section
if (!isset($menu_data[$section_key]) || !is_array($menu_data[$section_key])) {
    $menu_data[$section_key] = [
        'type' => $data['link_type'],
        'img' => $data['image'],
        'urls' => []
    ];
}

// Add page to section
$menu_data[$section_key]['urls'][$data['link_name']] = [
    'url' => $data['filename'],
    'roles' => $data['new_adom_groups']
];

// Update rbac.json with all unique roles
$all_roles = [];
foreach ($menu_data as $section) {
    if (is_array($section) && isset($section['urls'])) {
        foreach ($section['urls'] as $page) {
            if (isset($page['roles'])) {
                $all_roles = array_merge($all_roles, $page['roles']);
            }
        }
    }
}
$rbac_data['adom_groups'] = array_values(array_unique($all_roles));

// Generate pages table data from menu configuration
$pages_table_data = [];
foreach ($menu_data as $section_key => $section) {
    if (is_array($section) && isset($section['urls'])) {
        foreach ($section['urls'] as $name => $page) {
            if (isset($page['url']) && isset($page['roles'])) {
                $pages_table_data[] = [
                    $name,
                    $page['url'],
                    $section['type'],
                    implode(', ', $page['roles']),
                    "<button type='button' class='btn btn-primary btn-sm' onclick='manage_page(\"" . basename($page['url'], '.php') . "\", \"" . $page['url'] . "\", \"edit\")'><i class='fas fa-edit'></i></button>"
                ];
            }
        }
    }
}

// Create pages table JSON structure
$pages_table_json = [
    'headers' => ['Name', 'File', 'Type', 'Roles', 'Actions'],
    'pages_table_data' => $pages_table_data,
    'pages_table_headers' => ['Name', 'File', 'Type', 'Roles', 'Actions']
];

// Update the configuration
$config_data['menu_structure'] = $menu_data;
$config_data['rbac_settings'] = $rbac_data;
$config_data['pages_config'] = $pages_table_json;

// Save the configuration
if (file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT)) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save configuration']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Changes saved successfully']);
