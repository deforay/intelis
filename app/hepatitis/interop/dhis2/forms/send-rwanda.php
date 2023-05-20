<?php

// this file is included in /hepatitis/interop/dhis2/hepatitis-send.php

use App\Interop\Dhis2;
use App\Registries\ContainerRegistry;
use App\Services\HepatitisService;

$dhis2 = new Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);


/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);

$query = "SELECT * FROM form_hepatitis WHERE (source_of_request LIKE 'dhis2' OR unique_id like 'dhis2%') AND result_status = 7 AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent')";
//$query = "SELECT * FROM form_hepatitis WHERE source_of_request LIKE 'dhis2' AND result_status = 7";// AND result_sent_to_source NOT LIKE 'sent'";
$formResults = $db->rawQuery($query);
//var_dump($formResults);die;
$counter = 0;

$transactionId = $general->generateUUID();
$url = "/api/events";

$urlData = [];

foreach ($formResults as $row) {

  $uniqueIdArray = explode("::", $row['unique_id']);
  $trackedEntityInstance = $uniqueIdArray[1];

  $programID = 'LEhPhsbgfFB';
  $orgUnit = 'HsbgfFB';
  $programStages = array(
    'Screening' => 'ZBWBirHgmE6',
    'Lab Tests Request' => 'ODgOyrbLkvv',
    'Initial HBV VL' => 'KPBuhvFV5bK',
    'Initial HCV VL' => 'KPBuhvFV5bK',
    'Follow up HBV VL' => 'WAyPhFAJLdv',
    'SVR12 HCV VL' => 'qiqz1esOFKV',
    'SVR12 HCV VL - Second Line' => 'PE0MBFfqSSF',
  );

  if (!empty($row['reason_for_vl_test'])) {
    $pStage = $programStages[$row['reason_for_vl_test']];
  } else {
    $pStage = 'KPBuhvFV5bK';
  }

  $urlData = [];
  $urlData[] = "trackedEntityInstance=$trackedEntityInstance";
  $urlData[] = "programStage=" . $pStage;
  $urlData[] = "paging=false";
  //$urlData[] = "status=ACTIVE";





  $dhis2Response = $dhis2->get($url, $urlData);

  $dhis2Response = json_decode($dhis2Response, true);


  $strategy = null;
  $eventId = null;
  $eventPayload = [];
  $eventDate = date("Y-m-d");
  $payload = [];


  if (!empty($row['facility_id'])) {
    $facQuery = "SELECT facility_id, facility_name, other_id FROM facility_details where facility_id = " . $row['facility_id'];
    $facResult = $db->rawQueryOne($facQuery);
    $orgUnitId = $facResult['other_id'];
  } else {
    continue;
  }


  $programStagesVariables = array(
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
  );


  $dataValues = [];

  $row['sample_code'] = $row['sample_code'] . (!empty($row['remote_sample_code']) ? '/' . $row['remote_sample_code'] : '');

  if ($row['reason_for_vl_test'] == 'Initial HBV VL') {
    if (!empty($row['hbv_vl_count']) && in_array(strtolower($row['hbv_vl_count']), $hepatitisService->suppressedArray)) {
      $row['hbv_vl_count'] = 10;
    }

    if ($row['hbv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }
    $dataValues[$programStagesVariables['labResultHbvVlCount']]  = $row['hbv_vl_count'];
    $dataValues[$programStagesVariables['dateHbvResultsAvailable']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hbvSampleId']] =  $row['sample_code'];
    $dataValues[$programStagesVariables['hbvResultInterpretaion']] = $interpretaion;
  } else if ($row['reason_for_vl_test'] == 'Initial HCV VL') {
    if (!empty($row['hcv_vl_count']) && in_array(strtolower($row['hcv_vl_count']), $hepatitisService->suppressedArray)) {
      $row['hcv_vl_count'] = 10;
    }

    if ($row['hcv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }
    $dataValues[$programStagesVariables['labResultHcvVlCount']] = $row['hcv_vl_count'];
    $dataValues[$programStagesVariables['dateHcvResultsAvailable']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hcvSampleId']] =  $row['sample_code'];
    $dataValues[$programStagesVariables['hcvResultInterpretaion']] = $interpretaion;
  } else if ($row['reason_for_vl_test'] == 'Follow up HBV VL') {
    if (!empty($row['hbv_vl_count']) && in_array(strtolower($row['hbv_vl_count']), $hepatitisService->suppressedArray)) {
      $row['hbv_vl_count'] = 10;
    }

    if ($row['hbv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }

    $dataValues[$programStagesVariables['followUpHbvVlCount']]  = $row['hbv_vl_count'];
    $dataValues[$programStagesVariables['dateHbvResultsAvailable']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hbvSampleId']] =  $row['sample_code'];
    $dataValues[$programStagesVariables['hbvResultInterpretaion']] = $interpretaion;
  } else if ($row['reason_for_vl_test'] == 'SVR12 HCV VL') {
    $interpretaion = "";
    if (!empty($row['hcv_vl_count']) && in_array(strtolower($row['hcv_vl_count']), $hepatitisService->suppressedArray)) {
      $row['hcv_vl_count'] = 10;
    }

    if ($row['hcv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }
    $dataValues[$programStagesVariables['finalConfirmationHcvVlCount']] = $row['hcv_vl_count'];
    $dataValues[$programStagesVariables['hcvResultInterpretaion']] = $interpretaion;
    $dataValues[$programStagesVariables['sampleCollectionDate']] =  $row['sample_collection_date'];
    $dataValues[$programStagesVariables['sampleTestedDate']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['dateHcvResultsAvailable']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hcvSampleId']] =  $row['sample_code'];
  } else if ($row['reason_for_vl_test'] == 'SVR12 HCV VL - Second Line') {
    $interpretaion = "";
    $programID = 'LQUdgfzYQCt';
    if (!empty($row['hcv_vl_count']) && in_array(strtolower($row['hcv_vl_count']), $hepatitisService->suppressedArray)) {
      $row['hcv_vl_count'] = 10;
    }

    if ($row['hcv_vl_count'] > 20) {
      $interpretaion = 'Detected';
    } else {
      $interpretaion = 'Not Detected';
    }
    $dataValues[$programStagesVariables['2LlabResultHcvVlCount']] = $row['hcv_vl_count'];
    $dataValues[$programStagesVariables['2LhcvResultInterpretaion']] = $interpretaion;
    $dataValues[$programStagesVariables['2LsampleTestedDate']] =  $row['sample_tested_datetime'];
  }


  //var_dump($dataValues);

  if (count($dhis2Response['events']) == 0) {
    $idGeneratorApi = $dhis2->get("/api/system/id?limit=1");
    $idResponse = (json_decode($idGeneratorApi, true));
    $eventId = $idResponse['codes'][0];

    $eventPayload = array(
      "event" => $eventId,
      "eventDate" => $eventDate,
      "program" => $programID,
      "orgUnit" => $facResult['other_id'],
      "programStage" => $pStage,
      "status" => "COMPLETED",
      "trackedEntityInstance" => $trackedEntityInstance,
      "dataValues" => array()
    );
    if (!empty($dataValues)) {
      $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
      $payload = json_encode($eventPayload);
      // echo "<br><br><pre>";
      // var_dump($payload);
      // echo "</pre>";

      $response = $dhis2->post("/api/33/events/", $payload);
      // echo "<br><br><pre>";
      // var_dump($response);
      // echo "</pre>";
    }
  } else {
    foreach ($dhis2Response['events'] as $eventPayload) {
      if (!empty($dataValues)) {
        $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
        $payload = json_encode($eventPayload);
        // echo "<br><br><pre>";
        // var_dump($payload);
        // echo "</pre>";
        $urlParams = [];
        $urlParams[] = "mergeMode=REPLACE";
        $urlParams[] = "strategy=UPDATE";
        $urlParams[] = "importStrategy=CREATE_AND_UPDATE";
        $response = $dhis2->post("/api/33/events/", $payload, $urlParams);
        // echo "<br><br><pre>";
        // var_dump($response);
        // echo "</pre>";
      }
    }
  }

  $updateData = array('result_sent_to_source' => 'sent');
  $db = $db->where('hepatitis_id', $row['hepatitis_id']);
  $db->update('form_hepatitis', $updateData);
  $counter++;
}


$response = array('processed' => $counter);
$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'DHIS2-Hepatitis-send', 'hepatitis', $url, $urlData, null, 'json');
echo json_encode($response);
