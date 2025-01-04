<?php
session_start();
date_default_timezone_set('UTC');
error_reporting(E_ERROR | E_PARSE);

// Get current page name and URI from URL
$PAGE = basename($_SERVER['PHP_SELF']);
$URI = str_replace('%20', ' ', $_SERVER['REQUEST_URI']);

require_once(__DIR__ . "/../config.php");

// Initialize session variables if not set
if (!isset($_SESSION[$APP."_user_name"])) {
    $uswin = '';
    $username = '';
    $vzid = '';
    $fname = '';
    $lname = '';
    $user_email = '';
    $adom_groups = array();
}

// Initialize menu data
$data = array();
$menuFile = $DIR.'/config/menu-bar.json';

// Debug output
error_log("Attempting to read menu file: " . $menuFile);

if (file_exists($menuFile)) {
    $jsonContent = file_get_contents($menuFile);
    if ($jsonContent !== false) {
        $data = json_decode($jsonContent, true);
        if ($data !== null) {
            ksort($data);
        } else {
            error_log("Failed to decode JSON from menu-bar.json");
            $data = array(); // Fallback to empty array
        }
    } else {
        error_log("Failed to read contents of menu-bar.json");
        $data = array(); // Fallback to empty array
    }
} else {
    error_log("Menu file not found at: " . $menuFile);
    $data = array(); // Fallback to empty array
}

$alertMessage = '';
?>
