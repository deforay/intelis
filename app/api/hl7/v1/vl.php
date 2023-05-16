<?php

use App\Registries\ContainerRegistry;
use App\Services\VlService;
use App\Utilities\DateUtility;
use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segment;
use Aranyasen\HL7\Segments\PID;
use Aranyasen\HL7\Segments\OBX;
use Aranyasen\HL7\Messages\ACK;
use Aranyasen\HL7\Segments\MSH;


/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);
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
            c.iso_name as nationality,
            c.iso2 as country_code1,
            c.iso3 as country_code2,
            r_i_p.i_partner_name 
            
            FROM form_vl as vl 
            
            LEFT JOIN r_countries as c ON vl.patient_nationality=c.id
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
            LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
            LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
            LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
            LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
            LEFT JOIN user_details as r_c_b ON a_u_d.user_id=vl.request_created_by 
            LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician 
            LEFT JOIN r_vl_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_vl_testing 
            LEFT JOIN r_vl_sample_type as rst ON rst.sample_id=vl.sample_type 
            LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
            LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
            LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";
    $where = [];
    if (!empty($dateRange[1])) {
        $date = $dateRange[1];
        $where[] = " (DATE(sample_collection_date) between '$date[0]' AND '$date[1]')";
    }
    if (!empty($pidF[2])) {
        $where[] = " vl.patient_art_no LIKE '" . $pidF[2] . "' ";
    }

    if (!empty($spmF[4])) {
        $where[] = " rst.sample_name LIKE'" . $spmF[4] . "' ";
    }

    if (!empty($mshF[4])) {
        $where[] = " f.facility_name LIKE'" . $mshF[4] . "' ";
    }

    if (!empty($mshF[6])) {
        $where[] = " l_f.facility_name LIKE '" . $mshF[6] . "' ";
    }

    if (!empty($search[2])) {
        $where[] = " vl.is_sample_rejected ='" . $search[2] . "' ";
    }

    if (!empty($search[3]) && $search[3] == "yes") {
        $where[] = " (vl.sample_tested_datetime is not null AND vl.sample_tested_datetime not like '') ";
    }
    if (!empty($spmF[2]) && $spmF[2] != "") {
        $where[] = " (vl.sample_code like '" . $spmF[2] . "' OR vl.remote_sample_code like '" . $spmF[2] . "') ";
    }
    if ($type[1] == 'QRY') {
        $where[] = " (vl.result ='' OR vl.result IS NULL OR vl.result LIKE '')";
        $where[] = " (vl.is_sample_rejected ='no' OR vl.is_sample_rejected IS NULL OR vl.is_sample_rejected LIKE 'no' OR vl.is_sample_rejected like '')";
    }
    if (!empty($where)) {
        $sQuery .= ' WHERE ' . implode(" AND ", $where);
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
            $check = (in_array($row['patient_gender'], array("female", "male", "other"))) ? $row['patient_gender'] : "other";
            $sex = strtoupper(substr($check, 0, 1));
            $pid = new PID();
            $pid->setPatientID($row['patient_art_no']);
            $pid->setPatientName([$row['patient_first_name'], $row['patient_last_name']]);
            // $pid->setMothersMaidenName($row['patient_last_name']);
            $pid->setDateTimeOfBirth($row['patient_dob']);
            $pid->setSex($sex);
            $pid->setPhoneNumberHome($row['patient_mobile_number']);
            $msg->setSegment($pid, 1);
            /* Sample Information */
            $spm = new Segment('SPM');
            $spm->setField(2, $row['sample_code']);
            $spm->setField(4, $row['sample_name']);
            $spm->setField(10, $row['facility_name']);
            $spm->setField(17, $row['sample_collection_date']);
            $spm->setField(18, $row['sample_received_at_vl_lab_datetime']);
            $spm->setField(21, $row['test_reason_name']);
            // $spm->setField(24, $row['treatment_initiated_date']);
            $msg->setSegment($spm, 2);
            /* OBR Section */
            $obr = new Segment('OBR');
            $obr->setField(6, $row['request_created_datetime']);
            $obr->setField(9, $row['result_value_absolute']);
            $obr->setField(10, ['COLLECT', $row['reqCreatedBy']]);
            $obr->setField(14, $row['sample_received_at_hub_datetime']);
            $obr->setField(15, $row['funding_source_name']);
            $obr->setField(16, ['', '', $row['i_partner_name'], '', '', '']);
            $obr->setField(25, $row['status_name']);
            $obr->setField(26, $row['result']);
            $msg->setSegment($obr, 3);
            /* Patient Custom Fields Information Details */
            $zpi = new Segment('ZPI');
            $zpi->setField(1, $row['patient_age_in_years']);
            $zpi->setField(2, $row['patient_age_in_months']);
            $zpi->setField(3, $row['is_patient_pregnant']);
            $zpi->setField(4, $row['is_patient_breastfeeding']);
            $msg->setSegment($zpi, 4);
            /* Indication for VL Testing Information Details */
            $zai = new Segment('ZIT');
            $zpi->setField(1, $row['current_regimen']);
            $zpi->setField(2, $row['date_of_initiation_of_current_regimen']);
            $zpi->setField(3, $row['consent_to_receive_sms']);
            $zpi->setField(4, $row['arv_adherance_percentage']);
            $zpi->setField(5, $row['last_vl_date_routine']);
            $zpi->setField(6, $row['last_vl_result_routine']);
            $zai->setField(7, $row['last_vl_date_failure_ac']);
            $zai->setField(8, $row['last_vl_result_failure_ac']);
            $zai->setField(9, $row['last_vl_date_failure']);
            $zai->setField(10, $row['last_vl_result_failure']);
            $msg->setSegment($zai, 5);
            /*  System Variables Details */
            $zsv = new Segment('ZSV');
            $zsv->setField(1, $row['is_result_authorised']);
            $zsv->setField(2, $row['result_approved_by']);
            $zsv->setField(3, $row['result_approved_datetime']);
            $zsv->setField(4, $row['request_created_datetime']);
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
        $msh->setMessageType(["VL", "RES"]);
        $ack = new ACK($msg, $msh);
        $ack->setAckCode('AR', "Data not found");
        $returnString = $ack->toString(true);
        $response = $returnString;
        echo $returnString;
        // http_response_code(204);
        unset($ack);
    }
    $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'vl', $_SERVER['REQUEST_URI'], $hl7Msg, $response, 'hl7');
}

if ($type[1] == 'REQ' || $type[1] == 'UPI') {
    /* MSH Information */
    if ($msg->hasSegment('MSH')) {
        $msh = $msg->getSegmentByIndex(0);
        $facilityDetails = $facilitiesService->getFacilityByName($msh->getField(4));
        if (!empty($facilityDetails[0]) && $facilityDetails[0] != "") {
            $data['fName'] = $facilityDetails[0]['facility_id'];
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
        $data['artNo'] = $pid->getField(2);
        $data['patientFirstName'] = $pid->getField(5);
        $data['dob'] = $pid->getField(7);
        if ($pid->getField(8) == "F") {
            $gender = "female";
        } else if ($pid->getField(8) == "M") {
            $gender = "male";
        } else if ($pid->getField(8) == "O") {
            $gender = "other";
        }
        $data['gender'] = $gender;
        $data['patientPhoneNumber'] = $pid->getField(13);
    }
    /* Sample Information */
    if ($msg->hasSegment('SPM')) {
        $spm = $msg->getSegmentByIndex(2);
        if ($spm->getField(21) != "" && !empty($spm->getField(21))) {
            $respondID = $general->getValueByName($spm->getField(21), 'rejection_reason_name', 'r_vl_sample_rejection_reasons', 'rejection_reason_id');
            $data['sampleRejectionReason'] = $respondID;
        }
        $data['sampleCode'] = $spm->getField(2);
        if ($spm->getField(4) != "" && !empty($spm->getField(4))) {
            $vlSampleDetails = $vlService->getVlSampleTypesByName($spm->getField(4));
            $data['specimenType'] = $vlSampleDetails[0]['sample_id'];
        }
        $data['sampleCollectionDate'] = $spm->getField(17);
        $data['sampleReceivedDate'] = $spm->getField(18);
        $data['sampleRejectionReason'] = $spm->getField(21);
    }
    /* OBR Section */
    if ($msg->hasSegment('OBR')) {
        $obr = $msg->getSegmentByIndex(3);
        // $data['priorityStatus'] = $obr->getField(5);
        $data['sampleReceivedAtHubOn'] = $obr->getField(14);
        $data['result'] = $obr->getField(26);
        if ($obr->getField(15) != "" && !empty($obr->getField(15))) {
            $vlResultStatus = $general->getValueByName($obr->getField(15), 'funding_source_name', 'r_funding_sources', 'funding_source_id');
            $data['fundingSource'] = base64_encode($vlResultStatus);
        } else {
            $data['fundingSource'] = null;
        }
        if ($obr->getField(25) != "" && !empty($obr->getField(25))) {
            $vlResultStatus = $general->getValueByName($obr->getField(25), 'status_name', 'r_sample_status', 'status_id');
            $data['result_status'] = $vlResultStatus;
        } else {
            $data['result_status'] = null;
        }
    }
    /* Patient Custom Fields Information Details */
    if ($msg->hasSegment('ZPI')) {
        $zpi = $msg->getSegmentByIndex(4);
        $data['ageInYears'] = $zpi->getField(1);
        $data['ageInMonths'] = $zpi->getField(2);
        $data['patientPregnant'] = $zpi->getField(3);
        $data['breastfeeding'] = $zpi->getField(4);
    }
    /* Airline Information Details */
    if ($msg->hasSegment('ZIT')) {
        $zai = $msg->getSegmentByIndex(5);
        $data['artRegimen'] = $zai->getField(1);
        $data['regimenInitiatedOn'] = $zai->getField(2);
        $data['receiveSms'] = $zai->getField(3);
        $data['arvAdherence'] = $zai->getField(4);
        $data['rmTestingLastVLDate'] = $zai->getField(5);
        $data['rmTestingVlValue'] = $zai->getField(6);
        $data['repeatTestingLastVLDate'] = $zai->getField(7);
        $data['repeatTestingVlValue'] = $zai->getField(8);
        $data['suspendTreatmentLastVLDate'] = $zai->getField(9);
        $data['suspendTreatmentVlValue'] = $zai->getField(10);
    }

    $data['formId'] = $data['countryId'] = $general->getGlobalConfig('vl_form');
    $sQuery = "SELECT vlsm_instance_id from s_vlsm_instance";
    $rowData = $db->rawQuery($sQuery);
    $data['instanceId'] = $rowData[0]['vlsm_instance_id'];
    // print_r($data);die;
    $sampleFrom = '';

    $data['api'] = "yes";
    $data['hl7'] = "yes";
    $_POST = $data;
    $id = 0;
    $provinceCode = (isset($_POST['provinceCode']) && !empty($_POST['provinceCode'])) ? $_POST['provinceCode'] : null;
    $provinceId = (isset($_POST['provinceId']) && !empty($_POST['provinceId'])) ? $_POST['provinceId'] : null;
    $sampleCollectionDate = (isset($_POST['sampleCollectionDate']) && !empty($_POST['sampleCollectionDate'])) ? $_POST['sampleCollectionDate'] : null;
    $where = [];
    $sQuery = "SELECT vl_sample_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_vl";
    if (isset($_POST['sampleCode']) && $_POST['sampleCode'] != "") {
        $where[] =  " (sample_code like '" . $_POST['sampleCode'] . "' or remote_sample_code like '" . $_POST['sampleCode'] . "')";
    }
    if (isset($_POST['artNo']) && $_POST['artNo'] != "") {
        $where[] =  " patient_art_no like '" . $_POST['artNo'] . "'";
    }
    if (isset($_POST['dob']) && $_POST['dob'] != "") {
        $where[] =  " patient_dob like '" . $_POST['dob'] . "'";
    }
    if (isset($_POST['gender']) && $_POST['gender'] != "") {
        $where[] =  " patient_gender like '" . $_POST['gender'] . "'";
    }

    if (!empty($where)) {
        $sQuery .= " where  " . implode(" AND ", $where) . "  limit 1";
    } else {
        $sQuery .= " limit 1";
    }
    // die($sQuery);
    $vlDuplicateData = $db->rawQueryOne($sQuery);
    if ($vlDuplicateData) {
        $sampleData['sampleCode'] = (!empty($vlDuplicateData['sample_code'])) ? $vlDuplicateData['sample_code'] : $vlDuplicateData['remote_sample_code'];
        $sampleData['sampleCodeFormat'] = (!empty($vlDuplicateData['sample_code_format'])) ? $vlDuplicateData['sample_code_format'] : $vlDuplicateData['remote_sample_code_format'];
        $sampleData['sampleCodeKey'] = (!empty($vlDuplicateData['sample_code_key'])) ? $vlDuplicateData['sample_code_key'] : $vlDuplicateData['remote_sample_code_key'];
    } else {
        $sampleJson = $vlService->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId);
        $sampleData = json_decode($sampleJson, true);
    }

    $vlData = array(
        'vlsm_country_id' => $_POST['countryId'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'vlsm_instance_id' => $_POST['instanceId'],
        'province_id' => $provinceId,
        'request_created_by' => $user['user_id'],
        'request_created_datetime' => DateUtility::getCurrentDateTime(),
        'last_modified_by' => $user['user_id'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime()
    );

    if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
        $vlData['remote_sample_code'] = $sampleData['sampleCode'];
        $vlData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
        $vlData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
        $vlData['remote_sample'] = 'yes';
        $vlData['result_status'] = 9;
    } else {
        $vlData['sample_code'] = $sampleData['sampleCode'];
        $vlData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $vlData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $vlData['remote_sample'] = 'no';
        $vlData['result_status'] = 6;
    }
    /* echo "<pre>";
    print_r($vlDuplicateData);
    print_r($vlData);
    die; */
    $id = 0;
    if ($vlDuplicateData) {
        $db = $db->where('vl_sample_id', $vlDuplicateData['vl_sample_id']);
        $id = $db->update("form_vl", $vlData);
        $_POST['vlSampleId'] = $vlDuplicateData['vl_sample_id'];
    } else {
        if ($type[1] == 'UPI') {
            $msh = new MSH();
            $ack = new ACK($msg, $msh);
            $ack->setAckCode('AR', "Existing data not found.");
            $returnString = $ack->toString(true);
            echo $returnString;
            // http_response_code(204);
            unset($ack);
            $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'vl', $requestUrl, $hl7Msg, $returnString, 'hl7');
            //exit(0);
        } else {
            $id = $db->insert("form_vl", $vlData);
            $_POST['vlSampleId'] = $id;
        }
    }

    if (!empty($vlData)) {
        $tableName = "form_vl";
        $tableName1 = "activity_log";
        $vlTestReasonTable = "r_vl_test_reasons";
        $fDetails = "facility_details";
        $vl_result_category = null;
        $status = 6;
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $status = 9;
        }
        //add province
        $splitProvince = explode("##", $_POST['province']);
        if (isset($splitProvince[0]) && trim($splitProvince[0]) != '') {
            $provinceQuery = "SELECT * from geographical_divisions where geo_name='" . $splitProvince[0] . "'";
            $provinceInfo = $db->query($provinceQuery);
            if (!isset($provinceInfo) || empty($provinceInfo)) {
                $db->insert('geographical_divisions', array('geo_name' => $splitProvince[0], 'geo_code' => $splitProvince[1]));
            }
        }
        if (isset($_POST['gender']) && trim($_POST['gender']) == 'male') {
            $_POST['patientPregnant'] = '';
            $_POST['breastfeeding'] = '';
        }
        if (empty($instanceId) && $_POST['instanceId']) {
            $instanceId = $_POST['instanceId'];
        }
        $testingPlatform = '';
        if (isset($_POST['testingPlatform']) && trim($_POST['testingPlatform']) != '') {
            $platForm = explode("##", $_POST['testingPlatform']);
            $testingPlatform = $platForm[0];
        }
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $sampleCode = 'remote_sample_code';
            $sampleCodeKey = 'remote_sample_code_key';
        } else {
            $sampleCode = 'sample_code';
            $sampleCodeKey = 'sample_code_key';
        }
        $vldata = array(
            'unique_id' => $_POST['uniqueId'] ?? $general->generateUUID(),
            'vlsm_instance_id' => $instanceId,
            'vlsm_country_id' => 1,
            'sample_code_format' => (isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat'] != '') ? $_POST['sampleCodeFormat'] :  null,
            'facility_id' => (isset($_POST['fName']) && $_POST['fName'] != '') ? $_POST['fName'] :  null,
            'sample_collection_date' => $_POST['sampleCollectionDate'],
            'patient_gender' => (isset($_POST['gender']) && $_POST['gender'] != '') ? $_POST['gender'] :  null,
            'patient_dob' => $_POST['dob'],
            'patient_age_in_years' => (isset($_POST['ageInYears']) && $_POST['ageInYears'] != '') ? $_POST['ageInYears'] :  null,
            'patient_age_in_months' => (isset($_POST['ageInMonths']) && $_POST['ageInMonths'] != '') ? $_POST['ageInMonths'] :  null,
            'is_patient_pregnant' => (isset($_POST['patientPregnant']) && $_POST['patientPregnant'] != '') ? $_POST['patientPregnant'] :  null,
            'is_patient_breastfeeding' => (isset($_POST['breastfeeding']) && $_POST['breastfeeding'] != '') ? $_POST['breastfeeding'] :  null,
            'patient_art_no' => (isset($_POST['artNo']) && $_POST['artNo'] != '') ? $_POST['artNo'] :  null,
            'current_regimen' => (isset($_POST['artRegimen']) && $_POST['artRegimen'] != '') ? $_POST['artRegimen'] :  null,
            'date_of_initiation_of_current_regimen' => $_POST['regimenInitiatedOn'],
            'patient_mobile_number' => (isset($_POST['patientPhoneNumber']) && $_POST['patientPhoneNumber'] != '') ? $_POST['patientPhoneNumber'] :  null,
            'consent_to_receive_sms' => (isset($_POST['receiveSms']) && $_POST['receiveSms'] != '') ? $_POST['receiveSms'] :  null,
            'sample_type' => (isset($_POST['specimenType']) && $_POST['specimenType'] != '') ? $_POST['specimenType'] :  null,
            'arv_adherance_percentage' => (isset($_POST['arvAdherence']) && $_POST['arvAdherence'] != '') ? $_POST['arvAdherence'] :  null,
            'last_vl_date_routine' => (isset($_POST['rmTestingLastVLDate']) && $_POST['rmTestingLastVLDate'] != '') ? DateUtility::isoDateFormat($_POST['rmTestingLastVLDate']) :  null,
            'last_vl_result_routine' => (isset($_POST['rmTestingVlValue']) && $_POST['rmTestingVlValue'] != '') ? $_POST['rmTestingVlValue'] :  null,
            'last_vl_date_failure_ac' => (isset($_POST['repeatTestingLastVLDate']) && $_POST['repeatTestingLastVLDate'] != '') ? DateUtility::isoDateFormat($_POST['repeatTestingLastVLDate']) :  null,
            'last_vl_result_failure_ac' => (isset($_POST['repeatTestingVlValue']) && $_POST['repeatTestingVlValue'] != '') ? $_POST['repeatTestingVlValue'] :  null,
            'last_vl_date_failure' => (isset($_POST['suspendTreatmentLastVLDate']) && $_POST['suspendTreatmentLastVLDate'] != '') ? DateUtility::isoDateFormat($_POST['suspendTreatmentLastVLDate']) :  null,
            'last_vl_result_failure' => (isset($_POST['suspendTreatmentVlValue']) && $_POST['suspendTreatmentVlValue'] != '') ? $_POST['suspendTreatmentVlValue'] :  null,
            'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] :  null,
            'vl_test_platform' => $testingPlatform,
            'sample_received_at_hub_datetime' => $_POST['sampleReceivedAtHubOn'],
            'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
            'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] :  null,
            'result_value_absolute' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Below Detection Level')) ? $_POST['vlResult'] :  null,
            'result_value_absolute_decimal' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Below Detection Level')) ? number_format((float)$_POST['vlResult'], 2, '.', '') :  null,
            'result' => (isset($_POST['result']) && $_POST['result'] != '') ? $_POST['result'] :  null,
            'result_status' => $status,
            'funding_source' => (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') ? base64_decode($_POST['fundingSource']) : null,
            'request_created_datetime' => DateUtility::getCurrentDateTime(),
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            'manual_result_entry' => 'yes',
            'vl_result_category' => $vl_result_category
        );

        $vldata['source_of_request'] = 'hl7';
        if (isset($_POST['vlSampleId']) && $_POST['vlSampleId'] != '') {
            $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
            $id = $db->update($tableName, $vldata);
        } else {
            //check existing sample code
            $existSampleQuery = "SELECT " . $sampleCode . "," . $sampleCodeKey . " FROM form_vl where " . $sampleCode . " ='" . trim($_POST['sampleCode']) . "'";
            $existResult = $db->rawQuery($existSampleQuery);
            if (isset($existResult[0][$sampleCodeKey]) && $existResult[0][$sampleCodeKey] != '') {
                if ($existResult[0][$sampleCodeKey] != '') {
                    $sCode = $existResult[0][$sampleCodeKey] + 1;
                    $strparam = strlen($sCode);
                    $zeros = substr("000", $strparam);
                    $maxId = $zeros . $sCode;
                    $_POST['sampleCode'] = $_POST['sampleCodeFormat'] . $maxId;
                    $_POST['sampleCodeKey'] = $maxId;
                }
            }
            // print_r($_POST['sampleCode']);die;

            if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
                $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  null;
                $vldata['remote_sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] :  null;
                $vldata['remote_sample'] = 'yes';
            } else {
                $vldata['sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  null;
                //$vldata['sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  null;
                $vldata['sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] :  null;
            }
            $vldata['sample_code_format'] = (isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat'] != '') ? $_POST['sampleCodeFormat'] :  null;
            $id = $db->insert($tableName, $vldata);
        }
        $sQuery = "SELECT vl_sample_id, sample_code, remote_sample_code FROM form_vl where vl_sample_id = " . $_POST['vlSampleId'];
        $savedSamples = $db->rawQueryOne($sQuery);
    }
    if ($id > 0 && isset($vlData) && !empty($vlData)) {
        if ($savedSamples['sample_code'] != '') {
            $sampleCode = $savedSamples['sample_code'];
        } else {
            $sampleCode = $savedSamples['remote_sample_code'];
        }
        /* $msh = new MSH();
        $msh->setMessageType(["VL", "REQ"]);
        $ack = new ACK($msg, $msh);
        $spm = new Segment('SPM'); */
        $spm->setField(2, $sampleCode);
        // $ack->setSegment($spm, 2);
        $returnString = $msg->toString(true);
        $response = $returnString;
        echo $returnString;
        unset($ack);
    }
    $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'vl', $requestUrl, $hl7Msg, $response, 'hl7');
}
