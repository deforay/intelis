<?php

// this file is included in /hepatitis/interop/dhis2/hepatitis-receive.php


$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);

$receivedCounter = 0;
$processedCounter = 0;

$data = array();
$data[] = "lastUpdatedDuration=6m";
$data[] = "ou=Hjw70Lodtf2"; // Rwanda
$data[] = "ouMode=DESCENDANTS";
$data[] = "program=LEhPhsbgfFB";
$data[] = "fields=attributes[attribute,code,value],enrollments[*],orgUnit,trackedEntityInstance";
$data[] = "paging=false";

$url = "/api/trackedEntityInstances.json";

$jsonResponse = $dhis2->get($url, $data);

//var_dump($jsonResponse);die;

$trackedEntityInstances = \JsonMachine\JsonMachine::fromString($jsonResponse, "/trackedEntityInstances");

$dhis2GenderOptions = array('Male' => 'male', 'Female' => 'female');
$dhis2SocialCategoryOptions = array('1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D');
//$dhis2VlTestReasonOptions = array('I_VL001' => 'Initial HBV VL', 'HBV_F0012' => 'Follow up HBV VL', 'SVR12_HCV01' => 'SVR12 HCV VL');

$dhis2VlTestReasonOptions = array('Initial Viral Load Test' => 'Initial HBV VL', 'HBV Follow-up Test' => 'Follow up HBV VL', 'SVR12 HCV Viral Load Test' => 'SVR12 HCV VL');

$attributesDataElementMapping = [
    'iwzGzKTlYGR' => 'external_sample_code', //dhis2 case id
    'bVXK3FxmU1L' => 'patient_id',
    'JtuGgGPsSuZ' => 'patient_province',
    'yvkYfTjxEJU' => 'patient_district',
    //'' => 'patient_city',
    'qYpyifGg6Yi' => 'patient_occupation',
    'EEAIP0aO4aR' => 'patient_marital_status',
    'iUkIkQbkxI1' => 'patient_phone_number',
    'BzEcIK9udqH' => 'patient_insurance',
    'p2e195R27TO' => 'patient_name',
    'mtRPhPyLDsv' => 'patient_dob',
    'DP8JyLEof33' => 'social_category',
    'Rq4qM2wKYFL' => 'patient_gender',
    //'' => 'patient_nationality'
];




$eventsDataElementMapping = [
    'GWoBWpKWlWJ' => 'sample_collection_date',
    'hvznTv3ZjXv' => 'hbsag_result',
    'szTAjn4r7yM' => 'anti_hcv_result',
    'Di17rUJDIWZ' => 'hbv_vl_count',
    'Oem0BXNDPWL' => 'hcv_vl_count',
    'Mpc3ftVuSvK' => 'hepatitis_test_type',
    'DMQSNcqWRvI' => 'lab_id',
    'KPFLSlmiY89' => 'reason_for_vl_test'
];


foreach ($trackedEntityInstances as $tracker) {

    $receivedCounter++;


    $formData = array();
    $screeningEventIds = array();
    $enrollmentDate = null;
    //echo "<pre>";var_dump(array_keys($tracker['enrollments']));echo "</pre>";;
    //echo "<pre>";var_dump(($tracker['enrollments']));echo "</pre>";
    foreach ($tracker['enrollments'] as $enrollments) {

        $allProgramStages = array_column($enrollments['events'], 'programStage', 'event');

        $screeningEventIds = array_keys($allProgramStages, 'ZBWBirHgmE6'); // screening programStage

        if (count($screeningEventIds) == 0)  continue 2; // if no screening stage, skip this tracker entirely

        //echo "<pre>";var_dump($enrollments['events']);echo "</pre>";

        $enrollmentDate = explode("T", $enrollments['enrollmentDate']);
        $enrollmentDate = $enrollmentDate[0];

        $eventsData = array();
        foreach ($enrollments['events'] as $event) {

            if ($event['programStage'] != 'ZBWBirHgmE6') continue;

            foreach ($event['dataValues'] as $dV) {
                if (empty($eventsDataElementMapping[$dV['dataElement']])) continue;
                // echo "<h1>". $eventsDataElementMapping[$dV['dataElement']] . "</h1>";
                //echo "<pre>"; var_dump($dV['dataElement']);echo "</pre>";
                //echo "<pre>"; var_dump($dV['value']);echo "</pre>";
                //echo "<h1>". $eventsDataElementMapping[$dV['value']] . "</h1>";
                //echo "<pre>"; var_dump($dV);echo "</pre>";
                $eventsData["dhis2::" . $tracker['trackedEntityInstance'] . "::" . $event['event']][$eventsDataElementMapping[$dV['dataElement']]] = $dV['value'];
                //echo "<h1>". $formData[$eventsDataElementMapping[$dV['dataElement']]] . "</h1>";

            }
        }
    }




    $attributesData = array();
    foreach ($tracker['attributes'] as $trackerAttr) {
        if (empty($attributesDataElementMapping[$trackerAttr['attribute']])) continue;
        //echo $attributesDataElementMapping[$trackerAttr['attribute']] . "%%%%%%%" . $trackerAttr['value'] . PHP_EOL . PHP_EOL;
        $attributesData[$attributesDataElementMapping[$trackerAttr['attribute']]] = $trackerAttr['value'];
    }

    foreach ($eventsData as $sourceOfRequest => $singleEventData) {
        $formData = array_merge($singleEventData, $attributesData);
        $formData['source_of_request'] = $sourceOfRequest;
        $formData['source_data_dump'] = json_encode($tracker);


        $facility = $tracker['orgUnit'];





        //$formData['patient_province'] = $_SESSION['DHIS2_HEP_PROVINCES'][$formData['patient_province']];
        //$formData['patient_district'] = $_SESSION['DHIS2_HEP_DISTRICTS'][$formData['patient_district']];



        if (!empty($formData['reason_for_hepatitis_test'])) {
            $db->where("test_reason_name", $formData['reason_for_hepatitis_test']);
            $reason = $db->getOne("r_hepatitis_test_reasons");
            $formData['reason_for_hepatitis_test'] = $reason['test_reason_id'];
        }


        if (!empty($formData['patient_nationality'])) {

            $db->where("iso3", $formData['patient_nationality']);
            $country = $db->getOne("r_countries");
            $formData['patient_nationality'] = $country['id'];
        }

        if (!empty($formData['lab_id'])) {
            $db->where("facility_name", $formData['lab_id']);
            $db->orWhere("other_id", $formData['lab_id']);
            $lab = $db->getOne("facility_details");
            // echo "<pre>";var_dump($formData['lab_id']);echo "</pre>";
            // echo "<pre>";var_dump($lab);echo "</pre>";
            $formData['lab_id'] = $lab['facility_id'];
        } else {
            $formData['lab_id'] = null;
        }


        $db->where("other_id", $facility);
        $db->orWhere("other_id", $facility);
        $fac = $db->getOne("facility_details");
        $formData['facility_id'] =  $fac['facility_id'];

        $db->where("province_name", $fac['facility_state']);
        $prov = $db->getOne("province_details");

        $formData['province_id'] = !empty($prov['province_id']) ? $prov['province_id'] : 1;


        $formData['specimen_type'] = 1; // Always Whole Blood
        $formData['result_status'] = 6;


        $formData['social_category'] = (!empty($formData['social_category']) ? $dhis2SocialCategoryOptions[$formData['social_category']] : null);
        $formData['patient_gender'] = (!empty($formData['patient_gender']) ? $dhis2GenderOptions[$formData['patient_gender']] : null);
        //$formData['specimen_quality'] = (!empty($formData['specimen_quality']) ? strtolower($formData['specimen_quality']) : null);

        $formData['reason_for_vl_test'] = (!empty($formData['reason_for_vl_test']) ?  $dhis2VlTestReasonOptions[$_SESSION['DHIS2_VL_TEST_REASONS'][$formData['reason_for_vl_test']]] : null);

        $formData['sample_collection_date'] = (!empty($formData['sample_collection_date']) ?  $formData['sample_collection_date'] : $enrollmentDate);
        $formData['reason_for_hepatitis_test'] = (!empty($formData['reason_for_hepatitis_test']) ?  $formData['reason_for_hepatitis_test'] : "Suspect");
        if (isset($formData['hepatitis_test_type']) && stripos($formData['hepatitis_test_type'], "hbv") === FALSE) {
            $formData['hepatitis_test_type'] = "HBV";
        } else {
            $formData['hepatitis_test_type'] = "HCV";
        }


        // echo "<pre>";
        // var_dump($formData);
        // continue;

        $general = new \Vlsm\Models\General($db);
        $hepatitisModel = new \Vlsm\Models\Hepatitis($db);


        // $db->where("source_of_request", $formData['source_of_request']);
        // $hepatitisData = $db->getOne("form_hepatitis");


        $sampleJson = $hepatitisModel->generateHepatitisSampleCode($formData['hepatitis_test_type'], null, $general->humanDateFormat($formData['sample_collection_date']));

        $sampleData = json_decode($sampleJson, true);

        $formData['sample_code'] = $sampleData['sampleCode'];
        $formData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $formData['sample_code_key'] = $sampleData['sampleCodeKey'];

        $formData['request_created_by'] = 1;
        $formData['request_created_datetime'] = $general->getDateTime();

        $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

        $formData['vlsm_instance_id'] = $instanceResult['vlsm_instance_id'];
        $formData['vlsm_country_id'] = 7; // RWANDA
        $formData['last_modified_datetime'] = $general->getDateTime();
        //echo "<pre>";var_dump($formData);echo "</pre>";
        $updateColumns = array_keys($formData);
        $db->onDuplicate($updateColumns, 'source_of_request');
        $id = $db->insert("form_hepatitis", $formData);
        if ($id != false) {
            $processedCounter++;
        }
    }
}

$response = array('received' => $receivedCounter, 'processed' => $processedCounter);

echo (json_encode($response));
