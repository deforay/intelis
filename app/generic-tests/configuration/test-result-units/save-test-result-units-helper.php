<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$tableName = "r_generic_test_result_units";

$unitId = (int) base64_decode((string) $_POST['unitId']);
try {
    if (!empty($_POST['unitName'])) {

        $data = ['unit_name' => trim((string) $_POST['unitName']), 'unit_status' => $_POST['status'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($unitId !== 0) {
            $db->where('unit_id', $unitId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Result Unit Details updated successfully");
                $general->activityLog('Test Result Units', $_SESSION['userName'] . ' updated new test result Unit for ' . $_POST['unitName'], 'generic-test-result-units');
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Result Unit details added successfully");
                $general->activityLog('Test Result Units', $_SESSION['userName'] . ' added new test result unit for ' . $_POST['unitName'], 'generic-test-result-units');
            }
        }
    }
    header("location:generic-test-result-units.php");
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
