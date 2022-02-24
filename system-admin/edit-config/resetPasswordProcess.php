<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
  
$tableName= "system_admin";

try {
    $userId = base64_decode($_POST['userId']);
    if(isset($_POST['password']) && trim($_POST['password'])!=""){
    $data['system_admin_password'] = sha1($_POST['password'] . $systemConfig['passwordSalt']);
    $db = $db->where('system_admin_id', $userId);
    $db->update($tableName, $data);
    $_SESSION['alertMsg'] = _("Password updated successfully");
    }
    header("location:/system-admin/edit-config/index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
