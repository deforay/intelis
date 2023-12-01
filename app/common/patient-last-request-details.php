<?php

use App\Services\PatientsService;
use App\Registries\ContainerRegistry;

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$result = $patientsService->getLastRequestForPatientID($_POST['testType'] ?? '',  $_POST['patientId']);

echo !empty($result) ? json_encode($result) : "0";
