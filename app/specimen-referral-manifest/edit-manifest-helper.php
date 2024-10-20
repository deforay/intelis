<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
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

if (empty($_POST['testingLab']) || 0 === (int) $_POST['testingLab']) {
    $_SESSION['alertMsg'] = _translate("Please select the Testing lab", true);;
    header("Location:/specimen-referral-manifest/edit-manifest.php?t=" . ($_POST['module']));
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = TestsService::getTestTableName($_POST['module']);
$primaryKey = TestsService::getTestPrimaryKeyColumn($_POST['module']);

$packageTable = "package_details";
try {
    $selectedSample = MiscUtility::desqid($_POST['selectedSample']);
    $uniqueSampleId = array_unique($selectedSample);
    if (isset($_POST['packageCode']) && trim((string) $_POST['packageCode']) != "" && !empty($selectedSample)) {

        $db->where('sample_package_code', $_POST['packageCode']);
        $db->update($tableName, [
            'sample_package_id'   => null,
            'sample_package_code' => null
        ]);

        $lastId = $_POST['packageId'];
        $db->where('package_id', $lastId);
        $db->update($packageTable, array(
            'lab_id' => $_POST['testingLab'],
            'number_of_samples' => count($selectedSample),
            'package_status' => $_POST['packageStatus'],
            'last_modified_datetime' => DateUtility::getCurrentDateTime()
        ));

        if ($lastId > 0) {
            for ($j = 0; $j < count($selectedSample); $j++) {
                $dataToUpdate = [
                    'sample_package_id'   => $lastId,
                    'sample_package_code' => $_POST['packageCode'],
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'data_sync' => 0
                ];
                $db->where($primaryKey, $uniqueSampleId[$j]);
                $db->update($tableName, $dataToUpdate);
            }

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

    header("Location:view-manifests.php?t=" . ($_POST['module']));
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
