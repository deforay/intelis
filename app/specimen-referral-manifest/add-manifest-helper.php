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
use App\Services\TestRequestsService;

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

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = TestsService::getTestTableName($_POST['module']);
$primaryKey = TestsService::getPrimaryColumn($_POST['module']);

try {
    $db->beginTransaction();
    $selectedSamples = MiscUtility::desqid($_POST['selectedSample'], returnArray: true);
    $selectedSamples = array_unique($selectedSamples);

    $manifestHash = $testRequestsService->getManifestHash($selectedSamples, $_POST['module'], $_POST['packageCode']);
    $numberOfSamples = count($selectedSamples);
    if (isset($_POST['packageCode']) && trim((string) $_POST['packageCode']) != "") {
        $currentDateTime = DateUtility::getCurrentDateTime();
        $data = [
            'package_code'              => $_POST['packageCode'],
            'module'                    => $_POST['module'],
            'added_by'                  => $_SESSION['userId'],
            'lab_id'                    => $_POST['testingLab'],
            'number_of_samples'         => $numberOfSamples,
            'package_status'            => 'pending',
            'request_created_datetime'  => $currentDateTime,
            'last_modified_datetime'    => $currentDateTime
        ];

        $db->insert('specimen_manifests', $data);
        $lastId = $db->getInsertId();
        if ($lastId > 0) {
            $dataToUpdate = [
                'sample_package_id' => $lastId,
                'sample_package_code' => $_POST['packageCode'],
                'lab_id'    => $_POST['testingLab'],
                'last_modified_datetime' => $currentDateTime,
                'data_sync' => 0
            ];

            $formAttributes = [
                'manifest' => [
                    "number_of_samples" => $numberOfSamples,
                    'last_modified_datetime' => $currentDateTime
                ],
            ];

            $formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');
            $dataToUpdate['form_attributes'] = $db->func($formAttributes);

            $db->where($primaryKey, $selectedSamples, 'IN');
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
} catch (Throwable $e) {
    $db->rollbackTransaction();
    LoggerUtility::log('error',  $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError()
    ]);
}
