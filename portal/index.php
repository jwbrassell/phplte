<?php
require_once 'includes/init.php';
require_once 'header.php';
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
                        <h3 class="card-title">Welcome</h3>
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
