<?php

use App\Registries\ContainerRegistry;
use App\Services\ApiService;


/** @var ApiService $app */
$app = ContainerRegistry::get(ApiService::class);

$test = "";
if (isset($_POST['testType']) && $_POST['testType'] != '') {
    $test = $_POST['testType'];
}

$patientId = $_POST['patientId'];
$result = $app->getLastRequestForPatientID($test, $patientId);
if (count($result) > 0) {
    echo json_encode($result);
} else {
    echo "0";
}
