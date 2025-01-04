<?php
require_once(__DIR__ . '/header.php');
$requested_page = isset($_GET['page']) ? htmlspecialchars($_GET['page']) : '';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>404 Error Page</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="error-page">
            <h2 class="headline text-warning">404</h2>
            <div class="error-content">
                <h3><i class="fas fa-exclamation-triangle text-warning"></i> Oops! Page not found.</h3>
                <p>
                    We could not find the page you were looking for<?php echo $requested_page ? ": $requested_page" : '.'; ?>
                    Meanwhile, you may <a href="index.php">return to dashboard</a>.
                </p>
            </div>
        </div>
    </section>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>
