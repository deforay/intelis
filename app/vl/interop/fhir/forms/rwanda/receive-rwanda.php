<?php

// this file is included in /vl/interop/fhir/vl-receive.php
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Interop\Fhir;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;

$interopConfig = [];
if (file_exists(APPLICATION_PATH . '/../configs/config.interop.php')) {
    $interopConfig = require_once(APPLICATION_PATH . '/../configs/config.interop.php');
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

try {

    $db->beginTransaction();

    $vlsmSystemConfig = $general->getSystemConfig();

    $fhir = new Fhir($interopConfig['FHIR']['url'], $interopConfig['FHIR']['auth']);

    $receivedCounter = 0;
    $processedCounter = 0;
    $errors = [];

    $data = [];

    //$data[] = "modified=ge2020-08-05";
    //$data[] = "requester=Organization/101282";
    $data[] = "modified=ge" . date("Y-m-d", strtotime("-1 day"));
    $data[] = "_include=Task:based-on:ServiceRequest";
    $data[] = "status=requested";
    $data[] = "_count=200";

    $json = $fhir->get('/Task', $data);
    //echo $general->prettyJson($json);

    // echo "\n\n\n\n\n\n";
    // $json = $fhir->get('/ServiceRequest/107150');
    // echo $general->prettyJson($json);
    // echo "\n\n\n\n\n\n";
    // $json = $fhir->get('/Patient/107164');
    // echo $general->prettyJson($json);
    // echo "\n\n\n\n\n\n";
    // $json = $fhir->get('/Specimen/107169');
    // echo $general->prettyJson($json);
    // echo "\n\n\n\n\n\n";
    // $json = $fhir->get('/Practitioner/107167');
    // echo $general->prettyJson($json);


    //die;


    $parser = new PHPFHIRResponseParser();

    $metaResource = $parser->parse($json);
    $entries = $metaResource->getEntry();

    $formData = [];

    $bundleId = (string) $metaResource->getId()->getValue();

    $instanceId = $general->getInstanceId();
    $taskAttributes = $serviceAttributes = [];

    // echo ("No. of entries in this bundle: " . count($entries) . "\n\n\n");
    // echo prettyJson($json);
    // die;

    $transactionId = MiscUtility::generateULID();
    $version = $general->getAppVersion();

    foreach ($entries as $entry) {

        try {
            $resource = $entry->getResource();

            if ($resource->getIntent() == 'TaskIntent') {
                $receivedCounter++;
                // echo "<h1> Entry " . $i++ . " </h1>";
                // echo "<h1> Entry " . $resource->getIntent() . " </h1>";
                $status = (string) $resource->getStatus()->getValue();
                $taskId = (string) $resource->getId();
                if (empty($resource->getBasedOn()) && empty($resource->getBasedOn()))

                    throw new SystemException("ServiceRequest is missing for Task/$taskId");
                $basedOnServiceRequest = basename((string) $resource->getBasedOn()[0]->getReference());
                $uniqueId = "FHIR::$basedOnServiceRequest";
                $organization = $fhir->getFHIRReference($resource->getRequester()->getReference());
                $orgParsed = $parser->parse($organization);
                $orgFhirId = (string) $orgParsed->getId()->getValue();
                $formData[$basedOnServiceRequest]['unique_id'] = $uniqueId;
                $taskAttributes[$basedOnServiceRequest] = ['task' => $taskId, 'bundle' => $bundleId, 'serviceRequest' => $basedOnServiceRequest, 'taskStatus' => $status];
                // $facilityName = (string) $orgParsed->getName();
                // $facilityCode = (string) $orgParsed->getIdentifier()[0]->getValue();
                // $db->where("other_id", $facilityCode);
                // $db->orWhere("facility_name", $facilityName);
                // $fac = $db->get("facility_details");
                $facilityRow = $facilitiesService->getFacilityByAttribute('facility_fhir_id', $orgFhirId);
                $formData[$basedOnServiceRequest]['facility_id'] = $facilityRow['facility_id'];
                if (empty($resource->getIdentifier()) || empty($resource->getIdentifier()[0]->getValue())) {
                    throw new SystemException("Order ID is missing for Task/$taskId");
                }
                $orderIdentifiers = $resource->getIdentifier();
                foreach ($orderIdentifiers as $oid) {
                    $system = $oid->getSystem()->getValue();
                    if (str_contains((string)$system, 'OHRI_ENCOUNTER_UUID')) {
                        $taskAttributes[$basedOnServiceRequest]['OHRI_ENCOUNTER_UUID'] = (string) $oid->getValue();
                    }
                    if (str_contains((string)$system, '/test-order-number')) {
                        $formData[$basedOnServiceRequest]['external_sample_code'] = (string) $oid->getValue();
                    }
                }
                // echo ("Type of Request: " . (string) $resource->getIntent()->getValue()) . "<br>";
                // echo ("Order ID: " . (string) $resource->getIdentifier()[0]->getValue()) . "<br>";
                //echo ("Task ID: " . $id) . "<br>";
                //echo ("<strong>FACILITY DETAILS</strong>") . "<hr>";
                // echo "<pre>";
                // var_dump($organization);
                // echo "</pre>" . "<hr>";
                //echo "<pre>" . $orgParsed->getId() . "<br>";
                // echo "<strong>Facility Name: </strong>" . $orgParsed->getName() . "<br>";
                // echo "<strong>Facility Code: </strong>" . $orgParsed->getIdentifier()[0]->getValue() . "<br>";
                // echo "<strong>Facility State: </strong>" . $orgParsed->getAddress()[0]->getState() . "<br>";
                // echo "<strong>Facility District: </strong>" . $orgParsed->getAddress()[0]->getDistrict() . "<br>";
                //var_dump($organization->getAddress());
                // echo ("Task Status:" . $status) . "<br>";
                // echo "<br>";
                //var_dump(($resource));
                //var_dump($resource->getId());
                //var_dump($resource->getId());
                //echo ($resource->getBasedOn()[0]->getReference()) . "<br>";
            } elseif ($resource->getIntent() == 'RequestIntent') {
                // echo "<h1> Entry " . $resource->getIntent() . " </h1>";
                $basedOnServiceRequest = (string) $resource->getId();
                $patient = $fhir->getFHIRReference($resource->getSubject()->getReference());
                $patientParsed = $parser->parse($patient);
                $patientFhirId = (string) $patientParsed->getId();
                if (empty($resource->getSpecimen())) {
                    throw new SystemException("Specimen is missing for ServiceRequest/$basedOnServiceRequest");
                }
                $specimen = $fhir->getFHIRReference($resource->getSpecimen()[0]->getReference());
                $specimenParsed = $parser->parse($specimen);
                $specimenFhirId = (string) $specimenParsed->getId();
                if (empty($resource->getRequester())) {
                    throw new SystemException("Requester is missing for ServiceRequest/$basedOnServiceRequest");
                }
                $requestor = $fhir->getFHIRReference($resource->getRequester()->getReference());
                $requestorParsed = $parser->parse($requestor);
                $requestorFhirId = (string) $requestorParsed->getId();
                $reasonForTesting = (string) ($resource->getReasonCode()[0]->getCoding()[0]->getCode());
                $serviceAttributes[$basedOnServiceRequest] = ['patient' => $patientFhirId, 'specimen' => $specimenFhirId, 'requestor' => $requestorFhirId, 'serviceRequestStatus' => (string) $resource->getStatus()->getValue()];
                //$patientIdentifiers = $patientParsed->getIdentifier();
                // foreach ($patientIdentifiers as $pid) {
                //     if (empty($pid) || empty($pid->getSystem())) continue;
                //     $system = $pid->getSystem()->getValue();
                //     if (strpos($system, '/art') !== false) {
                //         $formData[$basedOnServiceRequest]['patient_art_no'] = (string) $pid->getValue();
                //     }
                // }
                $formData[$basedOnServiceRequest]['patient_art_no'] = (string) $patientParsed->getIdentifier()[0]->getValue();
                $formData[$basedOnServiceRequest]['patient_gender'] = (string) $patientParsed->getGender()->getValue();
                $formData[$basedOnServiceRequest]['patient_dob'] = (string) $patientParsed->getBirthDate()->getValue();
                $formData[$basedOnServiceRequest]['patient_first_name'] = $patientParsed->getName()[0]->getGiven()[0] . " " . $patientParsed->getName()[0]->getFamily();
                if (!empty($patientParsed->getAddress())) {
                    $formData[$basedOnServiceRequest]['patient_province'] = (string) $patientParsed->getAddress()[0]->getState();
                    $formData[$basedOnServiceRequest]['patient_district'] = (string) $patientParsed->getAddress()[0]->getDistrict();

                    $patientNationality = (string) $patientParsed->getAddress()[0]->getCountry();
                    if ($patientNationality !== '' && $patientNationality !== '0') {
                        $db->where("iso3", $patientNationality);
                        $country = $db->getOne("r_countries");
                        if (!empty($country)) {
                            $formData[$basedOnServiceRequest]['patient_nationality'] = $country['id'];
                        }
                    }
                }
                $formData[$basedOnServiceRequest]['request_clinician_name'] = $requestorParsed->getName()[0]->getGiven()[0] . " " . $requestorParsed->getName()[0]->getFamily();
                if (!empty($requestorParsed->getTelecom())) {
                    $formData[$basedOnServiceRequest]['request_clinician_phone_number'] = (string) $requestorParsed->getTelecom()[0]->getValue();
                }
                $formData[$basedOnServiceRequest]['sample_collection_date'] = (string) $specimenParsed->getCollection()->getCollectedDateTime();
                if (!empty($specimenParsed->getType())) {
                    $specimenCode = (string) $specimenParsed->getType()->getCoding()[0]->getCode();
                }
                if (!empty($specimenCode)) {
                    $db->where("sample_name", $specimenCode);
                    $sampleTypeResult = $db->getOne("r_vl_sample_type");
                    if (!empty($sampleTypeResult)) {
                        $formData[$basedOnServiceRequest]['sample_type'] = $sampleTypeResult['sample_id'];
                    }
                }
                $formData[$basedOnServiceRequest]['form_attributes']['applicationVersion'] = $version;
                $formData[$basedOnServiceRequest]['form_attributes']['apiTransactionId'] = $transactionId;
                $formData[$basedOnServiceRequest]['form_attributes']['fhir'] = (array_merge($taskAttributes[$basedOnServiceRequest], $serviceAttributes[$basedOnServiceRequest]));
                $formData[$basedOnServiceRequest]['form_attributes'] = json_encode($formData[$basedOnServiceRequest]['form_attributes']);
                $formData[$basedOnServiceRequest]['request_created_datetime'] = DateUtility::getCurrentDateTime();
                $formData[$basedOnServiceRequest]['vlsm_instance_id'] = $instanceId;
                $formData[$basedOnServiceRequest]['vlsm_country_id'] = 7;
                // RWANDA
                $formData[$basedOnServiceRequest]['last_modified_datetime'] = DateUtility::getCurrentDateTime();
                $formData[$basedOnServiceRequest]['source_of_request'] = 'fhir';
                $formData[$basedOnServiceRequest]['result_status'] = RECEIVED_AT_TESTING_LAB;
                $formData[$basedOnServiceRequest]['sample_type'] = 1;
                //echo "<strong>Specimen Type: </strong>" .  ($specimenParsed->getType()->getCoding()[0]->getCode()) . "<br>";
                //echo ("Service Status:" . $status) . "<br>";
            }
        } catch (SystemException $e) {
            LoggerUtility::logError($e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'taskId' => $taskId ?? null,
                'basedOnServiceRequest' => $basedOnServiceRequest ?? null,
                'resource' => $resource ?? null,
                'bundleId' => $bundleId ?? null,
                'instanceId' => $instanceId ?? null,
                'transactionId' => $transactionId ?? null,
            ]);
            $errors[] = "Error while processing FHIR data for Task/$taskId";
            if (isset($basedOnServiceRequest)) {
                unset($formData[$basedOnServiceRequest]);
            }

            continue;
        }
    }



    //die;

    foreach ($formData as $serviceRequest => $data) {
        $db->where('unique_id', $data['unique_id']);
        $vlResult = $db->getOne("form_vl");

        if (!empty($vlResult)) {
            continue;
        }

        $sampleCodeParams = [];
        $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate ?? null;
        $sampleCodeParams['insertOperation'] = true;
        $sampleJson = $vlService->getSampleCode($sampleCodeParams);

        $sampleData = json_decode((string) $sampleJson, true);
        if ($general->isSTSInstance()) {
            $sampleCode = 'remote_sample_code';
            $sampleCodeKey = 'remote_sample_code_key';
            $sampleCodeFormat = 'remote_sample_code_format';
            $data['remote_sample'] = 'yes';
        } else {
            $sampleCode = 'sample_code';
            $sampleCodeKey = 'sample_code_key';
            $sampleCodeFormat = 'sample_code_format';
            $data['remote_sample'] = 'no';
        }
        $data[$sampleCode] = $sampleData['sampleCode'];
        $data[$sampleCodeFormat] = $sampleData['sampleCodeFormat'];
        $data[$sampleCodeKey] = $sampleData['sampleCodeKey'];

        // echo "<pre>";
        // echo "<h1>" . $data['unique_id'] . "</h1>";
        // print_r($data);
        // echo "</pre><br><br><br><br><br><br><br>";
        // continue;

        try {
            // echo "<pre>";
            // echo "<h1>" . $data['unique_id'] . "</h1>";
            // print_r($data);
            // echo "</pre><br><br><br><br><br><br><br>";
            //continue;
            $id = $db->insert("form_vl", $data);
            //echo "<h1>" . $id . "</h1>";
            //LoggerUtility::logError("Error in Receive Rwanda FHIR Script : " . $db->getLastError() . PHP_EOL);
        } catch (Throwable $e) {
            LoggerUtility::logError("Error in Receive Rwanda FHIR Script : " . $db->getLastError() . PHP_EOL);
            throw new SystemException($e->getMessage(), $e->getCode(), $e);
        }

        if (isset($id) && $id) {
            $processedCounter++;
        }
    }

    $response = [
        'timestamp' => time(),
        'received' => $receivedCounter,
        'processed' => $processedCounter
    ];

    if ($errors !== []) {
        $response['errors'] = $errors;
    }

    $trackId = $general->addApiTracking($transactionId, 'intelis-system', $processedCounter, 'FHIR-VL-Receive', 'vl', $fhir->getRequestUrl(), $json, null, 'json');

    $db->commitTransaction();
} catch (Throwable $exception) {
    $db->rollbackTransaction();
    $response = [
        'timestamp' => time(),
        'received' => $receivedCounter,
        'processed' => $processedCounter,
        'errors' => ['Error while processing FHIR data: ' . $exception->getMessage()]
    ];
    LoggerUtility::logError("Error while receiving FHIR data : " . $exception->getMessage());
}

header('Content-Type: application/json');
echo JsonUtility::prettyJson($response);
