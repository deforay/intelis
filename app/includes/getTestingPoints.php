<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = \App\Registries\ContainerRegistry::get(FacilitiesService::class);

$labId = !empty($_POST['labId']) ? $_POST['labId'] : null;
$selectedTestingPoint = !empty($_POST['selectedTestingPoint']) ? $_POST['selectedTestingPoint'] : null;
$response = "";

$testingPoints = $facilitiesService->getTestingPoints($labId);
/* Set index as value for testing point JSON */
$testingPointsList = [];
if(isset($testingPoints) && count($testingPoints) > 0){
  foreach($testingPoints as $val){
    $testingPointsList[$val] = $val;
  }
}
if (!empty($testingPointsList)) {
  $response = $general->generateSelectOptions($testingPointsList, $selectedTestingPoint, "-- Select --");
}

echo $response;
