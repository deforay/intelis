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

    // The picker used to wrap each code in literal single quotes for raw SQL
    // interpolation; those quotes make a bound parameter match nothing, so
    // strip them in case an older cached picker still posts quoted codes.
    $packageCodes = array_map(fn($code) => trim((string) $code, "'"), (array) ($_POST['packageCode'] ?? []));
    $packageCodes = array_values(array_filter($packageCodes, 'strlen'));

    if (isset($_POST['assignLab']) && trim((string) $_POST['assignLab']) !== "" && $packageCodes !== []) {

        $assignLab = (int) $_POST['assignLab'];
        $placeholders = implode(',', array_fill(0, count($packageCodes), '?'));

        // Append this move to each manifest's OWN change history. This used to
        // read one undefined manifest's history and stamp that merged blob onto
        // every selected manifest, with the codes interpolated unquoted into the
        // SQL; the codes are bound now.
        $newReason = ['reason' => $_POST['reasonForChange'], 'changedBy' => $_SESSION['userId'], 'date' => DateUtility::getCurrentDateTime()];
        $manifests = $db->rawQuery(
            "SELECT manifest_code, manifest_change_history FROM specimen_manifests WHERE manifest_code IN ($placeholders)",
            $packageCodes
        );
        foreach ($manifests as $manifest) {
            $history = json_decode((string) $manifest['manifest_change_history']) ?: [];
            $history[] = $newReason;
            $db->reset();
            $db->where('manifest_code', $manifest['manifest_code']);
            $db->update('specimen_manifests', ['lab_id' => $assignLab, 'manifest_change_history' => json_encode($history)]);
        }

        // A sample_code is the TESTING LAB's working id, minted from that lab's
        // own series. Moving a sample used to carry the origin lab's code along,
        // planting a foreign code inside the destination lab's series -- which is
        // what wedged a lab's code generation in the field. Clear it (before the
        // lab flips below, while lab_id still names the origin) so the
        // destination lab mints its own code at activation. The network code
        // (remote_sample_code) is untouched.
        $db->rawQuery(
            "UPDATE $table
                SET sample_code = NULL, sample_code_format = NULL, sample_code_key = NULL
              WHERE sample_package_code IN ($placeholders)
                AND lab_id <> ?
                AND sample_code IS NOT NULL",
            [...$packageCodes, $assignLab]
        );

        $value = [
            'lab_id' => $assignLab,
            'referring_lab_id' => (int) $_POST['testingLab'],
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            'samples_referred_datetime' => DateUtility::getCurrentDateTime(),
            'data_sync' => 0
        ];
        /* Update test types */
        $db->reset();
        $db->where("sample_package_code IN ($placeholders)", $packageCodes);
        $db->update($table, $value);

        $_SESSION['alertMsg'] = "Manifest code(s) moved successfully";
    }

    //Add event log
    $eventType = 'move-manifest';
    $action = $_SESSION['userName'] . ' moved Sample Manifest(s) ' . implode(", ", $packageCodes) . ' from ' . $facilitiesService->getFacilityName($_POST['testingLab']) . ' to ' . $facilitiesService->getFacilityName($_POST['assignLab']);
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
    throw $e;
}
