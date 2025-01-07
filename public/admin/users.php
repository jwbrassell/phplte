<?php
require_once dirname(dirname(__FILE__)) . '/config/config.php';
require_once dirname(dirname(__FILE__)) . '/includes/header.php';
require_once dirname(dirname(__FILE__)) . '/private/includes/components/datatables/DataTableComponent.php';

// Configure datatable
$posted_data = [
    'type' => 'rbac_config',  // Changed from rbac_pages to rbac_config
    'data_directory' => '',
    'root_data_dir' => 'config',
    'data_key' => 'pages_table_data',
    'header_key' => 'pages_table_headers',
    'row_height' => 30
];

// Check if user has access to admin functionality
if (!check_access('user_admin')) {
    debug_log("Access denied to user admin");
    header('Location: ../403.php');
    exit();
}

// Handle login submission
if (isset($_POST['login_submit'])) {
    if ($_POST['login_user'] === 'test' && $_POST['login_passwd'] === 'test123') {
        $_SESSION[$APP."_user_name"] = "Test User";
        $_SESSION[$APP."_user_session"] = "test";
        $_SESSION[$APP."_user_vzid"] = "test123";
        $_SESSION[$APP."_user_email"] = "test@example.com";
        $_SESSION[$APP."_adom_groups"] = "['admin']";
        header("Location: index.php");
        exit;
    } else {
        // For local development, just redirect to index.php
        header("Location: index.php");
        exit;
    }
}
?>

<!-- Custom Styles -->
<style>
    .select2-container { 
        width: 100% !important;
    }
    
    .select2-selection {
        width: 100% !important;
    }
    
    .custom-word-wrap { 
        word-wrap: break-word;
        max-height: 6em;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
    }
</style>

<!-- Page Content -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>User Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item">
                            <a href="../index.php">Home</a>
                        </li>
                        <li class="breadcrumb-item active">
                            <a href="users.php">User Management</a>
                        </li>
                    </ol>
                </div>
            </div>
            <div class="row mb-2" style="padding-top: 10px">
                <div class="col-sm-12">
                    <span>Manage user access and permissions</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Actions Row -->
            <div id="actions_row" class="row">
                <div class="col-2">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Add Page</h3>
                        </div>
                        <div class="card-body">
                            <div class="input-group">
                                <input type="text" class="form-control" id="textInput" placeholder="filename.php">
                                <div class="input-group-append">
                                    <button type="button" onclick="getAndManagePage()" class="btn btn-info btn-sm">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div id="tables_row" class="row">
                <div class="col-md-5">
                    <div class="card card-info" style="overflow-x: auto;max-width: 100%;">
                        <div class="card-header">
                            <h3 class="card-title">Pages</h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            $table = new DataTableComponent();
                            echo $table->renderDictionaryTable($posted_data, [
                                'pageLength' => 10,
                                'responsive' => true,
                                'order' => [[0, 'asc']]
                            ]); 
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div id="edit_page_div" name="edit_page_div"></div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- JavaScript Functions -->
<script>
/**
 * Gets and manages page information
 */
function getAndManagePage() {
    const input = document.getElementById('textInput');
    const filename = input.value.trim();
    
    if (!filename) {
        showToastr('error', 'Please enter a filename', 'error');
        return;
    }
    
    if (!filename.endsWith('.php')) {
        showToastr('error', 'Filename must end with .php', 'error');
        return;
    }
    
    const action = 'edit';
    const name = filename.replace('.php', '');
    manage_page(name, filename, action);
    input.value = ''; // Clear input after use
}

/**
 * Attaches event listener to the dropdown
 */
function attachDropdownListener() {
    $('#link_type').on('select2:select', function(e) {
        console.log('Dropdown value:', $(this).val());
        const value = $(this).val();
        if (value === 'category') {
            $('#category_dropdown').removeClass('d-none');
        } else {
            $('#category_dropdown').addClass('d-none');
        }
    });
}

/**
 * Updates category dropdown visibility
 */
function updateCategoryDropdownVisibility() {
    const value = $('#link_type').val();
    if (value === 'category') {
        $('#category_dropdown').removeClass('d-none');
    } else {
        $('#category_dropdown').addClass('d-none');
    }
}

/**
 * Manages page actions and RBAC settings
 */
function manage_page(name, link, action) {
    const data = {
        "request_payload": {
            "request_type": "rbac",
            "data": {
                page_name: name,
                page_file_name: link,
                action: action,
                app: "<?php echo $APP; ?>"
            }
        }
    };

    $.ajax({
        type: "POST",
        url: "../api/rbac_edit_page.php",
        data: JSON.stringify(data),
        contentType: 'application/json',
        dataType: "html",
        success: function(response) {
            if (response.startsWith('{"error":')) {
                // Handle JSON error response
                const errorData = JSON.parse(response);
                showToastr('error', errorData.error, 'Error');
                return;
            }
            document.getElementById('edit_page_div').innerHTML = response;
            
            // Initialize Select2 elements
            $('#adom_groups').select2({
                tags: true
            });
            $('#icon_options').select2();
            $('#link_type').select2();
            $('#category_options').select2({
                tags: true
            });

            attachDropdownListener();
            updateCategoryDropdownVisibility();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('Error:', textStatus, errorThrown);
            console.log('Response:', jqXHR.responseText);
            showToastr('error', 'Failed to load edit form: ' + errorThrown, 'Error');
        }
    });
}

/**
 * Sends RBAC request to the server
 */
function sendRequest(script_directory = null) {
    const vzid = "<?php echo $vzid; ?>";
    const app = "<?php echo $APP; ?>";
    const first_name = "<?php echo $fname; ?>";
    const last_name = "<?php echo $lname; ?>";
    const full_name = first_name + " " + last_name;

    // Get form values
    const link_name = document.getElementById('link_name').value;
    const link_type = document.getElementById('link_type').value;
    let category = null;
    if (link_type === 'category') {
        category = document.getElementById('category_options').value;
    }
    const image = document.getElementById('icon_options').value;
    const filename = document.getElementById('filename').textContent;
    const old_adom_groups = $('#current_adom_groups').val();
    const new_adom_groups = $('#adom_groups').val();

    // Prepare the data payload
    const data = {
        "request_payload": {
            "request_type": "rbac",
            "data": {
                "action_type": "save_page_rbac",
                "link_name": link_name,
                "link_type": link_type,
                "filename": filename,
                "old_adom_groups": old_adom_groups,
                "new_adom_groups": new_adom_groups,
                "full_name": full_name,
                "vzid": vzid,
                "app": app,
                "image": image
            }
        }
    };

    if (category) {
        data.request_payload.data.category = category;
    }

    if (script_directory) {
        data.request_payload.script_directory = script_directory;
    }

    $.ajax({
        type: "POST",
        url: "../api/run_python_script.php",
        data: JSON.stringify(data),
        contentType: 'application/json',
        dataType: "text",
        success: function(response) {
            console.log(response);
            if (response.includes('fail')) {
                showToastr('error', 'Operation failed', 'error');
            } else {
                showToastr('success', response || 'Operation successful', 'success');
                // Reload the page to show updated data
                location.reload();
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('Error:', textStatus, errorThrown);
            showToastr('error', 'Failed to save changes', 'error');
        }
    });
}

// Initialize on page load
$(function() {
    // Apply word wrap to Type column after table is loaded
    $('table#table_rbac_config td:nth-child(3)').addClass('custom-word-wrap');
});
</script>

<?php require_once dirname(dirname(__FILE__)) . '/includes/footer.php'; ?>
