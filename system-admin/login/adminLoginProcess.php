<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');  

try {
    if(isset($_POST['username']) && trim($_POST['username'])!="" && isset($_POST['password']) && trim($_POST['password'])!=""){
        $adminUsername = trim($_POST['username']);
        $adminPassword = trim($_POST['password']);
        $params = array($adminUsername,$adminPassword);
        $admin = $db->rawQuery("SELECT * FROM system_admin as ud WHERE ud.system_admin_login = ? AND ud.system_admin_password = ?", $params);
        if(count($admin)>0){
            $_SESSION['adminUserId']=$admin[0]['system_admin_id'];
            $_SESSION['adminUserName']=ucwords($admin[0]['system_admin_name']);
            header("location:/system-admin/edit-config/index.php");
        }else{
            header("location:/system-admin/login/login.php");
            $_SESSION['alertMsg']="Please check your login credentials";
        }
    }else{
        header("location:/system-admin/login/login.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
