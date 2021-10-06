<?php

// this file is included in /covid-19/interop/dhis2/covid-19-receive.php

$programStages = [
    'clinicalExaminationAndDiagnosis' => 'LpWNjNGvCO5',
    'labRequest' => 'iR8O4hSLHnu',
    'labReception' => 'QaAb8G10EKp',
    'labProcessingAndResults' => 'CTdzCeTbYay',
    'patientConditionAndManagement' => 'QHr9W5Gr1ao',
    'finalTestResult' => 'l4KoHCW02x7',
    'healthOutcome' => 'dZXkdh0kR3x',
    'specimenManagement' => 'FaXWNZei3np',
];


$attributesDataElementMapping = [
    'HAZ7VQ730yn' => 'external_sample_code', //dhis2 case id
    'yCWkkKtr6vd' => 'source_of_alert',
    'he05i8FUwu3' => 'patient_id',
    'sB1IHYu2xQT' => 'patient_name',
    'ENRjVGxVL6l' => 'patient_surname',
    'NI0QRzJvQ0k' => 'patient_dob',
    'Rv8WM2mTuS5' => 'patient_age',
    'oindugucx72' => 'patient_gender',
    'aMJNeET3i7B' => 'patient_occupation',
    'fctSQp5nAYl' => 'patient_phone_number',
    'qlYg7fundnJ' => 'patient_nationality'
];

$eventsDataElementMapping = [
    'Q98LhagGLFj' => 'sample_collection_date',
    'H3UJlHuglGv' => 'reason_for_covid19_test',
    'b4PEeF4OOwc' => 'covid19_test_platform',
    'P61FWjSAjjA' => 'sample_condition',
    'bujqZ6Dqn4m' => 'lab_id',
    'kL7PTi4lRSl' => 'specimen_type',
    'pxPdKaS9CqF' => 'sample_received_datetime',
    'Cl2I1H6Y3oj' => 'sample_tested_datetime',
    //'f5HxreMlOWP' => 'result',
    'ovY6E8BSdto' => 'result'
];


$general = new \Vlsm\Models\General($db);
$covid19Model = new \Vlsm\Models\Covid19($db);


$vlsmSystemConfig = $general->getSystemConfig();

$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);

$receivedCounter = 0;
$processedCounter = 0;


// https://southsudanhis.org/covid19southsudan/api/trackedEntityInstances.json?programStartDate=2020-04-01&programEndDate=2021-04-02&ou=OV9zi20DDXP&ouMode=DESCENDANTS&program=uYjxkTbwRNf&fields=attributes[attribute,code,value],enrollments[*],orgUnit,trackedEntityInstance&paging=false

$counter = 0;
$data = array();
$data[] = "lastUpdatedDuration=200d";
$data[] = "ou=OV9zi20DDXP"; // South Sudan
$data[] = "ouMode=DESCENDANTS";
$data[] = "program=uYjxkTbwRNf";
$data[] = "fields=attributes[attribute,code,value],enrollments[*],orgUnit,trackedEntityInstance";
$data[] = "paging=false";

$url = "/api/trackedEntityInstances.json";

$jsonResponse = $dhis2->get($url, $data);

if($jsonResponse == '' || empty($jsonResponse)) die('No Response from API');

$trackedEntityInstances = \JsonMachine\JsonMachine::fromString($jsonResponse, "/trackedEntityInstances");

// echo "<pre>";
// var_dump(iterator_to_array($trackedEntityInstances));
// echo "</pre>";
// die;



// echo ("<h5>...</h5>");
// echo ("<h5>...</h5>");
// echo ("<h3>Total trackers received from DHIS2 : " . count($response['trackedEntityInstances']) . "</h3>");

foreach ($trackedEntityInstances as $tracker) {

    $receivedCounter++;

    $formData = array();
    $screeningEventIds = array();
    $enrollmentDate = null;

    // $facility = $tracker['orgUnit'];
    // $formData['source_of_request'] = "dhis2-" . $tracker['trackedEntityInstance'];
    // $formData['source_data_dump'] = json_encode($tracker);

    foreach ($tracker['enrollments'] as $enrollments) {

        $allProgramStages = array_column($enrollments['events'], 'programStage', 'event');

        $screeningEventIds = array_keys($allProgramStages, $programStages['labRequest']); // screening programStage

        if (count($screeningEventIds) == 0)  continue 2; // if no screening stage, skip this tracker entirely

        //echo "<pre>";var_dump($enrollments['events']);echo "</pre>";

        $enrollmentDate = explode("T", $enrollments['enrollmentDate']);
        $enrollmentDate = $enrollmentDate[0];

        $eventsData = array();
        foreach ($enrollments['events'] as $event) {

            if ($event['programStage'] != $programStages['labRequest']) continue;

            foreach ($event['dataValues'] as $dV) {
                if (empty($eventsDataElementMapping[$dV['dataElement']])) continue;
                $eventsData["dhis2::" . $tracker['trackedEntityInstance'] . "::" . $event['event']][$eventsDataElementMapping[$dV['dataElement']]] = $dV['value'];
            }
        }
    }

    $attributesData = array();
    foreach ($tracker['attributes'] as $trackerAttr) {
        if (empty($attributesDataElementMapping[$trackerAttr['attribute']])) continue;
        //echo $attributesDataElementMapping[$trackerAttr['attribute']] . "%%%%%%%" . $trackerAttr['value'] . PHP_EOL . PHP_EOL;
        $attributesData[$attributesDataElementMapping[$trackerAttr['attribute']]] = $trackerAttr['value'];
    }


    // echo "<h1>";
    // var_dump($tracker['trackedEntityInstance']);
    // echo "</h1>";


    foreach ($eventsData as $sourceOfRequest => $singleEventData) {
        $formData = array_merge($singleEventData, $attributesData);
        $formData['source_of_request'] = $sourceOfRequest;
        $formData['source_data_dump'] = json_encode($tracker);


        $facility = $tracker['orgUnit'];


        $formData['sample_collection_date'] = (!empty($formData['sample_collection_date']) ?  $formData['sample_collection_date'] : $enrollmentDate);

        // Reason for Testing
        if (!empty($formData['reason_for_covid19_test'])) {
            $db->where("test_reason_name", $formData['reason_for_covid19_test']);
            $reason = $db->getOne("r_covid19_test_reasons");
            if (!empty($reason) && $reason != false) {
                $formData['reason_for_covid19_test'] = $reason['test_reason_id'];
            } else {
                $reasonData = array(
                    'test_reason_name' => $formData['reason_for_covid19_test'],
                    'test_reason_status' => 'active',
                    'updated_datetime' => $general->getDateTime()
                );
                $formData['reason_for_covid19_test'] =   $db->insert("r_covid19_test_reasons", $reasonData);
            }
        }



        $db->where("iso3", $formData['patient_nationality']);
        $country = $db->getOne("r_countries");
        $formData['patient_nationality'] = $country['id'];

        // Platform
        if (!empty($formData['covid19_test_platform'])) {
            $db->where("machine_name", $formData['covid19_test_platform']);
            $testPlatform = $db->getOne("import_config");
            $formData['covid19_test_platform'] = $testPlatform['config_id'];
        } else {
            $formData['covid19_test_platform'] = null;
        }

        // Lab ID
        if (!empty($formData['lab_id'])) {
            $db->where("facility_name", $formData['lab_id']);
            $db->orWhere("other_id", $formData['lab_id']);
            $lab = $db->getOne("facility_details");
            $formData['lab_id'] = $lab['facility_id'];
        } else {
            $formData['lab_id'] = null;
        }


        // Facility ID
        $db->where("other_id", $facility);
        $fac = $db->getOne("facility_details");
        $formData['facility_id'] =  $fac['facility_id'];

        $db->where("province_name", $fac['facility_state']);
        $prov = $db->getOne("province_details");

        $formData['province_id'] = !empty($prov['province_id']) ? $prov['province_id'] : 1;


        //Specimen Type
        if (!empty($formData['specimen_type'])) {
            $db->where("sample_name", $formData['specimen_type']);
            $sampleType = $db->getOne("r_covid19_sample_type");

            if (!empty($sampleType) && $sampleType != false) {
                $formData['specimen_type'] = $sampleType['sample_id'];
            } else {
                $sampleTypeData = array(
                    'sample_name' => $formData['specimen_type'],
                    'status' => 'active',
                    'updated_datetime' => $general->getDateTime()
                );
                $formData['specimen_type'] = $db->insert("r_covid19_sample_type", $sampleTypeData);
            }
        }


    
        $status = 6;
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $status = 9;
        }

        $formData['result_status'] = $status;
        $formData['last_modified_datetime'] = $general->getDateTime();


        $formData['patient_gender'] = (!empty($formData['patient_gender']) ? strtolower($formData['patient_gender']) : null);
        if (!empty($formData['specimen_quality'])) {
            $formData['specimen_quality'] =  strtolower($formData['specimen_quality']);
        }


        // all the columns at this point will be in update columns list
        // the columns below this are only for inserting
        $updateColumns = array_keys($formData);




        $sampleJson = $covid19Model->generateCovid19SampleCode(null, $general->humanDateFormat($formData['sample_collection_date']), null, $formData['province_id']);

        $sampleData = json_decode($sampleJson, true);

        $formData['unique_id'] = $general->generateRandomString(32);

        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $sampleCode = 'remote_sample_code';
            $sampleCodeKey = 'remote_sample_code_key';
            $sampleCodeFormat = 'remote_sample_code_format';
        } else {
            $sampleCode = 'sample_code';
            $sampleCodeKey = 'sample_code_key';
            $sampleCodeFormat = 'sample_code_format';
        }

        $formData[$sampleCode] = $sampleData['sampleCode'];
        $formData[$sampleCodeFormat] = $sampleData['sampleCodeFormat'];
        $formData[$sampleCodeKey] = $sampleData['sampleCodeKey'];

        $formData['request_created_by'] = 1;
        $formData['request_created_datetime'] = $general->getDateTime();

        $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

        $formData['vlsm_instance_id'] = $instanceResult['vlsm_instance_id'];
        $formData['vlsm_country_id'] = 1; // South Sudan

        //echo "<pre>";var_dump($formData);echo "</pre>";continue 2;

        $db->onDuplicate($updateColumns, 'source_of_request');
        $id = $db->insert("form_covid19", $formData);
        // echo "<h1>IDIDIDI: $id</h1>";
        if ($id != false) {
            $processedCounter++;
        }
    }
}

$response = array('received' => $receivedCounter, 'processed' => $processedCounter);

echo (json_encode($response));
