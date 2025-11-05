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

if (empty($_POST['testingLab']) || 0 === (int) $_POST['testingLab']) {
    $_SESSION['alertMsg'] = _translate("Please select the Testing lab", true);;
    header("Location:/specimen-referral-manifest/edit-manifest.php?t=" . ($_POST['module']));
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = TestsService::getTestTableName($_POST['module']);
$primaryKey = TestsService::getPrimaryColumn($_POST['module']);

$packageTable = "specimen_manifests";
try {
    $db->beginTransaction();
    $selectedSamples = MiscUtility::desqid($_POST['selectedSample'], returnArray: true);
    if (isset($_POST['packageCode']) && trim((string) $_POST['packageCode']) != "" && !empty($selectedSamples)) {

        // clear out existing samples from this manifest first
        $currentDateTime = DateUtility::getCurrentDateTime();

        $dataToUpdate = [];
        $formAttributes['manifest'] = [];
        $formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');
        $dataToUpdate['form_attributes'] = $db->func($formAttributes);
        $dataToUpdate['sample_package_id'] = null;
        $dataToUpdate['sample_package_code'] = null;


        $db->where('sample_package_code', $_POST['packageCode']);
        $db->update($tableName, $dataToUpdate);


        // now let's update the manifest details
        $selectedSamples = array_unique($selectedSamples);


        $manifestHash = $testRequestsService->getManifestHash($selectedSamples, $_POST['module'], $_POST['packageCode']);
        $numberOfSamples = count($selectedSamples);

        $lastId = $_POST['packageId'];

        $db->reset();
        $db->where('manifest_id', $lastId);
        $previousData = $db->getOne($packageTable);

        //echo "<pre>"; print_r($previousData); die;
        $existingChangeReasons = json_decode($previousData['manifest_change_history'], true);
        // echo "<pre>"; print_r($existingChangeReasons); die;


        $existingChangeReasons[] = [
            'reason' => $_POST['reasonForChange'],
            'changedBy' => $_SESSION['userId'],
            'date' => DateUtility::getCurrentDateTime()
        ];

        $pData = [
            'lab_id' => $_POST['testingLab'],
            'number_of_samples' => $numberOfSamples,
            'manifest_status' => $_POST['packageStatus'],
            'manifest_change_history' => json_encode($existingChangeReasons),
            'last_modified_datetime' => $currentDateTime
        ];


        $db->where('manifest_id', $lastId);
        $db->update($packageTable, $pData);

        if ($lastId > 0) {
            //for ($j = 0; $j < count($selectedSamples); $j++) {
            $dataToUpdate = [
                'sample_package_id'   => $lastId,
                'sample_package_code' => $_POST['packageCode'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
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


            // In case some records dont have lab_id in the testing table
            // let us update them to the selected lab
            $dataToUpdate = [
                'lab_id' => $_POST['testingLab'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            $db->where('sample_package_code', $_POST['packageCode']);
            $db->where('lab_id IS NULL OR lab_id = 0');
            $db->update($tableName, $dataToUpdate);

            $_SESSION['alertMsg'] = "Manifest details updated successfully";
        }
    }

    //Add event log
    $eventType = 'edit-manifest';
    $action = $_SESSION['userName'] . ' updated Manifest - ' . $_POST['packageCode'];
    $resource = 'specimen-manifest';

    $general->activityLog($eventType, $action, $resource);
    $db->commitTransaction();
    header("Location:view-manifests.php?t=" . ($_POST['module']));
} catch (Throwable $e) {
    $db->rollbackTransaction();
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError()
    ]);
}
