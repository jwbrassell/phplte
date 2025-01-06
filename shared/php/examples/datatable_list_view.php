<?php
function render_datatable_list($json_file, $page_title = null, $category = null) {
    require_once(dirname(__FILE__) . '/datatable_handler.php');
    
    // Get data from JSON file
    $data = get_datatable_data($json_file);
    
    if (!$page_title) {
        $page_title = $data['title'];
    }
    
    if (!$category) {
        $category = "Examples";
    }
    
    ob_start();
    ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?php echo htmlspecialchars($data['title']); ?></h1>
                        <p class="text-muted">Last Updated: <?php echo htmlspecialchars($data['last_updated']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <?php if (isset($data['error'])): ?>
                                    <div class="alert alert-danger">
                                        Error loading data: <?php echo htmlspecialchars($data['error']); ?>
                                    </div>
                                <?php else: ?>
                                    <link rel="stylesheet" href="static/examples/datatable.css">
                                    <table id="datatable-list-view" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <?php foreach ($data['headers'] as $header): ?>
                                                    <th><?php echo htmlspecialchars($header); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data['data'] as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $cell): ?>
                                                        <td><?php echo htmlspecialchars($cell); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <script src="static/examples/datatable.js"></script>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
