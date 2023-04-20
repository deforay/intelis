<?php

use App\Models\General;
use App\Models\Patients;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new General();
$patientsModel = new Patients();

$prefix  = $_POST['patientCodePrefix'];

echo $patientsModel->generatePatientId($prefix);
