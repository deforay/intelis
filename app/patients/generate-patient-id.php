<?php

use App\Models\General;
use App\Models\Patients;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new General();
$patientsModel = new Patients();

$prefix  = $_POST['patientCodePrefix'];

echo $patientsModel->generatePatientId($prefix);
