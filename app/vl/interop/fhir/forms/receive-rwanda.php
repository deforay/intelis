<?php

// this file is included in /vl/interop/fhir/vl-receive.php

use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use Vlsm\Interop\Fhir;

$general = new \Vlsm\Models\General();
$vlModel = new \Vlsm\Models\Vl();

$vlsmSystemConfig = $general->getSystemConfig();

$fhir = new Fhir('https://oh-route.gicsandbox.org/fhir', 'Custom test');

$receivedCounter = 0;
$processedCounter = 0;

$data = array();

$data[] = "modified=ge2022-01-01";
$data[] = "requester=Organization/101282";
$data[] = "_include=Task:based-on:ServiceRequest";
$data[] = "status=requested";

$json = $fhir->get('/Task', $data);

$parser = new PHPFHIRResponseParser();

$metaResource = $parser->parse($json);
$entries = $metaResource->getEntry();

foreach ($entries as $entry) {
    $resource = $entry->getResource();

    if ($resource->getIntent() == 'TaskIntent') {

        // echo "<h1> Entry " . $i++ . " </h1>";
        $organization = $fhir->getFHIRReference($resource->getRequester()->getReference());

        $status = $resource->getStatus()->getValue();
        $id = $resource->getId();


        echo ("Type of Request: " . $resource->getIntent()->getValue()) . "<br>";
        echo ("Order ID: " . $resource->getIdentifier()[0]->getValue()) . "<br>";
        //echo ("Task ID: " . $id) . "<br>";
        echo ("<strong>FACILITY DETAILS</strong>") . "<hr>";
        // echo "<pre>";
        // var_dump($organization);
        // echo "</pre>" . "<hr>";
        $orgParsed = $parser->parse($organization);

        //echo "<pre>" . $orgParsed->getId() . "<br>";
        echo "<strong>Facility Name: </strong>" . $orgParsed->getName() . "<br>";
        echo "<strong>Facility Code: </strong>" . $orgParsed->getIdentifier()[0]->getValue() . "<br>";
        echo "<strong>Facility State: </strong>" . $orgParsed->getAddress()[0]->getState() . "<br>";
        echo "<strong>Facility District: </strong>" . $orgParsed->getAddress()[0]->getDistrict() . "<br>";

        //var_dump($organization->getAddress());

        echo ("Task Status:" . $status) . "<br>";
        echo "<br>";
        //var_dump(($resource));
        //var_dump($resource->getId());
        //var_dump($resource->getId());

        //echo ($resource->getBasedOn()[0]->getReference()) . "<br>";

    } else if ($resource->getIntent() == 'RequestIntent') {

        //echo "<h1> Entry " . $i++ . " </h1>";
        $requestor = $resource->getRequester()->getReference();
        $status = $resource->getStatus()->getValue();
        $id = $resource->getId();
        //echo ("Type of Intent:" . $resource->getIntent()->getValue()) . "<br>";
        //echo ("ServiceRequest ID:" . $id) . "<br>";

        $patient = $fhir->getFHIRReference($resource->getSubject()->getReference());
        $patientParsed = $parser->parse($patient);
        echo ("<strong>PATIENT DETAILS</strong>") . "<hr>";
        //echo "<pre>" . $patient . "</pre>";

        //echo $patientParsed->getId() . "<br>";

        $patientIdentifiers = $patientParsed->getIdentifier();
        foreach ($patientIdentifiers as $pid) {

            $system = $pid->getSystem()->getValue();
            if (strpos($system, '/passport') !== false) {
                echo "<strong>Passport: </strong>" . $pid->getValue() . "<br>";
            }
            if (strpos($system, '/art') !== false) {
                echo "<strong>Patient ART No: </strong>" . $pid->getValue() . "<br>";
            }
        }




        echo "<strong>Patient First Name: </strong>" .  ($patientParsed->getName()[0]->getGiven()[0]) . "<br>";
        echo "<strong>Patient Last Name: </strong>" .  ($patientParsed->getName()[0]->getFamily()) . "<br>";
        echo "<strong>Patient DOB: </strong>" .  ($patientParsed->getBirthDate()->getValue()) . "<br>";
        echo "<strong>Patient Gender: </strong>" .  ($patientParsed->getGender()->getValue()) . "<br>";
        echo "<strong>Patient Marital Status: </strong>" .  ($patientParsed->getMaritalStatus()->getCoding()[0]->getCode()) . "<br>";



        echo "<br>";

        $requestor = $fhir->getFHIRReference($resource->getRequester()->getReference());
        $requestorParsed = $parser->parse($requestor);

        echo ("<strong>Requested By</strong>") . "<hr>";
        echo "<strong>Requesting Clinician First Name: </strong>" .  ($requestorParsed->getName()[0]->getGiven()[0]) . "<br>";
        echo "<strong>Requesting Clinician Last Name: </strong>" .  ($requestorParsed->getName()[0]->getFamily()) . "<br>";
        echo "<strong>Requesting Clinician Phone: </strong>" .  ($requestorParsed->getTelecom()[0]->getValue()) . "<br>";


        $specimen = $fhir->getFHIRReference($resource->getSpecimen()[0]->getReference());
        $specimenParsed = $parser->parse($specimen);
        echo ("<strong>Specimen Details</strong>") . "<hr>";
        echo "<strong>Specimen Type: </strong>" .  ($specimenParsed->getType()->getCoding()[0]->getCode()) . "<br>";
        echo "<strong>Specimen Collection Date: </strong>" .  ($specimenParsed->getCollection()->getCollectedDateTime()) . "<br>";
        echo ("Service Status:" . $status) . "<br>";
    }
}


die;

$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

foreach ($trackedEntityInstances as $tracker) {

    $receivedCounter++;


    $formData = array();
    $labTestEventIds = array();
    $enrollmentDate = null;
    //echo "<pre>";var_dump(array_keys($tracker['enrollments']));echo "</pre>";;
    //echo "<pre>";var_dump(($tracker['enrollments']));echo "</pre>";
    foreach ($tracker['enrollments'] as $enrollments) {

        $allProgramStages = array_column($enrollments['events'], 'programStage', 'event');

        $labTestEventIds = array_keys($allProgramStages, 'ODgOyrbLkvv'); // Lab Test Request programStage

        if (count($labTestEventIds) == 0)  continue 2; // if no lab test request stage, skip this tracker entirely

        //echo "<pre>";var_dump($enrollments['events']);echo "</pre>";

        $enrollmentDate = explode("T", $enrollments['enrollmentDate']);
        $enrollmentDate = $enrollmentDate[0];

        $eventsData = array();
        $screeningData = array();
        //$labTestRequestData = array();
        $event = array();
        foreach ($enrollments['events'] as $event) {

            $requestProgramStages = array('ODgOyrbLkvv', 'ZBWBirHgmE6');

            if (in_array($event['programStage'], $requestProgramStages)) {
                foreach ($event['dataValues'] as $dV) {
                    if (empty($eventsDataElementMapping[$dV['dataElement']])) continue;
                    if ($event['programStage'] == 'ODgOyrbLkvv') {
                        $eventsData["dhis2::" . $tracker['trackedEntityInstance'] . "::" . $event['event']][$eventsDataElementMapping[$dV['dataElement']]] = $dV['value'];
                    } else {
                        $screeningEventData["dhis2::" . $tracker['trackedEntityInstance'] . "::" . $event['event']][$eventsDataElementMapping[$dV['dataElement']]] = $dV['value'];
                    }
                }
            }
        }
    }

    $screeningStageData = array();
    foreach ($screeningEventData as $sID => $sData) {

        if (!empty($sData['anti_hcv_result'])) {
            if ($sData['anti_hcv_result'] == 'Reactive') {
                $screeningStageData['anti_hcv_result'] = 'positive';
            } else if ($sData['anti_hcv_result'] == 'NonReactive') {
                $screeningStageData['anti_hcv_result'] = 'negative';
            } else if ($sData['anti_hcv_result'] == 'Indeterminate') {
                $screeningStageData['anti_hcv_result'] = 'indeterminate';
            }
        } else {
            $screeningStageData['anti_hcv_result'] = null;
        }

        if (!empty($sData['hbsag_result'])) {
            if ($sData['hbsag_result'] == 'Reactive') {
                $screeningStageData['hbsag_result'] = 'positive';
            } else if ($sData['hbsag_result'] == 'NonReactive') {
                $screeningStageData['hbsag_result'] = 'negative';
            } else if ($sData['hbsag_result'] == 'Indeterminate') {
                $screeningStageData['hbsag_result'] = 'indeterminate';
            }
        } else {
            $screeningStageData['hbsag_result'] = null;
        }
    }


    $attributesData = array();
    foreach ($tracker['attributes'] as $trackerAttr) {
        if (empty($attributesDataElementMapping[$trackerAttr['attribute']])) continue;
        //echo $attributesDataElementMapping[$trackerAttr['attribute']] . "%%%%%%%" . $trackerAttr['value'] . PHP_EOL . PHP_EOL;
        $attributesData[$attributesDataElementMapping[$trackerAttr['attribute']]] = $trackerAttr['value'];
    }

    foreach ($eventsData as $uniqueID => $singleEventData) {

        $db->where('unique_id', $uniqueID);
        $hepResult = $db->getOne("form_hepatitis");

        if (!empty($hepResult)) {
            continue;
        }

        $formData = array_merge($singleEventData, $attributesData, $screeningStageData);

        // if DHIS2 Case ID is not set then skip
        if (!isset($formData['external_sample_code']) || empty(trim($formData['external_sample_code']))) continue;

        if ($formData['hbsag_result'] == 'negative' && $formData['anti_hcv_result'] == 'negative') {
            continue;
        }

        $formData['sample_collection_date'] = (!empty($formData['sample_collection_date']) ?  $formData['sample_collection_date'] : $enrollmentDate);

        // if this is an old request, then skip
        if (strtotime($formData['sample_collection_date']) < strtotime('-6 months')) {
            continue;
        }

        $formData['source_of_request'] = 'dhis2';
        $formData['source_data_dump'] = json_encode($tracker);



        //$formData['patient_province'] = $_SESSION['DHIS2_HEP_PROVINCES'][$formData['patient_province']];
        //$formData['patient_district'] = $_SESSION['DHIS2_HEP_DISTRICTS'][$formData['patient_district']];

        if (!empty($formData['patient_nationality'])) {
            $db->where("iso3", $formData['patient_nationality']);
            $country = $db->getOne("r_countries");
            $formData['patient_nationality'] = $country['id'];
        }

        //var_dump($formData['lab_id']);
        if (!empty($formData['lab_id'])) {
            $db->where("facility_name", $formData['lab_id']);
            $db->orWhere("other_id", $formData['lab_id']);
            $lab = $db->getOne("facility_details");
            // echo "<pre>";var_dump($formData['lab_id']);echo "</pre>";
            // echo "<pre>";var_dump($lab);echo "</pre>";
            if (!empty($lab)) {
                $formData['lab_id'] = $lab['facility_id'];
            } else {
                $formData['lab_id'] = null;
            }
        } else {
            //$formData['lab_id'] = null;
            continue;
        }

        $facility = $tracker['orgUnit'];

        $db->where("other_id", $facility);
        $db->orWhere("other_id", $facility);
        $fac = $db->getOne("facility_details");
        $formData['facility_id'] =  $fac['facility_id'];

        if (!empty($fac['facility_state'])) {
            $db->where("province_name", $fac['facility_state']);
            $prov = $db->getOne("province_details");
        }

        $formData['province_id'] = !empty($prov['province_id']) ? $prov['province_id'] : 1;

        $formData['specimen_type'] = 1; // Always Whole Blood
        $formData['result_status'] = 6;

        $formData['social_category'] = (!empty($formData['social_category']) ? $dhis2SocialCategoryOptions[$formData['social_category']] : null);
        $formData['patient_gender'] = (!empty($formData['patient_gender']) ? $dhis2GenderOptions[$formData['patient_gender']] : null);
        //$formData['specimen_quality'] = (!empty($formData['specimen_quality']) ? strtolower($formData['specimen_quality']) : null);


        $formData['reason_for_hepatitis_test'] = (!empty($formData['reason_for_hepatitis_test']) ?  $formData['reason_for_hepatitis_test'] : 1);


        //Initial HBV OR HCV VL
        if ($formData['reason_for_vl_test'] == 'I_VL001') {
            if ($formData['hepatitis_test_type'] == 'HCV') {
                $formData['reason_for_vl_test'] = 'Initial HCV VL';
            } else if ($formData['hepatitis_test_type'] == 'HBV') {
                $formData['reason_for_vl_test'] = 'Initial HBV VL';
            } else {
                $formData['reason_for_vl_test'] = 'Initial HBV VL';
            }
        } else {
            $formData['reason_for_vl_test'] = (!empty($formData['reason_for_vl_test']) ?  $dhis2VlTestReasonOptions[$formData['reason_for_vl_test']] : null);
        }

        // echo "<pre>";
        //var_dump($uniqueID . " -- " . $formData['reason_for_vl_test']);
        //var_dump($uniqueID . " -- " . $formData['hepatitis_test_type']);
        //continue;

        $formData['request_created_datetime'] = $general->getDateTime();
        $updateColumns = array_keys($formData);

        $formData['unique_id'] = $uniqueID;

        $sampleJson = $hepatitisModel->generateHepatitisSampleCode($formData['hepatitis_test_type'], null, $general->humanDateFormat($formData['sample_collection_date']));

        $sampleData = json_decode($sampleJson, true);
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



        $formData['vlsm_instance_id'] = $instanceResult['vlsm_instance_id'];
        $formData['vlsm_country_id'] = 7; // RWANDA
        $formData['last_modified_datetime'] = $general->getDateTime();
        //echo "<pre>";var_dump($formData);echo "</pre>";
        //$updateColumns = array_keys($formData);
        //$db->onDuplicate($updateColumns, 'unique_id');

        $id = $db->insert("form_hepatitis", $formData);
        //error_log("Error in Receive Rwanda DHIS2 Script : " . $db->getLastError() . PHP_EOL);
        if ($id != false) {
            $processedCounter++;
        }
    }
}

$response = array('received' => $receivedCounter, 'processed' => $processedCounter);
$app = new \Vlsm\Models\App();
$trackId = $app->addApiTracking(NULL, $processedCounter, 'DHIS2-Hepatitis-Receive', 'hepatitis');
echo (json_encode($response));
