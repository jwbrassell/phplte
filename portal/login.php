<?php
require_once(__DIR__ . '/header.php');

if(isset($_SESSION[$APP."-user_session"])) {
    $uname = $_SESSION[$APP."-user_session"];
    $browser = get_browser(null, true)["browser"];
    
    if ($browser == "IE") {
        ?>
        <script type="text/javascript">
            alert("IE is not a supported browser. \n\nPlease use Chrome or Safari.");
        </script>
        <?php
    }
}
?>

<script>
$(function() {
    $('#login_form').validate({
        rules: {
            login_user: {
                required: true
            },
            login_passwd: {
                required: true
            }
        },
        messages: {
            login_user: {
                required: "Username is required"
            },
            login_passwd: {
                required: "Password is required"
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
        }
    });
});
</script>

<script type="text/javascript">
function submitOnEnter() {
    if (event.keyCode == 13) {
        document.getElementById("login_submit").click();
    }
}
</script>

<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <b><?php echo ucfirst($APP); ?></b>
        </div>

        <div class="card">
            <div class="card-body login-card-body">
                <form id="login_form" method="POST">
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
                               onkeypress="submitOnEnter()">
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
                                    id="login_submit">SIGN IN</button>
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
