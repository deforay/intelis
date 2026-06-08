<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
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

$response = [
    'status' => 'error',
    'message' => _translate("An error occurred")
];

try {
    // Validate required fields
    if (empty($_POST['newReferralLabId'])) {
        $response['message'] = _translate("Please select a referral lab");
        echo JsonUtility::encodeUtf8Json($response);
        exit;
    }

    if (empty($_POST['sampleIds']) || !is_array($_POST['sampleIds'])) {
        $response['message'] = _translate("Please select at least one sample");
        echo JsonUtility::encodeUtf8Json($response);
        exit;
    }

    $testType = $_POST['type'] ?? 'tb';
    $newReferralLabId = $_POST['newReferralLabId'];
    $reasonForReferralLabChange = $_POST['reasonForReferralLabChange'];
    $sampleIds = $_POST['sampleIds'];

    // Get current user information
    $userId = $_SESSION['userId'] ?? null;

    // Get table information
    $table = TestsService::getTestTableName($testType);
    $primaryKeyColumn = TestsService::getPrimaryColumn($testType);

    // Current datetime
    $currentDateTime = DateUtility::getCurrentDateTime();

    $updateCount = 0;
    $errorCount = 0;

    foreach ($sampleIds as $sampleId) {
        $sampleId = (int) $sampleId;

        if ($sampleId <= 0) {
            $errorCount++;
            continue;
        }

        // Update the sample with new referral lab
        $updateData = [
            'referred_to_lab_id' => $newReferralLabId,
            'reason_for_referral' => $reasonForReferralLabChange,
            'last_modified_by' => $userId,
            'last_modified_datetime' => $currentDateTime
        ];

        $db->where($primaryKeyColumn, $sampleId);
        $update = $db->update($table, $updateData);

        if ($update) {
            $updateCount++;

            // Add event log
            $eventType = 'Updated referral lab';
            $action = $_SESSION['userName'] . ' updated referral lab for sample ID: ' . $sampleId;
            $resource = 'tb-results-referral-lab-update';
            $general->activityLog($eventType, $action, $resource);
        } else {
            $errorCount++;
        }
    }

    // Set response
    if ($updateCount > 0) {
        $response['status'] = 'success';
        $response['message'] = _translate("Successfully updated") . " $updateCount " . _translate("sample(s)");
        if ($errorCount > 0) {
            $response['message'] .= ". " . _translate("Failed to update") . " $errorCount " . _translate("sample(s)");
        }
    } else {
        $response['message'] = _translate("Failed to update samples. Please try again.");
    }
} catch (Throwable $e) {
    LoggerUtility::log("error", "Update TB Referral Error: " . $e->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'trace' => $e->getTraceAsString(),
    ]);
    $response['message'] = _translate("An error occurred while processing the update. Please try again.");
}

echo JsonUtility::encodeUtf8Json($response);
exit;
