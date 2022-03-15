<?php

session_unset(); // no need of session in json response
try {
    ini_set('memory_limit', -1);
    header('Content-Type: application/json');
    $general = new \Vlsm\Models\General();
    $userDb = new \Vlsm\Models\Users();
    $app = new \Vlsm\Models\App();
    $covid19Model = new \Vlsm\Models\Covid19();

    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();
    $user = null;

    $input = json_decode(file_get_contents("php://input"), true);

    /* For API Tracking params */
    $requestUrl .= $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];
    $params = file_get_contents("php://input");

    $auth = $general->getHeader('Authorization');
    if (!empty($auth)) {
        $authToken = str_replace("Bearer ", "", $auth);
        /* Check if API token exists */
        $user = $userDb->getAuthToken($authToken);
    }

    // If authentication fails then do not proceed
    if (empty($user) || empty($user['user_id'])) {
        $response = array(
            'status' => 'failed',
            'timestamp' => time(),
            'error' => 'Bearer Token Invalid',
            'data' => array()
        );
        http_response_code(401);
        echo json_encode($response);
        exit(0);
    }
    $roleUser = $userDb->getUserRole($user['user_id']);

    foreach ($input['data'] as $rootKey => $field) {
        $data = $field;
        $sampleFrom = '';
        $formId = $general->getGlobalConfig('vl_form');
        if ($data['formId'] == $formId) {
        }
        $sQuery = "SELECT vlsm_instance_id from s_vlsm_instance";
        $rowData = $db->rawQuery($sQuery);
        $data['instanceId'] = $rowData[0]['vlsm_instance_id'];
        $sampleFrom = '';
        /* V1 name to Id mapping */
        if (!is_numeric($data['provinceId'])) {
            $province = explode("##", $data['provinceId']);
            if (isset($province) && count($province) > 0) {
                $data['provinceId'] = $province[0];
            }
            $data['provinceId'] = $general->getValueByName($data['provinceId'], 'province_name', 'province_details', 'province_id', true);
        }
        if (!is_numeric($data['implementingPartner'])) {
            $data['implementingPartner'] = $general->getValueByName($data['implementingPartner'], 'i_partner_name', 'r_implementation_partners', 'i_partner_id');
        }
        if (!is_numeric($data['fundingSource'])) {
            $data['fundingSource'] = $general->getValueByName($data['fundingSource'], 'funding_source_name', 'r_funding_sources', 'funding_source_id');
        }
        if (!is_numeric($data['patientNationality'])) {
            $iso = explode("(", $data['patientNationality']);
            if (isset($iso) && count($iso) > 0) {
                $data['patientNationality'] = trim($iso[0]);
            }
            $data['patientNationality'] = $general->getValueByName($data['patientNationality'], 'iso_name', 'r_countries', 'id');
        }
        $pprovince = explode("##", $data['patientProvince']);
        if (isset($pprovince) && count($pprovince) > 0) {
            $data['patientProvince'] = $pprovince[0];
        }

        $data['api'] = "yes";
        $provinceCode = (isset($data['provinceCode']) && !empty($data['provinceCode'])) ? $data['provinceCode'] : null;
        $provinceId = (isset($data['provinceId']) && !empty($data['provinceId'])) ? $data['provinceId'] : null;
        $sampleCollectionDate = (isset($data['sampleCollectionDate']) && !empty($data['sampleCollectionDate'])) ? $data['sampleCollectionDate'] : null;

        if (empty($sampleCollectionDate)) {
            exit();
        }
        $update = "no";
        $rowData = false;
        if ((isset($data['sampleCode']) && !empty($data['sampleCode'])) || (isset($data['remoteSampleCode']) && !empty($data['uniqueId'])) || (isset($data['uniqueId']) && !empty($data['uniqueId']))) {
            $sQuery = "SELECT covid19_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_covid19 ";
            if (isset($data['uniqueId']) && !empty($data['uniqueId'])) {
                $sQuery .= "where unique_id like '" . $data['uniqueId'] . "'";
            } else if (isset($data['sampleCode']) && !empty($data['sampleCode'])) {
                $sQuery .= "where sample_code like '" . $data['sampleCode'] . "'";
            } else if (isset($data['remoteSampleCode']) != "" && !empty($data['remoteSampleCode'])) {
                $sQuery .= "where remote_sample_code like '" . $data['sampleCode'] . "'";
            }
            $sQuery .= "limit 1";
            $rowData = $db->rawQueryOne($sQuery);
            if ($rowData) {
                $update = "yes";
                $sampleData['sampleCode'] = (!empty($rowData['sample_code'])) ? $rowData['sample_code'] : $rowData['remote_sample_code'];
                $sampleData['sampleCodeFormat'] = (!empty($rowData['sample_code_format'])) ? $rowData['sample_code_format'] : $rowData['remote_sample_code_format'];
                $sampleData['sampleCodeKey'] = (!empty($rowData['sample_code_key'])) ? $rowData['sample_code_key'] : $rowData['remote_sample_code_key'];
            } else {
                $sampleJson = $app->generateSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, null, $user, 'covid19');
                $sampleData = json_decode($sampleJson, true);
            }
        } else {
            $sampleJson = $app->generateSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, null, $user, 'covid19');
            $sampleData = json_decode($sampleJson, true);
        }

        if (!isset($data['formId']) || $data['formId'] == '') {
            $data['formId'] = '';
        }
        $covid19Data = array(
            'vlsm_country_id' => $data['formId'],
            'sample_collection_date' => $data['sampleCollectionDate'],
            'vlsm_instance_id' => $data['instanceId'],
            'province_id' => $provinceId,
            'request_created_by' => '',
            'request_created_datetime' => $general->getDateTime(),
            'last_modified_by' => '',
            'last_modified_datetime' => $general->getDateTime()
        );

        if ($user['access_type'] != 'testing-lab') {
            $covid19Data['remote_sample_code'] = (isset($sampleData['sampleCode']) && $sampleData['sampleCode'] != "") ? $sampleData['sampleCode'] : null;
            $covid19Data['remote_sample_code_format'] = (isset($sampleData['sampleCodeFormat']) && $sampleData['sampleCodeFormat'] != "") ? $sampleData['sampleCodeFormat'] : null;
            $covid19Data['remote_sample_code_key'] = (isset($sampleData['sampleCodeKey']) && $sampleData['sampleCodeKey'] != "") ? $sampleData['sampleCodeKey'] : null;
            $covid19Data['remote_sample'] = 'yes';
            $covid19Data['result_status'] = 9;
            /* if ($roleUser['access_type'] == 'testing-lab') {
                $covid19Data['sample_code'] = !empty($data['appSampleCode']) ? $data['appSampleCode'] : null;
            } */
        } else {
            $covid19Data['sample_code'] = (isset($sampleData['sampleCode']) && $sampleData['sampleCode'] != "") ? $sampleData['sampleCode'] : null;
            $covid19Data['sample_code_format'] = (isset($sampleData['sampleCodeFormat']) && $sampleData['sampleCodeFormat'] != "") ? $sampleData['sampleCodeFormat'] : null;
            $covid19Data['sample_code_key'] = (isset($sampleData['sampleCodeKey']) && $sampleData['sampleCodeKey'] != "") ? $sampleData['sampleCodeKey'] : null;
            $covid19Data['remote_sample'] = 'no';
            $covid19Data['result_status'] = 6;
        }
        $id = 0;
        if ($rowData) {
            $db = $db->where('covid19_id', $rowData['covid19_id']);
            $id = $db->update("form_covid19", $covid19Data);
            $data['covid19SampleId'] = $rowData['covid19_id'];
        } else {
            $id = $db->insert("form_covid19", $covid19Data);
            $data['covid19SampleId'] = $id;
        }
        $tableName = "form_covid19";
        $tableName1 = "activity_log";
        $testTableName = 'covid19_tests';

        $instanceId = '';
        if (empty($instanceId) && $data['instanceId']) {
            $instanceId = $data['instanceId'];
        }
        if (!empty($data['arrivalDateTime']) && trim($data['arrivalDateTime']) != "") {
            $arrivalDate = explode(" ", $data['arrivalDateTime']);
            $data['arrivalDateTime'] = $general->dateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
        } else {
            $data['arrivalDateTime'] = NULL;
        }


        if (empty(trim($data['sampleCode']))) {
            $data['sampleCode'] = NULL;
        }

        $status = 6;
        if ($roleUser['access_type'] != 'testing-lab') {
            $status = 9;
        }

        if (isset($data['isSampleRejected']) && $data['isSampleRejected'] == 'yes') {
            $data['result'] = null;
            $status = 4;
        } else if ($data['isSampleRejected'] == 'no' && isset($data['result']) && !empty($data['result']) && isset($data['approvedBy']) && !empty($data['approvedBy'])) {
            $status = 7;
        }

        if (!empty($data['sampleCollectionDate']) && trim($data['sampleCollectionDate']) != "") {
            $sampleCollectionDate = explode(" ", $data['sampleCollectionDate']);
            $data['sampleCollectionDate'] = $general->dateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
        } else {
            $data['sampleCollectionDate'] = NULL;
        }

        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim($data['sampleReceivedDate']) != "") {
            $sampleReceivedDate = explode(" ", $data['sampleReceivedDate']);
            $data['sampleReceivedDate'] = $general->dateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
        } else {
            $data['sampleReceivedDate'] = NULL;
        }
        if (!empty($data['sampleTestedDateTime']) && trim($data['sampleTestedDateTime']) != "") {
            $sampleTestedDate = explode(" ", $data['sampleTestedDateTime']);
            $data['sampleTestedDateTime'] = $general->dateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
        } else {
            $data['sampleTestedDateTime'] = NULL;
        }

        if (!empty($data['arrivalDateTime']) && trim($data['arrivalDateTime']) != "") {
            $arrivalDate = explode(" ", $data['arrivalDateTime']);
            $data['arrivalDateTime'] = $general->dateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
        } else {
            $data['arrivalDateTime'] = NULL;
        }

        if (!empty($data['revisedOn']) && trim($data['revisedOn']) != "") {
            $revisedOn = explode(" ", $data['revisedOn']);
            $data['revisedOn'] = $general->dateFormat($revisedOn[0]) . " " . $revisedOn[1];
        } else {
            $data['revisedOn'] = NULL;
        }

        if (isset($data['approvedOn']) && trim($data['approvedOn']) != "") {
            $approvedOn = explode(" ", $data['approvedOn']);
            $data['approvedOn'] = $general->dateFormat($approvedOn[0]) . " " . $approvedOn[1];
        } else {
            $data['approvedOn'] = NULL;
        }

        if (isset($data['reviewedOn']) && trim($data['reviewedOn']) != "") {
            $reviewedOn = explode(" ", $data['reviewedOn']);
            $data['reviewedOn'] = $general->dateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
        } else {
            $data['reviewedOn'] = NULL;
        }

        $covid19Data = array(
            'vlsm_instance_id'                    => $instanceId,
            'vlsm_country_id'                     => $data['formId'],
            'unique_id'                           => isset($data['uniqueId']) ? $data['uniqueId'] : null,
            'app_sample_code'                     => !empty($data['appSampleCode']) ? $data['appSampleCode'] : null,
            'external_sample_code'                => !empty($data['externalSampleCode']) ? $data['externalSampleCode'] : null,
            'facility_id'                         => !empty($data['facilityId']) ? $data['facilityId'] : null,
            'investigator_name'                   => !empty($data['investigatorName']) ? $data['investigatorName'] : null,
            'investigator_phone'                  => !empty($data['investigatorPhone']) ? $data['investigatorPhone'] : null,
            'investigator_email'                  => !empty($data['investigatorEmail']) ? $data['investigatorEmail'] : null,
            'clinician_name'                      => !empty($data['clinicianName']) ? $data['clinicianName'] : null,
            'clinician_phone'                     => !empty($data['clinicianPhone']) ? $data['clinicianPhone'] : null,
            'clinician_email'                     => !empty($data['clinicianEmail']) ? $data['clinicianEmail'] : null,
            'test_number'                         => !empty($data['testNumber']) ? $data['testNumber'] : null,
            'province_id'                         => !empty($data['provinceId']) ? $data['provinceId'] : null,
            'lab_id'                              => !empty($data['labId']) ? $data['labId'] : null,
            'testing_point'                       => !empty($data['testingPoint']) ? $data['testingPoint'] : null,
            'implementing_partner'                => !empty($data['implementingPartner']) ? $data['implementingPartner'] : null,
            'source_of_alert'                     => !empty($data['sourceOfAlertPOE']) ? strtolower(str_replace(" ", "-", $data['sourceOfAlertPOE'])) : null,
            'source_of_alert_other'               => (!empty($data['sourceOfAlertPOE']) && $data['sourceOfAlertPOE'] == 'others') ? $data['alertPoeOthers'] : null,
            'funding_source'                      => !empty($data['fundingSource']) ? $data['fundingSource'] : null,
            'patient_id'                          => !empty($data['patientId']) ? $data['patientId'] : null,
            'patient_name'                        => !empty($data['firstName']) ? (string)trim($data['firstName']) : null,
            'patient_surname'                     => !empty($data['lastName']) ? $data['lastName'] : null,
            'patient_dob'                         => !empty($data['patientDob']) ? $general->dateFormat($data['patientDob']) : null,
            'patient_gender'                      => !empty($data['patientGender']) ? $data['patientGender'] : null,
            'is_patient_pregnant'                 => !empty($data['isPatientPregnant']) ? $data['isPatientPregnant'] : null,
            'patient_age'                         => !empty($data['patientAge']) ? $data['patientAge'] : null,
            'patient_phone_number'                => !empty($data['patientPhoneNumber']) ? $data['patientPhoneNumber'] : null,
            'patient_address'                     => !empty($data['patientAddress']) ? $data['patientAddress'] : null,
            'patient_province'                    => !empty($data['patientProvince']) ? $data['patientProvince'] : null,
            'patient_district'                    => !empty($data['patientDistrict']) ? $data['patientDistrict'] : null,
            'patient_city'                        => !empty($data['patientCity']) ? $data['patientCity'] : null,
            'patient_zone'                        => !empty($data['patientZone']) ? $data['patientZone'] : null,
            'patient_occupation'                  => !empty($data['patientOccupation']) ? $data['patientOccupation'] : null,
            'does_patient_smoke'                  => !empty($data['doesPatientSmoke']) ? $data['doesPatientSmoke'] : null,
            'patient_nationality'                 => !empty($data['patientNationality']) ? $data['patientNationality'] : null,
            'patient_passport_number'             => !empty($data['patientPassportNumber']) ? $data['patientPassportNumber'] : null,
            'flight_airline'                      => !empty($data['airline']) ? $data['airline'] : null,
            'flight_seat_no'                      => !empty($data['seatNo']) ? $data['seatNo'] : null,
            'flight_arrival_datetime'             => !empty($data['arrivalDateTime']) ? $data['arrivalDateTime'] : null,
            'flight_airport_of_departure'         => !empty($data['airportOfDeparture']) ? $data['airportOfDeparture'] : null,
            'flight_transit'                      => !empty($data['transit']) ? $data['transit'] : null,
            'reason_of_visit'                     => !empty($data['reasonOfVisit']) ? $data['reasonOfVisit'] : null,
            'is_sample_collected'                 => !empty($data['isSampleCollected']) ? $data['isSampleCollected'] : null,
            'reason_for_covid19_test'             => !empty($data['reasonForCovid19Test']) ? $data['reasonForCovid19Test'] : null,
            'type_of_test_requested'              => !empty($data['testTypeRequested']) ? $data['testTypeRequested'] : null,
            'specimen_type'                       => !empty($data['specimenType']) ? $data['specimenType'] : null,
            'sample_collection_date'              => !empty($data['sampleCollectionDate']) ? $data['sampleCollectionDate'] : null,
            'health_outcome'                      => !empty($data['healthOutcome']) ? $data['healthOutcome'] : null,
            'health_outcome_date'                 => !empty($data['outcomeDate']) ? $general->dateFormat($data['outcomeDate']) : null,
            // 'is_sampledata_mortem'                => !empty($data['isSamplePostMortem']) ? $data['isSamplePostMortem'] : null,
            'priority_status'                     => !empty($data['priorityStatus']) ? $data['priorityStatus'] : null,
            'number_of_days_sick'                 => !empty($data['numberOfDaysSick']) ? $data['numberOfDaysSick'] : null,
            'suspected_case'                      => !empty($data['suspectedCase']) ? $data['suspectedCase'] : null,
            'date_of_symptom_onset'               => !empty($data['dateOfSymptomOnset']) ? $general->dateFormat($data['dateOfSymptomOnset']) : null,
            'date_of_initial_consultation'        => !empty($data['dateOfInitialConsultation']) ? $general->dateFormat($data['dateOfInitialConsultation']) : null,
            'fever_temp'                          => !empty($data['feverTemp']) ? $data['feverTemp'] : null,
            'medical_history'                     => !empty($data['medicalHistory']) ? $data['medicalHistory'] : null,
            'recent_hospitalization'              => !empty($data['recentHospitalization']) ? $data['recentHospitalization'] : null,
            'patient_lives_with_children'         => !empty($data['patientLivesWithChildren']) ? $data['patientLivesWithChildren'] : null,
            'patient_cares_for_children'          => !empty($data['patientCaresForChildren']) ? $data['patientCaresForChildren'] : null,
            'temperature_measurement_method'      => !empty($data['temperatureMeasurementMethod']) ? $data['temperatureMeasurementMethod'] : null,
            'respiratory_rate'                    => !empty($data['respiratoryRate']) ? $data['respiratoryRate'] : null,
            'oxygen_saturation'                   => !empty($data['oxygenSaturation']) ? $data['oxygenSaturation'] : null,
            'close_contacts'                      => !empty($data['closeContacts']) ? $data['closeContacts'] : null,
            'contact_with_confirmed_case'         => !empty($data['contactWithConfirmedCase']) ? $data['contactWithConfirmedCase'] : null,
            'has_recent_travel_history'           => !empty($data['hasRecentTravelHistory']) ? $data['hasRecentTravelHistory'] : null,
            'travel_country_names'                => !empty($data['countryName']) ? $data['countryName'] : null,
            'travel_return_date'                  => !empty($data['returnDate']) ? $general->dateFormat($data['returnDate']) : null,
            'sample_received_at_vl_lab_datetime'  => !empty($data['sampleReceivedDate']) ? $data['sampleReceivedDate'] : null,
            'sample_condition'                    => !empty($data['sampleCondition']) ? $data['sampleCondition'] : (isset($data['specimenQuality']) ? $data['specimenQuality'] : null),
            'asymptomatic'                             => !empty($data['asymptomatic']) ? $data['asymptomatic'] : null,
            'lab_technician'                      => (!empty($data['labTechnician']) && $data['labTechnician'] != '') ? $data['labTechnician'] :  $user['user_id'],
            'is_sample_rejected'                  => !empty($data['isSampleRejected']) ? $data['isSampleRejected'] : null,
            'result'                              => !empty($data['result']) ? $data['result'] : null,
            'if_have_other_diseases'              => (!empty($data['ifOtherDiseases'])) ? $data['ifOtherDiseases'] : null,
            'other_diseases'                      => (!empty($data['otherDiseases']) && $data['result'] != 'positive') ? $data['otherDiseases'] : null,
            'tested_by'                           => !empty($data['testedBy']) ? $data['testedBy'] : null,
            'is_result_authorised'                => !empty($data['isResultAuthorized']) ? $data['isResultAuthorized'] : null,
            'authorized_by'                       => !empty($data['authorizedBy']) ? $data['authorizedBy'] : null,
            'authorized_on'                       => !empty($data['authorizedOn']) ? $general->dateFormat($data['authorizedOn']) : null,
            'revised_by'                          => (isset($_POST['revisedBy']) && $_POST['revisedBy'] != "") ? $_POST['revisedBy'] : "",
            'revised_on'                          => (isset($_POST['revisedOn']) && $_POST['revisedOn'] != "") ? $_POST['revisedOn'] : "",
            'result_reviewed_by'                   => (isset($data['reviewedBy']) && $data['reviewedBy'] != "") ? $data['reviewedBy'] : "",
            'result_reviewed_datetime'               => (isset($data['reviewedOn']) && $data['reviewedOn'] != "") ? $data['reviewedOn'] : null,
            'result_approved_by'                   => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedBy'] :  NULL,
            'result_approved_datetime'               => (isset($data['approvedOn']) && $data['approvedOn'] != '') ? $data['approvedOn'] :  NULL,
            'reason_for_changing'                 => (!empty($_POST['reasonForCovid19ResultChanges']) && !empty($_POST['reasonForCovid19ResultChanges'])) ? $_POST['reasonForCovid19ResultChanges'] : null,
            'rejection_on'                        => (!empty($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? $general->dateFormat($data['rejectionDate']) : null,
            'result_status'                       => $status,
            'data_sync'                           => 0,
            'reason_for_sample_rejection'         => (isset($data['sampleRejectionReason']) && $data['isSampleRejected'] == 'yes') ? $data['sampleRejectionReason'] : null,
            'source_of_request'                   => "app"
        );
        if ($rowData) {
            $covid19Data['last_modified_datetime']  = $general->getDateTime();
            $covid19Data['last_modified_by']  = $user['user_id'];
        } else {
            $covid19Data['request_created_datetime']  = (isset($data['sampleRejectionReason']) && $data['isSampleRejected'] == 'yes') ? $data['sampleRejectionReason'] : $general->getDateTime();
            $covid19Data['sample_registered_at_lab']  = $general->getDateTime();
            $covid19Data['request_created_by']  = $user['user_id'];
        }
        // $lock = $general->getGlobalConfig('lock_approved_covid19_samples');
        // if ($status == 7 && $lock == 'yes') {
        //     $covid19Data['locked'] = 'yes';
        // }
        $covid19Data['request_created_by'] =  $user['user_id'];
        $covid19Data['last_modified_by'] =  $user['user_id'];
        if (isset($data['asymptomatic']) && $data['asymptomatic'] != "yes") {
            $db = $db->where('covid19_id', $data['covid19SampleId']);
            $db->delete("covid19_patient_symptoms");
            if (isset($data['symptomDetected']) && !empty($data['symptomDetected']) || (isset($data['symptom']) && !empty($data['symptom']))) {
                for ($i = 0; $i < count($data['symptomDetected']); $i++) {
                    $symptomData = array();
                    $symptomData["covid19_id"] = $data['covid19SampleId'];
                    $symptomData["symptom_id"] = $data['symptomId'][$i];
                    $symptomData["symptom_detected"] = $data['symptomDetected'][$i];
                    $symptomData["symptom_details"]     = (isset($data['symptomDetails'][$data['symptomId'][$i]]) && count($data['symptomDetails'][$data['symptomId'][$i]]) > 0) ? json_encode($data['symptomDetails'][$data['symptomId'][$i]]) : null;
                    //var_dump($symptomData);
                    $db->insert("covid19_patient_symptoms", $symptomData);
                }
            }
        }

        $db = $db->where('covid19_id', $data['covid19SampleId']);
        $db->delete("covid19_reasons_for_testing");
        if (!empty($data['reasonDetails'])) {
            $reasonData = array();
            $reasonData["covid19_id"]         = $data['covid19SampleId'];
            $reasonData["reasons_id"]         = $data['reasonForCovid19Test'];
            $reasonData["reasons_detected"]    = "yes";
            $reasonData["reason_details"]     = json_encode($data['reasonDetails']);
            $db->insert("covid19_reasons_for_testing", $reasonData);
        }

        $db = $db->where('covid19_id', $data['covid19SampleId']);
        $db->delete("covid19_patient_comorbidities");
        if (isset($data['comorbidityDetected']) && !empty($data['comorbidityDetected'])) {
            for ($i = 0; $i < count($data['comorbidityDetected']); $i++) {
                $comorbidityData = array();
                $comorbidityData["covid19_id"] = $data['covid19SampleId'];
                $comorbidityData["comorbidity_id"] = $data['comorbidityId'][$i];
                $comorbidityData["comorbidity_detected"] = $data['comorbidityDetected'][$i];
                $db->insert("covid19_patient_comorbidities", $comorbidityData);
            }
        }
        if (isset($data['covid19SampleId']) && $data['covid19SampleId'] != '' && ($data['isSampleRejected'] == 'no' || $data['isSampleRejected'] == '')) {
            if (isset($data['c19Tests']) && count($data['c19Tests']) > 0) {
                $db = $db->where('covid19_id', $data['covid19SampleId']);
                $db->delete($testTableName);
                foreach ($data['c19Tests'] as $testKey => $test) {
                    if (isset($test['testName']) && !empty($test['testName'])) {
                        if (isset($test['testDate']) && trim($test['testDate']) != "") {
                            $testedDateTime = explode(" ", $test['testDate']);
                            $test['testDate'] = $general->dateFormat($testedDateTime[0]) . " " . $testedDateTime[1];
                        } else {
                            $test['testDate'] = NULL;
                        }
                        $covid19TestData = array(
                            'covid19_id'             => $data['covid19SampleId'],
                            'test_name'              => ($test['testName'] == 'other') ? $test['testNameOther'] : $test['testName'],
                            'facility_id'            => isset($data['labId']) ? $data['labId'] : null,
                            'sample_tested_datetime' => date('Y-m-d H:i:s', strtotime($test['testDate'])),
                            'testing_platform'       => isset($test['testingPlatform']) ? $test['testingPlatform'] : null,
                            'kit_lot_no'             => (strpos($test['testName'], 'RDT') !== false) ? $test['kitLotNo'] : null,
                            'kit_expiry_date'        => (strpos($test['testName'], 'RDT') !== false) ? $general->dateFormat($test['kitExpiryDate']) : null,
                            'result'                 => $test['testResult'],
                        );
                        $db->insert($testTableName, $covid19TestData);
                        $covid19Data['sample_tested_datetime'] = date('Y-m-d H:i:s', strtotime($test['testDate']));
                    }
                }
            }
        } else {
            $db = $db->where('covid19_id', $data['covid19SampleId']);
            $db->delete($testTableName);
            $covid19Data['sample_tested_datetime'] = null;
        }
        $id = 0;
        if (!empty($data['covid19SampleId'])) {
            $db = $db->where('covid19_id', $data['covid19SampleId']);
            $id = $db->update($tableName, $covid19Data);
        }
        $responseData = array();
        if ($id > 0) {
            $c19Data = $app->getTableDataUsingId('form_covid19', 'covid19_id', $data['covid19SampleId']);
            $c19SampleCode = (isset($c19Data['sample_code']) && $c19Data['sample_code']) ? $c19Data['sample_code'] : $c19Data['remote_sample_code'];
            if (isset($data['appSampleCode']) && $data['appSampleCode'] != "") {
                $responseData[$rootKey] = array(
                    'status' => 'success',
                    'sampleCode' => $c19SampleCode,
                    'uniqueId' => $c19Data['unique_id'],
                    'appSampleCode' => $c19Data['app_sample_code'],
                );
            } else {
                $responseData[$rootKey] = array(
                    'status' => 'success',
                    'sampleCode' => $c19SampleCode,
                    'uniqueId' => $c19Data['unique_id'],
                    'appSampleCode' => $c19Data['app_sample_code'],
                );
            }
            http_response_code(200);
        } else {
            if (isset($data['appSampleCode']) && $data['appSampleCode'] != "") {
                $responseData[$rootKey] = array(
                    'status' => 'failed'
                );
            } else {
                $payload = array(
                    'status' => 'failed',
                    'timestamp' => time(),
                    'error' => 'Unable to add this Covid-19 sample. Please try again later',
                    'data' => array()
                );
            }
            http_response_code(301);
        }
        $app = new \Vlsm\Models\App();
        $trackId = $app->addApiTracking($user['user_id'], $data['covid19SampleId'], 'add-request', 'covid19', $requestUrl, $params, 'json');
    }
    if ($update == "yes") {
        $msg = 'Successfully updated.';
    } else {
        $msg = 'Successfully added.';
    }
    if (isset($responseData) && count($responseData) > 0) {
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
            'message' => $msg,
            'data'  => $responseData
        );
    } else {
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
            'message' => $msg
        );
    }
    if (isset($user['token_updated']) && $user['token_updated'] == true) {
        $payload['token'] = $user['new_token'];
    } else {
        $payload['token'] = null;
    }
    http_response_code(200);
    echo json_encode($payload);
    exit(0);
} catch (Exception $exc) {

    http_response_code(500);
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );


    echo json_encode($payload);

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    exit(0);
}
