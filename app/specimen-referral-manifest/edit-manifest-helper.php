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
use App\Services\TestRequestsService;

use const SAMPLE_STATUS\CANCELLED;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

$module = (string) ($_POST['module'] ?? '');
$manifestId = (int) ($_POST['packageId'] ?? 0);
$testingLab = (int) ($_POST['testingLab'] ?? 0);
$listUrl = "/specimen-referral-manifest/view-manifests.php?t=" . urlencode($module);
$editUrl = "/specimen-referral-manifest/edit-manifest.php?t=" . urlencode($module)
    . "&id=" . base64_encode((string) $manifestId);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

if ($testingLab <= 0) {
    $_SESSION['alertMsg'] = _translate("Please select the Testing lab", true);
    MiscUtility::redirect($editUrl);
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = TestsService::getTestTableName($module);
$primaryKey = TestsService::getPrimaryColumn($module);

$packageTable = "specimen_manifests";

// Decided inside the transaction, acted on after it: a redirect is an exit, and
// one thrown inside the try would be caught below as a failure.
$refusal = null;
try {
    $db->beginTransaction();

    // Locked, so the lab receiving the package cannot slip in between the check
    // and the save.
    $manifest = $db->rawQueryOne("SELECT * FROM $packageTable WHERE manifest_id = ? FOR UPDATE", [$manifestId]);
    $selectedSamples = array_values(array_unique(array_map(
        'intval',
        MiscUtility::desqid((string) ($_POST['selectedSample'] ?? ''), returnArray: true)
    )));
    $previousLab = (int) ($manifest['lab_id'] ?? 0);

    $labs = $facilitiesService->getTestingLabs($module, alwaysIncludeLabId: $previousLab) ?: [];

    if (empty($manifest) || (!empty($manifest['module']) && strcasecmp((string) $manifest['module'], $module) !== 0)) {
        $refusal = [_translate("This manifest could not be found"), $listUrl];
    } elseif ($manifest['manifest_status'] === TestRequestsService::MANIFEST_RECEIVED) {
        // The page refuses too, but only the page; this is where it holds.
        $refusal = [
            _translate("This manifest has been received at the testing lab and can no longer be changed"),
            $listUrl,
        ];
    } elseif (!array_key_exists($testingLab, $labs)) {
        $refusal = [_translate("Please select the Testing lab", true), $editUrl];
    } elseif ($selectedSamples === []) {
        $refusal = [_translate("Please select one or more samples", true), $editUrl];
    } else {
        $manifestCode = (string) $manifest['manifest_code'];
        $currentDateTime = DateUtility::getCurrentDateTime();

        // A manifest holds the samples of its one testing lab. Editing it never
        // moves a sample to another lab (that is Move Manifest): a sample from any
        // other lab, one already on a different manifest, or a cancelled one is
        // simply not taken.
        $db->reset();
        $db->where($primaryKey, $selectedSamples, 'IN');
        $db->where('lab_id', $testingLab);
        $db->where('result_status', CANCELLED, '!=');
        $db->where('(sample_package_id IS NULL OR sample_package_id = 0 OR sample_package_id = ?)', [$manifestId]);
        $db->update($tableName, [
            'sample_package_id' => $manifestId,
            'sample_package_code' => $manifestCode,
            'last_modified_datetime' => $currentDateTime,
            'data_sync' => 0,
        ]);

        // Same conditions again: a sample of the old lab is already on this
        // manifest, and is not kept just because it was selected.
        $db->reset();
        $db->where('sample_package_id', $manifestId);
        $db->where($primaryKey, $selectedSamples, 'IN');
        $db->where('lab_id', $testingLab);
        $db->where('result_status', CANCELLED, '!=');
        $keptSamples = array_map('intval', $db->getValue($tableName, $primaryKey, null) ?: []);

        if ($keptSamples === []) {
            $refusal = [_translate("None of the selected samples belong to the chosen testing lab"), $editUrl];
        } else {
            $numberOfSamples = count($keptSamples);

            // Everything else on this manifest comes off it: samples left out of
            // the selection, and, after a lab change, every sample of the old lab.
            // Re-sent, so the STS stops listing them under this manifest.
            $removed = JsonUtility::jsonToSetString(json_encode(['manifest' => []]), 'form_attributes');
            $db->reset();
            $db->where('(sample_package_id = ? OR sample_package_code = ?)', [$manifestId, $manifestCode]);
            $db->where($primaryKey, $keptSamples, 'NOT IN');
            $db->update($tableName, [
                'sample_package_id' => null,
                'sample_package_code' => null,
                'form_attributes' => $db->func($removed),
                'last_modified_datetime' => $currentDateTime,
                'data_sync' => 0,
            ]);

            $kept = JsonUtility::jsonToSetString(json_encode([
                'manifest' => [
                    'number_of_samples' => $numberOfSamples,
                    'last_modified_datetime' => $currentDateTime,
                ],
            ]), 'form_attributes');
            $db->reset();
            $db->where($primaryKey, $keptSamples, 'IN');
            $db->update($tableName, ['form_attributes' => $db->func($kept)]);

            $change = [
                'reason' => $_POST['reasonForChange'] ?? null,
                'changedBy' => $_SESSION['userId'],
                'date' => $currentDateTime,
            ];
            if ($previousLab !== $testingLab) {
                $change['previousLabId'] = $previousLab;
                $change['labId'] = $testingLab;
            }
            $history = json_decode((string) $manifest['manifest_change_history'], true);
            $history = is_array($history) ? $history : [];
            $history[] = $change;

            $db->reset();
            $db->where('manifest_id', $manifestId);
            $db->update($packageTable, [
                'lab_id' => $testingLab,
                'number_of_samples' => $numberOfSamples,
                // manifest_status is not taken from the form: printing sets it to
                // dispatched and activation to received, and a save must not undo them.
                'manifest_change_history' => json_encode($history),
                'last_modified_datetime' => $currentDateTime,
            ]);

            $action = $_SESSION['userName'] . ' updated Manifest - ' . $manifestCode;
            if ($previousLab !== $testingLab) {
                $action .= " (testing lab $previousLab -> $testingLab)";
            }
            $general->activityLog('edit-manifest', $action, 'specimen-manifest');
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
    [$_SESSION['alertMsg'], $url] = $refusal;
    MiscUtility::redirect($url);
}
$_SESSION['alertMsg'] = _translate("Manifest details updated successfully");
MiscUtility::redirect($listUrl);
