<?php
require_once(__DIR__ . '/header.php');
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Dashboard</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            Welcome to <?php echo ucfirst($APP); ?> NMC
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>
