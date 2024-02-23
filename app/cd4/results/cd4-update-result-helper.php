<?php

use App\Services\CD4Service;
use App\Utilities\DateUtility;
use Laminas\Filter\StringTrim;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use Laminas\Filter\FilterChain;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Cd4Service $cd4Service */
$cd4Service = ContainerRegistry::get(CD4Service::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');

// Define custom filters, with only StringTrim for viral load results
$onlyStringTrim = (new FilterChain())->attach(new StringTrim());


// Sanitize input
$_POST = _sanitizeInput($_POST, $customFilters);

$tableName = "form_cd4";
$tableName2 = "log_result_updates";

try {
    $instanceId = $general->getInstanceId();
    $testingPlatform = null;
    if (isset($_POST['testingPlatform']) && trim((string) $_POST['testingPlatform']) != '') {
        $platForm = explode("##", (string) $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
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
                    FROM r_vl_sample_rejection_reasons
                    WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = [
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime()
            ];
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $db->getInsertId();
        } else {
            $_POST['rejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
    }



    $reasonForChanges = null;
    $allChange = null;
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if (isset($_POST['reasonForResultChanges']) && trim((string) $_POST['reasonForResultChanges']) != '') {
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . DateUtility::getCurrentDateTime();
    }
    if (!empty($allChange) && !empty($reasonForChanges)) {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } elseif (!empty($reasonForChanges)) {
        $allChange = $reasonForChanges;
    }

    if ($_POST['failedTestingTech'] != '') {
        $platForm = explode("##", (string) $_POST['failedTestingTech']);
        $_POST['failedTestingTech'] = $platForm[0];
    }


    $vlData = [
        'vlsm_instance_id' => $instanceId,
        'cd4_result' => $_POST['cd4Result'] ?? null,
        'cd4_result_percentage' => $_POST['cd4ResultPercentage'] ?? null,
        'request_clinician_name' => $_POST['reqClinician'] ?? null,
        'request_clinician_phone_number' => $_POST['reqClinicianPhoneNumber'] ?? null,
        'test_requested_on' => DateUtility::isoDateFormat($_POST['requestDate'] ?? '', true),
        'cd4_focal_person' => $_POST['cd4FocalPerson'] ?? null,
        'cd4_focal_person_phone_number' => $_POST['cd4FocalPersonPhoneNumber'] ?? null,
        //'lab_id' => $_POST['labId'] ?? null,
        'cd4_test_platform' => $testingPlatform ?? null,
        'instrument_id' => $instrumentId ?? null,
        'sample_received_at_hub_datetime' => DateUtility::isoDateFormat($_POST['sampleReceivedAtHubOn'] ?? '', true),
        'sample_received_at_lab_datetime' => DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true),
        'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['sampleTestingDateAtLab'] ?? '', true),
        'result_dispatched_datetime' => DateUtility::isoDateFormat($_POST['resultDispatchedOn'] ?? '', true),
        'is_sample_rejected' => $_POST['isSampleRejected'] ?? null,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && trim((string) $_POST['rejectionReason']) != '') ? $_POST['rejectionReason'] : null,
        'rejection_on' => DateUtility::isoDateFormat($_POST['rejectionDate'] ?? ''),
        'result_reviewed_by' => $_POST['reviewedBy'] ?? null,
        'result_reviewed_datetime' => DateUtility::isoDateFormat($_POST['reviewedOn'] ?? ''),
        'tested_by' => $_POST['testedBy'] ?? null,
        'result_approved_by' => $_POST['approvedBy'] ?? null,
        'result_approved_datetime' => DateUtility::isoDateFormat($_POST['approvedOnDateTime'] ?? '', true),
        'date_test_ordered_by_physician' => DateUtility::isoDateFormat($_POST['dateOfDemand'] ?? ''),
        'lab_tech_comments' => $_POST['labComments'] ?? null,
        'result_status' => 8,
        'request_created_datetime' => DateUtility::getCurrentDateTime(),
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'result_modified'  => 'no',
        'manual_result_entry' => 'yes',
    ];

    $db->where('cd4_id', $_POST['cd4SampleId']);
    $getPrevResult = $db->getOne('form_cd4');
    if ($getPrevResult['cd4_result'] != "" && $getPrevResult['cd4_result'] != $finalResult) {
        $vlData['result_modified'] = "yes";
    } else {
        $vlData['result_modified'] = "no";
    }


    $db->where('cd4_id', $_POST['cd4SampleId']);
    $id = $db->update($tableName, $vlData);
    if ($id === true) {
        $_SESSION['alertMsg'] = _translate("CD4 request updated successfully");
        //Log result updates
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $_POST['cd4SampleId'],
            'test_type' => 'cd4',
            'updated_on' => DateUtility::getCurrentDateTime()
        );
        $db->insert($tableName2, $data);
    } else {
        $_SESSION['alertMsg'] = _translate("Please try again later");
    }

    header("Location:/cd4/results/cd4-manual-results.php");
} catch (Exception $exc) {
    throw new SystemException($exc->getMessage(), 500, $exc);
}
