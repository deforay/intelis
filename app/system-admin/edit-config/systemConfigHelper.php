<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

  

$tableName = "system_config";
try {
    foreach ($_POST as $fieldName => $fieldValue) {
        $data = array('value' => $fieldValue);
        $db = $db->where('name', $fieldName);
        $db->update($tableName, $data);
    }
    $_SESSION['alertMsg'] = _("System Config values updated successfully");
    header("Location:index.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
