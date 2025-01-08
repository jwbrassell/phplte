<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit;
}
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
        <h3>Session Debug Info:</h3>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>
</body>
</html>
