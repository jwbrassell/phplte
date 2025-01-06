<?php
// Disable error reporting for clean output
error_reporting(0);
ini_set('display_errors', 0);

// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="weblinks_template.csv"');

// Create CSV content with headers and example row
$output = fopen('php://output', 'w');
fputcsv($output, ['url', 'title', 'description', 'icon', 'tags']);
fputcsv($output, [
    'https://example.com',
    'Example Link',
    'Description of the link',
    'fas fa-link',
    'tag1,tag2,tag3'
]);
fclose($output);
exit();
