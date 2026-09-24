<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

use const SAMPLE_STATUS\CANCELLED;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

$module = (string) ($_POST['module'] ?? '');
$testingLab = (int) ($_POST['testingLab'] ?? 0);
$manifestCode = trim((string) ($_POST['packageCode'] ?? ''));
$addUrl = "/specimen-referral-manifest/add-manifest.php?t=" . urlencode($module);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

if ($testingLab <= 0 || !array_key_exists($testingLab, $facilitiesService->getTestingLabs($module) ?: [])) {
    $_SESSION['alertMsg'] = _translate("Please select the Testing lab", true);
    MiscUtility::redirect($addUrl);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = TestsService::getTestTableName($module);
$primaryKey = TestsService::getPrimaryColumn($module);

// Decided inside the transaction, acted on after it: a redirect is an exit, and
// one thrown inside the try would be caught below as a failure.
$refusal = null;
try {
    $db->beginTransaction();
    $selectedSamples = array_values(array_unique(array_map(
        'intval',
        MiscUtility::desqid((string) ($_POST['selectedSample'] ?? ''), returnArray: true)
    )));

    if ($manifestCode === '') {
        $refusal = _translate("Please enter manifest code");
    } elseif ($selectedSamples === []) {
        $refusal = _translate("Please select one or more samples", true);
    } else {
        $currentDateTime = DateUtility::getCurrentDateTime();
        $db->insert('specimen_manifests', [
            'manifest_code' => $manifestCode,
            'module' => $module,
            'added_by' => $_SESSION['userId'],
            'lab_id' => $testingLab,
            'number_of_samples' => count($selectedSamples),
            'manifest_status' => 'pending',
            'request_created_datetime' => $currentDateTime,
            'last_modified_datetime' => $currentDateTime
        ]);
        $lastId = (int) $db->getInsertId();

        // A manifest holds the samples of its one testing lab. Only that lab's
        // samples, not yet on a manifest and not cancelled, are taken; the rest
        // are left alone rather than moved to this lab.
        $db->reset();
        $db->where($primaryKey, $selectedSamples, 'IN');
        $db->where('lab_id', $testingLab);
        $db->where('result_status', CANCELLED, '!=');
        $db->where('(sample_package_id IS NULL OR sample_package_id = 0)');
        $db->update($tableName, [
            'sample_package_id' => $lastId,
            'sample_package_code' => $manifestCode,
            'last_modified_datetime' => $currentDateTime,
            'data_sync' => 0
        ]);

        $db->reset();
        $db->where('sample_package_id', $lastId);
        $keptSamples = array_map('intval', $db->getValue($tableName, $primaryKey, null) ?: []);

        if ($keptSamples === []) {
            $refusal = _translate("None of the selected samples belong to the chosen testing lab");
        } else {
            $numberOfSamples = count($keptSamples);
            $formAttributes = JsonUtility::jsonToSetString(json_encode([
                'manifest' => [
                    'number_of_samples' => $numberOfSamples,
                    'last_modified_datetime' => $currentDateTime
                ],
            ]), 'form_attributes');
            $db->reset();
            $db->where($primaryKey, $keptSamples, 'IN');
            $db->update($tableName, ['form_attributes' => $db->func($formAttributes)]);

            $db->reset();
            $db->where('manifest_id', $lastId);
            $db->update('specimen_manifests', ['number_of_samples' => $numberOfSamples]);

            $general->activityLog(
                'add-manifest',
                $_SESSION['userName'] . ' added Manifest - ' . $manifestCode,
                'specimen-manifest'
            );
        }
    }

    if ($refusal === null) {
        $db->commitTransaction();
    } else {
        $db->rollbackTransaction();
    }
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

if ($refusal !== null) {
    $_SESSION['alertMsg'] = $refusal;
    MiscUtility::redirect($addUrl);
}
$_SESSION['alertMsg'] = _translate("Manifest added successfully");
MiscUtility::redirect("/specimen-referral-manifest/view-manifests.php?t=" . urlencode($module));
