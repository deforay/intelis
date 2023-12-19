<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());



if (isset($_POST['testType']) && $_POST['testType'] == "") {
    $_POST['testType'] = "generic-tests";
}

$table = TestsService::getTestTableName($_POST['testType'] ?? 'vl');

//echo $table; die;
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
try {
    if (isset($_POST['assignLab']) && trim((string) $_POST['assignLab']) != "" && !empty($_POST['packageCode'])) {
        $value = array(
            'lab_id'                    => $_POST['assignLab'],
            'referring_lab_id'          => $_POST['testingLab'],
            'last_modified_datetime'    => DateUtility::getCurrentDateTime(),
            'samples_referred_datetime' => DateUtility::getCurrentDateTime(),
            'data_sync'                 => 0
        );
        /* Update Package details table */
        $db->where('package_code IN(' . implode(",", $_POST['packageCode']) . ')');
        $db->update('package_details', array("lab_id" => $_POST['assignLab']));

        /* Update test types */
        $db->where('sample_package_code IN(' . implode(",", $_POST['packageCode']) . ')');
        $db->update($table, $value);

        $_SESSION['alertMsg'] = "Manifest code(s) moved successfully";
    }

    //Add event log
    $eventType = 'move-manifest';
    $action = $_SESSION['userName'] . ' moved Sample Manifest ' . $_POST['packageCode'] . ' to lab ' . $_POST['assignLab'] . ' from lab ' . $_POST['testingLab'];
    $resource = 'specimen-manifest';

    $general->activityLog($eventType, $action, $resource);

    header("Location:view-manifests.php?t=" . ($_POST['testType']));
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
