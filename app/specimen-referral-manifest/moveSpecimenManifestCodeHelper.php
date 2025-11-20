<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (isset($_POST['testType']) && $_POST['testType'] == "") {
    $_POST['testType'] = "generic-tests";
}
$table = TestsService::getTestTableName($_POST['testType']);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
try {

    $db->beginTransaction();

    if (isset($_POST['assignLab']) && trim((string) $_POST['assignLab']) !== "" && !empty($_POST['packageCode'])) {


        $lastId = $_POST['packageId'];

        $db->where('manifest_code', $packageCode);
        $previousData = $db->getOne("specimen_manifests");
        $oldReason = json_decode((string) $previousData['manifest_change_history']);

        $newReason = ['reason' => $_POST['reasonForChange'], 'changedBy' => $_SESSION['userId'], 'date' => DateUtility::getCurrentDateTime()];
        $oldReason[] = $newReason;

        $value = [
            'lab_id' => $_POST['assignLab'],
            'referring_lab_id' => $_POST['testingLab'],
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            'samples_referred_datetime' => DateUtility::getCurrentDateTime(),
            'data_sync' => 0
        ];
        /* Update Package details table */
        $db->where('manifest_code IN(' . implode(",", $_POST['packageCode']) . ')');
        $db->update('specimen_manifests', ["lab_id" => $_POST['assignLab'], "manifest_change_history" => json_encode($oldReason)]);

        /* Update test types */
        $db->where('sample_package_code IN(' . implode(",", $_POST['packageCode']) . ')');
        $db->update($table, $value);

        $_SESSION['alertMsg'] = "Manifest code(s) moved successfully";
    }

    //Add event log
    $eventType = 'move-manifest';
    $action = $_SESSION['userName'] . ' moved Sample Manifest(s) ' . implode(", ", $_POST['packageCode']) . ' from ' . $facilitiesService->getFacilityName($_POST['testingLab']) . ' to ' . $facilitiesService->getFacilityName($_POST['assignLab']);
    $resource = 'specimen-manifest';

    $general->activityLog($eventType, $action, $resource);


    $db->commitTransaction();
    header("Location:view-manifests.php?t=" . ($_POST['testType']));
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
