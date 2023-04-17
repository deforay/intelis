<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new \App\Models\General();
$patientsModel = new \App\Models\Patients();

$prefix  = $_POST['patientCodePrefix'];

echo $patientsModel->generatePatientId($prefix);
