<?php

// Moves one or more VL results that are currently flagged Failed/Invalid back to
// Accepted. Used from the Failed/Hold Samples screen to recover valid results
// (e.g. "Target Not Detected", "< 40", numeric copy numbers) that were wrongly
// marked failed. A genuine failure (result text Failed/Error/Invalid) is refused
// and left as-is, so this can never accept a real instrument failure.

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\TEST_FAILED;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

try {
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);

    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    // Same edit privilege that gates the action on the listing page. AJAX
    // endpoints bypass the page ACL, so we re-check here.
    if (!_isAllowed("/vl/requests/editVlRequest.php")) {
        echo "0";
        exit;
    }

    // Resolve the target vl_sample_ids. Single action sends a base64 id; the
    // bulk action sends a raw numeric array (mirrors failed-results-retest.php).
    $bulk = !empty($_POST['bulkIds']) && $_POST['bulkIds'] !== 'false';
    $ids = [];
    if ($bulk && is_array($_POST['vlId'] ?? null)) {
        foreach ($_POST['vlId'] as $vlId) {
            if (is_numeric($vlId)) {
                $ids[] = (int) $vlId;
            }
        }
    } elseif (!empty($_POST['vlId'])) {
        $decoded = base64_decode((string) $_POST['vlId']);
        if (is_numeric($decoded)) {
            $ids[] = (int) $decoded;
        }
    }

    $updated = 0;

    if ($ids !== []) {
        $now = DateUtility::getCurrentDateTime();
        $userId = $_SESSION['userId'] ?? null;
        $userName = $_SESSION['userName'] ?? 'System';

        foreach ($ids as $vlSampleId) {
            $db->where('vl_sample_id', $vlSampleId);
            $row = $db->getOne('form_vl', 'vl_sample_id, sample_code, remote_sample_code, patient_art_no, result, result_status, result_reviewed_by, result_approved_by');

            if (empty($row)) {
                continue;
            }

            // Only act on rows that are currently failed and actually carry a
            // result to recover. Hold/Lost/empty rows are left untouched.
            if ((int) $row['result_status'] !== TEST_FAILED || trim((string) $row['result']) === '') {
                continue;
            }

            // Re-interpret the stored result as if accepted. If the result text
            // is itself a genuine failure, the category comes back failed/invalid
            // and we refuse to accept it.
            $category = $vlService->getVLResultCategory(ACCEPTED, $row['result']);
            if (in_array($category, ['failed', 'invalid', 'rejected'], true)) {
                continue;
            }

            $update = [
                'result_status' => ACCEPTED,
                'vl_result_category' => $category,
                'is_sample_rejected' => 'no',
                'reason_for_sample_rejection' => null,
                'last_modified_by' => $userId,
                'last_modified_datetime' => $now,
                'data_sync' => 0,
            ];
            // Stamp reviewer/approver only if the result was never signed off,
            // so we don't overwrite the original lab tech / approver.
            if (empty($row['result_reviewed_by'])) {
                $update['result_reviewed_by'] = $userId;
            }
            if (empty($row['result_approved_by'])) {
                $update['result_approved_by'] = $userId;
                $update['result_approved_datetime'] = $now;
            }

            $db->where('vl_sample_id', $vlSampleId);
            $ok = $db->update('form_vl', $update);

            if ($ok) {
                $updated++;

                // Detailed, per-sample activity log capturing the transition.
                $sampleLabel = $general->isSTSInstance() && !empty($row['remote_sample_code'])
                    ? $row['remote_sample_code']
                    : $row['sample_code'];
                $artLabel = !empty($row['patient_art_no'])
                    ? ' (' . _translate('Patient ID') . ' ' . $row['patient_art_no'] . ')'
                    : '';
                $action = $userName . ' '
                    . _translate('changed VL sample status from Failed/Invalid to Accepted for') . ' '
                    . _translate('Sample ID') . ' ' . $sampleLabel . $artLabel
                    . '. ' . _translate('Result') . ': ' . $row['result'];
                $general->activityLog('accept-failed-result', $action, 'vl-results');
            }
        }
    }

    echo $updated;
} catch (Throwable $e) {
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}
