<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\PatientsService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$patientsModel = new PatientsService();

$prefix  = $_POST['patientCodePrefix'];

echo $patientsModel->generatePatientId($prefix);
