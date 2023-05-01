<?php

use App\Registries\ContainerRegistry;
use App\Services\UsersService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName= "system_admin";
$userName = ($_POST['username']);
$emailId = ($_POST['email']);
$loginId = ($_POST['loginid']);
$password = ($_POST['password']);
$secretKey = trim($_POST['secretKey']);

$user = ContainerRegistry::get(UsersService::class);

$userPassword = $user->passwordHash($password);

try {
    $key = file_get_contents("app/system-admin/secretKey.txt");

    if($secretKey==trim($key)){
    if (isset($_POST['username']) && !empty($_POST['username']) && isset($_POST['password']) && !empty($_POST['password'])) {
    $insertData = array(
        'system_admin_name'     => $userName,
        'system_admin_email'    => $emailId,
        'system_admin_login'    => $loginId,
        'system_admin_password' => $userPassword
    );
    $db->insert($tableName, $insertData);
    unlink("app/system-admin/secretKey.txt");
    $_SESSION['alertMsg'] = _("New User Added successfully");
    header("Location:/system-admin/login/login.php");
    }
    }
    else{
        $_SESSION['alertMsg'] = _("Invalid Secret Key, Please enter valid key");
        header("Location:/system-admin/setup/index.php");
    }
    
        
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
