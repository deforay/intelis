<?php

// this file is included in /hepatitis/interop/dhis2/hepatitis-receive.php
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Interop\Dhis2;
use JsonMachine\Items;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

$dhis2 = new Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);

$syncType = 'DHIS2-Hepatitis-Receive';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

$globalConfig = $general->getGlobalConfig();
$sampleCodeFormat = $globalConfig['hepatitis_sample_code'] ?? 'MMYY';

$transactionId = MiscUtility::generateULID();
$processingErrors = [];

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);

$uniqueIdsForSampleCodeGeneration = [];

try {

    $db->beginTransaction();

    $vlsmSystemConfig = $general->getSystemConfig();

    $instanceId = $general->getInstanceId();

    $lastSyncDateTime = $general->getLastApiSyncByType($syncType);

    if ($lastSyncDateTime !== null) {
        $currentDateTime = new DateTime();
        $lastSync = new DateTime($lastSyncDateTime);
        $interval = $currentDateTime->diff($lastSync);
        $diffInHours = $interval->days * 24 + $interval->h;
        $durationToSync = ($diffInHours + 2) . 'h';
    } else {
        $durationToSync = '6h';
    }

    $receivedCounter = 0;
    $processedCounter = 0;

    $data = [];
    //$data[] = "lastUpdatedDuration=180m";
    $data[] = "lastUpdatedDuration=$durationToSync";
    $data[] = "ou=Hjw70Lodtf2"; // Rwanda
    $data[] = "ouMode=DESCENDANTS";
    $data[] = "program=LEhPhsbgfFB";
    $data[] = "fields=attributes[attribute,code,value],enrollments[*],orgUnit,trackedEntityInstance";
    $data[] = "paging=false";
    $data[] = "skipPaging=true";

    $url = "/api/trackedEntityInstances.json";

    $jsonResponse = $dhis2->get($url, $data);
    $jsonResponse = (string) $jsonResponse->getBody();

    if ($jsonResponse === '' || $jsonResponse === '[]' || ($jsonResponse === '' || $jsonResponse === '0')) {
        die('No Response from API');
    }

    $options = [
        'pointer' => '/trackedEntityInstances',
        'decoder' => new ExtJsonDecoder(true)
    ];
    $trackedEntityInstances = Items::fromString($jsonResponse, $options);

    $dhis2GenderOptions = ['Male' => 'male', '1' => 'male', 'Female' => 'female', '2' => 'female'];
    $dhis2SocialCategoryOptions = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];

    $dhis2VlTestReasonOptions = [
        'I_VL001' => 'Initial HBV VL',
        'HBV_F0012' => 'Follow up HBV VL',
        'SVR12_HCV01' => 'SVR12 HCV VL',
        'SVR12_HCV02' => 'SVR12 HCV VL - Second Line'
    ];

    $attributesDataElementMapping = [
        'iwzGzKTlYGR' => 'external_sample_code',
        //dhis2 case id
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
        'IeduuuWaWa4' => 'patient_gender',
        //'' => 'patient_nationality'
    ];




    $eventsDataElementMapping = [
        'GWoBWpKWlWJ' => 'sample_collection_date',
        'hvznTv3ZjXv' => 'hbsag_result',
        'szTAjn4r7yM' => 'anti_hcv_result',
        'Di17rUJDIWZ' => 'hbv_vl_count',
        'Oem0BXNDPWL' => 'hcv_vl_count',
        //'Mpc3ftVuSvK' => 'hepatitis_test_type',
        'DMQSNcqWRvI' => 'lab_id',
        'G8K0RLiK9lu' => 'hepatitis_test_type',
        'KPFLSlmiY89' => 'reason_for_vl_test'
    ];


    $version = $general->getAppVersion();

    foreach ($trackedEntityInstances as $tracker) {

        $receivedCounter++;

        $formData = [];
        $labTestEventIds = [];
        $enrollmentDate = null;
        foreach ($tracker['enrollments'] as $enrollments) {

            $allProgramStages = array_column($enrollments['events'], 'programStage', 'event');

            $labTestEventIds = array_keys($allProgramStages, 'ODgOyrbLkvv'); // Lab Test Request programStage

            if (count($labTestEventIds) == 0) {
                $processingErrors[] = "No Lab Test Request programStage found for " . $tracker['trackedEntityInstance'];
                // if no lab test request stage, skip this tracker entirely
                continue 2;
            }

            $enrollmentDate = strstr((string) $enrollments['enrollmentDate'], 'T', true);

            $eventsData = [];
            $screeningEventData = [];
            $requestProgramStages = ['ODgOyrbLkvv', 'ZBWBirHgmE6'];

            foreach ($enrollments['events'] as $event) {
                if (!in_array($event['programStage'], $requestProgramStages)) {
                    continue;
                }

                foreach ($event['dataValues'] as $dV) {
                    $dataElement = $eventsDataElementMapping[$dV['dataElement']] ?? null;
                    if (!$dataElement) {
                        continue;
                    }

                    $key = "dhis2::{$tracker['trackedEntityInstance']}::{$event['event']}";
                    $value = $dV['value'];

                    if ($event['programStage'] == 'ODgOyrbLkvv') {
                        $eventsData[$key][$dataElement] = $value;
                    } else {
                        $screeningEventData[$key][$dataElement] = $value;
                    }
                }
            }
        }

        $screeningStageData = [];
        $resultMapping = [
            'Reactive' => 'positive',
            'NonReactive' => 'negative',
            'Indeterminate' => 'indeterminate',
        ];

        foreach ($screeningEventData as $sID => $sData) {
            foreach (['anti_hcv_result', 'hbsag_result'] as $resultKey) {
                $screeningStageData[$resultKey] = $resultMapping[$sData[$resultKey]] ?? null;
            }
        }

        $attributesData = [];
        foreach ($tracker['attributes'] as $trackerAttr) {
            $key = $attributesDataElementMapping[$trackerAttr['attribute']] ?? null;
            if ($key) {
                $attributesData[$key] = $trackerAttr['value'];
            }
        }

        $attributesAndScreeningData = array_merge($attributesData, $screeningStageData);

        foreach ($eventsData as $uniqueID => $singleEventData) {

            $db->where('unique_id', $uniqueID);
            $hepResult = $db->getOne("form_hepatitis");

            if (!empty($hepResult)) {
                $processingErrors[] = 'Duplicate Hepatitis Result Found: ' . $uniqueID;
                continue;
            }

            $formData = array_merge($singleEventData, $attributesAndScreeningData);

            // if DHIS2 Case ID is not set then skip
            if (!isset($formData['external_sample_code']) || in_array(trim((string) $formData['external_sample_code']), ['', '0'], true)) {
                continue;
            }

            if ($formData['hbsag_result'] == 'negative' && $formData['anti_hcv_result'] == 'negative') {
                continue;
            }

            $formData['sample_collection_date'] ??= $enrollmentDate;

            // if this is an old request, then skip
            if (strtotime((string) $formData['sample_collection_date']) < strtotime('-6 months')) {
                $processingErrors[] = "Existing Hepatitis Request: $uniqueID";
                continue;
            }

            $formData['source_of_request'] = 'dhis2';
            $formData['source_data_dump'] = json_encode($tracker);

            if (!empty($formData['patient_nationality'])) {
                $db->where("iso3", $formData['patient_nationality']);
                $country = $db->getOne("r_countries");
                $formData['patient_nationality'] = $country['id'];
            }

            if (!empty($formData['lab_id'])) {
                $db->where("facility_type=2");
                $db->where("facility_name like '" . $formData['lab_id'] . "%'");
                $db->orWhere("other_id", $formData['lab_id']);
                $lab = $db->getOne("facility_details");
                $formData['lab_id'] = empty($lab) ? null : $lab['facility_id'];
            } else {
                $processingErrors[] = 'Lab ID not found: ' . $uniqueID . ' ==== Hep Sample ID : ' . $formData['external_sample_code'];
                continue;
            }

            $facility = $tracker['orgUnit'];

            $db->where("other_id", $facility);
            $db->orWhere("other_id", $facility);
            $fac = $db->getOne("facility_details");
            $formData['facility_id'] = $fac['facility_id'];

            if (!empty($fac['facility_state'])) {
                $db->where("geo_name", $fac['facility_state']);
                $prov = $db->getOne("geographical_divisions");
            }

            $formData['province_id'] = $prov['geo_id'] ?? null;

            $formData['specimen_type'] = 1; // Always Whole Blood
            $formData['result_status'] = RECEIVED_AT_CLINIC; // Registered on STS but not in Testing Lab

            $formData['social_category'] = $dhis2SocialCategoryOptions[$formData['social_category']] ?? null;
            $formData['patient_gender'] = $dhis2GenderOptions[$formData['patient_gender']] ?? null;

            $formData['reason_for_hepatitis_test'] ??= 1;

            //Initial HBV OR HCV VL
            if ($formData['reason_for_vl_test'] == 'I_VL001') {
                if ($formData['hepatitis_test_type'] == 'HCV') {
                    $formData['reason_for_vl_test'] = 'Initial HCV VL';
                } elseif ($formData['hepatitis_test_type'] == 'HBV') {
                    $formData['reason_for_vl_test'] = 'Initial HBV VL';
                } else {
                    $formData['reason_for_vl_test'] = 'Initial HBV VL';
                }
            } else {
                $formData['reason_for_vl_test'] = $dhis2VlTestReasonOptions[$formData['reason_for_vl_test']] ?? null;
            }

            $formData['request_created_datetime'] = DateUtility::getCurrentDateTime();
            $updateColumns = array_keys($formData);

            $formData['unique_id'] = $uniqueID;

            $sampleCollectionDate = DateUtility::humanReadableDateFormat($formData['sample_collection_date'] ?? '');
            $prefix = $formData['hepatitis_test_type'] ?? 'HEP';

            $uniqueIdsForSampleCodeGeneration[] = $uniqueID;

            // Insert into the Code Generation Queue
            $testRequestsService->addToSampleCodeQueue(
                $uniqueID,
                'hepatitis',
                DateUtility::isoDateFormat($formData['sample_collection_date'] ?? '', true),
                null,
                $sampleCodeFormat,
                $prefix,
                'collection-site'
            );

            $formData['remote_sample'] = ($general->isSTSInstance()) ? 'yes' : 'no';

            $formData['request_created_by'] = 1;

            $formData['vlsm_instance_id'] = $instanceId;
            $formData['vlsm_country_id'] = 7; // RWANDA
            $formData['last_modified_datetime'] = DateUtility::getCurrentDateTime();


            $formAttributes = [];
            $formAttributes['apiTransactionId'] = $transactionId;
            $formAttributes['applicationVersion'] = $version;
            $formAttributes['trackedEntityInstance'] = $tracker['trackedEntityInstance'];
            $formData['form_attributes'] = json_encode($formAttributes);

            $id = $db->insert("form_hepatitis", $formData);
            if ($db->getLastErrno() > 0) {
                $processingErrors[] = $db->getLastError();
            }
            if ($id !== false) {
                $processedCounter++;
            }
        }
    }

    $responsePayload = json_encode([
        'transactionId' => $transactionId,
        'received' => $receivedCounter,
        'processed' => $processedCounter,
        'errors' => $processingErrors
    ]);


    $db->commitTransaction();

    // For inserted samples, generate sample code
    if ($uniqueIdsForSampleCodeGeneration !== []) {
        $sampleCodeData = $testRequestsService->processSampleCodeQueue(uniqueIds: $uniqueIdsForSampleCodeGeneration, parallelProcess: true);
    }
} catch (Throwable $exception) {
    $responsePayload = json_encode([
        'transactionId' => $transactionId,
        'received' => $receivedCounter,
        'processed' => $processedCounter,
        'errors' => $processingErrors
    ]);
    $db->rollbackTransaction();
    error_log("Error while receiving DHIS2 data : " . $exception->getMessage());
}


echo $responsePayload;


$general->addApiTracking(
    $transactionId,
    'intelis-system',
    $processedCounter,
    $syncType,
    'hepatitis',
    $dhis2->getCurrentRequestUrl(),
    $jsonResponse,
    $responsePayload,
    'json'
);
