<?php
// Load initialization
require_once 'includes/init.php';

// Debug session state before header inclusion
error_log("Index.php - Session state before header: " . print_r($_SESSION, true));
error_log("Index.php - Current user: " . ($_SESSION[$APP.'_user_name'] ?? 'none'));
error_log("Index.php - Authenticated: " . ($_SESSION['authenticated'] ? 'yes' : 'no'));

// Check authentication before loading header
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    error_log("Index.php - Not authenticated, redirecting to login");
    header("Location: login.php");
    exit;
}

require_once 'header.php';

// Debug session state after header inclusion
error_log("Index.php - Session state after header: " . print_r($_SESSION, true));
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Dashboard</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Welcome <?php echo htmlspecialchars($_SESSION[$APP.'_user_name'] ?? ''); ?></h3>
                    </div>
                    <div class="card-body">
                        <p>Welcome to the portal. Please use the sidebar navigation to access different sections.</p>
                        
                        <!-- Debug Information -->
                        <?php if (!IS_PRODUCTION): ?>
                        <div class="mt-4">
                            <h4>Debug Information</h4>
                            <pre>
Project Root: <?php echo PROJECT_ROOT; ?>

Shared Directory: <?php echo SHARED_DIR; ?>

Data Directory: <?php echo DATA_DIR; ?>

Scripts Directory: <?php echo SCRIPTS_DIR; ?>

Session Information:
<?php
echo "User: " . ($_SESSION[$APP.'_user_name'] ?? 'Not set') . "\n";
echo "Authenticated: " . ($_SESSION['authenticated'] ? 'Yes' : 'No') . "\n";
echo "Groups: " . ($_SESSION[$APP.'_adom_groups'] ?? 'Not set') . "\n";
?>
                            </pre>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>
