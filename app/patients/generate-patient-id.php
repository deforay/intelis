<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\PatientsService;

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$patientsModel = new PatientsService();

$prefix  = $_POST['patientCodePrefix'];

echo $patientsModel->generatePatientId($prefix);
