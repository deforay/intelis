<?php

use App\Models\General;
use App\Models\GeoLocations;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new General();
$geoLocationDb = new GeoLocations();

$tableName = "form_tb";
$tableName1 = "activity_log";
$testTableName = 'tb_tests';

try {
    //system config
    $systemConfigQuery = "SELECT * FROM system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = array();
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
        $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }
    $instanceId = '';
    if (!empty($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }

    if (empty($instanceId) && $_POST['instanceId']) {
        $instanceId = $_POST['instanceId'];
    }
    if (!empty($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != "") {
        $sampleCollectionDate = explode(" ", $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = DateUtils::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
    } else {
        $_POST['sampleCollectionDate'] = null;
    }

    //Set sample received date
    if (!empty($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = DateUtils::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = null;
    }
    if (!empty($_POST['resultDispatchedDatetime']) && trim($_POST['resultDispatchedDatetime']) != "") {
        $resultDispatchedDatetime = explode(" ", $_POST['resultDispatchedDatetime']);
        $_POST['resultDispatchedDatetime'] = DateUtils::isoDateFormat($resultDispatchedDatetime[0]) . " " . $resultDispatchedDatetime[1];
    } else {
        $_POST['resultDispatchedDatetime'] = null;
    }
    if (!empty($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
        $sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
        $_POST['sampleTestedDateTime'] = DateUtils::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
    } else {
        $_POST['sampleTestedDateTime'] = null;
    }
    if (isset($_POST['sampleDispatchedDate']) && trim($_POST['sampleDispatchedDate']) != "") {
        $sampleDispatchedDate = explode(" ", $_POST['sampleDispatchedDate']);
        $_POST['sampleDispatchedDate'] = DateUtils::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
    } else {
        $_POST['sampleDispatchedDate'] = null;
    }
    if (isset($_POST['resultDate']) && trim($_POST['resultDate']) != "") {
        $resultDate = explode(" ", $_POST['resultDate']);
        $_POST['resultDate'] = DateUtils::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['resultDate'] = null;
    }
    if (!empty($_POST['arrivalDateTime']) && trim($_POST['arrivalDateTime']) != "") {
        $arrivalDate = explode(" ", $_POST['arrivalDateTime']);
        $_POST['arrivalDateTime'] = DateUtils::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
    } else {
        $_POST['arrivalDateTime'] = null;
    }

    if (!empty($_POST['requestedDate']) && trim($_POST['requestedDate']) != "") {
        $arrivalDate = explode(" ", $_POST['requestedDate']);
        $_POST['requestedDate'] = DateUtils::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
    } else {
        $_POST['requestedDate'] = null;
    }

    if (empty(trim($_POST['sampleCode']))) {
        $_POST['sampleCode'] = null;
    }

    if ($_SESSION['instanceType'] == 'remoteuser') {
        $sampleCode = 'remote_sample_code';
        $sampleCodeKey = 'remote_sample_code_key';
    } else {
        $sampleCode = 'sample_code';
        $sampleCodeKey = 'sample_code_key';
    }

    $status = 6;
    if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
        $status = 9;
    }

    $resultSentToSource = null;

    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $_POST['result'] = null;
        $status = 4;
        $resultSentToSource = 'pending';
    }
    if (!empty($_POST['patientDob'])) {
        $_POST['patientDob'] = DateUtils::isoDateFormat($_POST['patientDob']);
    }

    if (!empty($_POST['firstSputumSamplesCollectionDate'])) {
        $_POST['firstSputumSamplesCollectionDate'] = DateUtils::isoDateFormat($_POST['firstSputumSamplesCollectionDate']);
    }

    if (!empty($_POST['result'])) {
        $resultSentToSource = 'pending';
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtils::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }

    if (isset($_POST['approvedOn']) && trim($_POST['approvedOn']) != "") {
        $approvedOn = explode(" ", $_POST['approvedOn']);
        $_POST['approvedOn'] = DateUtils::isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
    } else {
        $_POST['approvedOn'] = null;
    }
    if (isset($_POST['province']) && $_POST['province'] != "") {
        $province = explode("##", $_POST['province']);
        $provinceDetails = $geoLocationDb->getByName($province[0]);
        $_POST['provinceId'] = $provinceDetails['geo_id'];
    }
    $reason = $_POST['reasonForTbTest'];
    $reason['reason'] = array($reason['reason']=>'yes');
    $tbData = array(
        'vlsm_instance_id'                    => $instanceId,
        'vlsm_country_id'                     => $_POST['formId'],
        'facility_id'                         => !empty($_POST['facilityId']) ? $_POST['facilityId'] : null,
        'specimen_quality'                    => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
        'province_id'                         => !empty($_POST['provinceId']) ? $_POST['provinceId'] : null,
        'lab_id'                              => !empty($_POST['labId']) ? $_POST['labId'] : null,
        'implementing_partner'                => !empty($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
        'funding_source'                      => !empty($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
        'referring_unit'                      => !empty($_POST['referringUnit']) ? $_POST['referringUnit'] : null,
        'patient_id'                          => !empty($_POST['patientId']) ? $_POST['patientId'] : null,
        'patient_type'                        => !empty($_POST['typeOfPatient']) ? json_encode($_POST['typeOfPatient']) : null,
        'patient_name'                        => !empty($_POST['firstName']) ? $_POST['firstName'] : null,
        'patient_surname'                     => !empty($_POST['lastName']) ? $_POST['lastName'] : null,
        'patient_dob'                         => !empty($_POST['patientDob']) ? $_POST['patientDob'] : null,
        'patient_gender'                      => !empty($_POST['patientGender']) ? $_POST['patientGender'] : null,
        'patient_age'                         => !empty($_POST['patientAge']) ? $_POST['patientAge'] : null,
        'reason_for_tb_test'                  => !empty($reason) ? json_encode($reason) : null,
        'hiv_status'                          => !empty($_POST['hivStatus']) ? $_POST['hivStatus'] : null,
        'previously_treated_for_tb'           => !empty($_POST['previouslyTreatedForTB']) ? $_POST['previouslyTreatedForTB'] : null,
        'tests_requested'                     => !empty($_POST['testTypeRequested']) ? json_encode($_POST['testTypeRequested']) : null,
        'number_of_sputum_samples'            => !empty($_POST['numberOfSputumSamples']) ? $_POST['numberOfSputumSamples'] : null,
        'first_sputum_samples_collection_date'=> !empty($_POST['firstSputumSamplesCollectionDate']) ? $_POST['firstSputumSamplesCollectionDate'] : null,
        'sample_requestor_name'               => !empty($_POST['sampleRequestorName']) ? $_POST['sampleRequestorName'] : null,
        'specimen_type'                       => !empty($_POST['specimenType']) ? $_POST['specimenType'] : null,
        'sample_collection_date'              => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
        'sample_dispatched_datetime'          => !empty($_POST['sampleDispatchedDate']) ? $_POST['sampleDispatchedDate'] : null,
        'sample_received_at_lab_datetime'     => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
        'is_sample_rejected'                  => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : '',
        'result'                              => !empty($_POST['result']) ? $_POST['result'] : null,
        'xpert_mtb_result'                    => !empty($_POST['xPertMTMResult']) ? $_POST['xPertMTMResult'] : null,
        'result_sent_to_source'               => $resultSentToSource,
        'result_dispatched_datetime'          => !empty($_POST['resultDispatchedDatetime']) ? $_POST['resultDispatchedDatetime'] : null,
        'result_reviewed_by'                  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime'            => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by'                  => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != "") ? $_POST['approvedBy'] : "",
        'result_approved_datetime'            => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != "") ? $_POST['approvedOn'] : null,
        'sample_tested_datetime'              => (isset($_POST['sampleTestedDateTime']) && $_POST['sampleTestedDateTime'] != "") ? $_POST['sampleTestedDateTime'] : null,
        'other_referring_unit'                => (isset($_POST['typeOfReferringUnit']) && $_POST['typeOfReferringUnit'] != "") ? $_POST['typeOfReferringUnit'] : null,
        'other_specimen_type'                 => (isset($_POST['specimenTypeOther']) && $_POST['specimenTypeOther'] != "") ? $_POST['specimenTypeOther'] : null,
        'other_patient_type'                  => (isset($_POST['typeOfPatientOther']) && $_POST['typeOfPatientOther'] != "") ? $_POST['typeOfPatientOther'] : null,
        'tested_by'                           => !empty($_POST['testedBy']) ? $_POST['testedBy'] : null,
        'result_date'                         => !empty($_POST['resultDate']) ? $_POST['resultDate'] : null,
        'rejection_on'                        => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtils::isoDateFormat($_POST['rejectionDate']) : null,
        'result_status'                       => $status,
        'data_sync'                           => 0,
        'reason_for_sample_rejection'         => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
        'sample_registered_at_lab'            => $db->now(),
        'last_modified_by'                    => $_SESSION['userId'],
        'last_modified_datetime'              => $db->now(),
        'request_created_by'                  => $_SESSION['userId'],
        'last_modified_by'                    => $_SESSION['userId'],
        'lab_technician'                      => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId'],
        'source_of_request'                   => "web"
    );
    $id = 0;

    if (isset($_POST['tbSampleId']) && $_POST['tbSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
        if (isset($_POST['testResult']) && count($_POST['testResult']) > 0) {
            $db = $db->where('tb_id', $_POST['tbSampleId']);
            $db->delete($testTableName);

            foreach ($_POST['testResult'] as $testKey => $testResult) {
                if (isset($testResult) && !empty($testResult) && trim($testResult) != "") {
                    $db->insert($testTableName, array(
                        'tb_id'             => $_POST['tbSampleId'],
                        'actual_no'         => isset($_POST['actualNo'][$testKey]) ? $_POST['actualNo'][$testKey] : null,
                        'test_result'       => $testResult,
                        'updated_datetime'  => DateUtils::getCurrentDateTime()
                    ));
                }
            }
        }
    } else {
        $db = $db->where('tb_id', $_POST['tbSampleId']);
        $db->delete($testTableName);
    }

    if (!empty($_POST['tbSampleId'])) {
        $db = $db->where('tb_id', $_POST['tbSampleId']);
        $id = $db->update($tableName, $tbData);
    }

    if ($id > 0) {
        $_SESSION['alertMsg'] = _("TB test request updated successfully");
        //Add event log
        $eventType = 'tb-add-request';
        $action = $_SESSION['userName'] . ' pdated a TB request with the Sample ID/Code  ' . $_POST['tbSampleId'];
        $resource = 'tb-add-request';

        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = _("Unable to update this TB sample. Please try again later");
    }

    if (!empty($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
        header("location:/tb/requests/tb-edit-request.php?id=" . base64_encode($_POST['tbSampleId']));
    } else {
        header("location:/tb/requests/tb-requests.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
