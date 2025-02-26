<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */

$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

if (empty($_POST['testingLab']) || 0 == (int) $_POST['testingLab']) {
    $_SESSION['alertMsg'] = _translate("Please select the Testing lab", true);;
    header("Location:/specimen-referral-manifest/add-manifest.php?t=" . ($_POST['module']));
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = TestsService::getTestTableName($_POST['module']);
$primaryKey = TestsService::getTestPrimaryKeyColumn($_POST['module']);

try {
    $db->beginTransaction();
    $selectedSample = MiscUtility::desqid($_POST['selectedSample'], returnArray: true);
    $uniqueSampleId = array_unique($selectedSample);
    $numberOfSamples = count($selectedSample);
    if (isset($_POST['packageCode']) && trim((string) $_POST['packageCode']) != "") {
        $data = [
            'package_code'              => $_POST['packageCode'],
            'module'                    => $_POST['module'],
            'added_by'                  => $_SESSION['userId'],
            'lab_id'                    => $_POST['testingLab'],
            'number_of_samples'         => $numberOfSamples,
            'package_status'            => 'pending',
            'request_created_datetime'  => DateUtility::getCurrentDateTime(),
            'last_modified_datetime'    => DateUtility::getCurrentDateTime()
        ];

        $db->insert('package_details', $data);
        $lastId = $db->getInsertId();
        if ($lastId > 0) {
                $dataToUpdate = [
                    'sample_package_id' => $lastId,
                    'sample_package_code' => $_POST['packageCode'],
                    'lab_id'    => $_POST['testingLab'],
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'data_sync' => 0
                ];

                $formAttributes['manifest'] = [
                    "number_of_samples" => $numberOfSamples,
                ];

                $formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');
                $dataToUpdate['form_attributes'] = $db->func($formAttributes);

                //$db->where($primaryKey, $uniqueSampleId[$j]);
                $db->where($primaryKey, $selectedSample, 'IN');
                $db->update($tableName, $dataToUpdate);

            $_SESSION['alertMsg'] = "Manifest added successfully";
        }
    }
    //Add event log
    $eventType = 'add-manifest';
    $action = $_SESSION['userName'] . ' added Manifest - ' . $_POST['packageCode'];
    $resource = 'specimen-manifest';

    $general->activityLog($eventType, $action, $resource);
    $db->commitTransaction();
    header("Location:view-manifests.php?t=" . ($_POST['module']));
} catch (Exception $exc) {
    $db->rollbackTransaction();
    LoggerUtility::log('error', $exc->getMessage(),[
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError()

    ]);
}
