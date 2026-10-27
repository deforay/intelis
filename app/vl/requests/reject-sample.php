<?php

// Rejects one VL sample from the VL Requests and Enter VL Result grids. Records
// the rejection reason, the rejection date, who approved the rejection and when,
// and optional lab comments. Only a sample with no result yet can be rejected
// here. A sample that carries a result is rejected from its result screen.

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\REJECTED;
use App\Services\VlService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

header('Content-Type: application/json');

try {
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var UsersService $usersService */
    $usersService = ContainerRegistry::get(UsersService::class);

    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

    $vlSampleId = (int) MiscUtility::desqid((string) ($_POST['id'] ?? ''));
    $row = null;
    $reasonId = 0;
    $rejectedOn = $approvedOn = $approvedBy = null;
    // Why the posted rejection cannot be saved, or null when it can
    $db->beginTransaction();
    $refusal = (static function () use (
        $db,
        $general,
        $usersService,
        $vlSampleId,
        &$row,
        &$reasonId,
        &$rejectedOn,
        &$approvedOn,
        &$approvedBy
    ): ?string {
        // Same rule that shows the button. AJAX endpoints bypass the page ACL.
        if (!VlService::canRejectFromListing()) {
            return _translate("You do not have permission to reject samples");
        }

        // Another lab's sample reads as not found. The row stays locked until the
        // write below, so a result saved meanwhile cannot be overwritten.
        $labScope = $general->labScopeWhere('vl');
        if ($vlSampleId > 0) {
            $row = $db->rawQueryOne(
                "SELECT * FROM form_vl AS vl WHERE vl.vl_sample_id = ?"
                    . ($labScope !== '' ? " AND $labScope" : '')
                    . " FOR UPDATE",
                [$vlSampleId]
            );
        }
        if (empty($row)) {
            return _translate("Sample not found");
        }
        $general->assertFacilityAllowed((int) ($row['facility_id'] ?? 0));

        if ((int) $row['result_status'] === REJECTED) {
            return _translate("This sample is already rejected");
        }
        if (trim((string) $row['result']) !== '') {
            return _translate("This sample has a result. Reject it from its result screen.");
        }
        $canEditLocked = _isAllowed("/vl/requests/edit-locked-vl-samples");
        if (!VlService::isRejectableFromListing($row, $general->isLISInstance(), $canEditLocked)) {
            return _translate("This sample cannot be rejected");
        }

        $reasonId = (int) ($_POST['rejectionReason'] ?? 0);
        $reasonExists = $reasonId > 0 && !empty($db->rawQueryOne(
            "SELECT 1 FROM r_vl_sample_rejection_reasons
                WHERE rejection_reason_id = ? AND rejection_reason_status = 'active'",
            [$reasonId]
        ));
        if (!$reasonExists) {
            return _translate("Please choose the rejection reason");
        }

        $rejectedOn = DateUtility::isoDateFormat($_POST['rejectionDate'] ?? '');
        $approvedOn = DateUtility::isoDateFormat($_POST['approvedOnDateTime'] ?? '', true);
        $approvedBy = $_POST['approvedBy'] ?? null;
        if (empty($rejectedOn) || empty($approvedOn) || empty($approvedBy)) {
            return _translate("Please enter the rejection date, approved by and approved on");
        }
        // The same lab-scoped list the dialog offers
        if (!array_key_exists((string) $approvedBy, $usersService->getActiveLabScopedUsers())) {
            return _translate("Please choose who approved the rejection");
        }
        $today = date('Y-m-d');
        if ($rejectedOn > $today || substr($approvedOn, 0, 10) > $today) {
            return _translate("Dates cannot be in the future");
        }
        $collectedOn = substr((string) ($row['sample_collection_date'] ?? ''), 0, 10);
        if ($collectedOn !== '' && $collectedOn !== '0000-00-00' && $rejectedOn < $collectedOn) {
            return _translate("Rejection date cannot be before the sample collection date");
        }
        if (substr($approvedOn, 0, 10) < $rejectedOn) {
            return _translate("Approved on cannot be before the rejection date");
        }

        return null;
    })();

    if ($refusal !== null) {
        $db->rollbackTransaction();
        echo json_encode(['status' => 'error', 'message' => $refusal]);
        return;
    }

    $now = DateUtility::getCurrentDateTime();
    $userId = $_SESSION['userId'] ?? null;
    $data = [
        'is_sample_rejected' => 'yes',
        'reason_for_sample_rejection' => $reasonId,
        'rejection_on' => $rejectedOn,
        'result_status' => REJECTED,
        'vl_result_category' => 'rejected',
        'result_approved_by' => $approvedBy,
        'result_approved_datetime' => $approvedOn,
        // Not rejected before (checked above), so the rejection is a change
        'result_modified' => 'yes',
        'manual_result_entry' => 'yes',
        'last_modified_by' => $userId,
        'last_modified_datetime' => $now,
        'data_sync' => 0,
        // A rejected sample is not tested, so nothing about a test or result stays
        'result' => null,
        'result_value_log' => null,
        'result_value_absolute' => null,
        'result_value_absolute_decimal' => null,
        'result_value_text' => null,
        'result_value_hiv_detection' => null,
        'reason_for_failure' => null,
        'sample_tested_datetime' => null,
        'tested_by' => null,
        'vl_test_platform' => null,
        'instrument_id' => null,
        'result_reviewed_by' => null,
        'result_reviewed_datetime' => null,
        'result_dispatched_datetime' => null,
    ];
    // Optional: a blank box keeps whatever comment the sample already had
    if (!empty($_POST['labComments'])) {
        $data['lab_tech_comments'] = $_POST['labComments'];
    }
    $labId = $general->resolveRequestLabId($row['lab_id'] ?? null, 'form_vl', 'vl_sample_id', $vlSampleId);
    if ($labId !== null) {
        $data['lab_id'] = $labId;
    }

    $db->where('vl_sample_id', $vlSampleId);
    if (!$db->update('form_vl', $data)) {
        $db->rollbackTransaction();
        echo json_encode(['status' => 'error', 'message' => _translate("Please try again later")]);
        return;
    }
    $db->commitTransaction();

    $sampleCode = $row['sample_code'] ?: $row['remote_sample_code'];
    $general->activityLog(
        'reject-vl-sample',
        ($_SESSION['userName'] ?? 'System') . ' rejected VL sample ' . $sampleCode,
        'vl-request'
    );

    echo json_encode([
        'status' => 'success',
        'message' => _translate("Sample rejected") . ' : ' . $sampleCode,
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->isTransactionActive()) {
        $db->rollbackTransaction();
    }
    throw new SystemException($e->getMessage(), 500, $e);
}
