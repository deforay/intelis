<?php

// app/specimen-referral-manifest/_update-referral-lab.php
//
// Moves referred samples to a different receiving lab. Shared by the TB and
// Custom Tests referral lists; the including page sets $referralTestType.

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

use const SAMPLE_STATUS\CANCELLED;

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
    $newReferralLabId = (int) ($_POST['newReferralLabId'] ?? 0);
    $sampleIds = $_POST['sampleIds'] ?? [];
    // Same scope as the referral list: a lab sees, and moves, only the referrals it sent.
    $ownLabId = (int) ($_SESSION['labId'] ?? 0);

    if ($newReferralLabId <= 0) {
        $response['message'] = _translate("Please select a referral lab");
    } elseif (!is_array($sampleIds) || $sampleIds === []) {
        $response['message'] = _translate("Please select at least one sample");
    } elseif ($ownLabId > 0 && $newReferralLabId === $ownLabId) {
        $response['message'] = _translate("A sample cannot be referred to the lab that is sending it");
    } else {
        $table = TestsService::getTestTableName($referralTestType);
        $primaryKeyColumn = TestsService::getPrimaryColumn($referralTestType);
        $currentDateTime = DateUtility::getCurrentDateTime();

        $updateCount = 0;
        $errorCount = 0;

        foreach ($sampleIds as $sampleId) {
            $sampleId = (int) $sampleId;
            if ($sampleId <= 0) {
                $errorCount++;
                continue;
            }

            $db->where($primaryKeyColumn, $sampleId);
            $db->where('referred_to_lab_id', null, 'IS NOT');
            $db->where('result_status', CANCELLED, '!=');
            if ($ownLabId > 0) {
                $db->where('referred_by_lab_id', $ownLabId);
            }
            $update = $db->update($table, [
                'referred_to_lab_id' => $newReferralLabId,
                'reason_for_referral' => $_POST['reasonForReferralLabChange'] ?? null,
                'last_modified_by' => $_SESSION['userId'] ?? null,
                'last_modified_datetime' => $currentDateTime,
                // The STS routes the sample to the new lab only once it has the change.
                'data_sync' => 0,
            ]);

            // update() reports whether the statement ran, not whether a row matched.
            if ($update && $db->count > 0) {
                $updateCount++;
                $general->activityLog(
                    'Updated referral lab',
                    ($_SESSION['userName'] ?? '') . ' updated referral lab for sample ID: ' . $sampleId,
                    "$referralTestType-results-referral-lab-update"
                );
            } else {
                $errorCount++;
            }
        }

        if ($updateCount > 0) {
            $response['status'] = 'success';
            $response['message'] = _translate("Successfully updated") . " $updateCount "
                . _translate("sample(s)");
            if ($errorCount > 0) {
                $response['message'] .= ". " . _translate("Failed to update") . " $errorCount "
                    . _translate("sample(s)");
            }
        } else {
            $response['message'] = _translate("Failed to update samples. Please try again.");
        }
    }
} catch (Throwable $e) {
    LoggerUtility::logError("Update referral lab error: " . $e->getMessage(), [
        'test_type' => $referralTestType,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'trace' => $e->getTraceAsString(),
    ]);
    $response['message'] = _translate("An error occurred while processing the update. Please try again.");
}

echo JsonUtility::encodeUtf8Json($response);
