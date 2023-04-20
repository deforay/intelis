<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

  

$tableName = "other_config";
try {
    foreach ($_POST as $fieldName => $fieldValue) {
        if (trim($fieldName) != '') {
            if ($fieldName == 'rs_field') {
                if (count($fieldValue) > 0) {
                    $fieldValue = implode(',', $fieldValue);
                } else {
                    $fieldValue = '';
                }
            }
            $data = array('value' => $fieldValue);
            $db = $db->where('name', $fieldName);
            $db->update($tableName, $data);
        }
    }
    $_SESSION['alertMsg'] = _("Test Result Email Config values updated successfully.");
    header("Location:testResultEmailConfig.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
