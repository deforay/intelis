<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\PatientsService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);

$prefix  = $_POST['patientCodePrefix'];

echo $patientsService->generatePatientId($prefix);
