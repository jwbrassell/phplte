<?php
$validuser = 1;

if (isset($_POST['username'])) {
    include('../config.php');
    $usermodal = $_POST['username'];
    $data = trim(`$DIR/scripts/user_lookup.py $usermodal`);
    
    if ($data == "User not found") {
        $validuser = 0;
    } else {
        list($vzid, $title, $name, $email, $address, $slack) = explode('|', $data);
    }
}

if ($validuser == 1) {
?>
    <div class="modal-body">
        <div class="col-12 d-flex align-items-stretch flex-column">
            <div class="card bg-light d-flex flex-fill">
                <div class="card-header border-bottom-0">
                    <h2 class="lead"><b><?php echo $name; ?></b></h2>
                </div>
                <div class="card-body pt-0">
                    <div class="row">
                        <div class="col-8">
                            <h6 class="text-muted" style="padding-bottom: 15px;"><?php echo $title; ?></h6>
                            <ul class="ml-4 mb-0 fa-ul text-muted">
                                <!-- Building/Address -->
                                <li class="small" style="padding-bottom:5px;">
                                    <span class="fa-li">
                                        <i class="fas fa-lg fa-building"></i>
                                    </span>
                                    <?php echo $address; ?>
                                </li>
                                
                                <!-- Slack Contact -->
                                <li class="small" style="padding-bottom:5px;">
                                    <span class="fa-li">
                                        <i class="fab fa-lg fa-slack"></i>
                                    </span>
                                    <a href="https://sampleorganization.enterprise.slack.com/user/@<?php echo $slack; ?>"
                                       target="_blank">Connect on Slack</a>
                                </li>
                                
                                <!-- Email -->
                                <li class="small" style="padding-bottom:5px;">
                                    <span class="fa-li">
                                        <i class="fas fa-lg fa-envelope"></i>
                                    </span>
                                    <?php echo $email; ?>
                                </li>
                            </ul>
                        </div>
                        <div class="col-4 text-center">
                            <img src="https://profilepicture.sampleorganization.com/apps/photoapp/ImageServlet?eid=<?php echo $vzid; ?>"
                                 class="img-circle img-fluid"
                                 alt="User Profile Picture">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer justify-content-between">
        <a href="https://oneprofile.sampleorganization.com/profile/<?php echo $vzid; ?>"
           target="_blank" 
           class="btn btn-primary">View Profile</a>
        <button type="button" 
                class="btn btn-default" 
                data-dismiss="modal">Close</button>
    </div>
<?php
} else {
?>
    <div class="modal-body">
        <div class="col-12 d-flex align-items-stretch flex-column">
            <h2 class="lead"><b>User not found</b></h2>
        </div>
    </div>
<?php
}
?>
