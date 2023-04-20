<?php

if (session_status() == PHP_SESSION_NONE) {
        session_start();
}
  


$tableName1 = "roles";
$tableName2 = "roles_privileges_map";
try {
        if (isset($_POST['roleName']) && trim($_POST['roleName']) != "") {
                $data = array(
                        'role_name' => $_POST['roleName'],
                        'role_code' => $_POST['roleCode'],
                        'status' => $_POST['status'],
                        'access_type' => $_POST['accessType'],
                        'landing_page' => $_POST['landingPage']
                );
                $db->insert($tableName1, $data);
                $lastId = $db->getInsertId();
                if ($lastId != 0 && $lastId != '') {
                        if (isset($_POST['resource']) && $_POST['resource'] != '') {
                                foreach ($_POST['resource'] as $key => $priviId) {
                                        if ($priviId == 'allow') {
                                                $value = array('role_id' => $lastId, 'privilege_id' => $key);
                                                $db->insert($tableName2, $value);
                                        }
                                }
                        }
                        $_SESSION['alertMsg'] = _("Roles Added successfully");
                }
        }
        header("Location:roles.php");
} catch (Exception $exc) {
        error_log($exc->getMessage());
        error_log($exc->getTraceAsString());
}
