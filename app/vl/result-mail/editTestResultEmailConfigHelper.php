<?php

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\SecurityService;





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
    MiscUtility::redirect("/vl/result-mail/testResultEmailConfig.php");
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage());
}
