<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$labId = empty($_POST['labId']) ? null : $_POST['labId'];
$selectedTestingPoint = empty($_POST['selectedTestingPoint']) ? null : $_POST['selectedTestingPoint'];
$response = "";

$testingPoints = $facilitiesService->getTestingPoints($labId);
/* Set index as value for testing point JSON */
$testingPointsList = [];
if (!empty($testingPoints)) {
  foreach ($testingPoints as $val) {
    $testingPointsList[$val] = $val;
  }
}
if ($testingPointsList !== []) {
  $response = $general->generateSelectOptions($testingPointsList, $selectedTestingPoint, "-- Select --");
}

echo $response;
