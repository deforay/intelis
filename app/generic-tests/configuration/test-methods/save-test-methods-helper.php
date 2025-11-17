<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_generic_test_methods";

$testMethodId = (int) base64_decode((string) $_POST['testMethodId']);
$_POST['testMethod'] = trim((string) $_POST['testMethod']);
try {
    if (isset($_POST['testMethod']) && ($_POST['testMethod'] !== '' && $_POST['testMethod'] !== '0')) {

        $data = ['test_method_name' => $_POST['testMethod'], 'test_method_status' => $_POST['testMethodStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($testMethodId !== 0) {
            $db->where('test_method_id', $testMethodId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Method updated successfully");
                $general->activityLog('Test Method', $_SESSION['userName'] . ' updated new Test Method for ' . $_POST['testMethod'], 'generic-test-methods');
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Method added successfully");
                $general->activityLog('Test Method', $_SESSION['userName'] . ' added new Test Method for ' . $_POST['testMethod'], 'generic-test-methods');
            }
        }
    }
    //error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("location:generic-test-methods.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
