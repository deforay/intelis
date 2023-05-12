<?php

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;
use App\Utilities\DateUtility;
use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segment;
use Aranyasen\HL7\Segments\PID;
use Aranyasen\HL7\Segments\OBX;
use Aranyasen\HL7\Messages\ACK;
use Aranyasen\HL7\Segments\MSH;


/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
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
            lt_u_d.user_name as labTechnician,
            rs.rejection_reason_name,
            r_f_s.funding_source_name,
            c.iso_name as nationality,
            c.iso2 as country_code1,
            c.iso3 as country_code2,
            r_i_p.i_partner_name 
            
            FROM form_covid19 as vl 
            
            LEFT JOIN r_countries as c ON vl.patient_nationality=c.id
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
            LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
            LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
            LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
            LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
            LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician 
            LEFT JOIN r_covid19_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_covid19_test 
            LEFT JOIN r_covid19_sample_type as rst ON rst.sample_id=vl.specimen_type 
            LEFT JOIN r_covid19_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
            LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
            LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";
    $where = [];
    if (!empty($dateRange[1])) {
        $date = $dateRange[1];
        $where[] = "(DATE(sample_collection_date) between '$date[0]' AND '$date[1]')";
    }

    if (!empty($pidF[2])) {
        $where[] = " vl.patient_id IN ('" . $pidF[2] . "') ";
    }

    if (!empty($spmF[4])) {
        $where[] = " rst.sample_name IN ('" . $spmF[4] . "') ";
    }

    if (!empty($mshF[4])) {
        $where[] = " f.facility_name IN ('" . $mshF[4] . "') ";
    }

    if (!empty($mshF[6])) {
        $where[] = " l_f.facility_name IN ('" . $mshF[6] . "') ";
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
            $check = (in_array($row['patient_gender'], array("female", "male", "other"))) ? $row['patient_gender'] : "other";
            $sex = strtoupper(substr($check, 0, 1));
            $pid = new PID();
            $pid->setPatientID($row['patient_id']);
            $pid->setPatientName($row['patient_name']);
            $pid->setMothersMaidenName([$row['patient_name'], $row['patient_surname']]);
            $pid->setDateTimeOfBirth($row['patient_dob']);
            $pid->setSex($sex);
            $pid->setPatientAddress($row['patient_address']);
            $pid->setCountryCode($row['patient_district']);
            $pid->setPhoneNumberHome($row['patient_phone_number']);
            $pid->setSSNNumber($row['external_sample_code']);
            $pid->setNationality($row['nationality']);
            $msg->setSegment($pid, 1);
            /* Sample Information */
            $spm = new Segment('SPM');
            $spm->setField(2, $row['sample_code']);
            $spm->setField(4, $row['sample_name']);
            // $spm->setField(10, $row['facility_name']);
            $spm->setField(12, $row['is_sample_collected']);
            $spm->setField(17, $row['sample_collection_date']);
            $spm->setField(18, $row['sample_received_at_vl_lab_datetime']);
            $spm->setField(21, $row['rejection_reason_name']);
            $spm->setField(24, $row['sample_condition']);
            $spm->setField(26, $row['test_number']);
            $msg->setSegment($spm, 2);
            /* OBR Section */
            $obr = new Segment('OBR');
            $obr->setField(5, $row['priority_status']);
            $obr->setField(6, $row['request_created_datetime']);
            $obr->setField(14, $row['sample_received_at_hub_datetime']);
            $obr->setField(15, $row['funding_source_name']);
            $obr->setField(25, $row['status_name']);
            $obr->setField(26, $row['result']);
            $obr->setField(31, $row['test_reason_name']);
            $obr->setField(33, [$row['lab_technician'], '']);
            $msg->setSegment($obr, 3);
            /* Clinic Custom Fields Information Details */
            $zci = new Segment('ZCI');
            $zci->setField(1, $row['is_sample_post_mortem']);
            $zci->setField(2, $row['number_of_days_sick']);
            $zci->setField(3, $row['date_of_symptom_onset']);
            $zci->setField(4, $row['date_of_initial_consultation']);
            $zci->setField(5, $row['fever_temp']);
            $zci->setField(6, $row['medical_history']);
            $zci->setField(7, $row['recent_hospitalization']);
            $zci->setField(8, $row['temperature_measurement_method']);
            $zci->setField(9, $row['respiratory_rate']);
            $zci->setField(10, $row['oxygen_saturation']);
            $zci->setField(11, $row['other_diseases']);
            $msg->setSegment($zci, 4);
            /* Patient Custom Fields Information Details */
            $zpi = new Segment('ZPI');
            $zpi->setField(1, $row['patient_occupation']);
            $zpi->setField(2, $row['patient_city']);
            $zpi->setField(3, $row['patient_province']);
            $zpi->setField(4, $row['patient_age']);
            $zpi->setField(5, $row['is_patient_pregnant']);
            $zpi->setField(6, $row['does_patient_smoke']);
            $zpi->setField(7, $row['patient_lives_with_children']);
            $zpi->setField(8, $row['patient_cares_for_children']);
            $zpi->setField(9, $row['close_contacts']);
            $zpi->setField(10, $row['contact_with_confirmed_case']);
            $zpi->setField(11, $row['source_of_alert']);
            $zpi->setField(12, $row['external_sample_code']);
            $msg->setSegment($zpi, 5);
            /* Airline Information Details */
            $zai = new Segment('ZAI');
            $zai->setField(1, $row['patient_passport_number']);
            $zai->setField(2, $row['flight_airline']);
            $zai->setField(3, $row['flight_seat_no']);
            $zai->setField(4, $row['flight_arrival_datetime']);
            $zai->setField(5, $row['flight_airport_of_departure']);
            $zai->setField(6, $row['flight_transit']);
            $zai->setField(7, $row['reason_of_visit']);
            $zai->setField(8, $row['has_recent_travel_history']);
            $zai->setField(9, $row['travel_country_names']);
            $zai->setField(10, $row['travel_return_date']);
            $msg->setSegment($zai, 6);
            /*  System Variables Details */
            $zsv = new Segment('ZSV');
            $zsv->setField(1, $row['is_result_authorised']);
            $zsv->setField(2, $row['authorized_by']);
            $zsv->setField(3, $row['authorized_on']);
            $zsv->setField(4, $row['rejection_on']);
            $zsv->setField(5, $row['request_created_datetime']);
            $msg->setSegment($zsv, 7);
            /*  Observation Details */
            $obx = new OBX;
            $obx->setObservationValue($row['result']);
            $msg->setSegment($obx, 8);

            $hl7Data .= $msg->toString(true);
            $response = $hl7Data;
            echo $hl7Data;
        }
        // http_response_code(200);
    } else {
        $msh = new MSH();
        $msh->setMessageType(["COVID-19", "RES"]);
        $ack = new ACK($msg, $msh);
        $ack->setAckCode('AR', "Data not found");
        $returnString = $ack->toString(true);
        $response = $returnString;
        echo $returnString;
        // http_response_code(204);
        unset($ack);
    }
    $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'covid19', $requestUrl, $hl7Msg, $response, 'hl7');
}

if ($type[1] == 'REQ' || $type[1] == 'UPI') {
    // $msg = new Message($hl7Msg);
    /* MSH Information */
    if ($msg->hasSegment('MSH')) {
        $msh = $msg->getSegmentByIndex(0);
        $facilityDetails = $facilitiesService->getFacilityByName($msh->getField(4));
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
        $name = $pid->getField(6);
        $data['patientId'] = $pid->getField(2);
        $data['firstName'] = $name[0];
        $data['lastName'] = $name[1];
        $data['patientDob'] = $pid->getField(7);
        $data['patientGender'] = $gender;
        $data['patientAddress'] = $pid->getField(11);
        $data['patientDistrict'] = $pid->getField(12);
        $data['patientPhoneNumber'] = $pid->getField(13);
        $data['patientNationality'] = $pid->getField(28);
    }
    // print_r($msg->getSegmentsByName("SPM"));die;
    /* Sample Information */
    if ($msg->hasSegment('SPM')) {
        $spm = $msg->getSegmentByIndex(2);
        $data['sampleCode'] = $spm->getField(2);
        // $data['sample_name'] = $spm->getField(4);
        if ($spm->getField(4) != "" && !empty($spm->getField(4))) {
            $c19Details = $covid19Service->getCovid19SampleTypesByName($spm->getField(4));
            $data['specimenType'] = $c19Details[0]['sample_id'];
        }

        $data['isSampleCollected'] = $spm->getField(12);
        $data['sampleCollectionDate'] = $spm->getField(17);
        $data['sampleReceivedDate'] = $spm->getField(18);
        if ($spm->getField(21) != "" && !empty($spm->getField(21))) {
            $returnId = $general->getValueByName($spm->getField(21), 'rejection_reason_name', 'r_covid19_sample_rejection_reasons', 'rejection_reason_id');
            $data['sampleRejectionReason'] = $returnId;
        }
        $data['sampleCondition'] = $spm->getField(24);
        $data['testNumber'] = $spm->getField(26);
        // die($spm->getField(10));
    }
    /* OBR Section */
    if ($msg->hasSegment('OBR')) {
        $obr = $msg->getSegmentByIndex(3);
        $data['priorityStatus'] = $obr->getField(5);
        $data['sample_received_at_hub_datetime'] = $obr->getField(14);
        if ($obr->getField(15) != "" && !empty($obr->getField(15))) {
            $returnId = $general->getValueByName($obr->getField(15), 'funding_source_name', 'r_funding_sources', 'funding_source_id');
            $data['fundingSource'] = $returnId;
        }
        // $data['result_status'] = $obr->getField(25);
        $data['result'] = $obr->getField(26);
        if ($obr->getField(25) != "" && !empty($obr->getField(25))) {
            $vlResultStatus = $general->getValueByName($obr->getField(25), 'status_name', 'r_sample_status', 'status_id');
            $data['result_status'] = $vlResultStatus;
        }
        if ($obr->getField(31) != "" && !empty($obr->getField(31))) {
            $returnId = $general->getValueByName($obr->getField(31), 'test_reason_name', 'r_covid19_test_reasons', 'test_reason_id');
            $data['reasonForCovid19Test'] = $returnId;
        }
    }

    /* Clinic Custom Fields Information Details */
    if ($msg->hasSegment('ZCI')) {
        $zci = $msg->getSegmentByIndex(4);
        $data['isSamplePostMortem'] = $zci->getField(1);
        $data['numberOfDaysSick'] = $zci->getField(2);
        $data['dateOfSymptomOnset'] = $zci->getField(3);
        $data['dateOfInitialConsultation'] = $zci->getField(4);
        $data['feverTemp'] = $zci->getField(5);
        $data['medicalHistory'] = $zci->getField(6);
        $data['recentHospitalization'] = $zci->getField(7);
        $data['temperatureMeasurementMethod'] = $zci->getField(8);
        $data['respiratoryRate'] = $zci->getField(9);
        $data['oxygenSaturation'] = $zci->getField(10);
        $data['otherDiseases'] = $zci->getField(11);
    }
    /* Patient Custom Fields Information Details */
    // print_r($msg->getSegmentsByName('ZPI'));die;
    if ($msg->hasSegment('ZPI')) {
        $zpi = $msg->getSegmentByIndex(4);
        $data['patientOccupation'] = $zpi->getField(1);
        $data['patientCity'] = $zpi->getField(2);
        $data['patientProvince'] = $zpi->getField(3);
        $data['patientAge'] = $zpi->getField(4);
        $data['isPatientPregnant'] = $zpi->getField(5);
        $data['doesPatientSmoke'] = $zpi->getField(6);
        $data['patientLivesWithChildren'] = $zpi->getField(7);
        $data['patientCaresForChildren'] = $zpi->getField(8);
        $data['closeContacts'] = $zpi->getField(9);
        $data['contactWithConfirmedCase'] = $zpi->getField(10);
        $data['sourceOfAlertPOE'] = $zpi->getField(11);
        $data['externalSampleCode'] = $zpi->getField(12);
    }
    /* Airline Information Details */
    if ($msg->hasSegment('ZAI')) {
        $zai = $msg->getSegmentByIndex(6);
        $data['patientPassportNumber'] = $zai->getField(1);
        $data['airline'] = $zai->getField(2);
        $data['seatNo'] = $zai->getField(3);
        $data['arrivalDateTime'] = $zai->getField(4);
        $data['airportOfDeparture'] = $zai->getField(5);
        $data['transit'] = $zai->getField(6);
        $data['reasonOfVisit'] = $zai->getField(7);
        $data['hasRecentTravelHistory'] = $zai->getField(8);
        $data['countryName'] = $zai->getField(9);
        $data['returnDate'] = $zai->getField(10);
    }

    $data['formId'] = $data['countryId'] = $general->getGlobalConfig('vl_form');
    $sQuery = "SELECT vlsm_instance_id from s_vlsm_instance";
    $rowData = $db->rawQuery($sQuery);
    $data['instanceId'] = $rowData[0]['vlsm_instance_id'];
    $sampleFrom = '';
    // echo "<pre>";print_r($data);die;
    $data['api'] = "yes";
    $data['hl7'] = "yes";
    $_POST = $data;
    $id = 0;
    $provinceCode = (isset($_POST['provinceCode']) && !empty($_POST['provinceCode'])) ? $_POST['provinceCode'] : null;
    $provinceId = (isset($_POST['provinceId']) && !empty($_POST['provinceId'])) ? $_POST['provinceId'] : null;
    $sampleCollectionDate = (isset($_POST['sampleCollectionDate']) && !empty($_POST['sampleCollectionDate'])) ? $_POST['sampleCollectionDate'] : null;
    $where = [];
    $c19DuplicateData = false;
    $sQuery = "SELECT covid19_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_covid19 ";

    if (isset($_POST['sampleCode']) && $_POST['sampleCode'] != "") {
        $where[] =  " (sample_code like '" . $_POST['sampleCode'] . "' or remote_sample_code like '" . $_POST['sampleCode'] . "')";
    }
    if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
        $where[] =  " patient_id like '" . $_POST['patientId'] . "'";
    }
    if (isset($_POST['patientDob']) && $_POST['patientDob'] != "") {
        $where[] =  " patient_dob like '" . $_POST['patientDob'] . "'";
    }
    if (isset($_POST['patientGender']) && $_POST['patientGender'] != "") {
        $where[] =  " patient_gender like '" . $_POST['patientGender'] . "'";
    }
    if (isset($_POST['patientDistrict']) && $_POST['patientDistrict'] != "") {
        $where[] =  " patient_district like '" . $_POST['patientDistrict'] . "'";
    }

    if (!empty($where)) {
        $sQuery .= " where  " . implode(" AND ", $where) . "  limit 1";
    } else {
        $sQuery .= " limit 1";
    }
    // die($sQuery);
    $c19DuplicateData = $db->rawQueryOne($sQuery);
    if ($c19DuplicateData) {
        $sampleData['sampleCode'] = (!empty($c19DuplicateData['sample_code'])) ? $c19DuplicateData['sample_code'] : $c19DuplicateData['remote_sample_code'];
        $sampleData['sampleCodeFormat'] = (!empty($c19DuplicateData['sample_code_format'])) ? $c19DuplicateData['sample_code_format'] : $c19DuplicateData['remote_sample_code_format'];
        $sampleData['sampleCodeKey'] = (!empty($c19DuplicateData['sample_code_key'])) ? $c19DuplicateData['sample_code_key'] : $c19DuplicateData['remote_sample_code_key'];
    } else {
        $sampleJson = $covid19Service->generateCovid19SampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
        $sampleData = json_decode($sampleJson, true);
    }

    $covid19Data = array(
        'vlsm_country_id' => $_POST['countryId'] ?: null,
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'vlsm_instance_id' => $_POST['instanceId'],
        'province_id' => $provinceId,
        'request_created_by' => null,
        'request_created_datetime' => $db->now(),
        'last_modified_by' => null,
        'last_modified_datetime' => $db->now()
    );

    if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
        $covid19Data['remote_sample_code'] = $sampleData['sampleCode'];
        $covid19Data['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
        $covid19Data['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
        $covid19Data['remote_sample'] = 'yes';
        $covid19Data['result_status'] = 9;
    } else {
        $covid19Data['sample_code'] = $sampleData['sampleCode'];
        $covid19Data['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $covid19Data['sample_code_key'] = $sampleData['sampleCodeKey'];
        $covid19Data['remote_sample'] = 'no';
        $covid19Data['result_status'] = 6;
    }
    $id = 0;
    if ($c19DuplicateData) {
        $db = $db->where('covid19_id', $c19DuplicateData['covid19_id']);
        $id = $db->update("form_covid19", $covid19Data);
        $_POST['covid19SampleId'] = $c19DuplicateData['covid19_id'];
    } else {
        if ($type[1] == 'UPI') {
            $msh = new MSH();
            $ack = new ACK($msg, $msh);
            $ack->setAckCode('AR', "Existing data not found.");
            $returnString = $ack->toString(true);
            echo $returnString;
            // http_response_code(204);
            $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'covid19', $requestUrl, $hl7Msg, $returnString, 'hl7');
            unset($ack);
            //exit(0);
        } else {
            $id = $db->insert("form_covid19", $covid19Data);
            $_POST['covid19SampleId'] = $id;
        }
    }
    if (isset($covid19Data) && !empty($covid19Data)) {
        $tableName = "form_covid19";
        $tableName1 = "activity_log";
        $testTableName = 'covid19_tests';
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
        $covid19Data = array(
            'unique_id'                           => $_POST['uniqueId'] ?? $general->generateUUID(),
            'vlsm_instance_id'                    => $instanceId,
            'vlsm_country_id'                     => $_POST['formId'],
            'external_sample_code'                => !empty($_POST['externalSampleCode']) ? $_POST['externalSampleCode'] : null,
            'facility_id'                         => !empty($_POST['facilityId']) ? $_POST['facilityId'] : null,
            'test_number'                         => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
            'province_id'                         => !empty($_POST['provinceId']) ? $_POST['provinceId'] : null,
            'lab_id'                              => !empty($_POST['labId']) ? $_POST['labId'] : null,
            'implementing_partner'                => !empty($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
            'source_of_alert'                     => !empty($_POST['sourceOfAlertPOE']) ? $_POST['sourceOfAlertPOE'] : null,
            'funding_source'                      => !empty($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
            'patient_id'                          => !empty($_POST['patientId']) ? $_POST['patientId'] : null,
            'patient_name'                        => !empty($_POST['firstName']) ? $_POST['firstName'] : null,
            'patient_surname'                     => !empty($_POST['lastName']) ? $_POST['lastName'] : null,
            'patient_dob'                         => !empty($_POST['patientDob']) ? $_POST['patientDob'] : null,
            'patient_gender'                      => !empty($_POST['patientGender']) ? $_POST['patientGender'] : null,
            'is_patient_pregnant'                 => !empty($_POST['isPatientPregnant']) ? $_POST['isPatientPregnant'] : null,
            'patient_age'                         => !empty($_POST['patientAge']) ? $_POST['patientAge'] : null,
            'patient_phone_number'                => !empty($_POST['patientPhoneNumber']) ? $_POST['patientPhoneNumber'] : null,
            'patient_address'                     => !empty($_POST['patientAddress']) ? $_POST['patientAddress'] : null,
            'patient_province'                    => !empty($_POST['patientProvince']) ? $_POST['patientProvince'] : null,
            'patient_district'                    => !empty($_POST['patientDistrict']) ? $_POST['patientDistrict'] : null,
            'patient_city'                        => !empty($_POST['patientCity']) ? $_POST['patientCity'] : null,
            'patient_occupation'                  => !empty($_POST['patientOccupation']) ? $_POST['patientOccupation'] : null,
            'does_patient_smoke'                  => !empty($_POST['doesPatientSmoke']) ? $_POST['doesPatientSmoke'] : null,
            'patient_nationality'                 => !empty($_POST['patientNationality']) ? $_POST['patientNationality'] : null,
            'patient_passport_number'             => !empty($_POST['patientPassportNumber']) ? $_POST['patientPassportNumber'] : null,
            'flight_airline'                      => !empty($_POST['airline']) ? $_POST['airline'] : null,
            'flight_seat_no'                      => !empty($_POST['seatNo']) ? $_POST['seatNo'] : null,
            'flight_arrival_datetime'             => !empty($_POST['arrivalDateTime']) ? $_POST['arrivalDateTime'] : null,
            'flight_airport_of_departure'         => !empty($_POST['airportOfDeparture']) ? $_POST['airportOfDeparture'] : null,
            'flight_transit'                      => !empty($_POST['transit']) ? $_POST['transit'] : null,
            'reason_of_visit'                     => !empty($_POST['reasonOfVisit']) ? $_POST['reasonOfVisit'] : null,
            'is_sample_collected'                 => !empty($_POST['isSampleCollected']) ? $_POST['isSampleCollected'] : null,
            'reason_for_covid19_test'             => !empty($_POST['reasonForCovid19Test']) ? $_POST['reasonForCovid19Test'] : null,
            'type_of_test_requested'              => !empty($_POST['testTypeRequested']) ? $_POST['testTypeRequested'] : null,
            'specimen_type'                       => !empty($_POST['specimenType']) ? $_POST['specimenType'] : null,
            'sample_collection_date'              => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
            'is_sample_post_mortem'               => !empty($_POST['isSamplePostMortem']) ? $_POST['isSamplePostMortem'] : null,
            'priority_status'                     => !empty($_POST['priorityStatus']) ? $_POST['priorityStatus'] : null,
            'number_of_days_sick'                 => !empty($_POST['numberOfDaysSick']) ? $_POST['numberOfDaysSick'] : null,
            'date_of_symptom_onset'               => !empty($_POST['dateOfSymptomOnset']) ? DateUtility::isoDateFormat($_POST['dateOfSymptomOnset']) : null,
            'date_of_initial_consultation'        => !empty($_POST['dateOfInitialConsultation']) ? DateUtility::isoDateFormat($_POST['dateOfInitialConsultation']) : null,
            'fever_temp'                          => !empty($_POST['feverTemp']) ? $_POST['feverTemp'] : null,
            'medical_history'                     => !empty($_POST['medicalHistory']) ? $_POST['medicalHistory'] : null,
            'recent_hospitalization'              => !empty($_POST['recentHospitalization']) ? $_POST['recentHospitalization'] : null,
            'patient_lives_with_children'         => !empty($_POST['patientLivesWithChildren']) ? $_POST['patientLivesWithChildren'] : null,
            'patient_cares_for_children'          => !empty($_POST['patientCaresForChildren']) ? $_POST['patientCaresForChildren'] : null,
            'temperature_measurement_method'      => !empty($_POST['temperatureMeasurementMethod']) ? $_POST['temperatureMeasurementMethod'] : null,
            'respiratory_rate'                    => !empty($_POST['respiratoryRate']) ? $_POST['respiratoryRate'] : null,
            'oxygen_saturation'                   => !empty($_POST['oxygenSaturation']) ? $_POST['oxygenSaturation'] : null,
            'close_contacts'                      => !empty($_POST['closeContacts']) ? $_POST['closeContacts'] : null,
            'contact_with_confirmed_case'         => !empty($_POST['contactWithConfirmedCase']) ? $_POST['contactWithConfirmedCase'] : null,
            'has_recent_travel_history'           => !empty($_POST['hasRecentTravelHistory']) ? $_POST['hasRecentTravelHistory'] : null,
            'travel_country_names'                => !empty($_POST['countryName']) ? $_POST['countryName'] : null,
            'travel_return_date'                  => !empty($_POST['returnDate']) ? DateUtility::isoDateFormat($_POST['returnDate']) : null,
            'sample_received_at_vl_lab_datetime'  => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
            'sample_condition'                    => !empty($_POST['sampleCondition']) ? $_POST['sampleCondition'] : ($_POST['specimenQuality'] ?? null),
            'is_sample_rejected'                  => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
            'result'                              => !empty($_POST['result']) ? $_POST['result'] : null,
            'other_diseases'                      => (!empty($_POST['otherDiseases']) && $_POST['result'] != 'positive') ? $_POST['otherDiseases'] : null,
            'result_status'                       => $status,
            'data_sync'                           => 0,
            'reason_for_sample_rejection'         => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
            'request_created_datetime'            => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : DateUtility::getCurrentDateTime(),
            'sample_registered_at_lab'            => $db->now(),
            'last_modified_datetime'              => $db->now()
        );

        $covid19Data['source_of_request'] = 'hl7';
        $id = 0;
        if (!empty($_POST['covid19SampleId'])) {
            $db = $db->where('covid19_id', $_POST['covid19SampleId']);
            $id = $db->update($tableName, $covid19Data);
        }
        $sQuery = "SELECT covid19_id, sample_code, remote_sample_code FROM form_covid19 where covid19_id = " . $_POST['covid19SampleId'];
        $savedSamples = $db->rawQueryOne($sQuery);
    }
    $returnString = "";
    // print_r($savedSamples);die;
    if ($id > 0 && isset($covid19Data) && !empty($covid19Data)) {
        if ($savedSamples['sample_code'] != '') {
            $sampleCode = $savedSamples['sample_code'];
        } else {
            $sampleCode = $savedSamples['remote_sample_code'];
        }
        /* $msh = new MSH();
        $msh->setMessageType(["COVID19", "REQ"]);
        $ack = new ACK($msg, $msh);
        $spm = new Segment('SPM'); */
        $spm->setField(2, $sampleCode);
        // $ack->setSegment($spm, 2);
        $returnString = $msg->toString(true);
        $response = $returnString;
        echo $returnString;
        unset($ack);
    }
    $trackId = $general->addApiTracking($transactionId, $user['user_id'], count($rowData), $type[1], 'covid19', $_SERVER['REQUEST_URI'], $hl7Msg, $response, 'hl7');
}
