<?php

// this file is included in /covid-19/interop/dhis2/covid-19-receive.php


$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);


// https://southsudanhis.org/covid19southsudan/api/trackedEntityInstances.json?programStartDate=2020-04-01&programEndDate=2021-04-02&ou=OV9zi20DDXP&ouMode=DESCENDANTS&program=uYjxkTbwRNf&fields=attributes[attribute,code,value],enrollments[*],orgUnit,trackedEntityInstance&paging=false

$counter = 0;
$data = array();
$data[] = "lastUpdatedDuration=300d";
$data[] = "ou=OV9zi20DDXP"; // South Sudan
$data[] = "ouMode=DESCENDANTS";
$data[] = "program=uYjxkTbwRNf";
$data[] = "fields=attributes[attribute,code,value],enrollments[*],orgUnit,trackedEntityInstance";
$data[] = "paging=false";

$url = "/api/trackedEntityInstances.json";

$response = $dhis2->get($url, $data);

$response = json_decode($response, true);

$attributesDataElementMapping = [
    'HAZ7VQ730yn' => 'external_sample_code', //dhis2 case id
    'yCWkkKtr6vd' => 'source_of_alert',
    'he05i8FUwu3' => 'patient_id',
    'sB1IHYu2xQT' => 'patient_name',
    'tIlOLmSOBGs' => 'patient_surname',
    'NI0QRzJvQ0k' => 'patient_dob',
    'Rv8WM2mTuS5' => 'patient_age',
    'oindugucx72' => 'patient_gender',
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
    'f5HxreMlOWP' => 'result'
];


// echo ("<h5>...</h5>");
// echo ("<h5>...</h5>");
// echo ("<h3>Total trackers received from DHIS2 : " . count($response['trackedEntityInstances']) . "</h3>");

foreach ($response['trackedEntityInstances'] as $tracker) {

    $formData = array();
    $facility = $tracker['orgUnit'];
    $formData['source_of_request'] = "dhis2-" . $tracker['trackedEntityInstance'];
    $formData['source_data_dump'] = json_encode($tracker);

    foreach ($tracker['attributes'] as $trackerAttr) {
        if (empty($attributesDataElementMapping[$trackerAttr['attribute']])) continue;
        //echo $attributesDataElementMapping[$trackerAttr['attribute']] . "%%%%%%%" . $trackerAttr['value'] . PHP_EOL . PHP_EOL;
        $formData[$attributesDataElementMapping[$trackerAttr['attribute']]] = $trackerAttr['value'];
    }




    foreach ($tracker['enrollments'] as $allEnrollments) {


        //if($allEnrollments['status'] != 'ACTIVE') var_dump($tracker); die('RONA');

        $enrollmentDate = explode("T", $allEnrollments['enrollmentDate']);
        $enrollmentDate = $enrollmentDate[0];
        foreach ($allEnrollments['events'] as $enrollmentEvent) {

            // iR8O4hSLHnu, CTdzCeTbYay
            //if ($enrollmentEvent['programStage'] == 'CTdzCeTbYay') {



            foreach ($enrollmentEvent['dataValues'] as $dV) {
                if (empty($eventsDataElementMapping[$dV['dataElement']])) continue;
                //echo $eventsDataElementMapping[$dV['dataElement']] . "======" . $dV['value'] . PHP_EOL . PHP_EOL;
                $formData[$eventsDataElementMapping[$dV['dataElement']]] = $dV['value'];
            }
            //}
        }
        //die;
    }

    $formData['sample_collection_date'] = (!empty($formData['sample_collection_date']) ?  $formData['sample_collection_date'] : $enrollmentDate);

    // Reason for Testing
    if(!empty($formData['reason_for_covid19_test'])){
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
    $db->where("machine_name", $formData['covid19_test_platform']);
    $testPlatform = $db->getOne("import_config");
    $formData['covid19_test_platform'] = $testPlatform['config_id'];

    // Lab ID
    $db->where("facility_name", $formData['lab_id']);
    $lab = $db->getOne("facility_details");
    $formData['lab_id'] = $lab['facility_id'];


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



    $formData['result_status'] = 6;


    $formData['patient_gender'] = (!empty($formData['patient_gender']) ? strtolower($formData['patient_gender']) : null);
    if (!empty($formData['specimen_quality'])) {
        $formData['specimen_quality'] =  strtolower($formData['specimen_quality']);
    }

    $general = new \Vlsm\Models\General($db);
    $covid19Model = new \Vlsm\Models\Covid19($db);


    $db->where("source_of_request", $formData['source_of_request']);
    $covid19Data = $db->getOne("form_covid19");

    $formData['last_modified_datetime'] = $general->getDateTime();

    if (empty($covid19Data) || empty($covid19Data['covid19_id'])) {
        $sampleJson = $covid19Model->generateCovid19SampleCode(null, $general->humanDateFormat($formData['sample_collection_date']), null, $formData['province_id']);

        $sampleData = json_decode($sampleJson, true);

        $formData['sample_code'] = $sampleData['sampleCode'];
        $formData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $formData['sample_code_key'] = $sampleData['sampleCodeKey'];

        $formData['request_created_by'] = 1;
        $formData['request_created_datetime'] = $general->getDateTime();

        $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

        $formData['vlsm_instance_id'] = $instanceResult['vlsm_instance_id'];
        $formData['vlsm_country_id'] = 1;
        $id = $db->insert("form_covid19", $formData);
        if ($id != false) {
            $counter++;
        }
    } else {
        $db = $db->where('covid19_id', $covid19Data['covid19_id']);
        $id = $db->update("form_covid19", $formData);
        if ($id != false) {
            $counter++;
        }
    }
}

$response = array('received' => count($response['trackedEntityInstances']), 'processed' => $counter);

echo (json_encode($response));
