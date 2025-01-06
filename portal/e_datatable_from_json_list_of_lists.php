<?php
require_once('includes/init.php');
require_once(realpath(dirname(__FILE__) . '/../shared/php/examples/datatable_list_view.php'));

// Debug logging
error_log("Loading examples datatable page");

// Check if user is logged in and has access
require_once 'includes/auth.php';
if (!check_access('e_datatable_from_json_list_of_lists.php')) {
    header('Location: 403.php');
    exit();
}

// Ensure static directory exists
$staticDir = dirname(__FILE__) . '/static/examples';
if (!is_dir($staticDir)) {
    error_log("Creating examples static directory");
    mkdir($staticDir, 0755, true);
}

error_log("Static files status:");
error_log("JS exists: " . (file_exists($staticDir . '/datatable.js') ? 'yes' : 'no'));

// Set page title and category
$page_title = "Examples - Datatable Lists of Lists";
$category = "Examples";

// Include header
include('header.php');

// Render the datatable list
echo render_datatable_list('example_datatable_report.json', $page_title, $category);

// Include footer
include('footer.php');
