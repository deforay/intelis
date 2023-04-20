<?php

use App\Models\Facilities;
use App\Models\General;

$general = new General();
$facilitiesDb = new Facilities();

$labId = !empty($_POST['labId']) ? $_POST['labId'] : null;
$selectedTestingPoint = !empty($_POST['selectedTestingPoint']) ? $_POST['selectedTestingPoint'] : null;
$response = "";

$testingPoints = $facilitiesDb->getTestingPoints($labId);
/* Set index as value for testing point JSON */
$testingPointsList = array();
if(isset($testingPoints) && count($testingPoints) > 0){
  foreach($testingPoints as $val){
    $testingPointsList[$val] = $val;
  }
}
if (!empty($testingPointsList)) {
  $response = $general->generateSelectOptions($testingPointsList, $selectedTestingPoint, "-- Select --");
}

echo $response;
