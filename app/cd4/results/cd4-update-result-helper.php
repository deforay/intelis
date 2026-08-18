<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use App\Services\CD4Service;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\TestAttemptService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Cd4Service $cd4Service */
$cd4Service = ContainerRegistry::get(CD4Service::class);


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "form_cd4";
$tableName2 = "log_result_updates";

try {
    // Acting as a LIS: a result can only be recorded for this install's own lab.
    // A sample already saved against another lab (referred in) keeps that lab, so
    // this never reassigns someone else's sample. No-op on STS and standalone.
    $_POST['labId'] = $general->resolveRequestLabId($_POST['labId'] ?? null, $tableName, 'cd4_id', $_POST['cd4SampleId'] ?? null);

    $db->where('cd4_id', $_POST['cd4SampleId'] ?? 0);
    $sampleFacilityId = (int) ($db->getValue($tableName, 'facility_id') ?? 0);
    $general->assertFacilityAllowed($sampleFacilityId);

    $instanceId = $general->getInstanceId();
    $testingPlatform = null;
    $instrumentId = null;
    if (isset($_POST['testingPlatform']) && trim((string) $_POST['testingPlatform']) !== '') {
        $platForm = explode("##", (string) $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
        $instrumentId = $platForm[3] ?? null;
    }

    $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true);
    $_POST['sampleReceivedAtHubOn'] = DateUtility::isoDateFormat($_POST['sampleReceivedAtHubOn'] ?? '', true);
    $_POST['approvedOnDateTime'] = DateUtility::isoDateFormat($_POST['approvedOnDateTime'] ?? '', true);
    $_POST['sampleTestingDateAtLab'] = DateUtility::isoDateFormat($_POST['sampleTestingDateAtLab'] ?? '', true);
    $_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($_POST['resultDispatchedOn'] ?? '', true);
    $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);

    // PNG SPECIFIC
    $_POST['failedTestDate'] = DateUtility::isoDateFormat($_POST['failedTestDate'] ?? '', true);
    $_POST['qcDate'] = DateUtility::isoDateFormat($_POST['qcDate'] ?? '');
    $_POST['reportDate'] = DateUtility::isoDateFormat($_POST['reportDate'] ?? '');
    $_POST['clinicDate'] = DateUtility::isoDateFormat($_POST['clinicDate'] ?? '');
    // DRC SPECIFIC
    $_POST['dateOfCompletionOfViralLoad'] = DateUtility::isoDateFormat($_POST['dateOfCompletionOfViralLoad'] ?? '', true);


    if (!empty($_POST['newRejectionReason'])) {
        $rejectionReasonQuery = "SELECT rejection_reason_id
                    FROM r_cd4_sample_rejection_reasons
                    WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = [
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime()
            ];
            $id = $db->insert('r_cd4_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $db->getInsertId();
        } else {
            $_POST['rejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
    }



    // The result-change reason is appended (preserving prior history) further below, after the
    // previous row is fetched -- see appendResultChangeReason near the result_modified check.

    if ($_POST['failedTestingTech'] != '') {
        $platForm = explode("##", (string) $_POST['failedTestingTech']);
        $_POST['failedTestingTech'] = $platForm[0];
    }


    $vlData = [
        'vlsm_instance_id' => $instanceId,
        'cd4_result' => $_POST['cd4Result'] ?? null,
        'cd4_result_percentage' => $_POST['cd4ResultPercentage'] ?? null,
        //'lab_id' => $_POST['labId'] ?? null,
        'cd4_test_platform' => $testingPlatform ?? null,
        'instrument_id' => $instrumentId ?? null,
        'sample_received_at_hub_datetime' => DateUtility::isoDateFormat($_POST['sampleReceivedAtHubOn'] ?? '', true),
        'sample_received_at_lab_datetime' => DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true),
        'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['sampleTestingDateAtLab'] ?? '', true),
        'result_dispatched_datetime' => $_POST['resultDispatchedOn'],
        'is_sample_rejected' => $_POST['isSampleRejected'] ?? null,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && trim((string) $_POST['rejectionReason']) !== '') ? $_POST['rejectionReason'] : null,
        'rejection_on' => DateUtility::isoDateFormat($_POST['rejectionDate'] ?? ''),
        'result_reviewed_by' => $_POST['reviewedBy'] ?? null,
        'result_reviewed_datetime' => DateUtility::isoDateFormat($_POST['reviewedOn'] ?? ''),
        'tested_by' => $_POST['testedBy'] ?? null,
        'result_approved_by' => $_POST['approvedBy'] ?? null,
        'result_approved_datetime' => DateUtility::isoDateFormat($_POST['approvedOnDateTime'] ?? '', true),
        'date_test_ordered_by_physician' => DateUtility::isoDateFormat($_POST['dateOfDemand'] ?? ''),
        'lab_tech_comments' => $_POST['labComments'] ?? null,
        'result_status' => PENDING_APPROVAL,
        'request_created_datetime' => DateUtility::getCurrentDateTime(),
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'result_modified' => 'no',
        'manual_result_entry' => 'yes',
    ];

    // The result form asks "Is sample re-ordered as part of corrective action?" and marks it
    // required on STS instances, but nothing here ever saved the answer, so it was discarded
    // on every save. Read under the same name the add/edit request helpers use.
    //
    // Only written when the field was actually submitted, so a country form that doesn't
    // render it cannot null out the stored value.
    if (array_key_exists('isSampleReordered', $_POST)) {
        $vlData['sample_reordered'] = $_POST['isSampleReordered'];
    }

    $db->where('cd4_id', $_POST['cd4SampleId']);
    $getPrevResult = $db->getOne('form_cd4');
    // The result counts as modified when the value changed or the sample flipped
    // between rejected and not rejected -- the same rule the change history is logged on.
    $previousState = ['result' => $getPrevResult['cd4_result'], 'result_status' => $getPrevResult['result_status'], 'is_sample_rejected' => $getPrevResult['is_sample_rejected'] ?? null];
    $currentState = ['result' => $finalResult, 'is_sample_rejected' => $_POST['isSampleRejected'] ?? null];
    $vlData['result_modified'] = MiscUtility::resultOrRejectionChanged($previousState, $currentState) ? "yes" : "no";

    // Append the change reason (preserving prior history) whenever the result or rejection changed.
    $reasonForChanges = MiscUtility::appendResultChangeReason(
        $getPrevResult['reason_for_result_changes'] ?? null,
        $_SESSION['userId'] ?? $_POST['userId'] ?? null,
        $_POST['reasonForResultChanges'] ?? null,
        $previousState,
        $currentState
    );
    if ($reasonForChanges !== null) {
        $vlData['reason_for_result_changes'] = $reasonForChanges;
    }

    // Retain the outgoing result before it is replaced. See TestAttemptService: editing a
    // result overwrites it just as surely as a re-test does, and nothing is written when
    // there is no prior result to keep.
    /** @var TestAttemptService $attempts */
    $attempts = ContainerRegistry::get(TestAttemptService::class);

    $db->beginTransaction();

    try {
        $attempts->archive(
            'cd4',
            (int) $_POST['cd4SampleId'],
            TestAttemptService::BY_RESULT_EDIT,
            $_POST['reasonForResultChanges'] ?? null
        );

        $db->where('cd4_id', $_POST['cd4SampleId']);
        $id = $db->update('form_cd4', $vlData);

        $db->commitTransaction();
    } catch (Throwable $e) {
        $db->rollbackTransaction();
        throw $e;
    }
    if ($id === true) {
        $_SESSION['alertMsg'] = _translate("CD4 request updated successfully");
        //Log result updates
        $data = [
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $_POST['cd4SampleId'],
            'test_type' => 'cd4',
            'updated_datetime' => DateUtility::getCurrentDateTime()
        ];
        $db->insert($tableName2, $data);
    } else {
        $_SESSION['alertMsg'] = _translate("Please try again later");
    }

    header("Location:/cd4/results/cd4-manual-results.php");
} catch (Exception $exc) {
    throw new SystemException($exc->getMessage(), 500, $exc);
}
