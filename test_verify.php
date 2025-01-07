<?php
// Simulate POST variables
$_POST = array(
    'login_user' => 'test',
    'login_passwd' => 'test123',
    'APP' => 'framework'
);

// Include verifyuser.php
require_once(__DIR__ . '/shared/scripts/modules/rbac/verifyuser.php');
