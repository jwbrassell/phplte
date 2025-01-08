<?php
session_start();

// Debug session state
error_log("Index.php - Session ID: " . session_id());
error_log("Index.php - Session Contents: " . print_r($_SESSION, true));
error_log("Index.php - Cookie: " . print_r($_COOKIE, true));

// Check if user is logged in
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    error_log("Index.php - Not authenticated, redirecting to login");
    header("Location: login.php");
    exit;
}

error_log("Index.php - User is authenticated");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></h1>
    <p>You are logged in.</p>
    <a href="logout.php">Logout</a>
    
    <div style="margin-top: 20px;">
        <h3>Session Info:</h3>
        <pre>
Session ID: <?php echo session_id(); ?>

Session Contents:
<?php print_r($_SESSION); ?>

Cookie Info:
<?php print_r($_COOKIE); ?>
        </pre>
    </div>
</body>
</html>
<?php
// Force session write at end of script
session_write_close();
?>
