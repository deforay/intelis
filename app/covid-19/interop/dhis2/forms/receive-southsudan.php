<?php

// this file is included in /covid-19/interop/dhis2/covid-19-receive.php

use App\Interop\Dhis2;
use App\Services\Covid19Service;
use App\Services\CommonService;
use App\Utilities\DateUtils;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

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


$general = new CommonService();
$covid19Model = new Covid19Service();


$vlsmSystemConfig = $general->getSystemConfig();

$dhis2 = new Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);

$receivedCounter = 0;
$processedCounter = 0;


// https://southsudanhis.org/covid19southsudan/api/trackedEntityInstances.json?programStartDate=2020-04-01&programEndDate=2021-04-02&ou=OV9zi20DDXP&ouMode=DESCENDANTS&program=uYjxkTbwRNf&fields=attributes[attribute,code,value],enrollments[*],orgUnit,trackedEntityInstance&paging=false

$counter = 0;
$data = [];
$data[] = "lastUpdatedDuration=1d";
$data[] = "ou=OV9zi20DDXP"; // South Sudan
$data[] = "ouMode=DESCENDANTS";
$data[] = "program=uYjxkTbwRNf";
$data[] = "fields=attributes[attribute,code,value],enrollments[*],orgUnit,trackedEntityInstance";
$data[] = "paging=false";
$data[] = "skipPaging=true";

$url = "/api/trackedEntityInstances.json";

$jsonResponse = $dhis2->get($url, $data);

if ($jsonResponse == '' || $jsonResponse == '[]' || empty($jsonResponse)) die('No Response from API');

$options = [
    'pointer' => '/trackedEntityInstances',
    'decoder' => new ExtJsonDecoder(true)
];
$trackedEntityInstances = Items::fromString($jsonResponse, $options);

foreach ($trackedEntityInstances as $tracker) {

    $receivedCounter++;

    $formData = [];
    $screeningEventIds = [];
    $enrollmentDate = null;


    foreach ($tracker['enrollments'] as $enrollments) {

        $allProgramStages = array_column($enrollments['events'], 'programStage', 'event');

        $screeningEventIds = array_keys($allProgramStages, $programStages['labRequest']); // screening programStage

        if (count($screeningEventIds) == 0)  continue 2; // if no screening stage, skip this tracker entirely

        $enrollmentDate = explode("T", $enrollments['enrollmentDate']);
        $enrollmentDate = $enrollmentDate[0];

        $eventsData = [];
        foreach ($enrollments['events'] as $event) {

            if ($event['programStage'] != $programStages['labRequest']) continue;

            foreach ($event['dataValues'] as $dV) {
                if (empty($eventsDataElementMapping[$dV['dataElement']])) continue;
                $eventsData["dhis2::" . $tracker['trackedEntityInstance'] . "::" . $event['event']][$eventsDataElementMapping[$dV['dataElement']]] = $dV['value'];
            }
        }
    }

    $attributesData = [];
    foreach ($tracker['attributes'] as $trackerAttr) {
        if (empty($attributesDataElementMapping[$trackerAttr['attribute']])) continue;
        $attributesData[$attributesDataElementMapping[$trackerAttr['attribute']]] = $trackerAttr['value'];
    }

    foreach ($eventsData as $uniqueID => $singleEventData) {


        $db->where('unique_id', $uniqueID);
        $c19Result = $db->getOne("form_covid19");

        if (!empty($c19Result)) {
            continue;
        }

        $formData = array_merge($singleEventData, $attributesData);
        $formData['source_of_request'] = 'dhis2';
        $formData['source_data_dump'] = json_encode($tracker);


        $facility = $tracker['orgUnit'];


        $formData['sample_collection_date'] = (!empty($formData['sample_collection_date']) ?  $formData['sample_collection_date'] : $enrollmentDate);

        // Reason for Testing
        if (!empty($formData['reason_for_covid19_test'])) {
            $db->where("test_reason_name", $formData['reason_for_covid19_test']);
            $reason = $db->getOne("r_covid19_test_reasons");
            if (!empty($reason) && $reason) {
                $formData['reason_for_covid19_test'] = $reason['test_reason_id'];
            } else {
                $reasonData = array(
                    'test_reason_name' => $formData['reason_for_covid19_test'],
                    'test_reason_status' => 'active',
                    'updated_datetime' => DateUtils::getCurrentDateTime()
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
            $testPlatform = $db->getOne("instruments");
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

        $db->where("geo_name", $fac['facility_state']);
        $prov = $db->getOne("geographical_divisions");

        $formData['province_id'] = !empty($prov['geo_id']) ? $prov['geo_id'] : 1;


        //Specimen Type
        if (!empty($formData['specimen_type'])) {
            $db->where("sample_name", $formData['specimen_type']);
            $sampleType = $db->getOne("r_covid19_sample_type");

            if (!empty($sampleType) && $sampleType) {
                $formData['specimen_type'] = $sampleType['sample_id'];
            } else {
                $sampleTypeData = array(
                    'sample_name' => $formData['specimen_type'],
                    'status' => 'active',
                    'updated_datetime' => DateUtils::getCurrentDateTime()
                );
                $formData['specimen_type'] = $db->insert("r_covid19_sample_type", $sampleTypeData);
            }
        }


        $status = 6;
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $status = 9;
        }

        $formData['result_status'] = $status;
        $formData['last_modified_datetime'] = DateUtils::getCurrentDateTime();


        $formData['patient_gender'] = (!empty($formData['patient_gender']) ? strtolower($formData['patient_gender']) : null);
        if (!empty($formData['specimen_quality'])) {
            $formData['specimen_quality'] =  strtolower($formData['specimen_quality']);
        }


        // all the columns at this point will be in update columns list
        // the columns below this are only for inserting
        //$updateColumns = array_keys($formData);




        $sampleJson = $covid19Model->generateCovid19SampleCode(null, DateUtils::humanReadableDateFormat($formData['sample_collection_date']), null, $formData['province_id']);

        $sampleData = json_decode($sampleJson, true);

        $formData['unique_id'] = $uniqueID;

        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $sampleCode = 'remote_sample_code';
            $sampleCodeKey = 'remote_sample_code_key';
            $sampleCodeFormat = 'remote_sample_code_format';
            $formData['remote_sample'] = 'yes';
        } else {
            $sampleCode = 'sample_code';
            $sampleCodeKey = 'sample_code_key';
            $sampleCodeFormat = 'sample_code_format';
            $formData['remote_sample'] = 'no';
        }

        $formData[$sampleCode] = $sampleData['sampleCode'];
        $formData[$sampleCodeFormat] = $sampleData['sampleCodeFormat'];
        $formData[$sampleCodeKey] = $sampleData['sampleCodeKey'];

        $formData['request_created_by'] = 1;
        $formData['request_created_datetime'] = DateUtils::getCurrentDateTime();

        $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

        $formData['vlsm_instance_id'] = $instanceResult['vlsm_instance_id'];
        $formData['vlsm_country_id'] = 1; // South Sudan


        //$db->onDuplicate($updateColumns, 'unique_id');
        $id = $db->insert("form_covid19", $formData);
        if ($id) {
            $processedCounter++;
        }
    }
}

$response = array('received' => $receivedCounter, 'processed' => $processedCounter);

echo (json_encode($response));
