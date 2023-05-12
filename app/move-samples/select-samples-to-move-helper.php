<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$testType = $_POST['testTypeId'];
$tableName = "move_samples";
$tableName2    = "move_samples_map";

$testTableName = "";
$primaryKey = "";
if ($testType == "vl") {
    $testTableName = "form_vl";
    $primaryKey = "vl_sample_id";
} else if ($testType == "eid") {
    $testTableName = "form_eid";
    $primaryKey = "eid_id";
} else if ($testType == "covid19") {
    $testTableName = "form_covid19";
    $primaryKey = "covid19_id";
} else if ($testType == "hepatitis") {
    $testTableName = "form_hepatitis";
    $primaryKey = "hepatitis_id";
}
try {
    $data = array(
        'moved_from_lab_id' => $_POST['labId'],
        'moved_to_lab_id'   => $_POST['labNameTo'],
        'moved_on'          => date('Y-m-d'),
        'moved_by'          => $_SESSION['userId'],
        'reason_for_moving' => $_POST['reasonForMoving'],
        'move_approved_by'  => $_POST['approveBy'],
        'test_type'         => $testType
    );
    $id = $db->insert($tableName, $data);


    if ($id > 0 && !empty($_POST['sampleCode'])) {
        if ($tableName != "") {
            $mainData = array(
                "lab_id"                    => $_POST['labNameTo'],
                "referring_lab_id"          => $_POST['labNameTo'],
                "data_sync"                 => 0,
                "samples_referred_datetime" => DateUtility::getCurrentDateTime(),
                "last_modified_datetime"    => DateUtility::getCurrentDateTime()
            );
            $db->where($primaryKey . " IN (" . implode(",", $_POST['sampleCode']) . ")");
            $db->where("lab_id", $_POST['labId']);
            $db->update($testTableName, $mainData);
        }
        $c = count($_POST['sampleCode']);
        for ($j = 0; $j <= $c; $j++) {
            if (isset($_POST['sampleCode'][$j]) && $_POST['sampleCode'][$j] != '') {
                $data = array(
                    'move_sample_id'        => $id,
                    'test_type_sample_id'   => $_POST['sampleCode'][$j],
                    'test_type'             => $testType,
                    'move_sync_status'      => 0
                );
                $db->insert($tableName2, $data);
            }
        }
        $_SESSION['alertMsg'] = "Sample List added!";
    } else {
        $_SESSION['alertMsg'] = "Something went wrong!";
    }
    header("Location:move-samples.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
