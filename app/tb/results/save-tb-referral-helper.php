<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use const SAMPLE_STATUS\REFERRED;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
    // Validate required fields
    if (empty($_POST['referralToLabId'])) {
        $_SESSION['alertMsg'] = _translate("Please select a referral to lab");
        header("Location: /tb/results/tb-referral.php");
        exit;
    }

    if (empty($_POST['referralSamples']) || !is_array($_POST['referralSamples'])) {
        $_SESSION['alertMsg'] = _translate("Please select at least one sample");
        header("Location: /tb/results/tb-referral.php");
        exit;
    }

    $testType = $_POST['type'] ?? 'tb';
    $referredBy = $_POST['referralLabId'];
    $referralSamples = $_POST['referralSamples'];
    $referredTo = $_POST['referralToLabId'];

    // Get current user and lab information
    $userId = $_SESSION['userId'] ?? null;
    $userFacilityId = $_SESSION['facilityId'] ?? null;

    // Get table information
    $table = TestsService::getTestTableName($testType);
    $primaryKeyColumn = TestsService::getPrimaryColumn($testType);

    // Current datetime
    $currentDateTime = DateUtility::getCurrentDateTime();

    $errorCount = $updateCount = $manifestId = 0;
    $numberOfSamples = count($referralSamples);
    if (isset($_POST['packageCode']) && trim((string) $_POST['packageCode']) !== "") {
        $currentDateTime = DateUtility::getCurrentDateTime();
        $data = [
            'module' => 'tb',
            'lab_id' => $referredTo,
            'number_of_samples' => $numberOfSamples,
            'manifest_type' => 'referral',
            'manifest_status' => 'pending',
            'last_modified_datetime' => $currentDateTime
        ];
        if (isset($_POST['manifestId']) && !empty($_POST['manifestId'])) {
            $manifestId = $_POST['manifestId'];
            $db->where('manifest_id', $manifestId);
            $db->update('specimen_manifests', $data);
        } else {
            $data['manifest_code'] = $_POST['packageCode'];
            $data['added_by'] = $_SESSION['userId'];
            $data['request_created_datetime'] = $currentDateTime;
            $db->insert('specimen_manifests', $data);
            $manifestId = $db->getInsertId();
        }
    }
    foreach ($referralSamples as $sampleId) {
        $sampleId = (int) $sampleId;

        if ($sampleId <= 0) {
            $errorCount++;
            continue;
        }
        // Update the sample with referral information
        $updateData = [
            'referral_manifest_code' => $_POST['packageCode'],
            'data_sync' => 0,
            'result_status' => REFERRED,
            'referred_by_lab_id' => $referredBy,
            'referred_to_lab_id' => $referredTo,
            'reason_for_referral' => $_POST['referralReason'],
            'last_modified_by' => $userId,
            'last_modified_datetime' => $currentDateTime
        ];

        $db->where($primaryKeyColumn, $sampleId);
        $update = $db->update($table, $updateData);

        if ($update) {
            $updateCount++;

            //Add event log
            $eventType = 'Referred sample to lab';
            $action = $_SESSION['userName'] . ' updated Referred sample to lab';
            $resource = 'tb-results-referral-lab';
            $general->activityLog($eventType, $action, $resource);
        } else {
            $errorCount++;
        }
    }

    // Set success message
    if ($updateCount > 0) {
        $_SESSION['alertMsg'] = _translate("Successfully referred") . " $updateCount " . _translate("sample(s) to the lab");
        if ($errorCount > 0) {
            $_SESSION['alertMsg'] .= ". " . _translate("Failed to refer") . " $errorCount " . _translate("sample(s)");
        }
    } else {
        $_SESSION['alertMsg'] = _translate("Failed to refer samples. Please try again.");
    }
} catch (Throwable $e) {

    LoggerUtility::log("error", "TB Referral Error: " . $e->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'trace' => $e->getTraceAsString(),
    ]);
    $_SESSION['alertMsg'] = _translate("An error occurred while processing the referral. Please try again.");
}

// Redirect back to the form
header("Location: /tb/results/tb-referral-list.php");
exit;
