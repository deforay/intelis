<?php

// this file is included in /covid-19/interop/dhis2/covid-19-send.php

$users = new \Vlsm\Models\Users($db);
$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);

//get facility map id
$query = "SELECT * FROM form_covid19 WHERE source_of_request LIKE 'dhis%' AND result_sent_to_source LIKE 'pending'";
$formResults = $db->rawQuery($query);


$counter = 0;
foreach ($formResults as $row) {

  $sourceOfRequestArray = explode("::", $row['source_of_request']);
  $trackedEntityInstance = $sourceOfRequestArray[1];




  $urlData = array();
  //$urlData[] = "fields=attributes[attribute,code,value],orgUnit,trackedEntityInstance";
  $urlData[] = "trackedEntityInstance=$trackedEntityInstance";
  $urlData[] = "programStage=CTdzCeTbYay";
  $urlData[] = "paging=false";
  $urlData[] = "status=ACTIVE";
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


  $approver = $users->getUserInfo($row['result_approved_by'], 'user_name');
  $tester = $users->getUserInfo($row['tested_by'], 'user_name');

  $dataValues = array(
    'b4PEeF4OOwc' => $row['covid19_test_platform'],
    'ZLEOP9JHZ5c' => $row['sample_tested_datetime'],
    'ovY6E8BSdto' => ucwords($row['result']),
    'mJFhS108OdO' => $approver['user_name'],
    'S0dl5jidUnW' => $tester['user_name'],
  );


  if (count($dhis2Response['events']) == 0) {
    $idGeneratorApi = $dhis2->get("/api/system/id?limit=1");
    $idResponse = (json_decode($idGeneratorApi, true));
    $eventId = $idResponse['codes'][0];

    $eventPayload = array(
      "event" => $eventId,
      "eventDate" => date("Y-m-d"),
      "program" => "uYjxkTbwRNf",
      "orgUnit" => $facResult['other_id'],
      "programStage" => 'CTdzCeTbYay',
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
        // $urlParams[] = "importStrategy=CREATE_AND_UPDATE";
        $response = $dhis2->post("/api/33/events/", $payload, $urlParams);
        echo "<br><br><pre>";
        echo ($response);
        echo "</pre>";
      }
    }
  }

  $updateData = array('result_sent_to_source' => 'sent');
  $db = $db->where('covid19_id', $row['covid19_id']);
  $db->update('form_covid19', $updateData);
  $counter++;
}


$response = array('processed' => $counter);

echo (json_encode($response));
