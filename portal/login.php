<?php
require_once(__DIR__ . '/header.php');

// Initialize error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to safely write to error log
function write_error_log($message, $type = 'ERROR') {
    $log_dir = __DIR__ . '/logs/errors';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/' . date('Ymd') . '_error.log';
    $log_message = sprintf(
        "%s||%s||%s||%s||%s\n",
        date('Y,m,d,H,i,s'),
        $type,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $message
    );
    
    error_log($log_message, 3, $log_file);
}

// Check for session hijacking
if(isset($_SESSION[$APP."_user_session"])) {
    if(!isset($_SESSION['ip']) || !isset($_SESSION['user_agent'])) {
        session_destroy();
        write_error_log("Session security check failed - missing IP or user agent");
        header("Location: login.php");
        exit;
    }
    
    if($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR'] || 
       $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        write_error_log("Session security check failed - IP or user agent mismatch");
        header("Location: login.php");
        exit;
    }
    
    $uname = $_SESSION[$APP."_user_session"];
    
    // Browser check with better error handling
    try {
        $browser_info = get_browser(null, true);
        if ($browser_info && isset($browser_info["browser"]) && $browser_info["browser"] == "IE") {
            $error_message = "Internet Explorer is not supported. Please use Chrome or Safari.";
            write_error_log($error_message, 'WARN');
            ?>
            <script type="text/javascript">
                $(document).ready(function() {
                    toastr.warning("<?php echo $error_message; ?>");
                });
            </script>
            <?php
        }
    } catch (Exception $e) {
        write_error_log("Browser detection failed: " . $e->getMessage());
    }
}

// Set session security tokens on login
if(isset($_POST['login_submit'])) {
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}
?>

<script>
$(function() {
    // Configure toastr
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "timeOut": "5000",
        "positionClass": "toast-top-center"
    };
    
    $('#login_form').validate({
        rules: {
            login_user: {
                required: true,
                minlength: 2
            },
            login_passwd: {
                required: true,
                minlength: 6
            }
        },
        messages: {
            login_user: {
                required: "Username is required",
                minlength: "Username must be at least 2 characters"
            },
            login_passwd: {
                required: "Password is required",
                minlength: "Password must be at least 6 characters"
            }
        },
        errorElement: 'span',
        errorPlacement: function(error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group').append(error);
        },
        highlight: function(element, errorClass, validClass) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function(element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
        },
        submitHandler: function(form) {
            $('#login_submit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Signing In...');
            form.submit();
        }
    });
    
    // Display error messages using toastr if they exist
    <?php if(isset($error)): ?>
        <?php if(strpos($error, 'Authentication error') !== false): ?>
            toastr.error("<?php echo htmlspecialchars($error); ?>", "System Error");
        <?php else: ?>
            toastr.warning("<?php echo htmlspecialchars($error); ?>", "Login Failed");
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<script type="text/javascript">
function submitOnEnter(event) {
    if (event.keyCode == 13) {
        if ($('#login_form').valid()) {
            document.getElementById("login_submit").click();
        } else {
            toastr.warning("Please fill in all required fields correctly.");
        }
    }
}

// Add error logging for client-side errors
window.onerror = function(msg, url, line) {
    $.post('log_error.php', {
        type: 'JS_ERROR',
        message: msg,
        url: url,
        line: line
    });
    return false;
};
</script>

<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <b><?php echo ucfirst($APP); ?></b>
        </div>

        <div class="card">
            <div class="card-body login-card-body">
                <form id="login_form" method="POST" autocomplete="off">
                    <div class="input-group mb-3 form-group">
                        <select class="form-control custom-select" name="login_domain" disabled>
                            <option selected>USWIN</option>
                        </select>
                    </div>

                    <div class="input-group mb-3 form-group">
                        <input type="text" 
                               class="form-control" 
                               name="login_user" 
                               <?php if(!isset($uname)) { 
                                   echo 'placeholder="Verizon ID"'; 
                               } else { 
                                   echo 'value="'.$uname.'"'; 
                               } ?>>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user"></span>
                            </div>
                        </div>
                    </div>

                    <div class="input-group mb-3 form-group">
                        <input type="password" 
                               class="form-control <?php if(isset($error)) { echo 'is-invalid'; } ?>" 
                               name="login_passwd" 
                               placeholder="Password" 
                               onkeypress="submitOnEnter(event)"
                               autocomplete="off">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                        <div class="invalid-feedback">
                            <?php if(isset($error)) { echo $error; } ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <input type="hidden" name="APP" value="<?php echo $APP; ?>">
                            <button type="submit" 
                                    class="btn btn-primary btn-block" 
                                    name="login_submit" 
                                    id="login_submit">
                                <i class="fas fa-sign-in-alt"></i> SIGN IN
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

<?php
require_once(__DIR__ . '/footer.php');
?>
