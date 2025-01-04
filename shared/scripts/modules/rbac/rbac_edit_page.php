<?php
/**
 * RBAC Page Editor Interface
 * Generates form for editing RBAC settings for pages
 */

// Get and parse request data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);
$request_payload = $data['request_payload'];

// Load RBAC configuration
$rbac_file_path = '/var/www/html/framework/portal/config/rbac.json';
$rbac_file_data = file_get_contents($rbac_file_path);
$rbac_data = json_decode($rbac_file_data, true);

// Extract page information
$page_file_name = $request_payload['data']['page_file_name'];
$page_name = $request_payload['data']['page_name'];
$existing_page_data = $rbac_data["pages"][$page_file_name] ?? null;

// Get configuration data
$adom_groups = $rbac_data["adom_groups"];
$icon_list = $rbac_data["icon_list"];
$category_list = $rbac_data["category_list"];

// Set defaults and handle existing data
if ($existing_page_data) {
    $existing_page_type = $existing_page_data["link_type"] ?? 'single';
    $existing_page_name = $existing_page_data["link_name"] ?? $page_name;
    $existing_roles = $existing_page_data["roles"] ?? [];
    
    if ($existing_page_type === "category") {
        $existing_page_category = $existing_page_data["category"] ?? null;
        $existing_image_icon = $rbac_data["categories"][$existing_page_category]["icon"] ?? null;
    } else {
        $existing_image_icon = $existing_page_data["img"] ?? null;
    }
} else {
    $existing_page_type = 'single';
    $existing_page_name = $page_name;
    $existing_roles = [];
    $existing_image_icon = null;
    $existing_page_category = null;
}
?>

<!-- RBAC Edit Form -->
<div class="col-md-12">
    <div class="card card-info" style="overflow-x: auto;max-width: 100%;">
        <!-- Card Header -->
        <div class="card-header">
            <h3 id="edit_page_title" class="card-title">Edit Page</h3>
        </div>

        <!-- Card Body -->
        <div id="edit_page_rbac_card_body" name="edit_page_rbac_card_body" class="card-body">
            <!-- Page Name and Filename -->
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="name_input">Name</label>
                    <input type="text" 
                           class="form-control" 
                           id="link_name" 
                           name="link_name" 
                           value="<?php echo htmlspecialchars($page_name); ?>">
                </div>
                <div class="form-group col">
                    <label for="filename">Filename</label><br>
                    <span id="filename" 
                          name="filename"><?php echo htmlspecialchars($page_file_name); ?></span><br>
                </div>
            </div>

            <!-- Role Management -->
            <div class="row">
                <!-- Current Roles -->
                <div class="form-group col-md-6">
                    <label for="current_adom_groups">Current Roles</label><br>
                    <select id="current_adom_groups" 
                            name="current_adom_groups" 
                            class="form-control select2" 
                            multiple="multiple" 
                            disabled>
                        <?php foreach ($existing_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>" selected>
                                <?php echo htmlspecialchars($role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- New Roles -->
                <div class="form-group col-md-6">
                    <label for="adom_groups">New Roles</label><br>
                    <select id="adom_groups" 
                            name="adom_groups[]" 
                            class="form-control select2" 
                            multiple="multiple">
                        <?php foreach ($adom_groups as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>" 
                                    <?php echo in_array($role, $existing_roles) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Page Configuration -->
            <div class="row">
                <!-- Icon Selection -->
                <div class="form-group col-md-4">
                    <div id="iconDropdown">
                        <label for="icon_options">Icon</label><br>
                        <select id="icon_options" name="icon_options" class="select2">
                            <?php foreach ($icon_list as $icon): ?>
                                <option value="<?php echo htmlspecialchars($icon); ?>" 
                                        <?php echo $icon === $existing_image_icon ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($icon); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Link Type Selection -->
                <div class="form-group col-md-4">
                    <label for="link_type">Link Type</label><br>
                    <select id="link_type" name="link_type" class="select2">
                        <option value="category" 
                                <?php echo $existing_page_type === 'category' ? 'selected' : ''; ?>>
                            Category
                        </option>
                        <option value="single" 
                                <?php echo $existing_page_type === 'single' ? 'selected' : ''; ?>>
                            Single
                        </option>
                    </select>
                </div>

                <!-- Category Selection -->
                <div id="category_dropdown" class="form-group col-md-4">
                    <label for="category_options">Category</label><br>
                    <select id="category_options" name="category_options" class="select2">
                        <?php foreach ($category_list as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" 
                                    <?php echo $category === $existing_page_category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Save Button -->
            <button class="btn btn-primary" onclick="sendRequest()">Save</button>
        </div>
    </div>
</div>