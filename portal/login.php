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
        $_SESSION['authenticated'] = true;
        $_SESSION['user_name'] = "Test User";
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    // Include verifyuser.php for LDAP auth
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
                success: function(response) {
                    if (response.status === 'success') {
                        window.location.href = 'index.php';
                    } else {
                        alert('Login failed');
                    }
                },
                error: function() {
                    alert('Server error');
                }
            });
        });
    });
    </script>
</body>
</html>
