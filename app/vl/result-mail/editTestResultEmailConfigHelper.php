<?php

use App\Utilities\LoggerUtility;





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
            $db->where('name', $fieldName);
            $db->update($tableName, $data);
        }
    }
    $_SESSION['alertMsg'] = _translate("Test Result Email Config values updated successfully.");
    header("Location:testResultEmailConfig.php");
} catch (Exception $exc) {
    LoggerUtility::log('error', $exc->getMessage());
}
