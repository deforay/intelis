<?php

use App\Models\Eid;
use App\Utilities\DateUtils;
use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segment;
use Aranyasen\HL7\Segments\PID;
use Aranyasen\HL7\Segments\OBX;
use Aranyasen\HL7\Messages\ACK;
use Aranyasen\HL7\Segments\MSH;
use Aranyasen\HL7\Segments\MSA;

$eidModel = new Eid();

$globalConfig = $general->getGlobalConfig();
$vlsmSystemConfig = $general->getSystemConfig();
if ($type[1] == 'RES' || $type[1] == 'QRY') {
    $sQuery = "SELECT 
            vl.*,
            rtr.test_reason_name,
            b.batch_code,
            ts.status_name,
            rst.sample_name,
            f.facility_name,
            l_f.facility_name as labName,
            f.facility_code,
            f.facility_state,
            f.facility_district,
            u_d.user_name as reviewedBy,
            a_u_d.user_name as approvedBy,
            r_c_b.user_name as reqCreatedBy,
            lt_u_d.user_name as labTechnician,
            rs.rejection_reason_name,
            r_f_s.funding_source_name,
            r_i_p.i_partner_name 
            
            FROM form_eid as vl 
            
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
            LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
            LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
            LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
            LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
            LEFT JOIN user_details as r_c_b ON a_u_d.user_id=vl.tested_by 
            LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician 
            LEFT JOIN r_eid_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_eid_test 
            LEFT JOIN r_eid_sample_type as rst ON rst.sample_id=vl.specimen_type 
            LEFT JOIN r_eid_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
            LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
            LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";
    $where = [];
    if (!empty($dateRange[1])) {
        $date = $dateRange[1];
        $where[] = " (DATE(sample_collection_date) between '$date[0]' AND '$date[1]')";
    }
    if (!empty($pidF[2])) {
        $where[] = " vl.child_id IN ('" . $pidF[2] . "') ";
    }

    if (!empty($spmF[4])) {
        $where[] = " rst.sample_name IN ('" . $spmF[4] . "') ";
    }

    if (!empty($mshF[4])) {
        $where[] = " f.facility_name LIKE '%" . $mshF[4] . "%' ";
    }

    if (!empty($mshF[6])) {
        $where[] = " l_f.facility_name LIKE '%" . $mshF[6] . "%' ";
    }

    if (!empty($search[2])) {
        $where[] = " vl.is_sample_rejected ='" . $search[2] . "' ";
    }

    if (!empty($search[3]) && $search[3] == "yes") {
        $where[] = " (vl.sample_tested_datetime != null AND vl.sample_tested_datetime not like '') ";
    }
    if (!empty($spmF[2]) && $spmF[2] != "") {
        $where[] = " (vl.sample_code like '" . $spmF[2] . "%' OR vl.remote_sample_code like '" . $spmF[2] . "%') ";
    }
    if ($type[1] == 'QRY') {
        $where[] = " (vl.result ='' OR vl.result IS NULL OR vl.result LIKE '')";
        $where[] = " (vl.is_sample_rejected ='no' OR vl.is_sample_rejected IS NULL OR vl.is_sample_rejected LIKE 'no' OR vl.is_sample_rejected like '')";
    }
    if (!empty($where)) {
        $sQuery .= " where  " . implode(" AND ", $where) . "  limit 1";
    } else {
        $sQuery .= " limit 1";
    }
    // die($sQuery);
    $rowData = $db->rawQuery($sQuery);
    if (!empty($rowData)) {
        foreach ($rowData as $row) {
            /* MSH Information */
            $msh = new MSH();
            $msh->setSendingFacility($row['facility_name']);
            $msh->setReceivingApplication("VLSM");
            $msh->setReceivingFacility($row['labName']);
            /* Patient Information */
            $check = (in_array($row['child_gender'], array("female", "male", "other"))) ? $row['child_gender'] : "other";
            $sex = strtoupper(substr($check, 0, 1));
            $pid = new PID();
            $pid->setPatientID($row['child_id']);
            $pid->setPatientName($row['child_name']);
            $pid->setMothersMaidenName($row['mother_name']);
            $pid->setDateTimeOfBirth($row['child_dob']);
            $pid->setSex($sex);
            $pid->setPhoneNumberHome($row['caretaker_address']);
            $msg->setSegment($pid, 1);
            /* Sample Information */
            $spm = new Segment('SPM');
            $spm->setField(2, $row['sample_code']);
            $spm->setField(4, $row['sample_name']);
            $spm->setField(10, $row['facility_name']);
            $spm->setField(17, $row['sample_collection_date']);
            $spm->setField(18, $row['sample_received_at_vl_lab_datetime']);
            $spm->setField(21, $row['rejection_reason_name']);
            $msg->setSegment($spm, 2);
            /* OBR Section */
            $obr = new Segment('OBR');
            $obr->setField(6, $row['request_created_datetime']);
            $obr->setField(10, ['COLLECT', $row['reqCreatedBy']]);
            $obr->setField(14, $row['sample_received_at_hub_datetime']);
            $obr->setField(15, $row['funding_source_name']);
            $obr->setField(16, ['', '', $row['i_partner_name'], '', '', '']);
            $obr->setField(25, $row['status_name']);
            $obr->setField(26, $row['result']);
            $obr->setField(33, [$row['sample_requestor_name'], '']);
            $msg->setSegment($obr, 3);
            /* Patient Custom Fields Information Details */
            $zpi = new Segment('ZPI');
            $zpi->setField(1, $row['child_age']);
            $zpi->setField(2, $row['child_treatment']);
            $zpi->setField(3, $row['mother_id']);
            $msg->setSegment($zpi, 4);
            /* Infant and Mother's Health Information Details */
            $zim = new Segment('ZIM');
            $zim->setField(1, $row['mother_hiv_status']);
            $zim->setField(2, $row['mother_treatment']);
            $zim->setField(3, $row['mother_treatment_initiation_date']);
            $zim->setField(4, $row['rapid_test_performed']);
            $zim->setField(5, $row['rapid_test_date']);
            $zim->setField(6, $row['rapid_test_result']);
            $zim->setField(7, $row['has_infant_stopped_breastfeeding']);
            $zim->setField(8, $row['age_breastfeeding_stopped_in_months']);
            $zim->setField(9, $row['pcr_test_performed_before']);
            $zim->setField(10, $row['previous_pcr_result']);
            $zim->setField(11, $row['last_pcr_date']);
            $zim->setField(12, $row['reason_for_pcr']);
            $msg->setSegment($zim, 5);
            /*  System Variables Details */
            $zsv = new Segment('ZSV');
            $zsv->setField(2, $row['approvedBy']);
            $zsv->setField(3, $row['result_approved_datetime']);
            $msg->setSegment($zsv, 6);
            /*  Observation Details */
            $obx = new OBX;
            $obx->setObservationValue($row['result']);
            $msg->setSegment($obx, 7);

            $hl7Data .= $msg->toString(true);
            $response = $hl7Data;
            echo $hl7Data;
            die;
        }
        // http_response_code(200);
    } else {
        $msh = new MSH();
        $msh->setMessageType(["EID", "RES"]);
        $ack = new ACK($msg, $msh);
        $ack->setAckCode('AR', "Data not found");
        $returnString = $ack->toString(true);
        $response = $returnString;
        echo $returnString;
        // http_response_code(204);
        unset($ack);
    }
    $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'eid', $requestUrl, $hl7Msg, $response, 'hl7');
}

if ($type[1] == 'REQ' || $type[1] == 'UPI') {
    /* MSH Information */
    if ($msg->hasSegment('MSH')) {
        $msh = $msg->getSegmentByIndex(0);
        $facilityDetails = $facilityDb->getFacilityByName($msh->getField(4));
        if (!empty($facilityDetails[0]) && $facilityDetails[0] != "") {
            $data['facilityId'] = $facilityDetails[0]['facility_id'];
            $data['provinceCode'] = $facilityDetails[0]['geo_code'];
        }

        if ($msh->getField(6) != "" && !empty($msh->getField(6))) {
            $returnId = $general->getValueByName($msh->getField(6), 'facility_name', 'facility_details', 'facility_id');
            $data['labId'] = $returnId;
        }
    }
    /* Patient Information */
    if ($msg->hasSegment('PID')) {
        $pid = $msg->getSegmentByIndex(1);
        if ($pid->getField(8) == "F") {
            $gender = "female";
        } else if ($pid->getField(8) == "M") {
            $gender = "male";
        } else if ($pid->getField(8) == "O") {
            $gender = "other";
        }
        $data['childId'] = $pid->getField(2);
        $data['childName'] = $pid->getField(5);
        $data['mothersName'] = $pid->getField(6);
        $data['childDob'] = $pid->getField(7);
        $data['childGender'] = $gender;
        $data['caretakerPhoneNumber'] = $pid->getField(13);
    }
    /* Sample Information */
    if ($msg->hasSegment('SPM')) {
        $spm = $msg->getSegmentByIndex(2);
        $data['sampleCode'] = $spm->getField(2);
        $data['sampleCollectionDate'] = $spm->getField(17);
        $data['sampleReceivedDate'] = $spm->getField(18);
        if ($spm->getField(21) != "" && !empty($spm->getField(21))) {
            $respondID = $general->getValueByName($spm->getField(21), 'rejection_reason_name', 'r_eid_sample_rejection_reasons', 'rejection_reason_id');
            $data['sampleRejectionReason'] = $respondID;
        }
        if ($spm->getField(4) != "" && !empty($spm->getField(4))) {
            $respondID = $general->getValueByName($spm->getField(4), 'sample_name', 'r_eid_sample_type', 'sample_id');
            $data['specimenType'] = $respondID;
        }
    }
    /* OBR Section */
    if ($msg->hasSegment('OBR')) {
        $obr = $msg->getSegmentByIndex(3);
        if ($obr->getField(25) != "" && !empty($obr->getField(25))) {
            $vlResultStatus = $general->getValueByName($obr->getField(25), 'status_name', 'r_sample_status', 'status_id');
            $data['result_status'] = $vlResultStatus;
        } else {
            $data['result_status'] = null;
        }
        $reqBy = $obr->getField(10);
        if (isset($reqBy[1]) != "" && !empty($reqBy[1])) {
            $vlResultStatus = $general->getValueByName($reqBy[1], 'user_name', 'user_details', 'user_id');
            $data['testedBy'] = $vlResultStatus;
        } else {
            $data['testedBy'] = null;
        }
        // $data['priorityStatus'] = $obr->getField(5);
        $data['sampleReceivedAtHubOn'] = $obr->getField(14);
        if ($obr->getField(15) != "" && !empty($obr->getField(15))) {
            $vlResultStatus = $general->getValueByName($obr->getField(15), 'funding_source_name', 'r_funding_sources', 'funding_source_id');
            $data['fundingSource'] = $vlResultStatus;
        } else {
            $data['fundingSource'] = null;
        }
        if ($spm->getField(16) != "" && !empty($spm->getField(16))) {
            $respondID = $general->getValueByName($spm->getField(16), 'i_partner_name', 'r_implementation_partners', 'i_partner_id');
            $data['implementingPartner'] = $respondID;
        }
        $data['result'] = $obr->getField(26);
        $sampleRequestorName = explode("^", $obr->getField(33));
        $data['sampleRequestorName'] = $sampleRequestorName[0];
    }
    /* Patient Custom Fields Information Details */
    if ($msg->hasSegment('ZPI')) {
        $zpi = $msg->getSegmentByIndex(4);
        $data['childAge'] = $zpi->getField(1);
        $data['childTreatment'] = $zpi->getField(2);
        $data['mothersId'] = $zpi->getField(3);
    }
    /*  Infant and Mother's Health Information Details */
    if ($msg->hasSegment('ZIM')) {
        $zim = $msg->getSegmentByIndex(5);
        $data['mothersHIVStatus'] = $zim->getField(1);
        $data['motherTreatment'] = $zim->getField(2);
        $data['motherTreatmentInitiationDate'] = $zim->getField(3);
        $data['rapidTestPerformed'] = $zim->getField(4);
        $data['rapidtestDate'] = $zim->getField(5);
        $data['rapidTestResult'] = $zim->getField(6);
        $data['hasInfantStoppedBreastfeeding'] = $zim->getField(7);
        $data['ageBreastfeedingStopped'] = $zim->getField(8);
        $data['pcrTestPerformedBefore'] = $zim->getField(9);
        $data['prePcrTestResult'] = $zim->getField(10);
        $data['previousPCRTestDate'] = $zim->getField(11);
        $data['pcrTestReason'] = $zim->getField(12);
    }

    $data['formId'] = $data['countryId'] = $general->getGlobalConfig('vl_form');
    $sQuery = "SELECT vlsm_instance_id from s_vlsm_instance";
    $rowData = $db->rawQuery($sQuery);
    $data['instanceId'] = $rowData[0]['vlsm_instance_id'];
    // print_r($data);
    $sampleFrom = '';

    $data['api'] = "yes";
    $data['hl7'] = "yes";
    $_POST = $data;
    $id = 0;


    $provinceCode = (isset($_POST['provinceCode']) && !empty($_POST['provinceCode'])) ? $_POST['provinceCode'] : null;
    $provinceId = (isset($_POST['provinceId']) && !empty($_POST['provinceId'])) ? $_POST['provinceId'] : null;
    $sampleCollectionDate = (isset($_POST['sampleCollectionDate']) && !empty($_POST['sampleCollectionDate'])) ? $_POST['sampleCollectionDate'] : null;
    $where = [];
    $eidDuplicateData = false;
    $sQuery = "SELECT eid_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_eid";
    if (isset($_POST['sampleCode']) && $_POST['sampleCode'] != "") {
        $where[] =  " (sample_code like '" . $_POST['sampleCode'] . "' or remote_sample_code like '" . $_POST['sampleCode'] . "')";
    }
    if (isset($_POST['childId']) && $_POST['childId'] != "") {
        $where[] =  " child_id like '" . $_POST['childId'] . "'";
    }
    if (isset($_POST['childDob']) && $_POST['childDob'] != "") {
        $where[] =  " child_dob like '" . $_POST['childDob'] . "'";
    }
    if (isset($_POST['childGender']) && $_POST['childGender'] != "") {
        $where[] =  " child_gender like '" . $_POST['childGender'] . "'";
    }

    if (!empty($where)) {
        $sQuery .= " where  " . implode(" AND ", $where) . "  limit 1";
    } else {
        $sQuery .= " limit 1";
    }
    // die($sQuery);
    $eidDuplicateData = $db->rawQueryOne($sQuery);
    if ($eidDuplicateData) {
        $sampleData['sampleCode'] = (!empty($eidDuplicateData['sample_code'])) ? $eidDuplicateData['sample_code'] : $eidDuplicateData['remote_sample_code'];
        $sampleData['sampleCodeFormat'] = (!empty($eidDuplicateData['sample_code_format'])) ? $eidDuplicateData['sample_code_format'] : $eidDuplicateData['remote_sample_code_format'];
        $sampleData['sampleCodeKey'] = (!empty($eidDuplicateData['sample_code_key'])) ? $eidDuplicateData['sample_code_key'] : $eidDuplicateData['remote_sample_code_key'];
    } else {
        $sampleJson = $eidModel->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
        $sampleData = json_decode($sampleJson, true);
    }
    /* echo "<pre>";
    print_r($sampleData);
    die; */
    $eidData = array(
        'vlsm_country_id' => $_POST['formId'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'vlsm_instance_id' => $_POST['instanceId'],
        'province_id' => $provinceId,
        'request_created_by' => null,
        'request_created_datetime' => $db->now(),
        'last_modified_by' => null,
        'last_modified_datetime' => $db->now()
    );

    if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
        $eidData['remote_sample_code'] = $sampleData['sampleCode'];
        $eidData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
        $eidData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
        $eidData['remote_sample'] = 'yes';
        $eidData['result_status'] = 9;
    } else {
        $eidData['sample_code'] = $sampleData['sampleCode'];
        $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $eidData['remote_sample'] = 'no';
        $eidData['result_status'] = 6;
    }
    // echo "<br>".$eidData['result_status'];
    $id = 0;
    if ($eidDuplicateData) {
        $db = $db->where('eid_id', $eidDuplicateData['eid_id']);
        $id = $db->update("form_eid", $eidData);
        $_POST['eidSampleId'] = $eidDuplicateData['eid_id'];
    } else {
        if ($type[1] == 'UPI') {
            $msh = new MSH();
            $ack = new ACK($msg, $msh);
            $ack->setAckCode('AR', "Existing data not found.");
            $returnString = $ack->toString(true);
            echo $returnString;
            // http_response_code(204);
            unset($ack);
            $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'eid', $_SERVER['REQUEST_URI'], $hl7Msg, $returnString, 'hl7');
            exit(0);
        } else {
            $id = $db->insert("form_eid", $eidData);
            $_POST['eidSampleId'] = $id;
        }
    }
    if (isset($eidData) && count($eidData) > 0) {
        $tableName = "form_eid";
        $tableName1 = "activity_log";
        $instanceId = $_POST['instanceId'];
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $sampleCode = 'remote_sample_code';
            $sampleCodeKey = 'remote_sample_code_key';
        } else {
            $sampleCode = 'sample_code';
            $sampleCodeKey = 'sample_code_key';
        }
        $status = 6;
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $status = 9;
        }


        if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
            $_POST['result'] = null;
            $status = 4;
        }
        $eidData = array(
            'unique_id'                                     => $_POST['uniqueId'] ?? $general->generateUUID(),
            'vlsm_instance_id'                                     => $instanceId,
            'vlsm_country_id'                                     => $_POST['formId'],
            'sample_code_key'                                     => $_POST['sampleCodeKey'] ?? null,
            'sample_code_format'                                 => $_POST['sampleCodeFormat'] ?? null,
            'facility_id'                                         => $_POST['facilityId'] ?? null,
            'province_id'                                         => $_POST['provinceId'] ?? null,
            'lab_id'                                             => $_POST['labId'] ?? null,
            'implementing_partner'                                 => $_POST['implementingPartner'] ?? null,
            'funding_source'                                     => $_POST['fundingSource'] ?? null,
            'mother_id'                                         => $_POST['mothersId'] ?? null,
            'caretaker_phone_number'                             => $_POST['caretakerPhoneNumber'] ?? null,
            'mother_name'                                         => $_POST['mothersName'] ?? null,
            'mother_treatment'                                     => isset($_POST['motherTreatment']) ? implode(",", $_POST['motherTreatment']) : null,
            'mother_treatment_initiation_date'                     => $_POST['motherTreatmentInitiationDate'] ?? null,
            'child_id'                                             => $_POST['childId'] ?? null,
            'child_name'                                         => $_POST['childName'] ?? null,
            'child_dob'                                         => $_POST['childDob'] ?? null,
            'child_gender'                                         => $_POST['childGender'] ?? null,
            'child_age'                                         => $_POST['childAge'] ?? null,
            'child_treatment'                                     => isset($_POST['childTreatment']) ? implode(",", $_POST['childTreatment']) : null,
            'mother_hiv_status'                                 => $_POST['mothersHIVStatus'] ?? null,
            'pcr_test_performed_before'                         => $_POST['pcrTestPerformedBefore'] ?? null,
            'previous_pcr_result'                                 => $_POST['prePcrTestResult'] ?? null,
            'last_pcr_date'                                     => $_POST['previousPCRTestDate'] ?? null,
            'reason_for_pcr'                                     => $_POST['pcrTestReason'] ?? null,
            'has_infant_stopped_breastfeeding'                     => $_POST['hasInfantStoppedBreastfeeding'] ?? null,
            'age_breastfeeding_stopped_in_months'                 => $_POST['ageBreastfeedingStopped'] ?? null,
            'specimen_type'                                     => $_POST['specimenType'] ?? null,
            'sample_collection_date'                             => $_POST['sampleCollectionDate'] ?? null,
            'sample_requestor_name'                             => $_POST['sampleRequestorName'] ?? null,
            'rapid_test_performed'                                 => $_POST['rapidTestPerformed'] ?? null,
            'rapid_test_date'                                     => $_POST['rapidtestDate'] ?? null,
            'rapid_test_result'                                 => $_POST['rapidTestResult'] ?? null,
            'sample_received_at_vl_lab_datetime'                 => $_POST['sampleReceivedDate'] ?? null,
            'is_sample_rejected'                                 => $_POST['isSampleRejected'] ?? null,
            'result'                                             => $_POST['result'] ?? null,
            'tested_by'                                         => (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] :  null,
            'result_status'                                     => $status,
            'data_sync'                                         => 0,
            'reason_for_sample_rejection'                         => $_POST['sampleRejectionReason'] ?? null,
            'request_created_datetime'                             => DateUtils::getCurrentDateTime(),
            'sample_registered_at_lab'                             => DateUtils::getCurrentDateTime(),
            'last_modified_datetime'                             => DateUtils::getCurrentDateTime()
        );

        $eidData['source_of_request'] = 'hl7';
        if (isset($_POST['eidSampleId']) && $_POST['eidSampleId'] != '') {
            $db = $db->where('eid_id', $_POST['eidSampleId']);
            $id = $db->update($tableName, $eidData);
        }
        $sQuery = "SELECT eid_id, sample_code, remote_sample_code FROM form_eid where eid_id = " . $_POST['eidSampleId'];
        $savedSamples = $db->rawQueryOne($sQuery);
    }
    if ($id > 0 && isset($eidData) && count($eidData) > 0) {
        if ($savedSamples['sample_code'] != '') {
            $sampleCode = $savedSamples['sample_code'];
        } else {
            $sampleCode = $savedSamples['remote_sample_code'];
        }
        /* $msh = new MSH();
        $msh->setMessageType(["EID", "REQ"]);
        $ack = new ACK($msg, $msh);
        $spm = new Segment('SPM'); */
        $spm->setField(2, $sampleCode);
        // $ack->setSegment($spm, 2);
        $returnString = $msg->toString(true);
        $response = $returnString;
        echo $returnString;
        unset($ack);
        http_response_code(201);
    }
    $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'eid', $requestUrl, $hl7Msg, $response, 'hl7');
}
