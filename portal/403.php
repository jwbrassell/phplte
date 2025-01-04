<?php
require_once(__DIR__ . '/header.php');
$requested_page = isset($_GET['page']) ? htmlspecialchars($_GET['page']) : '';
$referrer = isset($_GET['referrer']) ? htmlspecialchars($_GET['referrer']) : 'index.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>403 Error Page</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="error-page">
            <h2 class="headline text-danger">403</h2>
            <div class="error-content">
                <h3><i class="fas fa-exclamation-triangle text-danger"></i> Oops! Access Denied.</h3>
                <p>
                    You do not have permission to access<?php echo $requested_page ? " $requested_page" : ' this page'; ?>.
                    You may <a href="<?php echo $referrer; ?>">go back</a> or <a href="index.php">return to dashboard</a>.
                </p>
            </div>
        </div>
    </section>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>
