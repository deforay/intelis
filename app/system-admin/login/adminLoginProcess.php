<?php

use App\Registries\ContainerRegistry;
use App\Services\UserService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "system_admin";
$adminUsername = trim($_POST['username']);
$adminPassword = trim($_POST['password']);
$user = ContainerRegistry::get(UserService::class);


try {
    $adminCount = $db->getValue("system_admin", "count(*)");
    if ($adminCount != 0) {
        if (isset($adminUsername)) {
            $params = array($adminUsername);
            $adminRow = $db->rawQueryOne("SELECT * FROM system_admin as ud WHERE ud.system_admin_login = ?", $params);
            
            if (isset($adminRow) && !empty($adminRow) && password_verify($adminPassword, $adminRow['system_admin_password'])) {
                $_SESSION['adminUserId'] = $adminRow['system_admin_id'];
                $_SESSION['adminUserName'] = ($adminRow['system_admin_name']);
                header("Location:/system-admin/edit-config/index.php");
            } else {
                throw new Exception("Invalid username or password");
            }
        } else {
            throw new Exception("Invalid username or password");
        }
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    $_SESSION['alertMsg'] = _("Please check your login credentials");
    header("Location:/system-admin/login/login.php");
}
