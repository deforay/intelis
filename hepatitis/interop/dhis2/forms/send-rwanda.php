<?php

// this file is included in /hepatitis/interop/dhis2/hepatitis-send.php

$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);

$query = "SELECT * FROM form_hepatitis WHERE source_of_request LIKE 'dhis2' AND result_status = 7 AND result_sent_to_source NOT LIKE 'sent'";
//$query = "SELECT * FROM form_hepatitis WHERE source_of_request LIKE 'dhis2' AND result_status = 7";// AND result_sent_to_source NOT LIKE 'sent'";
$formResults = $db->rawQuery($query);
//var_dump($formResults);die;
$counter = 0;
foreach ($formResults as $row) {

  $uniqueIdArray = explode("::", $row['unique_id']);
  $trackedEntityInstance = $uniqueIdArray[1];

  $programStages = array(
    'Screening' => 'ZBWBirHgmE6',
    'Initial HBV VL' => 'KPBuhvFV5bK',
    'Initial HCV VL' => 'KPBuhvFV5bK',
    'Follow up HBV VL' => 'WAyPhFAJLdv',
    'SVR12 HCV VL' => 'qiqz1esOFKV',
  );

  $urlData = array();
  $urlData[] = "trackedEntityInstance=$trackedEntityInstance";
  $urlData[] = "programStage=" . $programStages[$row['reason_for_vl_test']];
  $urlData[] = "paging=false";
  //$urlData[] = "status=ACTIVE";


  $url = "/api/events";


  $dhis2Response = $dhis2->get($url, $urlData);

  $dhis2Response = json_decode($dhis2Response, true);


  $strategy = null;
  $eventId = null;
  $eventPayload = array();
  $eventDate = date("Y-m-d");
  $payload = array();


  if (!empty($row['facility_id'])) {
    $facQuery = "SELECT facility_id, facility_name, other_id FROM facility_details where facility_id = " . $row['facility_id'];
    $facResult = $db->rawQueryOne($facQuery);
    $orgUnitId = $facResult['other_id'];
  } else {
    continue;
  }


  $programStagesVariables = array(
    'labResultHcvVlCount' => 'Oem0BXNDPWL',
    'labResultHbvVlCount' => 'Di17rUJDIWZ',
    'followUpHbvVlCount' => 'LblBnouUMJE',
    'finalConfirmationHcvVlCount' => 'wVmsNyyPWT0',
    'dateHcvResultsAvailable' => 'GGLsByl8p0L',
    'dateHbvResultsAvailable' => 'AzuU2zVke8N',
    'hbvSampleId' => 'mVNtr2M5Nw3',
    'hcvSampleId' => 'z6L8rdc77DL',
  );


  $dataValues = array();
  if ($row['reason_for_vl_test'] == 'Initial HBV VL') {
    $dataValues[$programStagesVariables['labResultHbvVlCount']]  = $row['hbv_vl_count'];
    $dataValues[$programStagesVariables['dateHbvResultsAvailable']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hbvSampleId']] =  $row['sample_code'];
  } else if ($row['reason_for_vl_test'] == 'Initial HCV VL') {
    $dataValues[$programStagesVariables['labResultHcvVlCount']] = $row['hcv_vl_count'];
    $dataValues[$programStagesVariables['dateHcvResultsAvailable']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hcvSampleId']] =  $row['sample_code'];
  } else if ($row['reason_for_vl_test'] == 'Follow up HBV VL') {
    $dataValues[$programStagesVariables['followUpHbvVlCount']]  = $row['hbv_vl_count'];
    $dataValues[$programStagesVariables['dateHbvResultsAvailable']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hbvSampleId']] =  $row['sample_code'];
  } else if ($row['reason_for_vl_test'] == 'SVR12 HCV VL') {
    $dataValues[$programStagesVariables['finalConfirmationHcvVlCount']] = $row['hcv_vl_count'];
    $dataValues[$programStagesVariables['dateHcvResultsAvailable']] =  $row['sample_tested_datetime'];
    $dataValues[$programStagesVariables['hcvSampleId']] =  $row['sample_code'];
  }




  if (count($dhis2Response['events']) == 0) {
    $idGeneratorApi = $dhis2->get("/api/system/id?limit=1");
    $idResponse = (json_decode($idGeneratorApi, true));
    $eventId = $idResponse['codes'][0];

    $eventPayload = array(
      "event" => $eventId,
      "eventDate" => $eventDate,
      "program" => "LEhPhsbgfFB",
      "orgUnit" => $facResult['other_id'],
      "programStage" => $programStages[$row['reason_for_vl_test']],
      "status" => "ACTIVE",
      "trackedEntityInstance" => $trackedEntityInstance,
      "dataValues" => array()
    );
    if (!empty($dataValues)) {
      $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
      $payload = json_encode($eventPayload);
      // echo "<br><br><pre>";
      // print_r($payload);
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
        // echo ($payload);
        // echo "</pre>";
        $urlParams = array();
        $urlParams[] = "mergeMode=REPLACE";
        $urlParams[] = "strategy=UPDATE";
        $urlParams[] = "importStrategy=CREATE_AND_UPDATE";
        $response = $dhis2->post("/api/33/events/", $payload, $urlParams);
        echo "<br><br><pre>";
        echo ($response);
        echo "</pre>";
      }
    }
  }




  $updateData = array('result_sent_to_source' => 'sent');
  $db = $db->where('hepatitis_id', $row['hepatitis_id']);
  $db->update('form_hepatitis', $updateData);
  $counter++;
}


$response = array('processed' => $counter);
$app = new \Vlsm\Models\App();
$trackId = $app->addApiTracking(NULL, $processedCounter, 'DHIS2-Hepatitis-Send', 'hepatitis');
echo (json_encode($response));
