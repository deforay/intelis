<?php

use App\Services\CommonService;
use App\Services\PatientsService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new CommonService();
$patientsModel = new PatientsService();

$prefix  = $_POST['patientCodePrefix'];

echo $patientsModel->generatePatientId($prefix);
