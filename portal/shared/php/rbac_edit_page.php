<?php
// Get the request payload
$request = json_decode(file_get_contents('php://input'), true);

if (!$request) {
    http_response_code(400);
    echo "Invalid request format";
    exit;
}

// Get configuration files
$config_dir = __DIR__ . '/../../config/';
$rbac_file = $config_dir . 'rbac.json';
$menu_file = $config_dir . 'menu-bar.json';

// Load configurations
$rbac_data = json_decode(file_get_contents($rbac_file), true);
$menu_data = json_decode(file_get_contents($menu_file), true);

// Extract request data
$page_name = $request['request_payload']['data']['page_name'];
$page_file = $request['request_payload']['data']['page_file_name'];
$action = $request['request_payload']['data']['action'];

// Get available groups and icons
$adom_groups = $rbac_data['adom_groups'];
$icons = $rbac_data['icon_list'];
$categories = $rbac_data['category_list'];

// Find existing page configuration if it exists
$existing_config = null;
foreach ($menu_data as $key => $value) {
    if ($value['type'] === 'single') {
        foreach ($value['urls'] as $title => $info) {
            if ($info['url'] === $page_file) {
                $existing_config = [
                    'name' => $title,
                    'type' => 'single',
                    'category' => $key,
                    'icon' => $value['img'],
                    'roles' => $info['roles']
                ];
                break 2;
            }
        }
    } elseif ($value['type'] === 'category') {
        foreach ($value['urls'] as $title => $info) {
            if ($info['url'] === $page_file) {
                $existing_config = [
                    'name' => $title,
                    'type' => 'category',
                    'category' => $key,
                    'icon' => $value['img'],
                    'roles' => $info['roles']
                ];
                break 2;
            }
        }
    }
}

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
