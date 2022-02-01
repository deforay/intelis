<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
  

$tableName = "system_config";
try {
    foreach ($_POST as $fieldName => $fieldValue) {
        $data = array('value' => $fieldValue);
        $db = $db->where('name', $fieldName);
        $db->update($tableName, $data);
    }
    $_SESSION['alertMsg'] = "System Config values updated successfully";
    header("location:index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
