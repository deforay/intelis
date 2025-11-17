<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\PatientsService;
use App\Registries\ContainerRegistry;

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$result = $patientsService->getLastRequestForPatientID($_POST['testType'] ?? '',  $_POST['patientId']);

echo empty($result) ? "0" : json_encode($result);
