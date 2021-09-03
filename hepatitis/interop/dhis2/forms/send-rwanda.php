<?php

// this file is included in /hepatitis/interop/dhis2/hepatitis-send.php

$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);

//$query = "SELECT * FROM form_hepatitis WHERE source_of_request LIKE 'dhis%' AND result_sent_to_source LIKE 'pending'";
$query = "SELECT * FROM form_hepatitis WHERE source_of_request LIKE 'dhis%' AND result_status = 7";
$formResults = $db->rawQuery($query);
$counter = 0;
foreach ($formResults as $row) {

  $trackedEntityInstance = str_replace("dhis2-", "", $row['source_of_request']);

  $programStages = array(
    'Initial HBV VL' => 'UXFQ8uL45XB',
    'Initial HCV VL' => 'UXFQ8uL45XB',
    'Follow up HBV VL' => 'hGSprgJ8SaO',
    'SVR12 HCV VL' => 'JYL69MJWyFc',
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
    $facQuery = "SELECT facility_id, facility_name, other_id from facility_details where facility_id = " . $row['facility_id'];
    $facResult = $db->rawQueryOne($facQuery);
    $orgUnitId = $facResult['other_id'];
  } else {
    continue;
  }





  $programStagesVariables = array(
    'labResultHcvVlCount' => 'KqH0EkWPGvR',
    'labResultHbvVlCount' => 'Ggd5bSi74kC',
    'followUpHbvVlCount' => 'Ggd5bSi74kC',
    'finalConfirmationHcvVlCount' => 'KqH0EkWPGvR',
    'dateHcvResultsAvailable' => 'ZO3rVJbTFDy',
    'dateHbvResultsAvailable' => 'YTKJ6PrlTSl',
    'hbvSampleId' => 'TybTedCboNb',
    'hcvSampleId' => 'CZrar5yxcrC',
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
      "program" => "nZRqRmZvdJd",
      "orgUnit" => $facResult['other_id'],
      "programStage" => $programStages[$row['reason_for_vl_test']],
      "status" => "ACTIVE",
      "trackedEntityInstance" => $trackedEntityInstance,
      "dataValues" => array()
    );
    if (!empty($dataValues)) {
      $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
      $payload = json_encode($eventPayload);
      echo "<br><br><pre>";
      print_r ($payload);
      echo "</pre>";
      
      $response = $dhis2->post("/api/33/events/", $payload);
      echo "<br><br><pre>";
      var_dump ($response);
      echo "</pre>";
    }
  } else {
    foreach ($dhis2Response['events'] as $eventPayload) {
      if (!empty($dataValues)) {
        $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
        $payload = json_encode($eventPayload);
        echo "<br><br><pre>";
        echo ($payload);
        echo "</pre>";
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

echo (json_encode($response));
