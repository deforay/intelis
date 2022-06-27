<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../startup.php');
$tableName= "system_admin";
$userName = $db->escape($_POST['username']);
$emailId = $db->escape($_POST['email']);
$loginId = $db->escape($_POST['loginid']);
$password = $db->escape($_POST['password']);
$userPassword = sha1($password . SYSTEM_CONFIG['passwordSalt']);

try {
    if (isset($_POST['username']) && !empty($_POST['username']) && isset($_POST['password']) && !empty($_POST['password'])) {
    $insertData = array(
        'system_admin_name'     => $userName,
        'system_admin_email'    => $emailId,
        'system_admin_login'    => $loginId,
        'system_admin_password' => $userPassword
    );
    $db->insert($tableName, $insertData);
    $_SESSION['alertMsg'] = _("New User Added successfully");
}
        header("location:/system-admin/login/login.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
