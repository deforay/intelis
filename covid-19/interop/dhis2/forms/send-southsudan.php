<?php

// this file is included in /covid-19/interop/dhis2/covid-19-send.php

$users = new \Vlsm\Models\Users($db);
$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);
$general = new \Vlsm\Models\General($db);

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


$eventsDataElementMapping = [
  'Q98LhagGLFj' => 'sample_collection_date',
  'H3UJlHuglGv' => 'reason_for_covid19_test',
  'w9R4l7O9Sau' => 'covid19_test_platform',
  'b4PEeF4OOwc' => 'covid19_test_name',
  'P61FWjSAjjA' => 'sample_condition',
  'bujqZ6Dqn4m' => 'lab_id',
  'kL7PTi4lRSl' => 'specimen_type',
  'pxPdKaS9CqF' => 'sample_received_datetime',
  'Cl2I1H6Y3oj' => 'sample_tested_datetime',
  'ZLEOP9JHZ5c' => 'sample_tested_datetime', // sample release date
  //'f5HxreMlOWP' => 'result',
  'ovY6E8BSdto' => 'result'
];

//get facility map id
$query = "SELECT 
            source_of_request,
            facility_id,
            lab_id,
            sample_code,
            remote_sample_code,
            sample_received_at_vl_lab_datetime,
            sample_condition,
            sample_collection_date,
            sample_tested_datetime,
            reason_for_covid19_test,
            is_sample_rejected,
            reason_for_sample_rejection,
            covid19_test_name,
            covid19_test_platform,
            result,
            result_approved_by,
            tested_by,
            lab_technician
            FROM form_covid19 
            WHERE source_of_request LIKE 'dhis%' 
            AND result_sent_to_source LIKE 'pending'";
$formResults = $db->rawQuery($query);


$counter = 0;
foreach ($formResults as $row) {

  $sourceOfRequestArray = explode("::", $row['source_of_request']);
  $trackedEntityInstance = $sourceOfRequestArray[1];



  if (!empty($row['facility_id'])) {
    $facQuery = "SELECT facility_id, facility_name, other_id from facility_details where facility_id = " . $row['facility_id'];
    $facResult = $db->rawQueryOne($facQuery);
    $orgUnitId = $facResult['other_id'];
  } else {
    continue;
  }

  $approver = $users->getUserInfo($row['result_approved_by'], 'user_name');
  $tester = $users->getUserInfo($row['tested_by'], 'user_name');
  $labTechnician = $users->getUserInfo($row['lab_technician'], 'user_name');


  $urlData = array();
  $strategy = null;
  $eventId = null;
  $eventPayload = array();
  $eventDate = date("Y-m-d");
  $payload = array();

  $sampleRejection = array('yes' => 'Rejected/Recollect', 'no' => 'Accepted');

  //Lab Reception program stage

  $dataValues = array(
    'f48odhAyNtd' => !isset($row['remote_sample_code']) ? $row['remote_sample_code'] : $row['sample_code'],
    'lHekjJANaNi' => $row['sample_received_at_vl_lab_datetime'],
    'P61FWjSAjjA' => $row['sample_condition'],
    'LbIwAbaSV6r' => $sampleRejection[$row['is_sample_rejected']],
    'GeR4aHFlc1O' => $labTechnician['user_name'],
  );


  if ($row['is_sample_rejected'] == 'yes') {
    $db->where("rejection_reason_id", $row['reason_for_sample_rejection']);
    $rejectionReason = $db->getOne("r_covid19_sample_rejection_reasons");
    $dataValues[$rejectionReason['rejection_reason_code']] = "true";
  }


  $idGeneratorApi = $dhis2->get("/api/system/id.json");
  $idResponse = (json_decode($idGeneratorApi, true));
  $eventId = $idResponse['codes'][0];

  if ($eventId == null) $eventId = $general->generateRandomString(11);

  $eventPayload = array(
    "event" => $eventId,
    "eventDate" => date("Y-m-d"),
    "program" => "uYjxkTbwRNf",
    "orgUnit" => $facResult['other_id'],
    "programStage" => $programStages['labReception'],
    "status" => "ACTIVE",
    "trackedEntityInstance" => $trackedEntityInstance,
    "dataValues" => array()
  );


  if (!empty($dataValues)) {
    $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
    $payload = json_encode($eventPayload);
    echo "<br><br><pre>";
    print_r($payload);
    echo "</pre>";

    $response = $dhis2->post("/api/33/events/", $payload);
    echo "<br><br><pre>";
    var_dump($response);
    echo "</pre>";
  }




  $dataValues = array(
    //'f48odhAyNtd' => !isset($row['remote_sample_code']) ? $row['remote_sample_code'] : $row['sample_code'],
    'b4PEeF4OOwc' => $row['covid19_test_name'],
    'w9R4l7O9Sau' => $row['covid19_test_platform'],
    'ZLEOP9JHZ5c' => $row['sample_tested_datetime'],
    'ovY6E8BSdto' => ucwords($row['result']),
    'mJFhS108OdO' => $approver['user_name'],
    'S0dl5jidUnW' => $tester['user_name'],
  );

  $idGeneratorApi = $dhis2->get("/api/system/id.json");
  $idResponse = (json_decode($idGeneratorApi, true));
  $eventId = $idResponse['codes'][0];

  if ($eventId == null) $eventId = $general->generateRandomString(11);

  $eventPayload = array(
    "event" => $eventId,
    "eventDate" => date("Y-m-d"),
    "program" => "uYjxkTbwRNf",
    "orgUnit" => $facResult['other_id'],
    "programStage" => $programStages['labProcessingAndResults'],
    "status" => "ACTIVE",
    "trackedEntityInstance" => $trackedEntityInstance,
    "dataValues" => array()
  );
  if (!empty($dataValues)) {
    $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
    $payload = json_encode($eventPayload);
    echo "<br><br><pre>";
    print_r($payload);
    echo "</pre>";

    $response = $dhis2->post("/api/33/events/", $payload);
    echo "<br><br><pre>";
    var_dump($response);
    echo "</pre>";
  }


  //$updateData = array('result_sent_to_source' => 'sent');
  //$db = $db->where('covid19_id', $row['covid19_id']);
  //$db->update('form_covid19', $updateData);
  $counter++;
}


$response = array('processed' => $counter);

echo (json_encode($response));
