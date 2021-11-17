<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');  
$tableName= "system_admin";

try {
    $userId = base64_decode($_POST['userId']);
    if(isset($_POST['password']) && trim($_POST['password'])!=""){
        $data = array(
            'system_admin_password' => $_POST['password'],
    );
    $db = $db->where('system_admin_id', $userId);
    $db->update($tableName, $data);
    $_SESSION['alertMsg'] = "Password updated successfully";
    }
    header("location:/system-admin/edit-config/index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
