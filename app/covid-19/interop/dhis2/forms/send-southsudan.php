<?php

// this file is included in /covid-19/interop/dhis2/covid-19-send.php

use App\Interop\Dhis2;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;

$users = ContainerRegistry::get(UsersService::class);
$dhis2 = new Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

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

$sampleRejection = array('yes' => 'Rejected/Recollect', 'no' => 'Accepted');
$testTypes = array(
  'GeneXpert' => 'GeneXpert',
  'Real Time RT-PCR' => 'RT-PCR',
  'RDT-Antibody' => 'Antigen RDT',
  'RDT-Antigen' => 'Antibody RDT'
);
$testPlatforms = array(
  'Abbott d/m/y' => 'Abbott m2000 System',
  'Abbott m/d/y' => 'Abbott m2000 System',
  'Abbott' => 'Abbott m2000 System',
  'ABI7500' => 'ABI7500 System',
  'BioRad PCR' => 'BioRad PCR System',
  'GeneXpert' => 'GeneXpert System',
  'Rotor Gene' => 'Rotor Gene PCR System'
);

//get facility map id
$query = "SELECT 
            unique_id,
            covid19_id,
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
            WHERE source_of_request LIKE 'dhis2' 
            AND result_status = 7
            AND result_sent_to_source NOT LIKE 'sent'";

$formResults = $db->rawQuery($query);
$counter = 0;

foreach ($formResults as $row) {

  $db->where('covid19_id', $row['covid19_id']);
  $testResults = $db->get('covid19_tests');

  $uniqueIdArray = explode("::", $row['unique_id']);
  $trackedEntityInstance = $uniqueIdArray[1];

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



  // LAB RECEPTION
  $eventId = null;
  $eventPayload = [];
  $eventDate = date("Y-m-d");
  $payload = [];


  $dataValues = array(
    'f48odhAyNtd' => !isset($row['remote_sample_code']) ? $row['remote_sample_code'] : $row['sample_code'],
    'lHekjJANaNi' => $row['sample_received_at_vl_lab_datetime'],
    'P61FWjSAjjA' => ($row['sample_condition']),
    'LbIwAbaSV6r' => $sampleRejection[$row['is_sample_rejected']],
    'GeR4aHFlc1O' => $labTechnician['user_name'],
  );


  if ($row['is_sample_rejected'] == 'yes') {
    $db->where("rejection_reason_id", $row['reason_for_sample_rejection']);
    $rejectionReason = $db->getOne("r_covid19_sample_rejection_reasons");
    $dataValues[$rejectionReason['rejection_reason_code']] = "true";
  }

  // $idGeneratorApi = $dhis2->get("/api/system/id.json");
  // $idResponse = (json_decode($idGeneratorApi, true));
  // $eventId = $idResponse['codes'][0];

  // if ($eventId == null) $eventId = $general->generateRandomString(11);

  $eventPayload = array(
    //"event" => $eventId,
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
    $payload[] = $eventPayload;
  }


  //Updating Test Results
  $eventId = null;
  $eventPayload = [];
  $eventDate = date("Y-m-d");

  foreach ($testResults as $testResult) {

    $testName = $testTypes[$testResult['test_name']] ?? 'Others';
    $testPlatform = $testPlatforms[$testResult['testing_platform']] ?? 'Others';

    $dataValues = array(
      //'f48odhAyNtd' => !isset($row['remote_sample_code']) ? $row['remote_sample_code'] : $row['sample_code'],
      'b4PEeF4OOwc' => $testName,
      'w9R4l7O9Sau' => $testPlatform,
      'ZLEOP9JHZ5c' => $testResult['sample_tested_datetime'],
      'ovY6E8BSdto' => ($testResult['result']),
      'mJFhS108OdO' => $approver['user_name'],
      'S0dl5jidUnW' => $tester['user_name'],
    );

    // $idGeneratorApi = $dhis2->get("/api/system/id.json");
    // $idResponse = (json_decode($idGeneratorApi, true));
    // $eventId = $idResponse['codes'][0];

    // if ($eventId == null) $eventId = $general->generateRandomString(11);

    $eventPayload = array(
      //"event" => $eventId,
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
      $payload[] = $eventPayload;
    }
  }



  // Final Result
  $eventId = null;
  $eventPayload = [];
  $eventDate = date("Y-m-d");


  $dataValues = array(
    'ovY6E8BSdto' => ($row['result'])
  );

  // $idGeneratorApi = $dhis2->get("/api/system/id.json");
  // $idResponse = (json_decode($idGeneratorApi, true));
  // $eventId = $idResponse['codes'][0];

  // if ($eventId == null) $eventId = $general->generateRandomString(11);

  $eventPayload = array(
    //"event" => $eventId,
    "eventDate" => date("Y-m-d"),
    "program" => "uYjxkTbwRNf",
    "orgUnit" => $facResult['other_id'],
    "programStage" => $programStages['finalTestResult'],
    "status" => "ACTIVE",
    "trackedEntityInstance" => $trackedEntityInstance,
    "dataValues" => array()
  );


  if (!empty($dataValues)) {
    $eventPayload = $dhis2->addDataValuesToEventPayload($eventPayload, $dataValues);
    $payload[] = $eventPayload;
  }



  $finalPayload['events'] = ($payload);
  $finalPayload = json_encode($finalPayload);
  // echo "<br><br><pre>";
  // var_dump($finalPayload);
  // echo "</pre>";
  $response = $dhis2->post("/api/33/events/", $finalPayload);
  // echo "<br><br><pre>";
  // var_dump($response);
  // echo "</pre>";


  $updateData = array('result_sent_to_source' => 'sent');
  $db = $db->where('covid19_id', $row['covid19_id']);
  $db->update('form_covid19', $updateData);
  $counter++;
}


$response = array('processed' => $counter);

echo (json_encode($response));
