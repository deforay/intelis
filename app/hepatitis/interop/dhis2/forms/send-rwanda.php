<?php

// this file is included in /hepatitis/interop/dhis2/hepatitis-send.php

use App\Interop\Dhis2;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;

$dhis2 = new Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


$syncType = 'DHIS2-Hepatitis-Send';

$query = "SELECT hep.*, fd.facility_name, fd.other_id
          FROM form_hepatitis as hep
          LEFT JOIN facility_details as fd ON hep.facility_id = fd.facility_id
          WHERE (hep.source_of_request LIKE 'dhis2' OR hep.unique_id like 'dhis2%')
          AND (hep.result_sent_to_source is null or hep.result_sent_to_source NOT LIKE 'sent')
          AND hep.result_status = " . SAMPLE_STATUS\ACCEPTED . " LIMIT 100";
$formResults = $db->rawQuery($query);
$counter = 0;

$transactionId = MiscUtility::generateULID();
$url = "/api/events";

$processingErrors = [];

$urlData = [];
$receivedCounter = 0;
foreach ($formResults as $row) {
  $receivedCounter++;
  $uniqueIdArray = explode("::", (string) $row['unique_id']);
  $trackedEntityInstance = $uniqueIdArray[1];

  $programID = 'LEhPhsbgfFB';
  $orgUnit = 'HsbgfFB';
  $programStages = [
    'Screening' => 'ZBWBirHgmE6',
    'Lab Tests Request' => 'ODgOyrbLkvv',
    'Initial HBV VL' => 'KPBuhvFV5bK',
    'Initial HCV VL' => 'KPBuhvFV5bK',
    'Follow up HBV VL' => 'WAyPhFAJLdv',
    'SVR12 HCV VL' => 'qiqz1esOFKV',
    'SVR12 HCV VL - Second Line' => 'PE0MBFfqSSF',
  ];

  if (!empty($row['reason_for_vl_test'])) {
    $pStage = $programStages[$row['reason_for_vl_test']];
  } else {
    $pStage = 'KPBuhvFV5bK';
  }

  $urlData = [];
  $urlData[] = "trackedEntityInstance=$trackedEntityInstance";
  $urlData[] = "programStage=" . $pStage;
  $urlData[] = "paging=false";

  $dhis2Response = $dhis2->get($url, $urlData);
  $dhis2Response = (string) $dhis2Response->getBody();

  $dhis2Response = json_decode($dhis2Response, true);


  $strategy = null;
  $eventId = null;
  $eventPayload = [];
  $eventDate = date("Y-m-d");
  $payload = [];


  if (!empty($row['facility_id'])) {
    $orgUnitId = $row['other_id'];
  } else {
    continue;
  }


  $programStagesVariables = [
    'hbvResultInterpretaion' => 'rEWik190gIO',
    'hcvResultInterpretaion' => 'V3kCLddinTF',
    'sampleCollectionDate' => 'q0s6zXe4sI4',
    'sampleTestedDate' => 'GteriydwIC1',
    'labResultHcvVlCount' => 'Oem0BXNDPWL',
    'labResultHbvVlCount' => 'Di17rUJDIWZ',
    'followUpHbvVlCount' => 'LblBnouUMJE',
    'finalConfirmationHcvVlCount' => 'wVmsNyyPWT0',
    'dateHcvResultsAvailable' => 'GGLsByl8p0L',
    'dateHbvResultsAvailable' => 'AzuU2zVke8N',
    'hbvSampleId' => 'mVNtr2M5Nw3',
    'hcvSampleId' => 'z6L8rdc77DL',
    '2LsampleTestedDate' => 'V7j7aBS4Kju',
    '2LhcvResultInterpretaion' => 'RaBRk1Dw3fu',
    '2LlabResultHcvVlCount' => 'YxcAk32kPRJ'
  ];


  $dataValues = [];

  $row['sample_code'] = $row['sample_code'] . (!empty($row['remote_sample_code']) ? '/' . $row['remote_sample_code'] : '');

  if ($row['reason_for_vl_test'] == 'Initial HBV VL') {
    if (
      !empty($row['hbv_vl_count']) &&
      in_array(strtolower((string) $row['hbv_vl_count']), $hepatitisService->suppressedArray)
    ) {
      $row['hbv_vl_count'] = 10;
    }

    if ($row['hbv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }
    $dataValues[$programStagesVariables['labResultHbvVlCount']] = $row['hbv_vl_count'];
    $dataValues[$programStagesVariables['dateHbvResultsAvailable']] = $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hbvSampleId']] = $row['sample_code'];
    $dataValues[$programStagesVariables['hbvResultInterpretaion']] = $interpretaion;
  } elseif ($row['reason_for_vl_test'] == 'Initial HCV VL') {
    if (
      !empty($row['hcv_vl_count']) &&
      in_array(strtolower((string) $row['hcv_vl_count']), $hepatitisService->suppressedArray)
    ) {
      $row['hcv_vl_count'] = 10;
    }

    if ($row['hcv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }
    $dataValues[$programStagesVariables['labResultHcvVlCount']] = $row['hcv_vl_count'];
    $dataValues[$programStagesVariables['dateHcvResultsAvailable']] = $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hcvSampleId']] = $row['sample_code'];
    $dataValues[$programStagesVariables['hcvResultInterpretaion']] = $interpretaion;
  } elseif ($row['reason_for_vl_test'] == 'Follow up HBV VL') {
    if (
      !empty($row['hbv_vl_count']) &&
      in_array(strtolower((string) $row['hbv_vl_count']), $hepatitisService->suppressedArray)
    ) {
      $row['hbv_vl_count'] = 10;
    }

    if ($row['hbv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }

    $dataValues[$programStagesVariables['followUpHbvVlCount']] = $row['hbv_vl_count'];
    $dataValues[$programStagesVariables['dateHbvResultsAvailable']] = $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hbvSampleId']] = $row['sample_code'];
    $dataValues[$programStagesVariables['hbvResultInterpretaion']] = $interpretaion;
  } elseif ($row['reason_for_vl_test'] == 'SVR12 HCV VL') {
    $interpretaion = "";
    if (
      !empty($row['hcv_vl_count']) &&
      in_array(strtolower((string) $row['hcv_vl_count']), $hepatitisService->suppressedArray)
    ) {
      $row['hcv_vl_count'] = 10;
    }

    if ($row['hcv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }
    $dataValues[$programStagesVariables['finalConfirmationHcvVlCount']] = $row['hcv_vl_count'];
    $dataValues[$programStagesVariables['hcvResultInterpretaion']] = $interpretaion;
    $dataValues[$programStagesVariables['sampleCollectionDate']] = $row['sample_collection_date'];
    $dataValues[$programStagesVariables['sampleTestedDate']] = $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['dateHcvResultsAvailable']] = $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hcvSampleId']] = $row['sample_code'];
  } elseif ($row['reason_for_vl_test'] == 'SVR12 HCV VL - Second Line') {
    $interpretaion = "";
    $programID = 'LQUdgfzYQCt';
    if (
      !empty($row['hcv_vl_count']) &&
      in_array(strtolower((string) $row['hcv_vl_count']), $hepatitisService->suppressedArray)
    ) {
      $row['hcv_vl_count'] = 10;
    }

    if ($row['hcv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }
    $dataValues[$programStagesVariables['2LlabResultHcvVlCount']] = $row['hcv_vl_count'];
    $dataValues[$programStagesVariables['2LhcvResultInterpretaion']] = $interpretaion;
    $dataValues[$programStagesVariables['2LsampleTestedDate']] = $row['sample_tested_datetime'];
  }

  if (empty($dhis2Response['events']) || count($dhis2Response['events']) == 0) {
    $idGeneratorApi = $dhis2->get("/api/system/id?limit=1");
    $idGeneratorApi = !empty($idGeneratorApi) ? (string) $idGeneratorApi->getBody() : "";
    $idResponse = (json_decode($idGeneratorApi, true));
    $eventId = $idResponse['codes'][0];

    $eventPayload = [
      "event" => $eventId,
      "eventDate" => $eventDate,
      "program" => $programID,
      "orgUnit" => $orgUnitId,
      "programStage" => $pStage,
      "status" => "COMPLETED",
      "trackedEntityInstance" => $trackedEntityInstance,
      "dataValues" => []
    ];
    if (!empty($dataValues)) {
      $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
      $payload = ($eventPayload);
      $response = $dhis2->post("/api/33/events/", $payload);
      $response = !empty($response) ? (string) $response->getBody() : null;
    }
  } else {
    foreach ($dhis2Response['events'] as $eventPayload) {
      if (!empty($dataValues)) {
        $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
        $payload = ($eventPayload);
        $urlParams = [];
        $urlParams[] = "mergeMode=REPLACE";
        $urlParams[] = "strategy=UPDATE";
        $urlParams[] = "importStrategy=CREATE_AND_UPDATE";
        $response = $dhis2->post("/api/33/events/", $payload, $urlParams);
        $response = !empty($response) ? (string) $response->getBody() : null;
      }
    }
  }

  $updateData = [
    'result_sent_to_source' => 'sent',
    'result_dispatched_datetime' => DateUtility::getCurrentDateTime(),
    'result_sent_to_source_datetime' => DateUtility::getCurrentDateTime()
  ];
  $db->where('hepatitis_id', $row['hepatitis_id']);
  $db->update('form_hepatitis', $updateData);
  $counter++;

  if ($db->getLastErrno() > 0) {
    $processingErrors[] = $db->getLastError();
  }
}


$general->addApiTracking(
  $transactionId,
  'intelis-system',
  $counter,
  $syncType,
  'hepatitis',
  $dhis2->getCurrentRequestUrl(),
  $payload,
  $response,
  'json'
);

echo json_encode([
  'transactionId' => $transactionId,
  'tried' => $receivedCounter,
  'processed' => $counter,
  'errors' => $processingErrors
]);
