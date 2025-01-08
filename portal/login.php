<?php
session_start();

// If already logged in, redirect to index
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: index.php");
    exit;
}

// For AJAX login requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    if ($_POST['login_user'] === 'test' && $_POST['login_passwd'] === 'test123') {
        // Set session variables
        $_SESSION['authenticated'] = true;
        $_SESSION['user_name'] = "Test User";
        
        // Force session write
        session_write_close();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    require_once "../private/scripts/modules/rbac/verifyuser.php";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <script src="plugins/jquery/jquery.min.js"></script>
</head>
<body>
    <form id="login_form" method="POST">
        <input type="text" name="login_user" placeholder="Username">
        <input type="password" name="login_passwd" placeholder="Password">
        <button type="submit">Login</button>
    </form>

    <script>
    $(document).ready(function() {
        $('#login_form').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: 'login.php',
                type: 'POST',
                data: $(this).serialize(),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                dataType: 'json',
                success: function(response) {
                    if (response && response.status === 'success') {
                        window.location.replace('index.php');
                    } else {
                        alert('Login failed: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Server error: ' + error);
                }
            });
        });
    });
    </script>
</body>
</html>
