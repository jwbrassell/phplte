<?php
require_once dirname(__FILE__) . '/../config/config.php';
require_once dirname(__FILE__) . '/../private/includes/components/datatables/DataTableComponent.php';

// Example list data (array of arrays)
$data = [
    ['John Doe', 'john@example.com', 'Admin', '2023-01-01'],
    ['Jane Smith', 'jane@example.com', 'User', '2023-01-02'],
    ['Bob Wilson', 'bob@example.com', 'Editor', '2023-01-03']
];

// Custom configuration with column titles
$config = [
    'pageLength' => 25,
    'order' => [[0, 'asc']], // Sort by name column
    'buttons' => ['copy', 'csv'], // Only show copy and CSV buttons
    'columns' => [
        ['title' => 'Name'],
        ['title' => 'Email'],
        ['title' => 'Role'],
        ['title' => 'Join Date']
    ]
];

try {
    // Initialize component
    $table = new DataTableComponent();
    
    // Handle AJAX requests
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($table->getJsonResponse(
            $data,
            $_GET['draw'] ?? 1,
            $_GET['start'] ?? 0,
            $_GET['length'] ?? 10,
            $_GET['search'] ?? null,
            $_GET['order'] ?? null
        ));
        exit;
    }
    
    // Regular page load
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>List Table Example</title>
        <!-- DataTables CSS -->
        <link rel="stylesheet" type="text/css" href="../static/plugins/datatables/jquery.dataTables.min.css">
        <link rel="stylesheet" type="text/css" href="../static/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
        <link rel="stylesheet" type="text/css" href="../static/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
        <link rel="stylesheet" type="text/css" href="../static/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="../static/css/bootstrap/bootstrap.min.css">
        
        <!-- jQuery -->
        <script type="text/javascript" src="../static/js/jquery/jquery.min.js"></script>
        <!-- Bootstrap -->
        <script type="text/javascript" src="../static/plugins/bootstrap/bootstrap.bundle.min.js"></script>
        <!-- DataTables JS -->
        <script type="text/javascript" src="../static/plugins/datatables/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="../static/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
        <script type="text/javascript" src="../static/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="../static/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
        <script type="text/javascript" src="../static/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
        <script type="text/javascript" src="../static/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
        <script type="text/javascript" src="../static/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
        <!-- JSZip for Excel export -->
        <script type="text/javascript" src="../static/plugins/jszip/jszip.min.js"></script>
        <!-- PDFMake for PDF export -->
        <script type="text/javascript" src="../static/plugins/pdfmake/pdfmake.min.js"></script>
        <script type="text/javascript" src="../static/plugins/pdfmake/vfs_fonts.js"></script>
    </head>
    <body>
        <div class="container mt-5">
            <h2>List Table Example</h2>
            <div class="card">
                <div class="card-body">
                    <?php echo $table->renderListTable($data, $config); ?>
                </div>
            </div>
            
            <div class="mt-4">
                <h4>Example Code:</h4>
                <pre><code>
// Example list data (array of arrays)
$data = [
    ['John Doe', 'john@example.com', 'Admin', '2023-01-01'],
    ['Jane Smith', 'jane@example.com', 'User', '2023-01-02'],
    ['Bob Wilson', 'bob@example.com', 'Editor', '2023-01-03']
];

// Initialize component
$table = new DataTableComponent();

// Render table
echo $table->renderListTable($data, [
    'columns' => [
        ['title' => 'Name'],
        ['title' => 'Email'],
        ['title' => 'Role'],
        ['title' => 'Join Date']
    ]
]);
                </code></pre>
            </div>
        </div>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
