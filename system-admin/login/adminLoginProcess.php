<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$tableName = "system_admin";
$adminUsername = trim($_POST['username']);
$adminPassword = trim($_POST['password']);
$user = new \Vlsm\Models\Users();


try {
    $adminCount = $db->getValue("system_admin", "count(*)");
    if ($adminCount != 0) {
        if (isset($_POST['username']) && trim($_POST['username']) != "" && isset($_POST['password']) && trim($_POST['password']) != "") {
            $params = array($adminUsername);
            $adminRow = $db->rawQueryOne("SELECT system_admin_password FROM system_admin as ud WHERE ud.system_admin_login = ?", $params);
            if (isset($adminRow) && !empty($adminRow) && password_verify($adminPassword, $adminRow['system_admin_password'])) {
                $_SESSION['adminUserId'] = $adminRow['system_admin_id'];
                $_SESSION['adminUserName'] = ucwords($adminRow['system_admin_name']);
                header("location:/system-admin/edit-config/index.php");
            } else {
                header("location:/system-admin/login/login.php");
                $_SESSION['alertMsg'] = _("Please check your login credentials");
            }
        } else {
            header("location:/system-admin/login/login.php");
        }
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
