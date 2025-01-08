<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/header.php';

// Initialize error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Get error from query parameter if it exists
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    error_log("Login error received: " . $error);
}

// Check for session hijacking
if(isset($_SESSION[$APP."_user_session"])) {
    if(!isset($_SESSION['ip']) || !isset($_SESSION['user_agent'])) {
        session_destroy();
        logError("Session security check failed - missing IP or user agent", [
            'type' => 'session_security',
            'check' => 'missing_identifiers'
        ]);
        header("Location: login.php");
        exit;
    }
    
    if($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR'] || 
       $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        logError("Session security check failed - IP or user agent mismatch", [
            'type' => 'session_security',
            'check' => 'identifier_mismatch',
            'stored_ip' => $_SESSION['ip'],
            'current_ip' => $_SERVER['REMOTE_ADDR']
        ]);
        header("Location: login.php");
        exit;
    }
    
    $uname = $_SESSION[$APP."_user_session"];
    
    // Browser check with better error handling
    try {
        $startTime = microtime(true);
        $browser_info = get_browser(null, true);
        logPerformance('browser_detection', (microtime(true) - $startTime) * 1000);
        
        if ($browser_info && isset($browser_info["browser"]) && $browser_info["browser"] == "IE") {
            $error_message = "Internet Explorer is not supported. Please use Chrome or Safari.";
            logError($error_message, [
                'type' => 'browser_compatibility',
                'browser' => $browser_info["browser"],
                'version' => $browser_info["version"] ?? 'unknown'
            ]);
            $_SESSION['browser_warning'] = $error_message;
        }
    } catch (Exception $e) {
        logError("Browser detection failed: " . $e->getMessage(), [
            'type' => 'browser_detection_error',
            'error_code' => $e->getCode()
        ]);
    }
}

// Set session security tokens on login
if(isset($_POST['login_submit'])) {
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}
?>

<!-- Add required plugins -->
<link rel="stylesheet" href="plugins/toastr/toastr.min.css">

<script src="plugins/toastr/toastr.min.js" defer></script>
<script src="plugins/jquery-validation/jquery.validate.min.js" defer></script>
<script src="plugins/jquery-validation/additional-methods.min.js" defer></script>

<!-- Initialize scripts after jQuery loads -->
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

// Enhanced client-side error logging
window.onerror = function(msg, url, line, col, error) {
    $.post('/portal/includes/logging/log_error.php', {
        type: 'JS_ERROR',
        message: msg,
        url: url,
        line: line,
        column: col,
        error: error ? error.stack : 'No error object',
        page: 'login'
    });
    return false;
};

// Performance monitoring
const pageLoadStart = performance.now();
window.addEventListener('load', function() {
    const pageLoadTime = performance.now() - pageLoadStart;
    $.post('/portal/includes/logging/log_error.php', {
        type: 'PERFORMANCE',
        message: 'Page Load Complete',
        duration: pageLoadTime,
        page: 'login'
    });
});

// Initialize after jQuery loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($_SESSION['browser_warning'])): ?>
    toastr.warning("<?php echo htmlspecialchars($_SESSION['browser_warning']); ?>");
    <?php unset($_SESSION['browser_warning']); ?>
    <?php endif; ?>

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

<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <b><?php echo ucfirst($APP); ?></b>
        </div>

        <div class="card">
            <div class="card-body login-card-body">
                <form id="login_form" method="POST" autocomplete="off">
                    <?php if(isset($_GET['next'])): ?>
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($_GET['next']); ?>">
                    <?php endif; ?>
                    <div class="input-group mb-3 form-group">
                        <select class="form-control custom-select" name="login_domain" disabled>
                            <option selected>USWIN</option>
                        </select>
                    </div>

                    <div class="input-group mb-3 form-group">
                        <input type="text" 
                               class="form-control <?php if(isset($error)) { echo 'is-invalid'; } ?>" 
                               name="login_user" 
                               <?php if(!isset($uname)) { 
                                   echo 'placeholder="SampleOrganization ID"';
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
                            <?php if(isset($error)) { echo htmlspecialchars($error); } ?>
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
require_once __DIR__ . '/footer.php';
?>
